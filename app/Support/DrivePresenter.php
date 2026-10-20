<?php

namespace App\Support;

use App\Models\Department;
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
        $drive->loadMissing(['branches', 'departments.program']);
        $departments = $drive->departments;

        // Compatibility for legacy code/tests that attached branches directly without the new pivot.
        if ($departments->isEmpty()) {
            $ids = $drive->branches->map(function ($branch) {
                if ($branch->code === 'CSE-AIML') {
                    $programId = Department::query()->whereKey($branch->department_id)->value('program_id');

                    return Department::query()->where('program_id', $programId)->where('code', 'AIML')->value('id');
                }

                return $branch->department_id;
            })->filter()->unique()->values();

            $departments = Department::query()
                ->whereIn('id', $ids)
                ->with('program')
                ->get();
        }

        $departments->loadMissing('divisions');

        return self::summary($drive) + [
            'description' => $drive->description,
            'additional_requirements' => $drive->additional_requirements,
            'allow_placed_students' => (bool) $drive->allow_placed_students,
            'cancel_reason' => $drive->cancel_reason,
            'eligibility' => $drive->eligibility?->only([
                'min_cgpa', 'min_tenth_percentage', 'min_twelfth_percentage', 'min_diploma_percentage',
                'max_active_backlogs', 'max_total_backlogs', 'diploma_counts_as_twelfth',
            ]),
            'branches' => $drive->branches->map(fn ($branch) => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'department_id' => $branch->department_id,
            ])->values(),
            'departments' => $departments->map(fn ($department) => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'program' => $department->relationLoaded('program') && $department->program
                    ? ['id' => $department->program->id, 'code' => $department->program->code, 'name' => $department->program->name]
                    : null,
            ])->values(),
            'divisions' => $departments->flatMap(fn ($department) => $department->divisions
                ->where('is_active', true)
                ->map(fn ($division) => [
                    'id' => $division->id,
                    'code' => $division->code,
                    'name' => $division->name,
                    'department_id' => $department->id,
                    'department_code' => $department->code,
                    'department_name' => $department->name,
                    'program_code' => $department->program?->code,
                    'program_name' => $department->program?->name,
                ]))->values(),
        ];
    }
}
