<?php

namespace App\Console\Commands;

use App\Services\Importers\SpellImporter;

class ImportSpells extends BaseImportCommand
{
    protected $signature = 'import:spells {file : Path to XML file}';

    protected $description = 'Import spells from XML file';

    private SpellImporter $importer;

    protected function getEntityName(): string
    {
        return 'spells';
    }

    public function handle(SpellImporter $importer): int
    {
        $this->importer = $importer;

        return $this->executeImport();
    }

    protected function performImport(string $filePath): ImportResult
    {
        $xmlContent = file_get_contents($filePath);
        $entities = $this->importer->getParser()->parse($xmlContent);

        $count = 0;
        $progressBar = $this->createProgressBar(count($entities));

        foreach ($entities as $data) {
            $this->importer->import($data);
            $count++;
            $progressBar->advance();
        }

        $this->finishProgressBar($progressBar);

        return ImportResult::simple($count);
    }
}
