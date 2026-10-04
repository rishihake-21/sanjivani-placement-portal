<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'sanctum.stateful' => array_values(array_unique(array_filter(array_merge(
                config('sanctum.stateful', []),
                [
                    'localhost:8000',
                    'localhost:8001',
                    '127.0.0.1:8000',
                    Sanctum::$currentRequestHostPlaceholder,
                ],
            )))),
        ]);
    }
}
