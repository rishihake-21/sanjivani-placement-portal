<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\ReuploadSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReuploadRequestRequest;
use App\Models\ReuploadRequest;
use App\Models\Student;
use App\Services\ReuploadRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReuploadRequestController extends Controller
{
    public function __construct(private ReuploadRequestService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['sometimes', 'in:OPEN,FULFILLED,CANCELLED']]);

        $requests = ReuploadRequest::query()
            ->whereHas('student', fn ($s) => $s->where('department_id', (int) $request->user()->department_id))
            ->with('student:id,university_id,full_name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest()->paginate(25);

        return response()->json($requests->through(fn (ReuploadRequest $r) => $this->row($r) + [
            'student' => $r->student->only(['id', 'university_id', 'full_name']),
        ]));
    }

    public function store(StoreReuploadRequestRequest $request, int $studentId): JsonResponse
    {
        $student = Student::query()->where('department_id', (int) $request->user()->department_id)->with('user')->findOrFail($studentId);

        $created = $this->service->create(
            $request->user(), $student, ReuploadSubject::from($request->validated('subject_type')),
            (int) $request->validated('subject_id'), $request->validated('reason'),
        );

        return response()->json(['data' => $this->row($created)], 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $reuploadRequest = ReuploadRequest::query()
            ->whereHas('student', fn ($s) => $s->where('department_id', (int) $request->user()->department_id))
            ->findOrFail($id);

        return response()->json(['data' => $this->row($this->service->cancel($reuploadRequest, $request->user()))]);
    }

    private function row(ReuploadRequest $r): array
    {
        return [
            'id' => $r->id,
            'subject_type' => $r->subject_type->value,
            'subject_id' => $r->subject_id,
            'reason' => $r->reason,
            'status' => $r->status->value,
            'created_at' => $r->created_at,
            'closed_at' => $r->closed_at,
        ];
    }
}
