<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\ReuploadSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReuploadRequestRequest;
use App\Models\ReuploadRequest;
use App\Models\Student;
use App\Services\Coordinator\ReuploadRequestDirectoryService;
use App\Services\ReuploadRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReuploadRequestController extends Controller
{
    public function __construct(
        private ReuploadRequestService $service,
        private ReuploadRequestDirectoryService $directory,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->directory->index($request));
    }

    public function store(StoreReuploadRequestRequest $request, int $studentId): JsonResponse
    {
        $student = Student::query()->where('department_id', (int) $request->user()->department_id)->with('user')->findOrFail($studentId);

        $created = $this->service->create(
            $request->user(), $student, ReuploadSubject::from($request->validated('subject_type')),
            (int) $request->validated('subject_id'), $request->validated('reason'),
        );

        return response()->json(['data' => $this->directory->row($created)], 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $reuploadRequest = ReuploadRequest::query()
            ->whereHas('student', fn ($s) => $s->where('department_id', (int) $request->user()->department_id))
            ->findOrFail($id);

        return response()->json(['data' => $this->directory->row($this->service->cancel($reuploadRequest, $request->user()))]);
    }
}
