<?php

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\PlacementStatus;
use App\Models\Application;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PlacementNotice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Official placement records. Only the TPO writes them; students and coordinators can only read.
 *
 *   SELECTED --recordOffer--> OFFER_RECEIVED (placement OFFERED) --verify--> PLACED (placement PLACED)
 *                                   \--decline--> application REJECTED (placement DECLINED)
 * So application.stage OFFER_RECEIVED/PLACED always has a placement row behind it.
 */
class PlacementService
{
    public const FIELDS = ['role_title', 'ctc_lpa', 'location', 'offer_date', 'joining_date'];

    public function __construct(private ApplicationService $applications, private AuditLogger $audit)
    {
    }

    public function recordOffer(User $tpo, Application $application, array $data): Placement
    {
        return DB::transaction(function () use ($tpo, $application, $data) {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->with('drive', 'student.user')->firstOrFail();
            abort_unless($locked->stage === ApplicationStage::SELECTED, 409, 'An offer can only be recorded for a SELECTED application.');
            abort_if(Placement::query()->where('application_id', $locked->id)->exists(), 409, 'An offer is already recorded for this application.');

            $drive = $locked->drive;
            $placement = Placement::create([
                'student_id' => $locked->student_id,
                'application_id' => $locked->id,
                'company_id' => $drive->company_id,
                'role_title' => $data['role_title'] ?? $drive->title,
                'ctc_lpa' => $data['ctc_lpa'] ?? $drive->ctc_lpa,
                'location' => $data['location'] ?? $drive->location,
                'offer_date' => $data['offer_date'] ?? now()->toDateString(),
                'joining_date' => $data['joining_date'] ?? null,
                'status' => PlacementStatus::OFFERED,
            ]);

            $this->applications->setStageInternal($locked, ApplicationStage::OFFER_RECEIVED, $tpo, 'Offer recorded', 'application.offer_received');
            $this->audit->record('placement.offer_recorded', $placement, $locked->student_id, null, AuditLogger::snapshot($placement), $tpo);

            // the student is notified by the stage change (OFFER RECEIVED) made above

            return $placement->load('company');
        });
    }

    /** Makes the placement official (TPO verification). */
    public function verify(User $tpo, Placement $placement): Placement
    {
        return DB::transaction(function () use ($tpo, $placement) {
            $locked = Placement::query()->whereKey($placement->id)->lockForUpdate()->with('student.user')->firstOrFail();
            abort_unless($locked->status === PlacementStatus::OFFERED, 409, 'Only offers awaiting verification can be verified.');

            $locked->forceFill(['status' => PlacementStatus::PLACED, 'verified_by' => $tpo->id, 'verified_at' => now()])->save();

            if ($locked->application_id !== null) {
                $application = Application::query()->whereKey($locked->application_id)->lockForUpdate()->firstOrFail();
                $this->applications->setStageInternal($application, ApplicationStage::PLACED, $tpo, 'Placement verified', 'application.placed');
            }

            $this->audit->record('placement.verified', $locked, $locked->student_id, ['status' => 'OFFERED'], ['status' => 'PLACED'], $tpo);
            if ($locked->application_id === null) { // with an application, the stage change already notified the student
                $locked->student->user->notify(new PlacementNotice('placed', 'Your placement is confirmed', $locked->role_title, ['placement_id' => $locked->id]));
            }

            return $locked->load('company');
        });
    }

    public function update(User $tpo, Placement $placement, array $data): Placement
    {
        $fields = Arr::only($data, self::FIELDS);

        return DB::transaction(function () use ($tpo, $placement, $fields) {
            $locked = Placement::query()->whereKey($placement->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === PlacementStatus::DECLINED, 409, 'A declined offer cannot be edited.');

            if ($locked->status === PlacementStatus::PLACED) {
                $frozen = array_values(array_diff(array_keys($fields), ['location', 'joining_date']));
                if ($frozen !== []) {
                    throw ValidationException::withMessages(['placement' => ['A verified placement can only change: location, joining_date. Not allowed: ' . implode(', ', $frozen) . '.']]);
                }
            }

            $old = Arr::only($locked->attributesToArray(), array_keys($fields));
            $locked->fill($fields)->save();
            $this->audit->record('placement.updated', $locked, $locked->student_id, $old, $fields, $tpo);

            return $locked->load('company');
        });
    }

    public function decline(User $tpo, Placement $placement, ?string $remarks): Placement
    {
        return DB::transaction(function () use ($tpo, $placement, $remarks) {
            $locked = Placement::query()->whereKey($placement->id)->lockForUpdate()->with('student.user')->firstOrFail();
            abort_unless($locked->status === PlacementStatus::OFFERED, 409, 'Only unverified offers can be marked declined.');

            $locked->forceFill(['status' => PlacementStatus::DECLINED])->save();

            if ($locked->application_id !== null) {
                $application = Application::query()->whereKey($locked->application_id)->lockForUpdate()->firstOrFail();
                $this->applications->setStageInternal($application, ApplicationStage::REJECTED, $tpo, $remarks ?: 'Offer declined', 'application.offer_declined');
            }

            $this->audit->record('placement.declined', $locked, $locked->student_id, ['status' => 'OFFERED'], ['status' => 'DECLINED'], $tpo, ['remarks' => $remarks]);

            return $locked->load('company');
        });
    }

    /** Off-campus / direct offers that did not come through a drive. Verified straight away unless status=OFFERED. */
    public function createManual(User $tpo, array $data): Placement
    {
        return DB::transaction(function () use ($tpo, $data) {
            $student = Student::query()->with('user')->findOrFail($data['student_id']);
            $status = PlacementStatus::from($data['status'] ?? PlacementStatus::PLACED->value);
            abort_if($status === PlacementStatus::DECLINED, 422, 'Create the offer as OFFERED or PLACED.');

            $placement = Placement::create(Arr::only($data, self::FIELDS) + [
                'student_id' => $student->id,
                'company_id' => $data['company_id'],
                'application_id' => null,
                'status' => $status,
                'verified_by' => $status === PlacementStatus::PLACED ? $tpo->id : null,
                'verified_at' => $status === PlacementStatus::PLACED ? now() : null,
            ]);

            $this->audit->record('placement.created_manual', $placement, $student->id, null, AuditLogger::snapshot($placement), $tpo);
            $student->user->notify(new PlacementNotice(
                $status === PlacementStatus::PLACED ? 'placed' : 'offer_recorded',
                $status === PlacementStatus::PLACED ? 'Your placement is confirmed' : 'Offer recorded: ' . $placement->role_title,
                null, ['placement_id' => $placement->id]
            ));

            return $placement->load('company');
        });
    }
}
