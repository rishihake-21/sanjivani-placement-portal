<?php

namespace App\Providers;

use App\Console\Commands\CloseExpiredDrives;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Loads routes/tpo.php, registers the artisan command and its hourly schedule. Register in bootstrap/providers.php. */
class TpoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->prefix('api')->group(base_path('routes/tpo.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([CloseExpiredDrives::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('tpms:close-expired-drives')->hourly()->withoutOverlapping();
        });
    }
}
