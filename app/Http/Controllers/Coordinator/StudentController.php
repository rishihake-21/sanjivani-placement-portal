<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Concerns\PresentsStudentDetail;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Coordinator\StudentDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** A T&P Coordinator sees ONLY students of their own department. */
class StudentController extends Controller
{
    use PresentsStudentDetail;

    public function __construct(private StudentDirectoryService $directory)
    {
    }

    public function index(Request $request)
    {
        return $this->directory->directory($request);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Another department's student is a 404, not a 403: their existence is not revealed.
        $student = Student::query()->inDepartment((int) $request->user()->department_id)->findOrFail($id);
        Gate::authorize('view', $student);

        return response()->json($this->studentDetail($student));
    }
}
