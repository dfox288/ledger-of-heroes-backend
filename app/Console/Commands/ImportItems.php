<?php

namespace App\Console\Commands;

use App\Services\Importers\ItemImporter;
use App\Services\Importers\StrategyStatistics;

class ImportItems extends BaseImportCommand
{
    protected $signature = 'import:items {file : Path to the XML file}';

    protected $description = 'Import items from an XML file';

    private ItemImporter $importer;

    private StrategyStatistics $statistics;

    protected function getEntityName(): string
    {
        return 'items';
    }

    public function handle(ItemImporter $importer, StrategyStatistics $statistics): int
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
