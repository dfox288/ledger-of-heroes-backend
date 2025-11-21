<?php

namespace App\Console\Commands;

use App\Services\Importers\SpellClassMappingImporter;
use Illuminate\Console\Command;

class ImportSpellClassMappingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:spell-class-mappings {file : Path to the additive XML file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import additional class/subclass associations for existing spells from additive XML files (e.g., spells-phb+dmg.xml)';

    /**
     * Execute the console command.
     */
    public function handle(SpellClassMappingImporter $importer): int
    {
        $filePath = $this->argument('file');

        // Validate file exists
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Importing spell class mappings from: {$filePath}");

        try {
            $stats = $importer->import($filePath);

            // Display results
            $this->newLine();
            $this->info('Import completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Entries processed', $stats['processed']],
                    ['Spells found', $stats['spells_found']],
                    ['Class associations added', $stats['classes_added']],
                    ['Spells not found', count($stats['spells_not_found'])],
                ]
            );

            // Show spells that weren't found (might need to be imported first)
            if (! empty($stats['spells_not_found'])) {
                $this->newLine();
                $this->warn('The following spells were not found in the database:');
                foreach ($stats['spells_not_found'] as $spellName) {
                    $this->line("  - {$spellName}");
                }
                $this->info('These spells may need to be imported from a main XML file first.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
