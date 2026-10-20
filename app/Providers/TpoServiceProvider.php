<?php

namespace App\Providers;

use App\Console\Commands\CloseExpiredDrives;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/** Registers the artisan command and its hourly schedule. routes/tpo.php is required by routes/api.php. Register in bootstrap/providers.php. */
class TpoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CloseExpiredDrives::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('tpms:close-expired-drives')->hourly()->withoutOverlapping();
        });
    }
}
