<?php

namespace App\Console\Commands;

use App\Services\Importers\ClassImporter;

class ImportClasses extends BaseImportCommand
{
    protected $signature = 'import:classes {file : Path to XML file}';

    protected $description = 'Import D&D classes from XML file';

    private ClassImporter $importer;

    protected function getEntityName(): string
    {
        return 'classes';
    }

    public function handle(ClassImporter $importer): int
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
