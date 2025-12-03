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
                            {--only= : Only import specific entity types (comma-separated: sources,classes,spells,races,items,backgrounds,feats,monsters,optionalfeatures)}';

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

    private ?string $xmlSourcePath = null;

    private ?array $sourceDirectories = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        $this->info('🚀 Starting complete data import...');
        $this->newLine();

        // Initialize XML source configuration
        $this->initializeSourcePaths();

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

        // Step 2: Import sources FIRST (required by ALL other entities)
        if (! $onlyTypes || in_array('sources', $onlyTypes)) {
            $this->step('Importing sources (STEP 1/10)');
            $this->importEntityType('source', 'import:sources');
        }

        // Step 3: Import items (required by classes/backgrounds for equipment)
        if (! $onlyTypes || in_array('items', $onlyTypes)) {
            $this->step('Importing items (STEP 2/10)');
            $this->importEntityType('items', 'import:items');

            // Link pack contents after all items are imported
            $this->info('  Linking pack contents...');
            $importer = app(\App\Services\Importers\ItemImporter::class);
            $packsLinked = $importer->importAllPackContents();
            $this->info("  ✓ Linked contents for {$packsLinked} equipment pack(s)");
        }

        // Step 4: Import classes (required by spells) - using batch merge strategy
        if (! $onlyTypes || in_array('classes', $onlyTypes)) {
            $this->step('Importing classes (STEP 3/10) - with multi-file merge');
            $this->importClassesBatch();
        }

        // Step 5: Import main spell files
        if (! $onlyTypes || in_array('spells', $onlyTypes)) {
            $this->step('Importing spells (STEP 4/10)');
            $this->importEntityType('spells', 'import:spells', exclude: ['*+*']);
        }

        // Step 6: Import additive spell class mappings
        if (! $onlyTypes || in_array('spells', $onlyTypes)) {
            $this->step('Importing spell class mappings (STEP 5/10)');
            $this->importAdditiveSpellFiles();
        }

        // Step 7: Import races
        if (! $onlyTypes || in_array('races', $onlyTypes)) {
            $this->step('Importing races (STEP 6/10)');
            $this->importEntityType('races', 'import:races');
        }

        // Step 8: Import backgrounds
        if (! $onlyTypes || in_array('backgrounds', $onlyTypes)) {
            $this->step('Importing backgrounds (STEP 7/10)');
            $this->importEntityType('backgrounds', 'import:backgrounds');
        }

        // Step 9: Import feats
        if (! $onlyTypes || in_array('feats', $onlyTypes)) {
            $this->step('Importing feats (STEP 8/10)');
            $this->importEntityType('feats', 'import:feats');
        }

        // Step 10: Import monsters
        if (! $onlyTypes || in_array('monsters', $onlyTypes)) {
            $this->step('Importing monsters (STEP 9/10)');
            $this->importEntityType('bestiary', 'import:monsters');
        }

        // Step 11: Import optional features (invocations, maneuvers, metamagic, etc.)
        if (! $onlyTypes || in_array('optionalfeatures', $onlyTypes)) {
            $this->step('Importing optional features (STEP 10/10)');
            $this->importEntityType('optionalfeatures', 'import:optional-features');
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
                'OptionalFeature' => 'App\\Models\\OptionalFeature',
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
        $pattern = "{$prefix}-*.xml";

        // Get all matching files across all source directories
        $files = $this->getSourceFiles($pattern);

        // Apply exclusions (e.g., skip spells-phb+dmg.xml for main spell import)
        if (! empty($exclude)) {
            $files = array_filter($files, function ($file) use ($exclude) {
                $filename = basename($file);
                foreach ($exclude as $excludePattern) {
                    if (fnmatch($excludePattern, $filename)) {
                        return false;
                    }
                }

                return true;
            });
            $files = array_values($files); // Re-index array
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
        // Get all class files across all source directories
        $files = $this->getSourceFiles('class-*.xml');

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

            // Pass the actual file paths to the batch importer
            $exitCode = $this->call('import:classes:batch', [
                'files' => $classFiles,
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
        // Get all additive spell files across all source directories
        $files = $this->getSourceFiles('spells-*+*.xml');

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

    /**
     * Initialize XML source path configuration.
     */
    private function initializeSourcePaths(): void
    {
        $configPath = config('import.xml_source_path', 'import-files');

        // Resolve relative paths from project root
        if (! str_starts_with($configPath, '/')) {
            $this->xmlSourcePath = base_path($configPath);
        } else {
            $this->xmlSourcePath = $configPath;
        }

        $this->sourceDirectories = config('import.source_directories');

        // Display source configuration
        if ($this->sourceDirectories) {
            $this->info('XML Source: '.$this->xmlSourcePath);
            $this->info('Source directories: '.count($this->sourceDirectories).' configured');
            $this->line('  → '.implode(', ', array_keys($this->sourceDirectories)));
        } else {
            $this->info('XML Source: '.$this->xmlSourcePath.' (flat directory mode)');
        }
        $this->newLine();
    }

    /**
     * Get all XML files matching a pattern across all configured source directories.
     *
     * @param  string  $pattern  Glob pattern (e.g., "source-*.xml", "class-*.xml")
     * @return array<string> Array of absolute file paths
     */
    private function getSourceFiles(string $pattern): array
    {
        // If no source directories configured, use flat directory mode (legacy)
        if (empty($this->sourceDirectories)) {
            return File::glob("{$this->xmlSourcePath}/{$pattern}");
        }

        // Glob across all configured source directories in order
        $files = [];
        foreach ($this->sourceDirectories as $abbrev => $subdir) {
            $dirPath = "{$this->xmlSourcePath}/{$subdir}";
            if (is_dir($dirPath)) {
                $found = File::glob("{$dirPath}/{$pattern}");
                $files = array_merge($files, $found);
            }
        }

        return $files;
    }
}
