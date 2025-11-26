<?php

namespace App\Console\Commands;

use App\Services\Importers\RaceImporter;

class ImportRaces extends BaseImportCommand
{
    protected $signature = 'import:races {file : Path to XML file}';

    protected $description = 'Import races from XML file';

    private RaceImporter $importer;

    protected function getEntityName(): string
    {
        return 'races';
    }

    public function handle(RaceImporter $importer): int
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
