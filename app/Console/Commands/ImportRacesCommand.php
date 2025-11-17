<?php

namespace App\Console\Commands;

use App\Services\Importers\RaceImporter;
use App\Services\Parsers\RaceXmlParser;
use Illuminate\Console\Command;

class ImportRacesCommand extends Command
{
    protected $signature = 'import:races {file : Path to XML file}';
    protected $description = 'Import D&D 5e races from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        $this->info("Importing races from: {$filePath}");

        if (!file_exists($filePath)) {
            $this->error('Error: File not found');
            return self::FAILURE;
        }

        try {
            $importer = new RaceImporter(new RaceXmlParser());
            $count = $importer->importFromXmlFile($filePath);

            $this->info("Import complete!");
            $this->info("Imported {$count} races.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
