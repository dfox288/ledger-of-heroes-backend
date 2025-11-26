<?php

namespace App\Console\Commands;

use App\Services\Importers\BackgroundImporter;

class ImportBackgrounds extends BaseImportCommand
{
    protected $signature = 'import:backgrounds {file : Path to XML file}';

    protected $description = 'Import D&D backgrounds from XML file';

    private BackgroundImporter $importer;

    protected function getEntityName(): string
    {
        return 'backgrounds';
    }

    public function handle(BackgroundImporter $importer): int
    {
        $this->importer = $importer;

        return $this->executeImport();
    }

    protected function performImport(string $filePath): ImportResult
    {
        $xmlContent = file_get_contents($filePath);
        $entities = $this->importer->getParser()->parse($xmlContent);

        $created = 0;
        $updated = 0;

        $progressBar = $this->createProgressBar(count($entities));

        foreach ($entities as $data) {
            $model = $this->importer->import($data);

            if ($model->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            $progressBar->advance();
        }

        $this->finishProgressBar($progressBar);

        return ImportResult::withBreakdown($created, $updated);
    }
}
