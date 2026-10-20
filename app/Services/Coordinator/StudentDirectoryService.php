<?php

namespace App\Services\Coordinator;

use App\Http\Controllers\Concerns\FiltersStudentDirectory;
use App\Http\Resources\StudentSummaryResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read model behind the coordinator student directory (GET /api/coordinator/students).
 * Every reader (the JSON API and the server-rendered page) goes through here, so the
 * department scoping, filters and row shape live in one place.
 */
class StudentDirectoryService
{
    use FiltersStudentDirectory;

    public function directory(Request $request): AnonymousResourceCollection
    {
        $query = Student::query()
            ->inDepartment((int) $request->user()->department_id)
            ->with(['department.program', 'branch', 'division'])
            ->withCount([
                'academicRecords as pending_academic_count' => fn ($q) => $q->where('status', 'PENDING'),
                'experiences as pending_experience_count' => fn ($q) => $q->where('status', 'PENDING'),
            ])
            ->orderBy('university_id');

        return StudentSummaryResource::collection($this->applyDirectoryFilters($query, $request)->paginate(25));
    }
}
