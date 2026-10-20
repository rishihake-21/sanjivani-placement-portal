<?php

namespace App\Services\Coordinator;

use App\Enums\DocumentType;
use App\Http\Resources\AcademicRecordResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ExperienceResource;
use App\Models\AcademicRecord;
use App\Models\Document;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Read model behind the coordinator verification queue (GET /api/coordinator/verification-queue):
 * everything in this coordinator's department that is waiting for a decision (oldest first).
 */
class VerificationQueueService
{
    public function queue(Request $request): array
    {
        $inDept = fn (Builder $q) => $q->where('department_id', (int) $request->user()->department_id);
        $studentCols = 'student:id,university_id,full_name,current_semester';

        $academic = AcademicRecord::query()->pendingReview()->whereHas('student', $inDept)
            ->with([$studentCols, 'document', 'supersedes'])->orderBy('submitted_at')->limit(100)->get()
            ->map(fn (AcademicRecord $r) => (new AcademicRecordResource($r))->toArray($request) + [
                'student' => $r->student->only(['id', 'university_id', 'full_name']),
                // for a revision: the currently verified values to compare against
                'previous_verified' => $r->supersedes ? (new AcademicRecordResource($r->supersedes))->toArray($request) : null,
            ]);

        $experiences = Experience::query()->pendingReview()->whereHas('student', $inDept)
            ->with([$studentCols, 'certificate', 'supersedes'])->orderBy('submitted_at')->limit(100)->get()
            ->map(fn (Experience $e) => (new ExperienceResource($e))->toArray($request) + [
                'student' => $e->student->only(['id', 'university_id', 'full_name']),
                'previous_verified' => $e->supersedes ? (new ExperienceResource($e->supersedes))->toArray($request) : null,
            ]);

        $documents = Document::query()->where('status', 'PENDING')
            ->whereIn('document_type', DocumentType::standaloneValues())
            ->whereHas('student', $inDept)->with($studentCols)->orderBy('created_at')->limit(100)->get()
            ->map(fn (Document $d) => (new DocumentResource($d))->toArray($request) + [
                'student' => $d->student->only(['id', 'university_id', 'full_name']),
            ]);

        return [
            'academic_records' => $academic,
            'experiences' => $experiences,
            'documents' => $documents,
        ];
    }
}
