<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\RecordStatus;
use App\Enums\RejectionReason;
use App\Models\AcademicRecord;
use App\Models\Document;
use App\Models\Experience;
use App\Models\User;
use App\Notifications\RecordReviewed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Coordinator-side decisions. A record is VERIFIED only together with its document, and both
 * change inside ONE transaction, so data and proof can never disagree.
 *
 * Authorisation (same department) is enforced by policies in the controllers; this class
 * enforces STATE rules only.
 */
class VerificationService
{
    public function __construct(private AuditLogger $audit, private DocumentService $documents)
    {
    }

    /** @param  AcademicRecord|Experience  $record */
    public function approve(AcademicRecord|Experience $record, User $reviewer): AcademicRecord|Experience
    {
        $approved = DB::transaction(function () use ($record, $reviewer) {
            $locked = $record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === RecordStatus::PENDING, 409, 'Only records awaiting review can be approved.');

            $document = $this->lockedPendingProof($locked);

            // A revision replaces the verified row it was based on - old first, so the
            // "one VERIFIED row per key" unique index is never violated.
            $before = null;
            if ($locked->supersedes_id !== null) {
                $old = $record::query()->whereKey($locked->supersedes_id)->lockForUpdate()->first();
                if ($old !== null && $old->status === RecordStatus::VERIFIED) {
                    $before = AuditLogger::snapshot($old);
                    $oldProof = $old->proofDocument();
                    $old->forceFill(['status' => RecordStatus::SUPERSEDED])->save();
                    if ($oldProof !== null && $oldProof->status === DocumentStatus::APPROVED) {
                        $this->documents->supersede($oldProof);
                    }
                }
            }

            $now = now();
            $locked->forceFill(array_merge([
                'status' => RecordStatus::VERIFIED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $now,
                'rejection_reason_code' => null,
                'rejection_reason' => null,
            ], $locked->verificationAttributes()))->save();

            $document->forceFill([
                'status' => DocumentStatus::APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $now,
                'rejection_reason_code' => null,
                'rejection_reason' => null,
            ])->save();

            $this->audit->record('record.approved', $locked, $locked->student_id, $before, AuditLogger::snapshot($locked), $reviewer, [
                'document_uuid' => $document->uuid,
            ]);

            return $locked;
        });

        $this->notify($approved, 'APPROVED');

        return $approved;
    }

    public function reject(
        AcademicRecord|Experience $record,
        User $reviewer,
        RejectionReason $code,
        ?string $text = null,
    ): AcademicRecord|Experience {
        $this->assertReasonText($code, $text);

        $rejected = DB::transaction(function () use ($record, $reviewer, $code, $text) {
            $locked = $record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === RecordStatus::PENDING, 409, 'Only records awaiting review can be rejected.');

            $document = $this->lockedPendingProof($locked);
            $now = now();

            $locked->forceFill([
                'status' => RecordStatus::REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $now,
                'rejection_reason_code' => $code,
                'rejection_reason' => $text,
            ])->save();

            // "Entered values don't match" blames the DATA, not the file: the student can correct
            // the numbers and resubmit without uploading the same document again.
            if ($code !== RejectionReason::DATA_MISMATCH) {
                $document->forceFill([
                    'status' => DocumentStatus::REJECTED,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => $now,
                    'rejection_reason_code' => $code,
                    'rejection_reason' => $text,
                ])->save();
            }

            $this->audit->record('record.rejected', $locked, $locked->student_id, null, [
                'reason_code' => $code->value,
                'reason' => $text,
            ], $reviewer, ['document_uuid' => $document->uuid]);

            return $locked;
        });

        $this->notify($rejected, 'REJECTED', $code, $text);

        return $rejected;
    }

    /** Past-term academic rows are locked once verified; the coordinator can unlock one for a correction. */
    public function unlock(AcademicRecord $record, User $coordinator, string $reason): AcademicRecord
    {
        $unlocked = DB::transaction(function () use ($record, $coordinator, $reason) {
            $locked = AcademicRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === RecordStatus::VERIFIED, 409, 'Only verified records can be unlocked.');
            abort_unless($locked->isLocked(), 409, 'This record is not locked.');

            $locked->forceFill(['locked_at' => null])->save();
            $this->audit->record('record.unlocked', $locked, $locked->student_id, ['locked' => true], ['locked' => false], $coordinator, [
                'reason' => $reason,
            ]);

            return $locked;
        });

        $this->notify($unlocked, 'UNLOCKED', null, $reason);

        return $unlocked;
    }

    // ---- standalone documents (resume, other certificates) ---------------------------------

    public function approveDocument(Document $document, User $reviewer): Document
    {
        abort_unless($document->isStandalone(), 422, 'Documents attached to a record are reviewed through that record.');

        $approved = DB::transaction(function () use ($document, $reviewer) {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === DocumentStatus::PENDING, 409, 'Only documents awaiting review can be approved.');

            $isPrimary = $locked->is_primary;

            // A newly approved resume takes over from the previous primary one.
            if ($locked->document_type->value === 'RESUME' && ! $isPrimary) {
                $previous = Document::query()
                    ->where('student_id', $locked->student_id)
                    ->where('document_type', 'RESUME')
                    ->where('is_primary', true)
                    ->where('status', '<>', DocumentStatus::SUPERSEDED->value)
                    ->lockForUpdate()
                    ->first();
                if ($previous !== null) {
                    $this->documents->supersede($previous);
                }
                $isPrimary = true;
            }

            $locked->forceFill([
                'status' => DocumentStatus::APPROVED,
                'is_primary' => $isPrimary,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason_code' => null,
                'rejection_reason' => null,
            ])->save();

            $this->audit->record('document.approved', $locked, $locked->student_id, null, ['status' => 'APPROVED'], $reviewer);

            return $locked;
        });

        $this->notifyDocument($approved, 'APPROVED');

        return $approved;
    }

    public function rejectDocument(Document $document, User $reviewer, RejectionReason $code, ?string $text = null): Document
    {
        abort_unless($document->isStandalone(), 422, 'Documents attached to a record are reviewed through that record.');
        $this->assertReasonText($code, $text);

        $rejected = DB::transaction(function () use ($document, $reviewer, $code, $text) {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === DocumentStatus::PENDING, 409, 'Only documents awaiting review can be rejected.');

            $locked->forceFill([
                'status' => DocumentStatus::REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason_code' => $code,
                'rejection_reason' => $text,
            ])->save();

            $this->audit->record('document.rejected', $locked, $locked->student_id, null, [
                'reason_code' => $code->value,
                'reason' => $text,
            ], $reviewer);

            return $locked;
        });

        $this->notifyDocument($rejected, 'REJECTED', $code, $text);

        return $rejected;
    }

    // ---- helpers -----------------------------------------------------------------------------

    private function lockedPendingProof(AcademicRecord|Experience $record): Document
    {
        $proof = $record->proofDocument();
        abort_if($proof === null, 409, 'This record has no supporting document.');

        $document = Document::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();
        abort_unless($document->status === DocumentStatus::PENDING, 409, 'The supporting document is not awaiting review.');

        return $document;
    }

    private function assertReasonText(RejectionReason $code, ?string $text): void
    {
        if ($code === RejectionReason::OTHER && trim((string) $text) === '') {
            throw ValidationException::withMessages(['reason' => ['Please describe the reason when choosing "Other".']]);
        }
    }

    private function notify(AcademicRecord|Experience $record, string $decision, ?RejectionReason $code = null, ?string $text = null): void
    {
        $record->student->user->notify(new RecordReviewed($record->label(), $decision, $code?->value, $text ?? $code?->label()));
    }

    private function notifyDocument(Document $document, string $decision, ?RejectionReason $code = null, ?string $text = null): void
    {
        $document->student->user->notify(new RecordReviewed($document->document_type->value, $decision, $code?->value, $text ?? $code?->label()));
    }
}
