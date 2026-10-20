<?php

namespace App\Http\Controllers\Coordinator;

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
use App\Services\Coordinator\VerificationQueueService;
use App\Services\VerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VerificationController extends Controller
{
    public function __construct(
        private VerificationService $verification,
        private VerificationQueueService $queue,
    ) {
    }

    /** Everything in this coordinator's department that is waiting for a decision (oldest first). */
    public function queue(Request $request): JsonResponse
    {
        return response()->json($this->queue->queue($request));
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
