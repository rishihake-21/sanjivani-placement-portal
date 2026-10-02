<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ExperienceType;
use App\Enums\RecordStatus;
use App\Models\Experience;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Internships / experience.
 *   no certificate                 -> SELF_DECLARED (visible, labelled unverified)
 *   certificate attached + submit  -> PENDING -> VERIFIED | REJECTED
 *   edit of a VERIFIED entry       -> new DRAFT revision that needs a fresh certificate
 */
class ExperienceService
{
    public const FIELDS = ['type', 'organization', 'role_title', 'start_date', 'end_date', 'is_ongoing', 'description'];

    public function __construct(private DocumentService $documents, private AuditLogger $audit)
    {
    }

    public function create(Student $student, User $actor, array $data, ?UploadedFile $certificate = null): Experience
    {
        $this->assertWritable($student);

        return DB::transaction(function () use ($student, $actor, $data, $certificate) {
            $experience = Experience::create($this->normalise(Arr::only($data, self::FIELDS)) + [
                'student_id' => $student->id,
                'status' => RecordStatus::SELF_DECLARED,
                'version' => 1,
                'is_ongoing' => false, // default; a value supplied by the student wins
            ]);

            $this->audit->record('experience.created', $experience, $student->id, null, AuditLogger::snapshot($experience), $actor);

            if ($certificate !== null) {
                $experience = $this->attachCertificate($experience, $actor, $certificate, submit: true);
            }

            return $experience->fresh(['certificate']);
        });
    }

    public function update(Experience $experience, User $actor, array $data): Experience
    {
        $this->assertWritable($experience->student);
        $fields = $this->normalise(Arr::only($data, self::FIELDS));

        return DB::transaction(function () use ($experience, $actor, $fields) {
            $experience = Experience::query()->whereKey($experience->id)->lockForUpdate()->firstOrFail();

            switch ($experience->status) {
                case RecordStatus::SELF_DECLARED:
                case RecordStatus::DRAFT:
                case RecordStatus::REJECTED:
                    $old = Arr::only($experience->attributesToArray(), array_keys($fields));
                    $experience->fill($fields);
                    $this->assertDatesConsistent($experience);
                    $experience->save();
                    $this->audit->record('experience.updated', $experience, $experience->student_id, $old, $fields, $actor);

                    return $experience->fresh(['certificate']);

                case RecordStatus::PENDING:
                    abort(409, 'This entry is under review and cannot be edited right now.');

                case RecordStatus::SUPERSEDED:
                    abort(409, 'This version was replaced by a newer one.');

                case RecordStatus::VERIFIED:
                    return $this->startRevision($experience, $actor, $fields);
            }
        });
    }

    private function startRevision(Experience $verified, User $actor, array $fields): Experience
    {
        $open = Experience::query()->where('supersedes_id', $verified->id)
            ->whereIn('status', [RecordStatus::DRAFT->value, RecordStatus::PENDING->value, RecordStatus::REJECTED->value])
            ->first();
        abort_if($open !== null, 409, 'A revision of this entry is already in progress (id ' . $open?->id . ').');

        $revision = $verified->replicate([
            'status', 'version', 'supersedes_id', 'certificate_document_id', 'submitted_at',
            'reviewed_by', 'reviewed_at', 'rejection_reason_code', 'rejection_reason',
        ]);
        $revision->fill($fields);
        $this->assertDatesConsistent($revision);
        $revision->forceFill([
            'status' => RecordStatus::DRAFT,
            'version' => $verified->version + 1,
            'supersedes_id' => $verified->id,
            'certificate_document_id' => null,
        ])->save();

        $this->audit->record('experience.revision_started', $revision, $verified->student_id,
            Arr::only($verified->attributesToArray(), array_keys($fields)), $fields, $actor,
            ['supersedes_id' => $verified->id]);

        return $revision->fresh(['certificate']);
    }

    /** Attaching a certificate and submitting for verification are one step for the student. */
    public function attachCertificate(Experience $experience, User $actor, UploadedFile $file, bool $submit = true): Experience
    {
        $this->assertWritable($experience->student);

        return DB::transaction(function () use ($experience, $actor, $file, $submit) {
            $experience = Experience::query()->whereKey($experience->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($experience->status, [RecordStatus::SELF_DECLARED, RecordStatus::DRAFT, RecordStatus::REJECTED], true),
                409,
                'A certificate can only be attached before review or after a rejection.'
            );

            $type = $experience->type instanceof ExperienceType ? $experience->type : ExperienceType::from($experience->type);
            $document = $this->documents->store(
                $experience->student, $file, $type->certificateType(), $actor, replaces: $experience->certificate
            );

            $experience->forceFill(['certificate_document_id' => $document->id])->save();
            $this->audit->record('experience.certificate_attached', $experience, $experience->student_id, null, [
                'document_uuid' => $document->uuid,
            ], $actor);

            return $submit ? $this->submit($experience, $actor) : $experience->fresh(['certificate']);
        });
    }

    public function submit(Experience $experience, User $actor): Experience
    {
        $this->assertWritable($experience->student);

        return DB::transaction(function () use ($experience, $actor) {
            $experience = Experience::query()->whereKey($experience->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($experience->status, [RecordStatus::SELF_DECLARED, RecordStatus::DRAFT, RecordStatus::REJECTED], true),
                409,
                'This entry cannot be submitted in its current state.'
            );

            $certificate = $experience->certificate;
            if ($certificate === null || $certificate->status !== DocumentStatus::PENDING) {
                throw ValidationException::withMessages([
                    'certificate' => ['Attach a certificate before submitting for verification.'],
                ]);
            }

            $before = ['status' => $experience->status->value];
            $experience->forceFill([
                'status' => RecordStatus::PENDING,
                'submitted_at' => now(),
                'rejection_reason_code' => null,
                'rejection_reason' => null,
            ])->save();

            $this->audit->record('experience.submitted', $experience, $experience->student_id, $before, ['status' => 'PENDING'], $actor);

            return $experience->fresh(['certificate']);
        });
    }

    /** Verified entries are history and are never deleted by the student. */
    public function delete(Experience $experience, User $actor): void
    {
        $this->assertWritable($experience->student);

        DB::transaction(function () use ($experience, $actor) {
            $experience = Experience::query()->whereKey($experience->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($experience->status, [RecordStatus::SELF_DECLARED, RecordStatus::DRAFT], true),
                409,
                'Only self-declared entries and drafts can be deleted.'
            );

            $snapshot = AuditLogger::snapshot($experience);
            $certificate = $experience->certificate;
            $experience->delete();

            if ($certificate !== null) {
                $this->documents->supersede($certificate);
            }

            $this->audit->record('experience.deleted', $experience, $experience->student_id, $snapshot, null, $actor);
        });
    }

    /** An ongoing entry never has an end date. */
    private function normalise(array $fields): array
    {
        if (($fields['is_ongoing'] ?? false) === true) {
            $fields['end_date'] = null;
        }

        return $fields;
    }

    private function assertDatesConsistent(Experience $experience): void
    {
        if ($experience->is_ongoing && $experience->end_date !== null) {
            $experience->end_date = null;
        }
        if (! $experience->is_ongoing && $experience->end_date === null) {
            throw ValidationException::withMessages(['end_date' => ['An end date is required unless the entry is ongoing.']]);
        }
        if ($experience->end_date !== null && $experience->end_date->lt($experience->start_date)) {
            throw ValidationException::withMessages(['end_date' => ['End date cannot be before the start date.']]);
        }
    }

    private function assertWritable(Student $student): void
    {
        abort_if($student->isLocked(), 423, 'Your profile is locked (batch completed). Contact the T&P office.');
    }
}
