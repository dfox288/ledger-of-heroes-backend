<?php

namespace App\Console\Commands;

use App\Services\Importers\SpellClassMappingImporter;

class ImportSpellClassMappingsCommand extends BaseImportCommand
{
    protected $signature = 'import:spell-class-mappings {file : Path to the additive XML file}';

    protected $description = 'Import additional class/subclass associations for existing spells from additive XML files (e.g., spells-phb+dmg.xml)';

    private SpellClassMappingImporter $importer;

    private array $stats = [];

    protected function getEntityName(): string
    {
        return 'spell-class mappings';
    }

    public function handle(SpellClassMappingImporter $importer): int
    {
        $this->importer = $importer;

        return $this->executeImport();
    }

    protected function performImport(string $filePath): ImportResult
    {
        $this->stats = $this->importer->import($filePath);

        return ImportResult::simple($this->stats['classes_added']);
    }

    protected function reportResults(ImportResult $result): void
    {
        $this->newLine();
        $this->info('✓ Import completed successfully!');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Entries processed', $this->stats['processed']],
                ['Spells found', $this->stats['spells_found']],
                ['Class associations added', $this->stats['classes_added']],
                ['Spells not found', count($this->stats['spells_not_found'])],
            ]
        );

        // Show spells that weren't found (might need to be imported first)
        if (! empty($this->stats['spells_not_found'])) {
            $this->newLine();
            $this->warn('The following spells were not found in the database:');
            foreach ($this->stats['spells_not_found'] as $spellName) {
                $this->line("  - {$spellName}");
            }
            $this->info('These spells may need to be imported from a main XML file first.');
        }
    }
}
