<?php

namespace App\Console\Commands;

use App\Models\Background;
use App\Services\Importers\BackgroundImporter;
use App\Services\Parsers\BackgroundXmlParser;
use Illuminate\Console\Command;

class ImportBackgrounds extends Command
{
    protected $signature = 'import:backgrounds {file : Path to XML file}';

    protected $description = 'Import D&D backgrounds from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        // Validate file exists
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        // Read XML content
        $this->info("Reading XML file: {$filePath}");
        $xmlContent = file_get_contents($filePath);

        try {
            // Parse XML
            $parser = new BackgroundXmlParser();
            $backgrounds = $parser->parse($xmlContent);
            $this->info('Parsed '.count($backgrounds).' backgrounds from XML');

            // Import each background
            $importer = new BackgroundImporter();
            $importedCount = 0;
            $updatedCount = 0;

            $progressBar = $this->output->createProgressBar(count($backgrounds));
            $progressBar->start();

            foreach ($backgrounds as $backgroundData) {
                $existing = Background::where('name', $backgroundData['name'])->first();

                $background = $importer->import($backgroundData);

                if ($existing) {
                    $updatedCount++;
                } else {
                    $importedCount++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Report results
            $this->info('✓ Import complete!');
            $this->table(
                ['Status', 'Count'],
                [
                    ['Created', $importedCount],
                    ['Updated', $updatedCount],
                    ['Total', $importedCount + $updatedCount],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
