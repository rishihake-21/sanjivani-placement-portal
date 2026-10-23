<?php

use App\Http\Controllers\Admin\MasterListController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TPMS - API (prefix /api, loaded from bootstrap/app.php via withRouting(api:))
|--------------------------------------------------------------------------
|
| Every route in this file runs behind the "api" middleware group. Endpoints
| that need a signed-in user are grouped under auth:sanctum (Sanctum accepts
| either a Bearer token or the stateful session of the SPA).
|
| The module endpoint groups stay in their own files and are required below so
| that they all share this one prefix and middleware stack:
|   routes/tpo.php         -> /api/tpo/*, /api/student/*        (drives, applications, ...)
|   routes/coordinator.php -> /api/coordinator/*, /api/admin/*,
|                              /api/student/reupload-requests
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);

    // ---- Master list import (TPO or System Admin) ----------------------------------------------
    Route::middleware('role:tpo,system_admin')->post('master-list/import', [MasterListController::class, 'import']);

    // ---- Module endpoint groups (inherit this prefix + auth:sanctum) ---------------------------
    require __DIR__.'/tpo.php';
    require __DIR__.'/coordinator.php';
});
