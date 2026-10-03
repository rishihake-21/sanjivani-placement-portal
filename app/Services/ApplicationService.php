<?php

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\DriveStatus;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PlacementNotice;
use App\Support\StageTransitions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student applies / withdraws; TPO moves applications through the rounds.
 * Every stage change writes application_status_history and the audit log.
 */
class ApplicationService
{
    public function __construct(
        private EligibilityEngine $engine,
        private VerifiedAcademicProfileService $academics,
        private ProfileCompletenessService $completeness,
        private AuditLogger $audit,
    ) {
    }

    // ---- student side ---------------------------------------------------------------------------

    public function apply(Student $student, PlacementDrive $drive, User $actor): Application
    {
        try {
            return DB::transaction(function () use ($student, $drive, $actor) {
                $drive = PlacementDrive::query()->whereKey($drive->id)->lockForUpdate()->firstOrFail();
                $drive->load(['company', 'eligibility', 'branches']);

                abort_unless($drive->status === DriveStatus::PUBLISHED, 409, 'This drive is not accepting applications.');
                abort_if($drive->application_deadline === null || $drive->application_deadline->lte(now()), 409, 'The application deadline has passed.');
                abort_if($student->isLocked(), 423, 'Your profile is locked. Contact the T&P office.');
                abort_if(
                    Application::query()->where('drive_id', $drive->id)->where('student_id', $student->id)->exists(),
                    409,
                    'You have already applied to this drive.'
                );

                // Evaluated NOW against verified data - never trust an earlier "eligible".
                $result = $this->engine->evaluate($student, $drive);
                if (! $result->isEligible()) {
                    throw ValidationException::withMessages(['eligibility' => $result->reasons() ?: ['You are not eligible for this drive.']]);
                }

                $summary = $this->completeness->summarize($student);
                if (! $summary['can_apply']) {
                    throw ValidationException::withMessages([
                        'profile' => array_map(fn ($b) => $b['hint'], $summary['blockers']) ?: ['Your profile is not ready to apply.'],
                    ]);
                }

                $now = now();
                $application = Application::create([
                    'drive_id' => $drive->id,
                    'student_id' => $student->id,
                    'stage' => ApplicationStage::APPLIED,
                    'applied_at' => $now,
                    'stage_updated_at' => $now,
                    // Frozen proof of what the decision was based on, even if data changes later.
                    'data_snapshot' => [
                        'student' => [
                            'branch_id' => $student->branch_id,
                            'current_semester' => $student->current_semester,
                            'admission_type' => $student->admission_type->value,
                        ],
                        'verified_academics' => $this->academics->forStudent($student)->toArray(),
                        'eligibility' => $result->toArray(),
                        'drive_criteria' => $drive->eligibility?->only(DriveService::ELIGIBILITY_FIELDS),
                    ],
                ]);

                $this->history($application, null, ApplicationStage::APPLIED, $actor, null);
                $this->audit->record('application.created', $application, $student->id, null, ['drive_id' => $drive->id], $actor);

                return $application->load('drive.company');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'You have already applied to this drive.');
        }
    }

    public function withdraw(Application $application, User $actor): Application
    {
        return DB::transaction(function () use ($application, $actor) {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless(StageTransitions::withdrawable($locked->stage), 409, 'You can only withdraw before you are selected.');

            $this->move($locked, ApplicationStage::WITHDRAWN, $actor, 'Withdrawn by student', 'application.withdrawn');

            return $locked->load('drive.company');
        });
    }

    // ---- TPO side -------------------------------------------------------------------------------

    public function changeStage(User $tpo, Application $application, ApplicationStage $to, ?string $remarks = null, bool $force = false): Application
    {
        return DB::transaction(function () use ($tpo, $application, $to, $remarks, $force) {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->with('drive.company', 'student.user')->firstOrFail();

            $error = $this->transitionError($locked, $to, $force, $remarks);
            if ($error !== null) {
                throw ValidationException::withMessages(['stage' => [$error]]);
            }

            $this->move($locked, $to, $tpo, $remarks, $force ? 'application.stage_corrected' : 'application.stage_changed');

            return $locked;
        });
    }

    /**
     * Same stage for many applications of ONE drive (e.g. shortlist 80 students).
     * All-or-nothing: if any application cannot make the move, nothing changes and every problem is listed.
     *
     * @param  list<int>  $applicationIds
     */
    public function bulkChangeStage(User $tpo, PlacementDrive $drive, array $applicationIds, ApplicationStage $to, ?string $remarks = null, bool $force = false): int
    {
        $ids = array_values(array_unique($applicationIds));

        return DB::transaction(function () use ($tpo, $drive, $ids, $to, $remarks, $force) {
            $applications = Application::query()->where('drive_id', $drive->id)->whereIn('id', $ids)
                ->orderBy('id')->lockForUpdate()->with('drive.company', 'student.user')->get();

            $errors = [];
            foreach (array_diff($ids, $applications->pluck('id')->all()) as $missing) {
                $errors[] = "Application {$missing} does not belong to this drive.";
            }
            foreach ($applications as $application) {
                if (($error = $this->transitionError($application, $to, $force, $remarks)) !== null) {
                    $errors[] = "Application {$application->id}: {$error}";
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages(['application_ids' => array_slice($errors, 0, 50)]);
            }

            foreach ($applications as $application) {
                $this->move($application, $to, $tpo, $remarks, $force ? 'application.stage_corrected' : 'application.stage_changed');
            }

            return $applications->count();
        });
    }

    /** Used by PlacementService for OFFER_RECEIVED / PLACED / REJECTED(offer declined). No transition checks. */
    public function setStageInternal(Application $application, ApplicationStage $to, User $actor, ?string $remarks, string $auditAction): void
    {
        $this->move($application, $to, $actor, $remarks, $auditAction);
    }

    private function transitionError(Application $application, ApplicationStage $to, bool $force, ?string $remarks): ?string
    {
        $from = $application->stage;
        $status = $application->drive->status;

        if (! in_array($status, [DriveStatus::PUBLISHED, DriveStatus::CLOSED], true)) {
            return 'The drive is ' . strtolower($status->value) . '.';
        }
        if (in_array($to, [ApplicationStage::OFFER_RECEIVED, ApplicationStage::PLACED], true)) {
            return 'Offers and placements are recorded through the placement endpoints, not as a stage change.';
        }
        if ($to === ApplicationStage::WITHDRAWN) {
            return 'Only the student can withdraw an application.';
        }
        if ($from === $to) {
            return "Already at {$to->value}.";
        }
        if (! in_array($to, StageTransitions::settableByTpo(), true)) {
            return "{$to->value} cannot be set here.";
        }

        if (in_array($to, StageTransitions::allowedNext($from), true)) {
            return null;
        }
        if (! $force) {
            return "Cannot move from {$from->value} to {$to->value}. Allowed: "
                . (implode(', ', array_map(fn ($s) => $s->value, StageTransitions::allowedNext($from))) ?: 'none') . '.';
        }
        if (! StageTransitions::correctableFrom($from)) {
            return "{$from->value} cannot be corrected here. Use the placement endpoints.";
        }
        if (trim((string) $remarks) === '' || mb_strlen(trim((string) $remarks)) < 10) {
            return 'A correction needs remarks (at least 10 characters) explaining why.';
        }

        return null;
    }

    private function move(Application $application, ApplicationStage $to, User $actor, ?string $remarks, string $auditAction): void
    {
        $from = $application->stage;
        $application->forceFill(['stage' => $to, 'stage_updated_at' => now()])->save();

        $this->history($application, $from, $to, $actor, $remarks);
        $this->audit->record($auditAction, $application, $application->student_id,
            ['stage' => $from->value], ['stage' => $to->value], $actor, ['remarks' => $remarks]);

        // Students are notified of staff-driven changes, not of their own withdrawal.
        if ($actor->id !== $application->student?->user_id) {
            $application->loadMissing('drive.company', 'student.user');
            $application->student->user->notify(new PlacementNotice(
                'stage_changed',
                $application->drive->company->name . ': ' . str_replace('_', ' ', $to->value),
                $remarks,
                ['application_id' => $application->id, 'stage' => $to->value],
            ));
        }
    }

    private function history(Application $application, ?ApplicationStage $from, ApplicationStage $to, User $actor, ?string $remarks): void
    {
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_stage' => $from?->value,
            'to_stage' => $to->value,
            'changed_by' => $actor->id,
            'remarks' => $remarks,
        ]);
    }
}
