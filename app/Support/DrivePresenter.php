<?php

namespace App\Support;

use App\Models\PlacementDrive;

final class DrivePresenter
{
    public static function summary(PlacementDrive $drive): array
    {
        return [
            'id' => $drive->id,
            'company' => $drive->relationLoaded('company') && $drive->company ? ['id' => $drive->company->id, 'name' => $drive->company->name] : null,
            'title' => $drive->title,
            'employment_type' => $drive->employment_type,
            'ctc_lpa' => $drive->ctc_lpa,
            'ctc_max_lpa' => $drive->ctc_max_lpa,
            'location' => $drive->location,
            'drive_date' => $drive->drive_date?->toDateString(),
            'application_deadline' => $drive->application_deadline,
            'graduation_year' => $drive->graduation_year,
            'status' => $drive->status->value,
            'published_at' => $drive->published_at,
        ];
    }

    public static function detail(PlacementDrive $drive): array
    {
        return self::summary($drive) + [
            'description' => $drive->description,
            'additional_requirements' => $drive->additional_requirements,
            'allow_placed_students' => (bool) $drive->allow_placed_students,
            'cancel_reason' => $drive->cancel_reason,
            'eligibility' => $drive->eligibility?->only([
                'min_cgpa', 'min_tenth_percentage', 'min_twelfth_percentage', 'min_diploma_percentage',
                'max_active_backlogs', 'max_total_backlogs', 'diploma_counts_as_twelfth',
            ]),
            'branches' => $drive->branches->map(fn ($b) => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name, 'department_id' => $b->department_id])->values(),
        ];
    }
}
