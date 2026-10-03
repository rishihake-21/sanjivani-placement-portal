<?php

use App\Http\Controllers\Student;
use App\Http\Controllers\Tpo;
use Illuminate\Support\Facades\Route;

/*
| Loaded by TpoServiceProvider under: prefix /api, middleware api + auth:sanctum.
*/

// ---- TPO ------------------------------------------------------------------------------------------
Route::middleware('role:tpo')->prefix('tpo')->group(function () {
    // companies
    Route::get('companies', [Tpo\CompanyController::class, 'index']);
    Route::post('companies', [Tpo\CompanyController::class, 'store']);
    Route::get('companies/{id}', [Tpo\CompanyController::class, 'show'])->whereNumber('id');
    Route::patch('companies/{id}', [Tpo\CompanyController::class, 'update'])->whereNumber('id');

    // drives
    Route::get('drives', [Tpo\DriveController::class, 'index']);
    Route::post('drives', [Tpo\DriveController::class, 'store']);
    Route::get('drives/{id}', [Tpo\DriveController::class, 'show'])->whereNumber('id');
    Route::patch('drives/{id}', [Tpo\DriveController::class, 'update'])->whereNumber('id');
    Route::delete('drives/{id}', [Tpo\DriveController::class, 'destroy'])->whereNumber('id');
    Route::post('drives/{id}/publish', [Tpo\DriveController::class, 'publish'])->whereNumber('id');
    Route::post('drives/{id}/close', [Tpo\DriveController::class, 'close'])->whereNumber('id');
    Route::post('drives/{id}/cancel', [Tpo\DriveController::class, 'cancel'])->whereNumber('id');

    // eligible students
    Route::get('drives/{id}/eligible-students', [Tpo\DriveEligibilityController::class, 'students'])->whereNumber('id');
    Route::get('drives/{id}/eligibility-summary', [Tpo\DriveEligibilityController::class, 'summary'])->whereNumber('id');

    // applications and recruitment rounds
    Route::get('applications', [Tpo\ApplicationController::class, 'index']);
    Route::get('applications/{id}', [Tpo\ApplicationController::class, 'show'])->whereNumber('id');
    Route::post('applications/{id}/stage', [Tpo\ApplicationController::class, 'changeStage'])->whereNumber('id');
    Route::post('applications/{id}/offer', [Tpo\ApplicationController::class, 'recordOffer'])->whereNumber('id');
    Route::post('drives/{driveId}/applications/bulk-stage', [Tpo\ApplicationController::class, 'bulkChangeStage'])->whereNumber('driveId');

    // official placements
    Route::get('placements', [Tpo\PlacementController::class, 'index']);
    Route::post('placements', [Tpo\PlacementController::class, 'store']);
    Route::patch('placements/{id}', [Tpo\PlacementController::class, 'update'])->whereNumber('id');
    Route::post('placements/{id}/verify', [Tpo\PlacementController::class, 'verify'])->whereNumber('id');
    Route::post('placements/{id}/decline', [Tpo\PlacementController::class, 'decline'])->whereNumber('id');

    // statistics and reports
    Route::get('dashboard', [Tpo\DashboardController::class, 'dashboard']);
    Route::get('reports/departments', [Tpo\DashboardController::class, 'departments']);
    Route::get('reports/branches', [Tpo\DashboardController::class, 'branches']);
    Route::get('reports/companies', [Tpo\DashboardController::class, 'companies']);
    Route::get('reports/students.csv', [Tpo\ReportController::class, 'students']);
    Route::get('reports/placements.csv', [Tpo\ReportController::class, 'placements']);
});

// ---- Student: drives, applications, own placements ------------------------------------------------------
Route::middleware('role:student')->prefix('student')->group(function () {
    Route::get('drives', [Student\DriveController::class, 'index']);
    Route::get('drives/{id}', [Student\DriveController::class, 'show'])->whereNumber('id');
    Route::post('drives/{id}/apply', [Student\DriveController::class, 'apply'])->whereNumber('id')->middleware('throttle:20,1');

    Route::get('applications', [Student\ApplicationController::class, 'index']);
    Route::post('applications/{id}/withdraw', [Student\ApplicationController::class, 'withdraw'])->whereNumber('id');

    Route::get('placements', [Student\PlacementController::class, 'index']);
});
