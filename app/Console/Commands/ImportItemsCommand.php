<?php

namespace App\Console\Commands;

use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use Illuminate\Console\Command;

class ImportItemsCommand extends Command
{
    protected $signature = 'import:items {file : Path to XML file}';
    protected $description = 'Import D&D 5e items from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        $this->info("Importing items from: {$filePath}");

        if (!file_exists($filePath)) {
            $this->error('Error: File not found');
            return self::FAILURE;
        }

        try {
            $importer = new ItemImporter(new ItemXmlParser());
            $count = $importer->importFromXmlFile($filePath);

            $this->info("Import complete!");
            $this->info("Imported {$count} items.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
