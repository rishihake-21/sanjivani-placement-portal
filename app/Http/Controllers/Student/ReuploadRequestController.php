<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ReuploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The student's view of requests raised by their coordinator (open ones first). */
class ReuploadRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = ReuploadRequest::query()->where('student_id', $this->currentStudent($request)->id)
            ->orderByRaw("case when status = 'OPEN' then 0 else 1 end")->latest()->limit(50)->get();

        return response()->json(['data' => $requests->map(fn (ReuploadRequest $r) => [
            'id' => $r->id,
            'subject_type' => $r->subject_type->value,
            'subject_id' => $r->subject_id,
            'reason' => $r->reason,
            'status' => $r->status->value,
            'created_at' => $r->created_at,
            'closed_at' => $r->closed_at,
        ])]);
    }
}
