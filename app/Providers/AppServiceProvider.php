<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Campus NAT-safe rate limiters: key by account identifier + IP so thousands of students
        // sharing university Wi-Fi NAT IPs do not lock out the entire campus, while still blocking
        // credential stuffing against any individual account.
        RateLimiter::for('auth-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            $key = $email !== '' ? 'login:' . $email . '|' . $request->ip() : 'login-ip:' . $request->ip();

            return [
                Limit::perMinute(6)->by($key),
                Limit::perMinute(300)->by('campus-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request) {
            $uniId = strtoupper(trim((string) $request->input('university_id', '')));
            $key = $uniId !== '' ? 'reg:' . $uniId . '|' . $request->ip() : 'reg-ip:' . $request->ip();

            return [
                Limit::perMinute(6)->by($key),
                Limit::perMinute(200)->by('campus-reg-ip:' . $request->ip()),
            ];
        });
    }
}
