<?php

namespace App\Console\Commands;

use App\Services\CharacterImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Import fixture characters from storage/fixtures.
 *
 * Used for manual testing of spellcasting classes at various levels.
 */
class ImportFixtureCharactersCommand extends Command
{
    protected $signature = 'fixtures:import-characters
                            {--file= : Specific JSON file to import (relative to storage/fixtures)}
                            {--all : Import all JSON files from storage/fixtures/class-tests and multiclass-tests}
                            {--class-tests : Import only class-tests fixtures}
                            {--multiclass-tests : Import only multiclass-tests fixtures}
                            {--force : Delete existing test characters before importing}
                            {--dry-run : Show what would be imported without importing}';

    protected $description = 'Import fixture characters from storage/fixtures for manual testing';

    public function __construct(
        private CharacterImportService $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fixturesPath = storage_path('fixtures');
        $specificFile = $this->option('file');
        $importAll = $this->option('all');
        $importClassTests = $this->option('class-tests');
        $importMulticlassTests = $this->option('multiclass-tests');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        // Check for conflicting options
        $importOptions = array_filter([$specificFile, $importAll, $importClassTests, $importMulticlassTests]);
        if (count($importOptions) > 1 && $specificFile) {
            $this->error('Cannot use --file with other import options');

            return self::FAILURE;
        }

        if ($specificFile) {
            $filePath = $fixturesPath.'/'.$specificFile;
            if (! File::exists($filePath)) {
                $this->error("File not found: {$filePath}");

                return self::FAILURE;
            }
            $files = [$filePath];
        } elseif ($importAll || $importClassTests || $importMulticlassTests) {
            $files = [];

            // Import class-tests if --all or --class-tests
            if ($importAll || $importClassTests) {
                $classTestsPath = $fixturesPath.'/class-tests';
                if (File::isDirectory($classTestsPath)) {
                    $classTestFiles = File::glob($classTestsPath.'/*.json');
                    $files = array_merge($files, $classTestFiles);
                }
            }

            // Import multiclass-tests if --all or --multiclass-tests
            if ($importAll || $importMulticlassTests) {
                $multiclassTestsPath = $fixturesPath.'/multiclass-tests';
                if (File::isDirectory($multiclassTestsPath)) {
                    $multiclassFiles = File::glob($multiclassTestsPath.'/*.json');
                    $files = array_merge($files, $multiclassFiles);
                }
            }

            sort($files);

            if (empty($files)) {
                $this->error('No JSON files found in the selected directories');

                return self::FAILURE;
            }

            $this->info('Found '.count($files).' fixture files');
        } else {
            $this->error('Please specify --file, --all, --class-tests, or --multiclass-tests');
            $this->info('Use --all to import all fixtures');
            $this->info('Use --class-tests to import only class test fixtures');
            $this->info('Use --multiclass-tests to import only multiclass test fixtures');
            $this->info('Use --file=class-tests/filename.json to import a specific file');

            return self::FAILURE;
        }

        $this->info('🎭 Importing Fixture Characters');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
            $this->newLine();
        }

        if ($force && ! $dryRun) {
            $this->deleteExistingTestCharacters();
        }

        $imported = 0;
        $skipped = 0;
        $warnings = [];

        foreach ($files as $filePath) {
            $this->info('📁 Processing: '.basename($filePath));

            $content = File::get($filePath);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON: '.json_last_error_msg());

                continue;
            }

            // Handle both array of exports and single export
            $exports = isset($data['format_version']) ? [$data] : $data;

            foreach ($exports as $export) {
                $publicId = $export['character']['public_id'] ?? 'unknown';
                $name = $export['character']['name'] ?? 'Unknown';
                $classes = $export['character']['classes'] ?? [];

                $classInfo = collect($classes)->map(fn ($c) => sprintf(
                    '%s L%d',
                    basename($c['class']),
                    $c['level']
                ))->implode(', ');

                if ($dryRun) {
                    $this->line("  Would import: {$name} ({$publicId}) - {$classInfo}");
                    $imported++;

                    continue;
                }

                // Check if already exists
                if (\App\Models\Character::where('public_id', $publicId)->exists()) {
                    if (! $force) {
                        $this->warn("  ⏭️  Skipped: {$publicId} (already exists, use --force to replace)");
                        $skipped++;

                        continue;
                    }
                }

                try {
                    $result = $this->importService->import($export);

                    $this->info("  ✅ Imported: {$result->character->name} ({$result->character->public_id})");

                    if (! empty($result->warnings)) {
                        foreach ($result->warnings as $warning) {
                            $this->warn("     ⚠️  {$warning}");
                            $warnings[] = $warning;
                        }
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $this->error("  ❌ Failed: {$publicId} - {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("✨ Import complete: {$imported} imported, {$skipped} skipped");

        if (! empty($warnings)) {
            $this->newLine();
            $this->warn('Total warnings: '.count($warnings));
        }

        return self::SUCCESS;
    }

    private function deleteExistingTestCharacters(): void
    {
        $this->info('🗑️  Deleting existing test characters...');

        // Match names like "Alchemist L1", "Battle Smith L20", etc. (class-tests)
        // Also match multiclass names like "Wizard 5 / Cleric 5" (multiclass-tests)
        $classTestPattern = ' L[0-9]+$';
        $multiclassPattern = ' [0-9]+ / ';  // Contains " X / " pattern

        $characters = \App\Models\Character::where(function ($query) use ($classTestPattern, $multiclassPattern) {
            $query->where('name', 'regexp', $classTestPattern)
                ->orWhere('name', 'regexp', $multiclassPattern);
        })->get();

        $count = $characters->count();

        if ($count > 0) {
            $characters->each(function ($character) {
                // Delete related data first
                $character->characterClasses()->delete();
                $character->spells()->delete();
                $character->equipment()->delete();
                $character->languages()->delete();
                $character->proficiencies()->delete();
                $character->conditions()->delete();
                $character->featureSelections()->delete();
                $character->notes()->delete();
                $character->abilityScores()->delete();
                $character->spellSlots()->delete();
                $character->features()->delete();
                $character->delete();
            });

            $this->info("  Deleted {$count} existing test characters");
        } else {
            $this->info('  No existing test characters found');
        }

        $this->newLine();
    }
}
