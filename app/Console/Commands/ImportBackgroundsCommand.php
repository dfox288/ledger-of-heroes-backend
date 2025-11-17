<?php

namespace App\Console\Commands;

use App\Services\Importers\BackgroundImporter;
use App\Services\Parsers\BackgroundXmlParser;
use Illuminate\Console\Command;

class ImportBackgroundsCommand extends Command
{
    protected $signature = 'import:backgrounds {file : Path to XML file}';
    protected $description = 'Import D&D 5e backgrounds from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        $this->info("Importing backgrounds from: {$filePath}");

        if (!file_exists($filePath)) {
            $this->error('Error: File not found');
            return self::FAILURE;
        }

        try {
            $importer = new BackgroundImporter(new BackgroundXmlParser());
            $count = $importer->importFromXmlFile($filePath);

            $this->info("Import complete!");
            $this->info("Imported {$count} backgrounds.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
