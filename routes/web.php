<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Web\AuthPageController;
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

        Route::get('/dashboard', function () {
            return view('coordinator.dashboard');
        })->name('dashboard');

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
