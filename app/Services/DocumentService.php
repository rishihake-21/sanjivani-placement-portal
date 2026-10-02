<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * All file handling goes through here:
 *   bytes  -> private storage disk  (students/{student_id}/{type}/{uuid}.{ext})
 *   facts  -> `documents` table     (metadata, status, review info)
 * Files are never publicly reachable; they are streamed through an authorised route.
 */
class DocumentService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * Store a file and create its metadata row (status PENDING).
     *
     * @param  Document|null  $replaces           document this upload is a new version of
     * @param  bool           $supersedeReplaced  mark the replaced document SUPERSEDED right away
     */
    public function store(
        Student $student,
        UploadedFile $file,
        DocumentType $type,
        User $uploader,
        ?Document $replaces = null,
        bool $primary = false,
        bool $supersedeReplaced = true,
    ): Document {
        $extension = $this->validatedExtension($file);
        $disk = config('tpms.documents.disk');
        $uuid = (string) Str::uuid();
        $directory = 'students/' . $student->id . '/' . strtolower($type->value);
        $filename = $uuid . '.' . $extension;

        // Metadata is read BEFORE the move (the temp file disappears afterwards).
        $meta = [
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ];

        $path = Storage::disk($disk)->putFileAs($directory, $file, $filename);
        if ($path === false) {
            abort(500, 'The file could not be stored. Please try again.');
        }

        try {
            return DB::transaction(function () use ($student, $type, $uploader, $replaces, $primary, $supersedeReplaced, $disk, $path, $uuid, $meta) {
                $version = 1;

                if ($replaces !== null) {
                    $old = Document::query()->whereKey($replaces->id)->lockForUpdate()->firstOrFail();
                    abort_unless($old->student_id === $student->id, 403);
                    abort_if($old->status === DocumentStatus::SUPERSEDED, 409, 'That document was already replaced.');
                    $version = $old->version + 1;

                    if ($supersedeReplaced) {
                        $this->supersede($old);
                    }
                }

                $document = Document::create($meta + [
                    'uuid' => $uuid,
                    'student_id' => $student->id,
                    'document_type' => $type,
                    'version' => $version,
                    'replaces_id' => $replaces?->id,
                    'disk' => $disk,
                    'path' => $path,
                    'status' => DocumentStatus::PENDING,
                    'is_primary' => $primary,
                    'uploaded_by' => $uploader->id,
                ]);

                $this->audit->record('document.uploaded', $document, $student->id, null, [
                    'document_type' => $type->value,
                    'version' => $version,
                    'original_name' => $meta['original_name'],
                    'size_bytes' => $meta['size_bytes'],
                ], $uploader);

                return $document;
            });
        } catch (Throwable $e) {
            // Don't leave an orphan file behind if the DB write failed.
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    /**
     * Standalone uploads: RESUME and CERT_OTHER.
     *
     * Resume rules
     *  - first resume                       -> becomes the primary immediately (still PENDING review)
     *  - current primary not yet approved   -> new upload replaces it
     *  - current primary is APPROVED        -> it stays primary until the new one is approved
     *                                          (so the student keeps "can apply" in the meantime)
     */
    public function uploadStandalone(Student $student, UploadedFile $file, DocumentType $type, User $uploader): Document
    {
        abort_unless($type->isStandalone(), 422, 'This document type must be attached to a record.');

        if ($type !== DocumentType::RESUME) {
            return $this->store($student, $file, $type, $uploader);
        }

        return DB::transaction(function () use ($student, $file, $type, $uploader) {
            $live = Document::query()
                ->where('student_id', $student->id)
                ->where('document_type', DocumentType::RESUME->value)
                ->where('status', '<>', DocumentStatus::SUPERSEDED->value)
                ->lockForUpdate()
                ->get();

            $primary = $live->firstWhere('is_primary', true);
            $candidate = $live->first(fn (Document $d) => ! $d->is_primary);

            if ($primary === null) {
                return $this->store($student, $file, $type, $uploader, primary: true);
            }

            if ($primary->status !== DocumentStatus::APPROVED) {
                return $this->store($student, $file, $type, $uploader, replaces: $primary, primary: true);
            }

            // Approved primary stays; the new file is a candidate that replaces any older candidate.
            return $this->store($student, $file, $type, $uploader, replaces: $candidate ?? $primary, primary: false, supersedeReplaced: $candidate !== null);
        });
    }

    public function supersede(Document $document): void
    {
        $document->forceFill(['status' => DocumentStatus::SUPERSEDED, 'is_primary' => false])->save();
    }

    public function download(Document $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404, 'File not found in storage.');

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validatedExtension(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) $file->guessExtension());
        $maxBytes = (int) config('tpms.documents.max_kb') * 1024;

        $errors = [];
        if (! in_array($mime, config('tpms.documents.allowed_mimes'), true)) {
            $errors[] = 'Only PDF, JPG and PNG files are accepted.';
        }
        if (! in_array($extension, config('tpms.documents.allowed_extensions'), true)) {
            $errors[] = 'Unsupported file type.';
        }
        if ($file->getSize() > $maxBytes) {
            $errors[] = 'The file is larger than ' . config('tpms.documents.max_kb') . ' KB.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['document' => $errors]);
        }

        return $extension;
    }
}
