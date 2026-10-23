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
use Illuminate\Support\Str;
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
        $drive->loadMissing(['company', 'eligibility', 'branches']);

        abort_unless($drive->status === DriveStatus::PUBLISHED, 409, 'This drive is not accepting applications.');
        abort_if($drive->application_deadline === null || $drive->application_deadline->lte(now()), 409, 'The application deadline has passed.');
        abort_if($student->isLocked(), 423, 'Your profile is locked. Contact the T&P office.');
        abort_if(
            Application::query()->where('drive_id', $drive->id)->where('student_id', $student->id)->exists(),
            409,
            'You have already applied to this drive.'
        );

        // Load live academic records once and reuse across both completeness and eligibility checks.
        $liveRecords = $student->relationLoaded('academicRecords')
            ? $student->academicRecords
            : $student->academicRecords()->live()->get();

        $verifiedSnapshot = $this->academics->fromRecords($student, $liveRecords);
        $result = $this->engine->evaluateWithSnapshot($student, $drive, $verifiedSnapshot);

        if (! $result->isEligible()) {
            throw ValidationException::withMessages(['eligibility' => $result->reasons() ?: ['You are not eligible for this drive.']]);
        }

        $summary = $this->completeness->summarize($student, $liveRecords);
        if (! $summary['can_apply']) {
            throw ValidationException::withMessages([
                'profile' => array_map(fn ($b) => $b['hint'], $summary['blockers']) ?: ['Your profile is not ready to apply.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($student, $drive, $actor, $verifiedSnapshot, $result) {
                // Re-verify drive is still published without holding an exclusive row lock on placement_drives
                // across thousands of concurrent student applications.
                $freshDrive = PlacementDrive::query()->whereKey($drive->id)->firstOrFail();
                abort_unless($freshDrive->status === DriveStatus::PUBLISHED, 409, 'This drive is not accepting applications.');
                abort_if($freshDrive->application_deadline === null || $freshDrive->application_deadline->lte(now()), 409, 'The application deadline has passed.');
                abort_if(
                    Application::query()->where('drive_id', $drive->id)->where('student_id', $student->id)->exists(),
                    409,
                    'You have already applied to this drive.'
                );

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
                        'verified_academics' => $verifiedSnapshot->toArray(),
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
            $drive->loadMissing('company');
            $applications = Application::query()->where('drive_id', $drive->id)->whereIn('id', $ids)
                ->orderBy('id')->lockForUpdate()->with('student:id,user_id')->get()
                ->each(fn (Application $a) => $a->setRelation('drive', $drive));

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

            $now = now();
            $auditAction = $force ? 'application.stage_corrected' : 'application.stage_changed';
            $request = request();
            $metaJson = json_encode([
                'remarks' => $remarks,
                'ip' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            ]);

            // Bulk update application stages in 1 query instead of N queries
            Application::query()->whereIn('id', $applications->pluck('id'))->update([
                'stage' => $to->value,
                'stage_updated_at' => $now,
                'updated_at' => $now,
            ]);

            $historyRows = [];
            $auditRows = [];
            $notificationRows = [];
            $title = $drive->company->name . ': ' . str_replace('_', ' ', $to->value);

            foreach ($applications as $application) {
                $fromValue = $application->stage->value;

                $historyRows[] = [
                    'application_id' => $application->id,
                    'from_stage' => $fromValue,
                    'to_stage' => $to->value,
                    'changed_by' => $tpo->id,
                    'remarks' => $remarks,
                    'created_at' => $now,
                ];

                $auditRows[] = [
                    'actor_id' => $tpo->id,
                    'actor_role' => $tpo->role?->value,
                    'action' => $auditAction,
                    'subject_type' => 'Application',
                    'subject_id' => $application->id,
                    'student_id' => $application->student_id,
                    'old_values' => json_encode(['stage' => $fromValue]),
                    'new_values' => json_encode(['stage' => $to->value]),
                    'meta' => $metaJson,
                    'created_at' => $now,
                ];

                if ($application->student?->user_id !== null && $tpo->id !== $application->student->user_id) {
                    $notificationRows[] = [
                        'id' => (string) Str::uuid(),
                        'type' => PlacementNotice::class,
                        'notifiable_type' => User::class,
                        'notifiable_id' => $application->student->user_id,
                        'data' => json_encode([
                            'kind' => 'stage_changed',
                            'title' => $title,
                            'body' => $remarks,
                            'application_id' => $application->id,
                            'stage' => $to->value,
                        ]),
                        'read_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            ApplicationStatusHistory::insert($historyRows);
            DB::table('audit_logs')->insert($auditRows);

            if ($notificationRows !== []) {
                DB::afterCommit(fn () => DB::table('notifications')->insert($notificationRows));
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
            $student = $application->student;
            $title = $application->drive->company->name . ': ' . str_replace('_', ' ', $to->value);
            $applicationId = $application->id;
            $stage = $to->value;

            DB::afterCommit(fn () => $student->user->notify(new PlacementNotice(
                'stage_changed',
                $title,
                $remarks,
                ['application_id' => $applicationId, 'stage' => $stage],
            )));
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
