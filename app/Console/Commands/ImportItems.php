<?php

namespace App\Console\Commands;

use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use Illuminate\Console\Command;

class ImportItems extends Command
{
    protected $signature = 'import:items {file : Path to the XML file}';
    protected $description = 'Import items from an XML file';

    public function handle(ItemXmlParser $parser, ItemImporter $importer): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Reading file: {$filePath}");
        $xmlContent = file_get_contents($filePath);

        $this->info('Parsing XML...');
        $items = $parser->parse($xmlContent);
        $this->info('Found ' . count($items) . ' items');

        $progressBar = $this->output->createProgressBar(count($items));
        $progressBar->start();

        $imported = 0;
        foreach ($items as $itemData) {
            try {
                $importer->import($itemData);
                $imported++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to import item: {$itemData['name']}");
                $this->error($e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully imported {$imported} items");

        return self::SUCCESS;
    }
}
