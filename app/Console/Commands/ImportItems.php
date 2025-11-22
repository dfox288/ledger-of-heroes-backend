<?php

namespace App\Console\Commands;

use App\Services\Importers\ItemImporter;
use App\Services\Importers\StrategyStatistics;
use Illuminate\Console\Command;

class ImportItems extends Command
{
    protected $signature = 'import:items {file : Path to the XML file}';

    protected $description = 'Import items from an XML file';

    public function handle(ItemImporter $importer, StrategyStatistics $statistics): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Importing items from: {$filePath}");

        // Clear strategy log before import
        $statistics->clearLog();

        try {
            $count = $importer->importFromFile($filePath);
            $this->info("✓ Successfully imported {$count} items");

            // Display strategy statistics
            $this->displayStrategyStatistics($statistics);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display strategy statistics table.
     */
    private function displayStrategyStatistics(StrategyStatistics $statistics): void
    {
        $stats = $statistics->getStatistics();

        if (empty($stats)) {
            return; // No strategies applied
        }

        $this->newLine();
        $this->info('Strategy Statistics:');

        $rows = [];
        foreach ($stats as $strategy => $data) {
            $rows[] = [
                $strategy,
                $data['items_enhanced'],
                $data['warnings'],
            ];
        }

        $this->table(
            ['Strategy', 'Items Enhanced', 'Warnings'],
            $rows
        );

        // Show log file location
        $logPath = 'storage/logs/import-strategy-'.date('Y-m-d').'.log';
        $this->comment("⚠ Detailed logs: {$logPath}");
    }
}
