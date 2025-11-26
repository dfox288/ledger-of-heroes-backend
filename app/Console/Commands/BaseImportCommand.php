<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Base class for entity import commands.
 *
 * Provides:
 * - File validation
 * - Error handling with verbosity-aware stack traces
 * - Progress bar display
 * - Smart result reporting (simple, table, or statistics)
 *
 * Subclasses implement performImport() with their specific import logic.
 */
abstract class BaseImportCommand extends Command
{
    /**
     * Get the human-readable entity name (plural).
     *
     * Example: 'spells', 'monsters', 'races'
     */
    abstract protected function getEntityName(): string;

    /**
     * Perform the import operation.
     *
     * Return an ImportResult with count and optional created/updated breakdown.
     *
     * @param  string  $filePath  Validated file path
     * @return ImportResult The import results
     */
    abstract protected function performImport(string $filePath): ImportResult;

    /**
     * Execute the import command.
     *
     * Call this from your handle() method after setting up dependencies.
     */
    protected function executeImport(): int
    {
        $filePath = $this->argument('file');

        if (! $this->validateFile($filePath)) {
            return self::FAILURE;
        }

        $this->info("Importing {$this->getEntityName()} from: {$filePath}");

        return $this->executeWithErrorHandling(fn () => $this->runImport($filePath));
    }

    /**
     * Validate that the file exists.
     */
    protected function validateFile(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return false;
        }

        return true;
    }

    /**
     * Execute import with consistent error handling.
     *
     * Shows stack trace only when -v flag is used.
     */
    protected function executeWithErrorHandling(callable $import): int
    {
        try {
            return $import();
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());

            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Run the import and report results.
     */
    protected function runImport(string $filePath): int
    {
        $result = $this->performImport($filePath);

        $this->reportResults($result);

        return self::SUCCESS;
    }

    /**
     * Report import results using smart detection.
     *
     * - If statistics provided: show statistics table
     * - If created/updated breakdown: show table
     * - Otherwise: show simple message
     */
    protected function reportResults(ImportResult $result): void
    {
        $this->newLine();

        if ($result->hasStatistics()) {
            $this->reportWithStatistics($result);
        } elseif ($result->hasBreakdown()) {
            $this->reportWithTable($result);
        } else {
            $this->reportSimple($result);
        }
    }

    /**
     * Report with simple success message.
     */
    protected function reportSimple(ImportResult $result): void
    {
        $this->info("✓ Successfully imported {$result->count} {$this->getEntityName()}.");
    }

    /**
     * Report with created/updated table.
     */
    protected function reportWithTable(ImportResult $result): void
    {
        $this->info('✓ Import complete!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Created', $result->created],
                ['Updated', $result->updated],
                ['Total', $result->count],
            ]
        );
    }

    /**
     * Report with strategy statistics.
     */
    protected function reportWithStatistics(ImportResult $result): void
    {
        $this->info("✓ Successfully imported {$result->count} {$this->getEntityName()}.");

        $stats = $result->statistics;

        if (empty($stats)) {
            return;
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

        $logPath = 'storage/logs/import-strategy-'.date('Y-m-d').'.log';
        $this->comment("Detailed logs: {$logPath}");
    }

    /**
     * Create a progress bar for entity iteration.
     */
    protected function createProgressBar(int $count): ProgressBar
    {
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        return $progressBar;
    }

    /**
     * Finish progress bar with proper spacing.
     */
    protected function finishProgressBar(ProgressBar $progressBar): void
    {
        $progressBar->finish();
        $this->newLine(2);
    }
}

/**
 * Value object for import results.
 *
 * Supports three reporting modes:
 * - Simple: just count
 * - Table: count with created/updated breakdown
 * - Statistics: count with strategy statistics
 */
class ImportResult
{
    public function __construct(
        public readonly int $count,
        public readonly ?int $created = null,
        public readonly ?int $updated = null,
        public readonly ?array $statistics = null,
    ) {}

    /**
     * Create simple result with just count.
     */
    public static function simple(int $count): self
    {
        return new self($count);
    }

    /**
     * Create result with created/updated breakdown.
     */
    public static function withBreakdown(int $created, int $updated): self
    {
        return new self(
            count: $created + $updated,
            created: $created,
            updated: $updated,
        );
    }

    /**
     * Create result with strategy statistics.
     */
    public static function withStatistics(int $count, array $statistics): self
    {
        return new self(
            count: $count,
            statistics: $statistics,
        );
    }

    /**
     * Check if result has created/updated breakdown.
     */
    public function hasBreakdown(): bool
    {
        return $this->created !== null && $this->updated !== null;
    }

    /**
     * Check if result has strategy statistics.
     */
    public function hasStatistics(): bool
    {
        return $this->statistics !== null;
    }
}
