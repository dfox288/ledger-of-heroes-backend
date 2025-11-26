<?php

namespace App\Console\Commands;

use App\Services\Importers\MonsterImporter;
use App\Services\Importers\StrategyStatistics;

class ImportMonsters extends BaseImportCommand
{
    protected $signature = 'import:monsters {file : Path to the XML file}';

    protected $description = 'Import monsters from an XML file';

    private MonsterImporter $importer;

    private StrategyStatistics $statistics;

    protected function getEntityName(): string
    {
        return 'monsters';
    }

    public function handle(MonsterImporter $importer, StrategyStatistics $statistics): int
    {
        $this->importer = $importer;
        $this->statistics = $statistics;

        return $this->executeImport();
    }

    protected function performImport(string $filePath): ImportResult
    {
        $this->statistics->clearLog();

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

        $stats = $this->statistics->getStatistics();

        return ImportResult::withStatistics($count, $stats);
    }
}
