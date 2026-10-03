<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Enums\ReuploadStatus;
use App\Enums\ReuploadSubject;
use App\Models\AcademicRecord;
use App\Models\Document;
use App\Models\Experience;
use App\Models\ReuploadRequest;
use App\Models\Student;
use App\Models\User;
use App\Notifications\RecordReviewed;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A coordinator asks a student to redo something that was ALREADY accepted
 * (pending items are simply rejected through the verification flow).
 *  - verified academic record : unlocked (if locked) so the student can start a correction
 *  - verified experience      : student edits it -> revision
 *  - standalone document      : student uploads a replacement
 * The request closes itself when the student submits the new version (CoordinatorServiceProvider).
 */
class ReuploadRequestService
{
    public function __construct(private AuditLogger $audit, private VerificationService $verification)
    {
    }

    public function create(User $coordinator, Student $student, ReuploadSubject $type, int $subjectId, string $reason): ReuploadRequest
    {
        try {
            $request = DB::transaction(function () use ($coordinator, $student, $type, $subjectId, $reason) {
                $label = $this->prepareSubject($coordinator, $student, $type, $subjectId, $reason);

                $request = ReuploadRequest::create([
                    'student_id' => $student->id,
                    'subject_type' => $type,
                    'subject_id' => $subjectId,
                    'requested_by' => $coordinator->id,
                    'reason' => $reason,
                    'status' => ReuploadStatus::OPEN,
                ]);

                $this->audit->record('reupload.requested', $request, $student->id, null, [
                    'subject_type' => $type->value, 'subject_id' => $subjectId, 'reason' => $reason,
                ], $coordinator);

                $student->user->notify(new RecordReviewed($label, 'REUPLOAD_REQUESTED', null, $reason));

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A re-upload request for this item is already open.');
        }

        return $request;
    }

    public function cancel(ReuploadRequest $request, User $coordinator): ReuploadRequest
    {
        return DB::transaction(function () use ($request, $coordinator) {
            $locked = ReuploadRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === ReuploadStatus::OPEN, 409, 'Only open requests can be cancelled.');

            $locked->forceFill([
                'status' => ReuploadStatus::CANCELLED,
                'closed_at' => now(),
                'closed_by' => $coordinator->id,
            ])->save();

            $this->audit->record('reupload.cancelled', $locked, $locked->student_id, ['status' => 'OPEN'], ['status' => 'CANCELLED'], $coordinator);

            return $locked;
        });
    }

    /** Validates the target belongs to the student and is in a state where a re-upload makes sense. Returns its label. */
    private function prepareSubject(User $coordinator, Student $student, ReuploadSubject $type, int $id, string $reason): string
    {
        switch ($type) {
            case ReuploadSubject::ACADEMIC_RECORD:
                $record = AcademicRecord::query()->where('student_id', $student->id)->lockForUpdate()->find($id);
                abort_if($record === null, 404, 'Record not found for this student.');
                abort_unless($record->status === RecordStatus::VERIFIED, 409, 'Only verified records need a re-upload request. Reject pending records instead.');
                if ($record->isLocked()) {
                    $this->verification->unlock($record, $coordinator, 'Re-upload requested: ' . $reason);
                }

                return $record->label();

            case ReuploadSubject::EXPERIENCE:
                $experience = Experience::query()->where('student_id', $student->id)->lockForUpdate()->find($id);
                abort_if($experience === null, 404, 'Experience not found for this student.');
                abort_unless($experience->status === RecordStatus::VERIFIED, 409, 'Only verified entries need a re-upload request. Reject pending entries instead.');

                return $experience->label();

            case ReuploadSubject::DOCUMENT:
                $document = Document::query()->where('student_id', $student->id)->lockForUpdate()->find($id);
                abort_if($document === null, 404, 'Document not found for this student.');
                abort_unless($document->isStandalone(), 422, 'Marksheets and certificates are re-requested through their record.');
                abort_if($document->status->value === 'SUPERSEDED', 409, 'That document was already replaced.');

                return $document->document_type->value;
        }
    }
}
