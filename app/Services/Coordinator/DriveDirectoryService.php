<?php

namespace App\Services\Coordinator;

use App\Models\Application;
use App\Models\Division;
use App\Models\PlacementDrive;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * READ-ONLY read model for the coordinator drives screens. Drives are created and published by the
 * TPO; a coordinator sees published/closed drives that target their department (across its divisions)
 * department, with THEIR department's applicants.
 */
class DriveDirectoryService
{
    public function index(Request $request): LengthAwarePaginator
    {
        $departmentId = (int) $request->user()->department_id;
        $request->validate(['status' => ['sometimes', 'in:PUBLISHED,CLOSED'], 'graduation_year' => ['sometimes', 'integer']]);

        return PlacementDrive::query()->visibleToDepartment($departmentId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('graduation_year'), fn ($q) => $q->where('graduation_year', $request->integer('graduation_year')))
            ->with('company:id,name')
            ->withCount(['applications as department_applications_count' => fn ($q) => $q->forDepartment($departmentId)])
            ->orderByDesc('published_at')
            ->paginate(20)
            ->through(fn (PlacementDrive $d) => $this->summary($d));
    }

    public function show(Request $request, int $id): array
    {
        $departmentId = (int) $request->user()->department_id;

        // A drive that does not target this department is a 404 (not visible to this coordinator).
        $drive = PlacementDrive::query()->visibleToDepartment($departmentId)
            ->with(['company', 'eligibility', 'branches' => fn ($q) => $q->where('branches.department_id', $departmentId)])
            ->findOrFail($id);

        $byStage = Application::query()->where('drive_id', $drive->id)->forDepartment($departmentId)
            ->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');

        $divisionRows = Division::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map->only(['id', 'code', 'name'])
            ->values();

        $legacyBranchRows = $drive->branches->map->only(['id', 'code', 'name'])->values();

        return $this->summary($drive) + [
            'description' => $drive->description,
            'additional_requirements' => $drive->additional_requirements,
            'eligibility' => $drive->eligibility,
            'department_divisions' => $divisionRows,
            // Legacy alias for older coordinator screens/clients during the transition.
            'department_branches' => $legacyBranchRows,
            'department_applications_by_stage' => $byStage,
        ];
    }

    private function summary(PlacementDrive $drive): array
    {
        return [
            'id' => $drive->id,
            'company' => $drive->company?->name,
            'title' => $drive->title,
            'employment_type' => $drive->employment_type,
            'ctc_lpa' => $drive->ctc_lpa,
            'ctc_max_lpa' => $drive->ctc_max_lpa,
            'location' => $drive->location,
            'graduation_year' => $drive->graduation_year,
            'drive_date' => $drive->drive_date?->toDateString(),
            'application_deadline' => $drive->application_deadline,
            'status' => $drive->status->value,
            'department_applications_count' => $drive->department_applications_count ?? null,
        ];
    }
}
