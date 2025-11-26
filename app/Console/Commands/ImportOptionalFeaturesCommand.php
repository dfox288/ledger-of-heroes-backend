<?php

namespace App\Console\Commands;

use App\Services\Importers\OptionalFeatureImporter;
use Illuminate\Console\Command;

class ImportOptionalFeaturesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:optional-features {file : Path to the XML file containing optional features}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import D&D 5e optional features (Eldritch Invocations, Elemental Disciplines, Maneuvers, etc.) from XML files';

    /**
     * Execute the console command.
     */
    public function handle(OptionalFeatureImporter $importer): int
    {
        $filePath = $this->argument('file');

        // Validate file exists
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Importing optional features from: {$filePath}");

        try {
            $count = $importer->importFromFile($filePath);

            $this->newLine();
            $this->info("Successfully imported {$count} optional features!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
