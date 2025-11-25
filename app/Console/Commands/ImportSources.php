<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Importers\SourceImporter;
use App\Services\Parsers\SourceXmlParser;
use Illuminate\Console\Command;

class ImportSources extends Command
{
    protected $signature = 'import:sources {file : Path to XML file}';

    protected $description = 'Import D&D sourcebook from XML file';

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
            // Parse XML (returns single-element array)
            $parser = new SourceXmlParser;
            $sources = $parser->parse($xmlContent);

            if (empty($sources)) {
                $this->warn('No source found in XML file');

                return self::FAILURE;
            }

            $this->info('Parsed '.count($sources).' source(s) from XML');

            // Import each source
            $importer = new SourceImporter;
            $importedCount = 0;
            $updatedCount = 0;

            foreach ($sources as $sourceData) {
                $existing = Source::where('code', $sourceData['code'])->first();

                $source = $importer->import($sourceData);

                if ($existing) {
                    $updatedCount++;
                    $this->line("  Updated: {$source->name} ({$source->code})");
                } else {
                    $importedCount++;
                    $this->line("  Created: {$source->name} ({$source->code})");
                }
            }

            $this->newLine();

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
