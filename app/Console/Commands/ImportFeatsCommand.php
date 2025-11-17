<?php

namespace App\Console\Commands;

use App\Services\Importers\FeatImporter;
use App\Services\Parsers\FeatXmlParser;
use Illuminate\Console\Command;

class ImportFeatsCommand extends Command
{
    protected $signature = 'import:feats {file : Path to XML file}';
    protected $description = 'Import D&D 5e feats from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        $this->info("Importing feats from: {$filePath}");

        if (!file_exists($filePath)) {
            $this->error('Error: File not found');
            return self::FAILURE;
        }

        try {
            $importer = new FeatImporter(new FeatXmlParser());
            $count = $importer->importFromXmlFile($filePath);

            $this->info("Import complete!");
            $this->info("Imported {$count} feats.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
