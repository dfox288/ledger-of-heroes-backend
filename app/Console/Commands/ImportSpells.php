<?php

namespace App\Console\Commands;

use App\Services\Importers\SpellImporter;
use Illuminate\Console\Command;

class ImportSpells extends Command
{
    protected $signature = 'import:spells {file}';
    protected $description = 'Import spells from XML file';

    public function handle(SpellImporter $importer): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Importing spells from {$file}...");

        try {
            $count = $importer->importFromFile($file);
            $this->info("Successfully imported {$count} spells.");
            $this->info("View via API: http://localhost/api/spells");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
