<?php

namespace App\Console\Commands;

use App\Services\Importers\OptionalFeatureImporter;

class ImportOptionalFeaturesCommand extends BaseImportCommand
{
    protected $signature = 'import:optional-features {file : Path to XML file}';

    protected $description = 'Import D&D 5e optional features (Eldritch Invocations, Elemental Disciplines, Maneuvers, etc.) from XML files';

    private OptionalFeatureImporter $importer;

    protected function getEntityName(): string
    {
        return 'optional features';
    }

    public function handle(OptionalFeatureImporter $importer): int
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
