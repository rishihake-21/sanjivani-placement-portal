<?php

namespace App\Providers;

use App\Enums\RecordStatus;
use App\Enums\ReuploadSubject;
use App\Models\AcademicRecord;
use App\Models\Document;
use App\Models\Experience;
use App\Models\ReuploadRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Closes a coordinator's re-upload request automatically when the student submits a new version.
 * Done with model events so the student-module services stay untouched.
 * Also loads routes/coordinator.php. Register in bootstrap/providers.php.
 */
class CoordinatorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->prefix('api')->group(base_path('routes/coordinator.php'));

        AcademicRecord::updated(function (AcademicRecord $record) {
            if ($record->wasChanged('status') && $record->status === RecordStatus::PENDING) {
                ReuploadRequest::fulfil(ReuploadSubject::ACADEMIC_RECORD, [$record->id, $record->supersedes_id]);
            }
        });

        Experience::updated(function (Experience $experience) {
            if ($experience->wasChanged('status') && $experience->status === RecordStatus::PENDING) {
                ReuploadRequest::fulfil(ReuploadSubject::EXPERIENCE, [$experience->id, $experience->supersedes_id]);
            }
        });

        // A new file that replaces an older one (resume / other certificate).
        Document::created(function (Document $document) {
            if ($document->replaces_id !== null) {
                ReuploadRequest::fulfil(ReuploadSubject::DOCUMENT, [$document->replaces_id]);
            }
        });
    }
}
