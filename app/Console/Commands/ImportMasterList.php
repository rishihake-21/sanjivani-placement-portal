<?php

namespace App\Console\Commands;

use App\Services\MasterListImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportMasterList extends Command
{
    protected $signature = 'tpms:import-master-list {file : Path to the CSV} {--batch= : Label stored with the imported rows}';

    protected $description = 'Import or refresh the student master list from a CSV file (all-or-nothing).';

    public function handle(MasterListImporter $importer): int
    {
        $file = $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Cannot read {$file}");

            return self::FAILURE;
        }

        try {
            $summary = $importer->import($file, $this->option('batch'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }
            $this->warn('Nothing was imported.');

            return self::FAILURE;
        }

        $this->info("Created {$summary['created']}, updated {$summary['updated']}.");

        return self::SUCCESS;
    }
}
