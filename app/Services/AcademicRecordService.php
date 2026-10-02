<?php

namespace App\Services;

use App\Enums\AcademicLevel;
use App\Enums\DocumentStatus;
use App\Enums\RecordStatus;
use App\Models\AcademicRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student-side lifecycle of academic rows.
 *
 *   create -> DRAFT --(attach marksheet)--> DRAFT --submit--> PENDING --(coordinator)--> VERIFIED | REJECTED
 *   REJECTED --(fix data / re-upload)--> submit --> PENDING
 *   VERIFIED --edit--> NEW revision row (DRAFT, supersedes the old one). The old row stays VERIFIED
 *                      until the revision is approved.
 */
class AcademicRecordService
{
    public const FIELDS = [
        'institution_name', 'board_or_university', 'passing_year',
        'percentage', 'obtained_marks', 'total_marks',
        'sgpa', 'cgpa', 'backlogs_in_term', 'active_backlogs_after_term',
    ];

    public function __construct(private DocumentService $documents, private AuditLogger $audit)
    {
    }

    public function create(Student $student, User $actor, array $data, ?UploadedFile $file = null): AcademicRecord
    {
        $this->assertWritable($student);

        $level = AcademicLevel::from($data['level']);
        $semester = $level === AcademicLevel::DEGREE_SEM ? (int) $data['semester'] : null;

        if (! $student->allowsAcademicKey($level, $semester)) {
            throw ValidationException::withMessages([
                'level' => [$this->notAllowedMessage($student, $level, $semester)],
            ]);
        }

        $existing = AcademicRecord::query()->where('student_id', $student->id)
            ->forKey($level, $semester)->live()->first();
        abort_if($existing !== null, 409, $level->label($semester) . ' already has a record (id ' . $existing?->id . '). Edit that record instead.');

        try {
            return DB::transaction(function () use ($student, $actor, $data, $file, $level, $semester) {
                $record = AcademicRecord::create(Arr::only($data, self::FIELDS) + [
                    'student_id' => $student->id,
                    'level' => $level,
                    'semester' => $semester,
                    'status' => RecordStatus::DRAFT,
                    'version' => 1,
                ]);

                if ($file !== null) {
                    $this->attachDocument($record, $actor, $file);
                }

                $this->audit->record('academic_record.created', $record, $student->id, null, AuditLogger::snapshot($record), $actor);

                return $record->fresh(['document']);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, $level->label($semester) . ' already has a record.');
        }
    }

    public function update(AcademicRecord $record, User $actor, array $data): AcademicRecord
    {
        $fields = Arr::only($data, self::FIELDS);
        $this->assertWritable($record->student);

        return DB::transaction(function () use ($record, $actor, $fields) {
            $record = AcademicRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

            switch ($record->status) {
                case RecordStatus::DRAFT:
                case RecordStatus::REJECTED:
                    $old = Arr::only($record->attributesToArray(), array_keys($fields));
                    $record->fill($fields)->save();
                    $this->audit->record('academic_record.updated', $record, $record->student_id, $old, $fields, $actor);

                    return $record->fresh(['document']);

                case RecordStatus::PENDING:
                    abort(409, 'This record is under review and cannot be edited right now.');

                case RecordStatus::SUPERSEDED:
                    abort(409, 'This version was replaced by a newer one.');

                case RecordStatus::VERIFIED:
                    return $this->startRevision($record, $actor, $fields);

                default:
                    abort(409, 'This record cannot be edited.');
            }
        });
    }

    /** Verified rows are never edited in place: the new values live in a new, unverified row. */
    private function startRevision(AcademicRecord $verified, User $actor, array $fields): AcademicRecord
    {
        abort_if(
            $verified->isLocked(),
            423,
            'This record is locked. Ask your T&P Coordinator to unlock it before editing.'
        );

        $open = AcademicRecord::query()->where('supersedes_id', $verified->id)
            ->whereIn('status', [RecordStatus::DRAFT->value, RecordStatus::PENDING->value, RecordStatus::REJECTED->value])
            ->first();
        abort_if($open !== null, 409, 'A revision of this record is already in progress (id ' . $open?->id . ').');

        $revision = $verified->replicate([
            'status', 'version', 'supersedes_id', 'document_id', 'submitted_at',
            'reviewed_by', 'reviewed_at', 'rejection_reason_code', 'rejection_reason', 'locked_at',
        ]);
        $revision->fill($fields);
        $revision->forceFill([
            'status' => RecordStatus::DRAFT,
            'version' => $verified->version + 1,
            'supersedes_id' => $verified->id,
            'document_id' => null, // changed values need fresh proof
        ])->save();

        $this->audit->record(
            'academic_record.revision_started',
            $revision,
            $verified->student_id,
            Arr::only($verified->attributesToArray(), array_keys($fields)),
            $fields,
            $actor,
            ['supersedes_id' => $verified->id],
        );

        return $revision->fresh(['document']);
    }

    public function attachDocument(AcademicRecord $record, User $actor, UploadedFile $file): AcademicRecord
    {
        $this->assertWritable($record->student);

        return DB::transaction(function () use ($record, $actor, $file) {
            $record = AcademicRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($record->status, [RecordStatus::DRAFT, RecordStatus::REJECTED], true),
                409,
                'A document can only be attached while the record is a draft or was rejected.'
            );

            $document = $this->documents->store(
                $record->student,
                $file,
                $record->level->documentType(),
                $actor,
                replaces: $record->document,
            );

            $record->forceFill(['document_id' => $document->id])->save();
            $this->audit->record('academic_record.document_attached', $record, $record->student_id, null, [
                'document_uuid' => $document->uuid,
                'version' => $document->version,
            ], $actor);

            return $record->fresh(['document']);
        });
    }

    public function submit(AcademicRecord $record, User $actor): AcademicRecord
    {
        $this->assertWritable($record->student);

        return DB::transaction(function () use ($record, $actor) {
            $record = AcademicRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($record->status, [RecordStatus::DRAFT, RecordStatus::REJECTED], true),
                409,
                'Only drafts or rejected records can be submitted.'
            );

            $document = $record->document;
            if ($document === null || $document->status !== DocumentStatus::PENDING) {
                throw ValidationException::withMessages([
                    'document' => ['Upload a supporting marksheet before submitting.'],
                ]);
            }

            $before = ['status' => $record->status->value, 'rejection_reason_code' => $record->rejection_reason_code?->value];
            $record->forceFill([
                'status' => RecordStatus::PENDING,
                'submitted_at' => now(),
                'rejection_reason_code' => null,
                'rejection_reason' => null,
            ])->save();

            $this->audit->record('academic_record.submitted', $record, $record->student_id, $before, ['status' => 'PENDING'], $actor);

            return $record->fresh(['document']);
        });
    }

    /** Only never-submitted drafts can be deleted. Verified history is never removed. */
    public function delete(AcademicRecord $record, User $actor): void
    {
        $this->assertWritable($record->student);

        DB::transaction(function () use ($record, $actor) {
            $record = AcademicRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === RecordStatus::DRAFT, 409, 'Only draft records can be deleted.');

            $snapshot = AuditLogger::snapshot($record);
            $document = $record->document;
            $record->delete();

            if ($document !== null) {
                $this->documents->supersede($document);
            }

            $this->audit->record('academic_record.deleted', $record, $record->student_id, $snapshot, null, $actor);
        });
    }

    private function assertWritable(Student $student): void
    {
        abort_if($student->isLocked(), 423, 'Your profile is locked (batch completed). Contact the T&P office.');
    }

    private function notAllowedMessage(Student $student, AcademicLevel $level, ?int $semester): string
    {
        return match (true) {
            $level === AcademicLevel::DIPLOMA => 'Diploma details apply to lateral-entry students only.',
            $level === AcademicLevel::DEGREE_SEM => sprintf(
                'Semester %s is not valid for you. You can add semesters %d to %d.',
                $semester ?? '?',
                $student->admission_type->firstSemester(),
                $student->current_semester,
            ),
            default => 'This academic level is not allowed.',
        };
    }
}
