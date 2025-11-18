<?php

namespace App\Console\Commands;

use App\Services\Importers\ItemImporter;
use Illuminate\Console\Command;

class ImportItems extends Command
{
    protected $signature = 'import:items {file : Path to the XML file}';

    protected $description = 'Import items from an XML file';

    public function handle(ItemImporter $importer): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Importing items from: {$filePath}");

        try {
            $count = $importer->importFromFile($filePath);
            $this->info("Successfully imported {$count} items");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
