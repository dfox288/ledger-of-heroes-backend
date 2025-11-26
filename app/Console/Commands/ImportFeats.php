<?php

namespace App\Console\Commands;

use App\Services\Importers\FeatImporter;

class ImportFeats extends BaseImportCommand
{
    protected $signature = 'import:feats {file : Path to XML file}';

    protected $description = 'Import feats from XML file';

    private FeatImporter $importer;

    protected function getEntityName(): string
    {
        return 'feats';
    }

    public function handle(FeatImporter $importer): int
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
