<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Concerns\FiltersStudentDirectory;
use App\Http\Controllers\Concerns\PresentsStudentDetail;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentSummaryResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** A T&P Coordinator sees ONLY students of their own department. */
class StudentController extends Controller
{
    use FiltersStudentDirectory, PresentsStudentDetail;

    public function index(Request $request)
    {
        $query = Student::query()
            ->inDepartment((int) $request->user()->department_id)
            ->with(['department', 'branch'])
            ->withCount([
                'academicRecords as pending_academic_count' => fn ($q) => $q->where('status', 'PENDING'),
                'experiences as pending_experience_count' => fn ($q) => $q->where('status', 'PENDING'),
            ])
            ->orderBy('university_id');

        return StudentSummaryResource::collection($this->applyDirectoryFilters($query, $request)->paginate(25));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Another department's student is a 404, not a 403: their existence is not revealed.
        $student = Student::query()->inDepartment((int) $request->user()->department_id)->findOrFail($id);
        Gate::authorize('view', $student);

        return response()->json($this->studentDetail($student));
    }
}
