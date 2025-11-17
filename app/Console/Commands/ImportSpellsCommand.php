<?php

namespace App\Console\Commands;

use App\Services\Importers\SpellImporter;
use App\Services\Parsers\SpellXmlParser;
use Illuminate\Console\Command;

class ImportSpellsCommand extends Command
{
    protected $signature = 'import:spells {file : Path to XML file}';
    protected $description = 'Import D&D 5e spells from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        $this->info("Importing spells from: {$filePath}");

        if (!file_exists($filePath)) {
            $this->error('Error: File not found');
            return self::FAILURE;
        }

        try {
            $importer = new SpellImporter(new SpellXmlParser());
            $count = $importer->importFromXmlFile($filePath);

            $this->info("Import complete!");
            $this->info("Imported {$count} spells.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
