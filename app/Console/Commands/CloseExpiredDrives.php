<?php

namespace App\Console\Commands;

use App\Services\DriveService;
use Illuminate\Console\Command;

class CloseExpiredDrives extends Command
{
    protected $signature = 'tpms:close-expired-drives';

    protected $description = 'Close published drives whose application deadline has passed.';

    public function handle(DriveService $drives): int
    {
        $this->info('Closed ' . $drives->closeExpired() . ' drive(s).');

        return self::SUCCESS;
    }
}
