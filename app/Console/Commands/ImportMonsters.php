<?php

namespace App\Console\Commands;

use App\Services\Importers\MonsterImporter;
use Illuminate\Console\Command;

class ImportMonsters extends Command
{
    protected $signature = 'import:monsters {file : Path to the XML file}';

    protected $description = 'Import monsters from an XML file';

    public function handle(MonsterImporter $importer): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Importing monsters from: {$filePath}");

        try {
            $result = $importer->importWithStats($filePath);
            $this->info("✓ Successfully imported {$result['total']} monsters");

            // Display strategy statistics
            $this->displayStrategyStatistics($result['strategy_stats']);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display strategy statistics table.
     */
    private function displayStrategyStatistics(array $strategyStats): void
    {
        if (empty($strategyStats)) {
            return; // No strategies applied
        }

        $this->newLine();
        $this->info('Strategy Statistics:');

        $rows = [];
        foreach ($strategyStats as $strategy => $data) {
            $rows[] = [
                $strategy,
                $data['count'],
                $data['warnings'],
            ];
        }

        $this->table(
            ['Strategy', 'Monsters', 'Warnings'],
            $rows
        );

        // Show log file location
        $logPath = 'storage/logs/import-strategy-'.date('Y-m-d').'.log';
        $this->comment("⚠ Detailed logs: {$logPath}");
    }
}
