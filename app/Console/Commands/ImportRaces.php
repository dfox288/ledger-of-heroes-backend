<?php

namespace App\Console\Commands;

use App\Services\Importers\RaceImporter;
use Illuminate\Console\Command;

class ImportRaces extends Command
{
    protected $signature = 'import:races {file}';

    protected $description = 'Import races from XML file';

    public function handle(RaceImporter $importer): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $this->info("Importing races from {$file}...");

        try {
            $count = $importer->importFromFile($file);
            $this->info("Successfully imported {$count} races.");
            $this->info('View via API: http://localhost/api/races');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
