<?php

namespace App\Console\Commands;

use App\Services\Importers\FeatImporter;
use Illuminate\Console\Command;

class ImportFeats extends Command
{
    protected $signature = 'import:feats {file}';

    protected $description = 'Import feats from XML file';

    public function handle(FeatImporter $importer): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $this->info("Importing feats from {$file}...");

        try {
            $count = $importer->importFromFile($file);
            $this->info("Successfully imported {$count} feats.");
            $this->info('View via API: http://localhost/api/v1/feats');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
