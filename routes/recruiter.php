<?php

use App\Http\Controllers\Recruiter;
use Illuminate\Support\Facades\Route;

/*
| Required by routes/api.php, so it is mounted on /api behind auth:sanctum.
| Read-only: a recruiter looks at the applicants of their own company and nothing else.
| `recruiter.active` also refuses a recruiter whose account or company has been switched off.
*/
Route::middleware(['role:recruiter', 'recruiter.active'])->prefix('recruiter')->group(function () {
    Route::get('profile', [Recruiter\ProfileController::class, 'show']);

    Route::get('drives', [Recruiter\DriveController::class, 'index']);
    Route::get('drives/{id}', [Recruiter\DriveController::class, 'show'])->whereNumber('id');

    Route::get('applicants', [Recruiter\ApplicantController::class, 'index']);
    Route::get('applicants/{id}', [Recruiter\ApplicantController::class, 'show'])->whereNumber('id');

    Route::get('documents/{document}', [Recruiter\DocumentController::class, 'show'])
        ->whereUuid('document')->name('recruiter.documents.show');
});
