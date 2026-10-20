<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Coordinator;
use App\Http\Controllers\Student;
use Illuminate\Support\Facades\Route;

/*
| Required by routes/api.php, so it is mounted on /api behind auth:sanctum.
| Shared by the T&P coordinator and the system admin.
*/

// ---- T&P Coordinator (own department only) ------------------------------------------------------
Route::middleware('role:tp_coordinator')->prefix('coordinator')->group(function () {
    Route::get('students', [Coordinator\StudentController::class, 'index']);
    Route::get('students/{id}', [Coordinator\StudentController::class, 'show'])->whereNumber('id');

    Route::get('profile', [Coordinator\ProfileController::class, 'show']);

    Route::get('dashboard', [Coordinator\DashboardController::class, 'dashboard']);
    Route::get('reports/branch-summary', [Coordinator\DashboardController::class, 'branchSummary']);
    Route::get('reports/students.csv', [Coordinator\ReportController::class, 'students']);
    Route::get('reports/placements.csv', [Coordinator\ReportController::class, 'placements']);

    Route::get('drives', [Coordinator\DriveController::class, 'index']);
    Route::get('drives/{id}', [Coordinator\DriveController::class, 'show'])->whereNumber('id');

    Route::get('applications', [Coordinator\ApplicationController::class, 'index']);
    Route::get('applications/{id}', [Coordinator\ApplicationController::class, 'show'])->whereNumber('id');
    Route::get('students/{studentId}/progress', [Coordinator\ApplicationController::class, 'studentProgress'])->whereNumber('studentId');

    Route::get('placements', [Coordinator\PlacementController::class, 'index']);

    Route::get('reupload-requests', [Coordinator\ReuploadRequestController::class, 'index']);
    Route::post('students/{studentId}/reupload-requests', [Coordinator\ReuploadRequestController::class, 'store'])->whereNumber('studentId');
    Route::post('reupload-requests/{id}/cancel', [Coordinator\ReuploadRequestController::class, 'cancel'])->whereNumber('id');

    Route::get('verification-queue', [Coordinator\VerificationController::class, 'queue']);
    Route::post('academic-records/{id}/approve', [Coordinator\VerificationController::class, 'approveAcademicRecord'])->whereNumber('id');
    Route::post('academic-records/{id}/reject', [Coordinator\VerificationController::class, 'rejectAcademicRecord'])->whereNumber('id');
    Route::post('academic-records/{id}/unlock', [Coordinator\VerificationController::class, 'unlockAcademicRecord'])->whereNumber('id');
    Route::post('experiences/{id}/approve', [Coordinator\VerificationController::class, 'approveExperience'])->whereNumber('id');
    Route::post('experiences/{id}/reject', [Coordinator\VerificationController::class, 'rejectExperience'])->whereNumber('id');
    Route::post('documents/{document}/approve', [Coordinator\VerificationController::class, 'approveDocument'])->whereUuid('document');
    Route::post('documents/{document}/reject', [Coordinator\VerificationController::class, 'rejectDocument'])->whereUuid('document');
});

// ---- Student: sees what their coordinator asked for ----------------------------------------------
Route::middleware('role:student')->get('student/reupload-requests', [Student\ReuploadRequestController::class, 'index']);

// ---- System Admin: coordinator accounts -----------------------------------------------------------
Route::middleware('role:system_admin')->prefix('admin')->group(function () {
    Route::get('coordinators', [Admin\CoordinatorController::class, 'index']);
    Route::post('coordinators', [Admin\CoordinatorController::class, 'store']);
    Route::patch('coordinators/{coordinator}', [Admin\CoordinatorController::class, 'update'])->whereNumber('coordinator');
    Route::post('coordinators/{coordinator}/deactivate', [Admin\CoordinatorController::class, 'deactivate'])->whereNumber('coordinator');
    Route::post('coordinators/{coordinator}/reactivate', [Admin\CoordinatorController::class, 'reactivate'])->whereNumber('coordinator');
    // Academic hierarchy: Programme -> Department -> Division.
    Route::get('structure', [Admin\DepartmentBranchController::class, 'index']);

    Route::post('programs', [Admin\DepartmentBranchController::class, 'storeProgram']);
    Route::patch('programs/{program}', [Admin\DepartmentBranchController::class, 'updateProgram'])->whereNumber('program');
    Route::post('programs/{program}/deactivate', [Admin\DepartmentBranchController::class, 'deactivateProgram'])->whereNumber('program');
    Route::post('programs/{program}/reactivate', [Admin\DepartmentBranchController::class, 'reactivateProgram'])->whereNumber('program');
    Route::post('programs/{program}/departments', [Admin\DepartmentBranchController::class, 'storeDepartment'])->whereNumber('program');

    Route::patch('departments/{department}', [Admin\DepartmentBranchController::class, 'updateDepartment'])->whereNumber('department');
    Route::post('departments/{department}/deactivate', [Admin\DepartmentBranchController::class, 'deactivateDepartment'])->whereNumber('department');
    Route::post('departments/{department}/reactivate', [Admin\DepartmentBranchController::class, 'reactivateDepartment'])->whereNumber('department');
    Route::post('departments/{department}/divisions', [Admin\DepartmentBranchController::class, 'storeDivision'])->whereNumber('department');

    Route::patch('divisions/{division}', [Admin\DepartmentBranchController::class, 'updateDivision'])->whereNumber('division');
    Route::post('divisions/{division}/deactivate', [Admin\DepartmentBranchController::class, 'deactivateDivision'])->whereNumber('division');
    Route::post('divisions/{division}/reactivate', [Admin\DepartmentBranchController::class, 'reactivateDivision'])->whereNumber('division');
});
