<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportAllDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:all
                            {--skip-migrate : Skip database migration and seeding}
                            {--skip-search : Skip search index configuration}
                            {--only= : Only import specific entity types (comma-separated: classes,spells,races,items,backgrounds,feats)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fresh database + seed + import ALL XML files in correct order';

    private int $totalFiles = 0;

    private int $successCount = 0;

    private int $failCount = 0;

    private array $stats = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        $this->info('🚀 Starting complete data import...');
        $this->newLine();

        // Parse --only filter if provided
        $onlyTypes = $this->option('only') ? explode(',', $this->option('only')) : null;

        // Step 1: Fresh database
        if (! $this->option('skip-migrate')) {
            $this->step('Refreshing database and seeding lookup tables');
            $this->call('migrate:fresh', ['--seed' => true]);
        }

        // Step 2: Import classes FIRST (required by spells)
        if (! $onlyTypes || in_array('classes', $onlyTypes)) {
            $this->step('Importing classes (STEP 1/6)');
            $this->importEntityType('class', 'import:classes');
        }

        // Step 3: Import main spell files
        if (! $onlyTypes || in_array('spells', $onlyTypes)) {
            $this->step('Importing spells (STEP 2/6)');
            $this->importEntityType('spells', 'import:spells', exclude: ['*+*']);
        }

        // Step 4: Import additive spell class mappings
        if (! $onlyTypes || in_array('spells', $onlyTypes)) {
            $this->step('Importing spell class mappings (STEP 3/6)');
            $this->importAdditiveSpellFiles();
        }

        // Step 5: Import races
        if (! $onlyTypes || in_array('races', $onlyTypes)) {
            $this->step('Importing races (STEP 4/6)');
            $this->importEntityType('races', 'import:races');
        }

        // Step 6: Import items
        if (! $onlyTypes || in_array('items', $onlyTypes)) {
            $this->step('Importing items (STEP 5/6)');
            $this->importEntityType('items', 'import:items');
        }

        // Step 7: Import backgrounds
        if (! $onlyTypes || in_array('backgrounds', $onlyTypes)) {
            $this->step('Importing backgrounds (STEP 6/6)');
            $this->importEntityType('backgrounds', 'import:backgrounds');
        }

        // Step 8: Import feats
        if (! $onlyTypes || in_array('feats', $onlyTypes)) {
            $this->step('Importing feats (STEP 7/6) - BONUS');
            $this->importEntityType('feats', 'import:feats');
        }

        // Step 9: Configure search indexes
        if (! $this->option('skip-search')) {
            $this->step('Configuring search indexes');
            $this->call('search:configure-indexes');
        }

        // Final summary
        $duration = round(microtime(true) - $startTime, 2);
        $this->showSummary($duration);

        return $this->failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Import files for a specific entity type.
     */
    private function importEntityType(string $prefix, string $command, array $exclude = []): void
    {
        $importPath = base_path('import-files');
        $pattern = "{$prefix}-*.xml";

        // Get all matching files
        $files = File::glob("{$importPath}/{$pattern}");

        // Apply exclusions (e.g., skip spells-phb+dmg.xml for main spell import)
        if (! empty($exclude)) {
            $files = array_filter($files, function ($file) use ($exclude) {
                $filename = basename($file);
                foreach ($exclude as $pattern) {
                    if (fnmatch($pattern, $filename)) {
                        return false;
                    }
                }

                return true;
            });
        }

        if (empty($files)) {
            $this->warn("  ⚠️  No {$prefix} files found");

            return;
        }

        $this->info('  Found '.count($files).' file(s)');
        $this->newLine();

        $entitySuccess = 0;
        $entityFail = 0;

        foreach ($files as $file) {
            $this->totalFiles++;
            $filename = basename($file);
            $this->line("  → {$filename}");

            // Call the import command
            $exitCode = $this->call($command, ['file' => $file]);

            if ($exitCode === 0) {
                $this->successCount++;
                $entitySuccess++;
            } else {
                $this->failCount++;
                $entityFail++;
                $this->error('    ✗ Import failed!');
            }
        }

        // Store stats for this entity type
        $this->stats[$prefix] = [
            'success' => $entitySuccess,
            'fail' => $entityFail,
            'total' => count($files),
        ];

        $this->newLine();
    }

    /**
     * Import additive spell class mapping files (spells-*+*.xml).
     */
    private function importAdditiveSpellFiles(): void
    {
        $importPath = base_path('import-files');
        $files = File::glob("{$importPath}/spells-*+*.xml");

        if (empty($files)) {
            $this->warn('  ⚠️  No additive spell files found');

            return;
        }

        $this->info('  Found '.count($files).' additive file(s)');
        $this->newLine();

        $entitySuccess = 0;
        $entityFail = 0;

        foreach ($files as $file) {
            $this->totalFiles++;
            $filename = basename($file);
            $this->line("  → {$filename}");

            $exitCode = $this->call('import:spell-class-mappings', ['file' => $file]);

            if ($exitCode === 0) {
                $this->successCount++;
                $entitySuccess++;
            } else {
                $this->failCount++;
                $entityFail++;
                $this->error('    ✗ Import failed!');
            }
        }

        $this->stats['spell-mappings'] = [
            'success' => $entitySuccess,
            'fail' => $entityFail,
            'total' => count($files),
        ];

        $this->newLine();
    }

    /**
     * Display a step header.
     */
    private function step(string $message): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("  {$message}");
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();
    }

    /**
     * Show final import summary.
     */
    private function showSummary(float $duration): void
    {
        $this->newLine(2);
        $this->info('╔═══════════════════════════════════════════════════════════╗');
        $this->info('║              IMPORT SUMMARY                               ║');
        $this->info('╚═══════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Per-entity breakdown
        $this->table(
            ['Entity Type', 'Success', 'Failed', 'Total'],
            collect($this->stats)->map(function ($stats, $type) {
                return [
                    ucfirst(str_replace('-', ' ', $type)),
                    $stats['success'],
                    $stats['fail'],
                    $stats['total'],
                ];
            })->toArray()
        );

        $this->newLine();

        // Overall stats
        $this->info("  Total files processed: {$this->totalFiles}");
        $this->info("  ✓ Successful: {$this->successCount}");
        if ($this->failCount > 0) {
            $this->error("  ✗ Failed: {$this->failCount}");
        }
        $this->info("  ⏱  Duration: {$duration}s");

        $this->newLine();

        if ($this->failCount === 0) {
            $this->info('✅ All imports completed successfully!');
        } else {
            $this->warn('⚠️  Some imports failed. Check output above for details.');
        }
    }
}
