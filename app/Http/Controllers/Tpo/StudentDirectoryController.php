<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Concerns\FiltersStudentDirectory;
use App\Http\Controllers\Concerns\PresentsStudentDetail;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentSummaryResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** University-wide, READ-ONLY view of students for the TPO (department-wise via ?department_id=). */
class StudentDirectoryController extends Controller
{
    use FiltersStudentDirectory, PresentsStudentDetail;

    public function index(Request $request)
    {
        $query = Student::query()
            ->with(['department', 'branch'])
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->orderBy('department_id')->orderBy('university_id');

        return StudentSummaryResource::collection($this->applyDirectoryFilters($query, $request)->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        $student = Student::query()->findOrFail($id);
        Gate::authorize('view', $student);

        return response()->json($this->studentDetail($student));
    }
}
