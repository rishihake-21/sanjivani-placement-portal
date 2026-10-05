<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Web\AuthPageController;
use App\Http\Controllers\Web\CoordinatorPageController;
use App\Http\Controllers\Web\StudentPageController;
use App\Http\Controllers\Web\TpoPageController;
use App\Http\Controllers\Student\AcademicRecordController;
use App\Http\Controllers\Student\DocumentController;
use App\Http\Controllers\Student\ExperienceController;
use App\Http\Controllers\Student\NotificationController;
use App\Http\Controllers\Student\ProfileController;
use App\Services\ProfileCompletenessService;


/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    return match (Auth::user()->role->value) {
        'student' => redirect()->route('student.dashboard'),
        'tp_coordinator' => redirect()->route('coordinator.dashboard'),
        'tpo' => redirect()->route('tpo.dashboard'),
        'system_admin' => redirect()->route('admin.dashboard'),
        default => redirect()->route('login'),
    };
});


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthPageController::class, 'showLogin'])
    ->name('login')
    ->middleware('guest');


Route::post('/login', [AuthPageController::class, 'login'])
    ->name('login.post')
    ->middleware('guest');


Route::get('/register', [AuthPageController::class, 'showRegister'])
    ->name('register')
    ->middleware('guest');


Route::post('/register', [AuthPageController::class, 'register'])
    ->name('register.post')
    ->middleware('guest');


Route::post('/logout', [AuthPageController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');


Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request')->middleware('guest');


Route::post('/forgot-password', function () {
    // Password reset logic will be implemented later.
})->name('password.email')->middleware('guest');


/*
|--------------------------------------------------------------------------
| Document download (API path)
|--------------------------------------------------------------------------
|
| DocumentResource builds download_url from this name. The portal's own copy
| lives at student.documents.download inside the student group below.
|
*/

Route::get('/api/documents/{document}/download', DocumentDownloadController::class)
    ->name('documents.download');


/*
|--------------------------------------------------------------------------
| Student Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

Route::get('/dashboard', [StudentPageController::class, 'dashboard'])->name('dashboard');


        /*
        |--------------------------------------------------------------------------
        | Profile
        |--------------------------------------------------------------------------
        */

        Route::get('/profile', [StudentPageController::class, 'profile'])->name('profile');


        /*
        |--------------------------------------------------------------------------
        | Academic Records
        |--------------------------------------------------------------------------
        */

        Route::get('/academic', [StudentPageController::class, 'academic'])->name('academic');


        /*
        |--------------------------------------------------------------------------
        | Experience
        |--------------------------------------------------------------------------
        */

        Route::get('/experience', [StudentPageController::class, 'experience'])->name('experience');


        /*
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        */

        Route::get('/documents', [StudentPageController::class, 'documents'])->name('documents');


        /*
        |--------------------------------------------------------------------------
        | Document download
        |--------------------------------------------------------------------------
        |
        | The only way to a stored file. Named twice on purpose: the portal uses
        | student.documents.download, while DocumentResource builds documents.download.
        |
        */

        Route::get('/documents/{document}/download', DocumentDownloadController::class)
            ->name('documents.download');


        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        Route::get('/notifications', [StudentPageController::class, 'notifications'])->name('notifications');


        /*
        |--------------------------------------------------------------------------
        | Write routes
        |--------------------------------------------------------------------------
        |
        | The portal's AJAX forms post here with the web session and the CSRF token.
        | They are the module's own API controllers mounted again, so validation,
        | policies and the 422/409/423 responses are the API's own. GET /api/*
        | with Sanctum is untouched.
        |
        */

        Route::prefix('x')->name('x.')->group(function () {

            Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

            Route::post('academic-records', [AcademicRecordController::class, 'store'])->name('academic.store');
            Route::patch('academic-records/{id}', [AcademicRecordController::class, 'update'])->whereNumber('id')->name('academic.update');
            Route::delete('academic-records/{id}', [AcademicRecordController::class, 'destroy'])->whereNumber('id')->name('academic.destroy');
            Route::post('academic-records/{id}/document', [AcademicRecordController::class, 'attachDocument'])->whereNumber('id')->name('academic.document');
            Route::post('academic-records/{id}/submit', [AcademicRecordController::class, 'submit'])->whereNumber('id')->name('academic.submit');

            Route::post('experiences', [ExperienceController::class, 'store'])->name('experience.store');
            Route::patch('experiences/{id}', [ExperienceController::class, 'update'])->whereNumber('id')->name('experience.update');
            Route::delete('experiences/{id}', [ExperienceController::class, 'destroy'])->whereNumber('id')->name('experience.destroy');
            Route::post('experiences/{id}/certificate', [ExperienceController::class, 'attachCertificate'])->whereNumber('id')->name('experience.certificate');
            Route::post('experiences/{id}/submit', [ExperienceController::class, 'submit'])->whereNumber('id')->name('experience.submit');

            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');

            Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        });


        /*
        |--------------------------------------------------------------------------
        | Placement Drives
        |--------------------------------------------------------------------------
        */

        Route::get('/drives', function (Request $request) {

            $student = $request->user()->student;

            $drives = \App\Models\Drive::query()
                ->where('status', \App\Enums\DriveStatus::OPEN)
                ->orderByDesc('created_at')
                ->get();

            return view('student.drives', [
                'active' => 'student.drives',
                'user' => $request->user(),
                'student' => $student,
                'drives' => $drives,
            ]);

        })->name('drives');


        Route::get('/drives/{drive}', function (Request $request, $drive) {

            $student = $request->user()->student;

            $engine = app(\App\Services\EligibilityEngine::class);
            $eligibility = $engine->evaluate($drive, $student);

            return view('student.drive-show', [
                'active' => 'student.drives',
                'user' => $request->user(),
                'student' => $student,
                'drive' => $drive,
                'isEligible' => $eligibility->eligible ?? false,
                'hasApplied' => $student->applications()
                    ->where('drive_id', $drive->id)
                    ->exists(),
            ]);

        })->name('drives.show');


        /*
        |--------------------------------------------------------------------------
        | Applications
        |--------------------------------------------------------------------------
        */

        Route::get('/applications', function (Request $request) {

            $student = $request->user()->student;

            return view('student.applications', [
                'active' => 'student.applications',
                'user' => $request->user(),
                'student' => $student,
                'applications' => $student->applications()->with('drive')->latest()->get(),
            ]);

        })->name('applications');

    });


/*
|--------------------------------------------------------------------------
| T&P Coordinator Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:tp_coordinator'])
    ->prefix('coordinator')
    ->name('coordinator.')
    ->group(function () {

        Route::get('/dashboard', [CoordinatorPageController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [CoordinatorPageController::class, 'profile'])->name('profile');
        Route::get('/verification-queue', [CoordinatorPageController::class, 'queue'])->name('queue');

        Route::get('/students', [CoordinatorPageController::class, 'students'])->name('students');
        Route::get('/students/{id}', [CoordinatorPageController::class, 'student'])->whereNumber('id')->name('students.show');

        Route::get('/reupload-requests', [CoordinatorPageController::class, 'reuploads'])->name('reuploads');

        Route::get('/drives', [CoordinatorPageController::class, 'drives'])->name('drives');
        Route::get('/drives/{id}', [CoordinatorPageController::class, 'drive'])->whereNumber('id')->name('drives.show');

        Route::get('/applications', [CoordinatorPageController::class, 'applications'])->name('applications');
        Route::get('/applications/{id}', [CoordinatorPageController::class, 'application'])->whereNumber('id')->name('applications.show');

        Route::get('/placements', [CoordinatorPageController::class, 'placements'])->name('placements');
        Route::get('/reports', [CoordinatorPageController::class, 'reports'])->name('reports');
        Route::get('/reports/students.csv', [\App\Http\Controllers\Coordinator\ReportController::class, 'students'])->name('reports.students-csv');
        Route::get('/reports/placements.csv', [\App\Http\Controllers\Coordinator\ReportController::class, 'placements'])->name('reports.placements-csv');

        Route::get('/documents/{document}/download', DocumentDownloadController::class)->name('documents.download');

        Route::prefix('x')->name('x.')->group(function () {
            Route::post('/academic-records/{id}/approve', [\App\Http\Controllers\Coordinator\VerificationController::class, 'approveAcademicRecord'])->whereNumber('id')->name('academic.approve');
            Route::post('/academic-records/{id}/reject', [\App\Http\Controllers\Coordinator\VerificationController::class, 'rejectAcademicRecord'])->whereNumber('id')->name('academic.reject');
            Route::post('/academic-records/{id}/unlock', [\App\Http\Controllers\Coordinator\VerificationController::class, 'unlockAcademicRecord'])->whereNumber('id')->name('academic.unlock');

            Route::post('/experiences/{id}/approve', [\App\Http\Controllers\Coordinator\VerificationController::class, 'approveExperience'])->whereNumber('id')->name('experience.approve');
            Route::post('/experiences/{id}/reject', [\App\Http\Controllers\Coordinator\VerificationController::class, 'rejectExperience'])->whereNumber('id')->name('experience.reject');

            Route::post('/documents/{document}/approve', [\App\Http\Controllers\Coordinator\VerificationController::class, 'approveDocument'])->whereUuid('document')->name('document.approve');
            Route::post('/documents/{document}/reject', [\App\Http\Controllers\Coordinator\VerificationController::class, 'rejectDocument'])->whereUuid('document')->name('document.reject');

            Route::post('/students/{studentId}/reupload-requests', [\App\Http\Controllers\Coordinator\ReuploadRequestController::class, 'store'])->whereNumber('studentId')->name('reupload.store');
            Route::post('/reupload-requests/{id}/cancel', [\App\Http\Controllers\Coordinator\ReuploadRequestController::class, 'cancel'])->whereNumber('id')->name('reupload.cancel');
        });

    });


/*
|--------------------------------------------------------------------------
| TPO Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:tpo'])
    ->prefix('tpo')
    ->name('tpo.')
    ->group(function () {

        Route::get('/dashboard', [TpoPageController::class, 'dashboard'])->name('dashboard');

        Route::get('/companies', [TpoPageController::class, 'companies'])->name('companies');

        Route::get('/drives', [TpoPageController::class, 'drives'])->name('drives');
        Route::get('/drives/create', [TpoPageController::class, 'createDrive'])->name('drives.create');
        Route::get('/drives/{id}', [TpoPageController::class, 'showDrive'])->whereNumber('id')->name('drives.show');
        Route::get('/drives/{id}/edit', [TpoPageController::class, 'editDrive'])->whereNumber('id')->name('drives.edit');

        Route::get('/applications', [TpoPageController::class, 'applications'])->name('applications');
        Route::get('/applications/{id}', [TpoPageController::class, 'showApplication'])->whereNumber('id')->name('applications.show');

        Route::get('/placements', [TpoPageController::class, 'placements'])->name('placements');
        Route::get('/reports', [TpoPageController::class, 'reports'])->name('reports');

    });


/*
|--------------------------------------------------------------------------
| System Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:system_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');

    });
