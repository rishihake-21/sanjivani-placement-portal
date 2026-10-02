<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\DocumentType;
use App\Enums\RejectionReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectRequest;
use App\Http\Requests\UnlockRequest;
use App\Http\Resources\AcademicRecordResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ExperienceResource;
use App\Models\AcademicRecord;
use App\Models\Document;
use App\Models\Experience;
use App\Services\VerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VerificationController extends Controller
{
    public function __construct(private VerificationService $verification)
    {
    }

    /** Everything in this coordinator's department that is waiting for a decision (oldest first). */
    public function queue(Request $request): JsonResponse
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

        return response()->json([
            'academic_records' => $academic,
            'experiences' => $experiences,
            'documents' => $documents,
        ]);
    }

    // ---- academic records ----------------------------------------------------------------------

    public function approveAcademicRecord(Request $request, int $id): AcademicRecordResource
    {
        $record = $this->academic($request, $id);
        Gate::authorize('review', $record);

        return new AcademicRecordResource($this->verification->approve($record, $request->user())->load('document'));
    }

    public function rejectAcademicRecord(RejectRequest $request, int $id): AcademicRecordResource
    {
        $record = $this->academic($request, $id);
        Gate::authorize('review', $record);

        $rejected = $this->verification->reject(
            $record, $request->user(), RejectionReason::from($request->validated('reason_code')), $request->validated('reason')
        );

        return new AcademicRecordResource($rejected->load('document'));
    }

    public function unlockAcademicRecord(UnlockRequest $request, int $id): AcademicRecordResource
    {
        $record = $this->academic($request, $id);
        Gate::authorize('unlock', $record);

        return new AcademicRecordResource($this->verification->unlock($record, $request->user(), $request->validated('reason'))->load('document'));
    }

    // ---- experiences ----------------------------------------------------------------------------

    public function approveExperience(Request $request, int $id): ExperienceResource
    {
        $experience = $this->experience($request, $id);
        Gate::authorize('review', $experience);

        return new ExperienceResource($this->verification->approve($experience, $request->user())->load('certificate'));
    }

    public function rejectExperience(RejectRequest $request, int $id): ExperienceResource
    {
        $experience = $this->experience($request, $id);
        Gate::authorize('review', $experience);

        $rejected = $this->verification->reject(
            $experience, $request->user(), RejectionReason::from($request->validated('reason_code')), $request->validated('reason')
        );

        return new ExperienceResource($rejected->load('certificate'));
    }

    // ---- standalone documents (resume etc.) -----------------------------------------------------

    public function approveDocument(Request $request, string $uuid): DocumentResource
    {
        $document = $this->document($request, $uuid);
        Gate::authorize('review', $document);

        return new DocumentResource($this->verification->approveDocument($document, $request->user()));
    }

    public function rejectDocument(RejectRequest $request, string $uuid): DocumentResource
    {
        $document = $this->document($request, $uuid);
        Gate::authorize('review', $document);

        return new DocumentResource($this->verification->rejectDocument(
            $document, $request->user(), RejectionReason::from($request->validated('reason_code')), $request->validated('reason')
        ));
    }

    // ---- department-scoped lookups (other departments -> 404) ----------------------------------

    private function inDepartment(Request $request): \Closure
    {
        return fn (Builder $q) => $q->where('department_id', (int) $request->user()->department_id);
    }

    private function academic(Request $request, int $id): AcademicRecord
    {
        return AcademicRecord::query()->whereHas('student', $this->inDepartment($request))->findOrFail($id);
    }

    private function experience(Request $request, int $id): Experience
    {
        return Experience::query()->whereHas('student', $this->inDepartment($request))->findOrFail($id);
    }

    private function document(Request $request, string $uuid): Document
    {
        return Document::query()->where('uuid', $uuid)->whereHas('student', $this->inDepartment($request))->firstOrFail();
    }
}
