<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:recruiter', 'recruiter.active'])
    ->prefix('recruiter')
    ->name('recruiter.')
    ->group(function () {
        Route::view('/dashboard', 'recruiter.dashboard')->name('dashboard');
    });
