<?php

namespace App\Services;

use App\Enums\DriveStatus;
use App\Jobs\NotifyEligibleStudents;
use App\Models\Application;
use App\Models\Company;
use App\Models\DriveEligibility;
use App\Models\PlacementDrive;
use App\Models\User;
use App\Notifications\PlacementNotice;
use App\Support\StageTransitions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drive lifecycle:  DRAFT -> PUBLISHED -> CLOSED   (or CANCELLED from DRAFT/PUBLISHED)
 *
 * While DRAFT everything is editable. After publishing, criteria and target branches are LOCKED
 * (students already saw them and applied against them); only schedule/description fields may change.
 */
class DriveService
{
    public const DRIVE_FIELDS = [
        'company_id', 'title', 'description', 'employment_type', 'ctc_lpa', 'ctc_max_lpa', 'location',
        'drive_date', 'application_deadline', 'graduation_year', 'additional_requirements', 'allow_placed_students',
    ];

    public const ELIGIBILITY_FIELDS = [
        'min_cgpa', 'min_tenth_percentage', 'min_twelfth_percentage', 'min_diploma_percentage',
        'max_active_backlogs', 'max_total_backlogs', 'diploma_counts_as_twelfth',
    ];

    /** The only drive fields that may change once published. */
    public const PUBLISHED_EDITABLE = ['description', 'additional_requirements', 'location', 'drive_date', 'application_deadline'];

    public function __construct(private AuditLogger $audit)
    {
    }

    public function create(User $tpo, array $data): PlacementDrive
    {
        return DB::transaction(function () use ($tpo, $data) {
            $this->assertCompanyActive((int) $data['company_id']);

            $drive = PlacementDrive::create(Arr::only($data, self::DRIVE_FIELDS) + [
                'status' => DriveStatus::DRAFT,
                'created_by' => $tpo->id,
            ]);

            DriveEligibility::create(['drive_id' => $drive->id] + Arr::only($data['eligibility'] ?? [], self::ELIGIBILITY_FIELDS));
            $drive->branches()->sync($data['branch_ids'] ?? []);

            $this->audit->record('drive.created', $drive, null, null, $this->state($drive), $tpo);

            return $this->loaded($drive);
        });
    }

    public function update(User $tpo, PlacementDrive $drive, array $data): PlacementDrive
    {
        return DB::transaction(function () use ($tpo, $drive, $data) {
            $drive = PlacementDrive::query()->whereKey($drive->id)->lockForUpdate()->firstOrFail();
            $before = $this->state($drive);

            if ($drive->status === DriveStatus::PUBLISHED) {
                $locked = array_values(array_diff(array_keys($data), self::PUBLISHED_EDITABLE));
                if ($locked !== []) {
                    throw ValidationException::withMessages([
                        'drive' => ['These fields are locked after publishing: ' . implode(', ', $locked) . '. Cancel the drive and create a new one to change them.'],
                    ]);
                }
                if (isset($data['application_deadline']) && now()->gte($data['application_deadline'])) {
                    throw ValidationException::withMessages(['application_deadline' => ['The deadline must be in the future.']]);
                }
            } elseif ($drive->status !== DriveStatus::DRAFT) {
                abort(409, 'A ' . strtolower($drive->status->value) . ' drive cannot be edited.');
            }

            if (isset($data['company_id']) && (int) $data['company_id'] !== (int) $drive->company_id) {
                $this->assertCompanyActive((int) $data['company_id']);
            }

            $drive->fill(Arr::only($data, self::DRIVE_FIELDS))->save();

            if (array_key_exists('eligibility', $data)) {
                DriveEligibility::query()->updateOrCreate(['drive_id' => $drive->id], Arr::only($data['eligibility'] ?? [], self::ELIGIBILITY_FIELDS));
            }
            if (array_key_exists('branch_ids', $data)) {
                $drive->branches()->sync($data['branch_ids']);
            }

            $this->audit->record('drive.updated', $drive, null, $before, $this->state($drive->refresh()), $tpo);

            return $this->loaded($drive);
        });
    }

    public function publish(User $tpo, PlacementDrive $drive): PlacementDrive
    {
        $published = DB::transaction(function () use ($tpo, $drive) {
            $drive = PlacementDrive::query()->whereKey($drive->id)->lockForUpdate()->firstOrFail();
            abort_unless($drive->status === DriveStatus::DRAFT, 409, 'Only draft drives can be published.');

            $errors = [];
            if ($drive->branches()->count() === 0) {
                $errors[] = 'Select at least one target branch.';
            }
            if ($drive->application_deadline === null || $drive->application_deadline->lte(now())) {
                $errors[] = 'Set an application deadline in the future.';
            }
            if (! Company::query()->whereKey($drive->company_id)->where('is_active', true)->exists()) {
                $errors[] = 'The company is inactive.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages(['drive' => $errors]);
            }

            $drive->forceFill(['status' => DriveStatus::PUBLISHED, 'published_at' => now()])->save();
            $this->audit->record('drive.published', $drive, null, ['status' => 'DRAFT'], ['status' => 'PUBLISHED'], $tpo);

            DB::afterCommit(fn () => NotifyEligibleStudents::dispatch($drive->id));

            return $drive;
        });

        return $this->loaded($published);
    }

    /** Stop accepting applications; existing applications keep moving through the rounds. */
    public function close(User $tpo, PlacementDrive $drive): PlacementDrive
    {
        return DB::transaction(function () use ($tpo, $drive) {
            $drive = PlacementDrive::query()->whereKey($drive->id)->lockForUpdate()->firstOrFail();
            abort_unless($drive->status === DriveStatus::PUBLISHED, 409, 'Only published drives can be closed.');

            $drive->forceFill(['status' => DriveStatus::CLOSED, 'closed_at' => now()])->save();
            $this->audit->record('drive.closed', $drive, null, ['status' => 'PUBLISHED'], ['status' => 'CLOSED'], $tpo);

            return $this->loaded($drive);
        });
    }

    public function cancel(User $tpo, PlacementDrive $drive, string $reason): PlacementDrive
    {
        return DB::transaction(function () use ($tpo, $drive, $reason) {
            $drive = PlacementDrive::query()->whereKey($drive->id)->lockForUpdate()->with('company:id,name')->firstOrFail();
            abort_unless(in_array($drive->status, [DriveStatus::DRAFT, DriveStatus::PUBLISHED], true), 409, 'Only draft or published drives can be cancelled.');

            $was = $drive->status->value;
            $drive->forceFill(['status' => DriveStatus::CANCELLED, 'cancel_reason' => $reason, 'closed_at' => now()])->save();
            $this->audit->record('drive.cancelled', $drive, null, ['status' => $was], ['status' => 'CANCELLED'], $tpo, ['reason' => $reason]);

            // Tell everyone who is still in the process.
            Application::query()->where('drive_id', $drive->id)->with('student.user')->get()
                ->filter(fn (Application $a) => StageTransitions::withdrawable($a->stage))
                ->each(fn (Application $a) => $a->student->user->notify(new PlacementNotice(
                    'drive_cancelled', 'Drive cancelled: ' . $drive->company->name . ' - ' . $drive->title, $reason, ['drive_id' => $drive->id]
                )));

            return $this->loaded($drive);
        });
    }

    /** Only a draft that was never published can be deleted; everything else is cancelled (history stays). */
    public function delete(User $tpo, PlacementDrive $drive): void
    {
        DB::transaction(function () use ($tpo, $drive) {
            $drive = PlacementDrive::query()->whereKey($drive->id)->lockForUpdate()->firstOrFail();
            abort_unless($drive->status === DriveStatus::DRAFT && $drive->published_at === null, 409, 'Only never-published drafts can be deleted. Cancel the drive instead.');

            $snapshot = $this->state($drive);
            $drive->branches()->detach();
            DriveEligibility::query()->where('drive_id', $drive->id)->delete();
            $drive->delete();

            $this->audit->record('drive.deleted', $drive, null, $snapshot, null, $tpo);
        });
    }

    /** Scheduled hourly (tpms:close-expired-drives): published drives whose deadline passed stop accepting applications. */
    public function closeExpired(): int
    {
        $count = 0;

        PlacementDrive::query()->where('status', DriveStatus::PUBLISHED->value)->where('application_deadline', '<', now())
            ->each(function (PlacementDrive $drive) use (&$count) {
                $drive->forceFill(['status' => DriveStatus::CLOSED, 'closed_at' => now()])->save();
                $this->audit->record('drive.auto_closed', $drive, null, ['status' => 'PUBLISHED'], ['status' => 'CLOSED'], null);
                $count++;
            });

        return $count;
    }

    private function assertCompanyActive(int $companyId): void
    {
        if (! Company::query()->whereKey($companyId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['company_id' => ['This company is inactive or does not exist.']]);
        }
    }

    private function loaded(PlacementDrive $drive): PlacementDrive
    {
        return $drive->fresh(['company', 'eligibility', 'branches']);
    }

    private function state(PlacementDrive $drive): array
    {
        $drive->loadMissing(['eligibility', 'branches']);

        return AuditLogger::snapshot($drive) + [
            'eligibility' => $drive->eligibility?->only(self::ELIGIBILITY_FIELDS),
            'branch_ids' => $drive->branches->pluck('id')->sort()->values()->all(),
        ];
    }
}
