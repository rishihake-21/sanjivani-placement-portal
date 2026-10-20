<?php

namespace App\Services\Coordinator;

use App\Models\ReuploadRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/** READ-ONLY read model for the department's re-upload requests (list, one student's rows, row shape). */
class ReuploadRequestDirectoryService
{
    public function index(Request $request): LengthAwarePaginator
    {
        $request->validate(['status' => ['sometimes', 'in:OPEN,FULFILLED,CANCELLED']]);

        return ReuploadRequest::query()
            ->whereHas('student', fn ($s) => $s->where('department_id', (int) $request->user()->department_id))
            ->with('student:id,university_id,full_name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest()->paginate(25)
            ->through(fn (ReuploadRequest $r) => $this->row($r) + [
                'student' => $r->student->only(['id', 'university_id', 'full_name']),
            ]);
    }

    /** The department-scoped request rows shown on one student's detail page. */
    public function forStudent(Request $request, int $studentId): array
    {
        return ReuploadRequest::query()
            ->where('student_id', $studentId)
            ->whereHas('student', fn ($query) => $query->where('department_id', (int) $request->user()->department_id))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ReuploadRequest $r) => $this->row($r))
            ->all();
    }

    public function row(ReuploadRequest $r): array
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
