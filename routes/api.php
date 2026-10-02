<?php

use App\Http\Controllers\Admin\MasterListController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Coordinator;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Student;
use App\Http\Controllers\Tpo;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TPMS - student module API  (prefix /api, token auth via Laravel Sanctum)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);

    // The only door to stored files; authorisation is done by DocumentPolicy.
    Route::get('documents/{document}/download', DocumentDownloadController::class)->name('documents.download');

    // ---- Student ----------------------------------------------------------------------------
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('profile', [Student\ProfileController::class, 'show']);
        Route::patch('profile', [Student\ProfileController::class, 'update']);

        Route::get('academic-records', [Student\AcademicRecordController::class, 'index']);
        Route::post('academic-records', [Student\AcademicRecordController::class, 'store']);
        Route::get('academic-records/{id}', [Student\AcademicRecordController::class, 'show'])->whereNumber('id');
        Route::patch('academic-records/{id}', [Student\AcademicRecordController::class, 'update'])->whereNumber('id');
        Route::delete('academic-records/{id}', [Student\AcademicRecordController::class, 'destroy'])->whereNumber('id');
        Route::post('academic-records/{id}/document', [Student\AcademicRecordController::class, 'attachDocument'])->whereNumber('id');
        Route::post('academic-records/{id}/submit', [Student\AcademicRecordController::class, 'submit'])->whereNumber('id');

        Route::get('experiences', [Student\ExperienceController::class, 'index']);
        Route::post('experiences', [Student\ExperienceController::class, 'store']);
        Route::patch('experiences/{id}', [Student\ExperienceController::class, 'update'])->whereNumber('id');
        Route::delete('experiences/{id}', [Student\ExperienceController::class, 'destroy'])->whereNumber('id');
        Route::post('experiences/{id}/certificate', [Student\ExperienceController::class, 'attachCertificate'])->whereNumber('id');
        Route::post('experiences/{id}/submit', [Student\ExperienceController::class, 'submit'])->whereNumber('id');

        Route::get('documents', [Student\DocumentController::class, 'index']);
        Route::post('documents', [Student\DocumentController::class, 'store']);

        Route::get('notifications', [Student\NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [Student\NotificationController::class, 'markRead']);
    });

    // ---- T&P Coordinator (own department only) -------------------------------------------------
    Route::middleware('role:tp_coordinator')->prefix('coordinator')->group(function () {
        Route::get('students', [Coordinator\StudentController::class, 'index']);
        Route::get('students/{id}', [Coordinator\StudentController::class, 'show'])->whereNumber('id');

        Route::get('verification-queue', [Coordinator\VerificationController::class, 'queue']);

        Route::post('academic-records/{id}/approve', [Coordinator\VerificationController::class, 'approveAcademicRecord'])->whereNumber('id');
        Route::post('academic-records/{id}/reject', [Coordinator\VerificationController::class, 'rejectAcademicRecord'])->whereNumber('id');
        Route::post('academic-records/{id}/unlock', [Coordinator\VerificationController::class, 'unlockAcademicRecord'])->whereNumber('id');

        Route::post('experiences/{id}/approve', [Coordinator\VerificationController::class, 'approveExperience'])->whereNumber('id');
        Route::post('experiences/{id}/reject', [Coordinator\VerificationController::class, 'rejectExperience'])->whereNumber('id');

        Route::post('documents/{uuid}/approve', [Coordinator\VerificationController::class, 'approveDocument'])->whereUuid('uuid');
        Route::post('documents/{uuid}/reject', [Coordinator\VerificationController::class, 'rejectDocument'])->whereUuid('uuid');
    });

    // ---- TPO (university-wide, read-only for student data) -------------------------------------
    Route::middleware('role:tpo')->prefix('tpo')->group(function () {
        Route::get('students', [Tpo\StudentDirectoryController::class, 'index']);
        Route::get('students/{id}', [Tpo\StudentDirectoryController::class, 'show'])->whereNumber('id');
    });

    // ---- Master list import (TPO or System Admin) ----------------------------------------------
    Route::middleware('role:tpo,system_admin')->post('master-list/import', [MasterListController::class, 'import']);
});
