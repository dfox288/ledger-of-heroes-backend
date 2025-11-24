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
                            {--only= : Only import specific entity types (comma-separated: classes,spells,races,items,backgrounds,feats,monsters)}';

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

        // Detect environment for Scout operations
        // NOTE: Environment is set at bootstrap time (via CLI --env or APP_ENV).
        // All nested $this->call() commands automatically inherit this environment.
        // We cannot pass --env to $this->call() - it only works from CLI before boot.
        // This display is purely informational to show which indexes will be used.
        $environment = config('app.env');
        $scoutPrefix = config('scout.prefix');

        if ($environment !== 'production' || $scoutPrefix) {
            $this->info("Environment: {$environment}");
            $this->info('Scout Prefix: '.($scoutPrefix ?: '(none)'));
            $this->newLine();
        }

        // Parse --only filter if provided
        $onlyTypes = $this->option('only') ? explode(',', $this->option('only')) : null;

        // Step 1: Fresh database
        if (! $this->option('skip-migrate')) {
            $this->step('Refreshing database and seeding lookup tables');
            $this->call('migrate:fresh', ['--seed' => true]);
        }

        // Step 2: Import items FIRST (required by classes/backgrounds for equipment)
        if (! $onlyTypes || in_array('items', $onlyTypes)) {
            $this->step('Importing items (STEP 1/8)');
            $this->importEntityType('items', 'import:items');
        }

        // Step 3: Import classes (required by spells) - using batch merge strategy
        if (! $onlyTypes || in_array('classes', $onlyTypes)) {
            $this->step('Importing classes (STEP 2/8) - with multi-file merge');
            $this->importClassesBatch();
        }

        // Step 4: Import main spell files
        if (! $onlyTypes || in_array('spells', $onlyTypes)) {
            $this->step('Importing spells (STEP 3/8)');
            $this->importEntityType('spells', 'import:spells', exclude: ['*+*']);
        }

        // Step 5: Import additive spell class mappings
        if (! $onlyTypes || in_array('spells', $onlyTypes)) {
            $this->step('Importing spell class mappings (STEP 4/8)');
            $this->importAdditiveSpellFiles();
        }

        // Step 6: Import races
        if (! $onlyTypes || in_array('races', $onlyTypes)) {
            $this->step('Importing races (STEP 5/8)');
            $this->importEntityType('races', 'import:races');
        }

        // Step 7: Import backgrounds
        if (! $onlyTypes || in_array('backgrounds', $onlyTypes)) {
            $this->step('Importing backgrounds (STEP 6/8)');
            $this->importEntityType('backgrounds', 'import:backgrounds');
        }

        // Step 8: Import feats
        if (! $onlyTypes || in_array('feats', $onlyTypes)) {
            $this->step('Importing feats (STEP 7/8)');
            $this->importEntityType('feats', 'import:feats');
        }

        // Step 9: Import monsters
        if (! $onlyTypes || in_array('monsters', $onlyTypes)) {
            $this->step('Importing monsters (STEP 8/8)');
            $this->importEntityType('bestiary', 'import:monsters');
        }

        // Step 10: Configure and populate search indexes
        if (! $this->option('skip-search')) {
            $this->step('Configuring and indexing search data');

            // Define searchable models upfront (used for both deletion and import)
            $searchableModels = [
                'Spell' => 'App\\Models\\Spell',
                'Item' => 'App\\Models\\Item',
                'Monster' => 'App\\Models\\Monster',
                'Race' => 'App\\Models\\Race',
                'CharacterClass' => 'App\\Models\\CharacterClass',
                'Background' => 'App\\Models\\Background',
                'Feat' => 'App\\Models\\Feat',
            ];

            // Only delete indexes when doing a fresh migration (not in production updates)
            // IMPORTANT: Delete indexes individually to respect Scout prefix (test_ vs production)
            // scout:delete-all-indexes would delete ALL indexes including production!
            if (! $this->option('skip-migrate')) {
                $this->info('  Deleting existing search indexes (fresh migration mode)...');
                if ($scoutPrefix) {
                    $this->info("  (Prefix: {$scoutPrefix})");
                }

                foreach ($searchableModels as $name => $class) {
                    $indexName = (new $class)->searchableAs();
                    $this->line("    → Deleting '{$indexName}'...");
                    $this->call('scout:delete-index', ['name' => $indexName]);
                }

                $this->info('  ✓ All environment-specific indexes deleted');
                $this->newLine();
            }

            // Configure indexes (uses current environment's config automatically)
            // NOTE: Scout commands inherit the environment from this parent command.
            // The models' searchableAs() methods will automatically apply the correct
            // prefix (e.g., 'test_spells' for testing, 'spells' for production).
            $this->call('search:configure-indexes');

            // Re-index all searchable entities with fresh data
            $this->info('  Importing entities to Scout...');
            if ($scoutPrefix) {
                $this->info("  (Using prefix: {$scoutPrefix})");
            }
            $this->newLine();

            foreach ($searchableModels as $name => $class) {
                $indexName = (new $class)->searchableAs();
                $this->line("  → Indexing {$name} to '{$indexName}'...");
                $this->call('scout:import', ['model' => $class]);
            }

            $this->newLine();
            $this->info('✓ Search indexes populated with fresh data');
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
     * Import classes using batch merge strategy.
     *
     * Groups files by class name and merges PHB + supplements.
     */
    private function importClassesBatch(): void
    {
        $importPath = base_path('import-files');
        $files = File::glob("{$importPath}/class-*.xml");

        if (empty($files)) {
            $this->warn('  ⚠️  No class files found');

            return;
        }

        // Group files by class name (e.g., all barbarian files together)
        $classByName = [];
        foreach ($files as $file) {
            $filename = basename($file);
            // Extract class name from "class-barbarian-phb.xml" → "barbarian"
            if (preg_match('/^class-([a-z]+)-/', $filename, $matches)) {
                $className = $matches[1];
                $classByName[$className][] = $file;
            }
        }

        $this->info('  Found '.count($files).' file(s) for '.count($classByName).' unique class(es)');
        $this->newLine();

        $classesImported = 0;
        $subclassesImported = 0;

        foreach ($classByName as $className => $classFiles) {
            $this->line('  → '.ucfirst($className).' ('.count($classFiles).' file(s))');

            // Use batch importer with merge mode
            $exitCode = $this->call('import:classes:batch', [
                'pattern' => "import-files/class-{$className}-*.xml",
                '--merge' => true,
            ]);

            $this->totalFiles += count($classFiles);

            if ($exitCode === 0) {
                $this->successCount += count($classFiles);
                $classesImported++;

                // Count subclasses for this class
                $subclassCount = \App\Models\CharacterClass::where('slug', 'like', "{$className}%")
                    ->count();
                if ($subclassCount > 1) {
                    $subclassesImported += ($subclassCount - 1); // -1 for base class
                    $this->line('     (1 base class + '.($subclassCount - 1).' subclass(es))');
                }
            } else {
                $this->failCount += count($classFiles);
                $this->error('    ✗ Import failed!');
            }
        }

        $this->stats['classes'] = [
            'success' => $classesImported,
            'fail' => count($classByName) - $classesImported,
            'total' => count($classByName),
            'subclasses' => $subclassesImported,
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
            ['Entity Type', 'Success', 'Failed', 'Total', 'Extras'],
            collect($this->stats)->map(function ($stats, $type) {
                $extras = '';
                if (isset($stats['subclasses'])) {
                    $extras = "{$stats['subclasses']} subclasses";
                }

                return [
                    ucfirst(str_replace('-', ' ', $type)),
                    $stats['success'],
                    $stats['fail'],
                    $stats['total'],
                    $extras,
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

        // Clear entity caches after import
        $this->newLine();
        $this->info('Clearing entity caches...');
        app(\App\Services\Cache\EntityCacheService::class)->clearAll();
        $this->info('✓ Entity caches cleared');
    }
}
