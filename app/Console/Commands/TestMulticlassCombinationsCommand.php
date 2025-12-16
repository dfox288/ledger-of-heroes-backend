<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\CharacterExportService;
use App\Services\MulticlassCharacterBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Create specific multiclass test characters for testing multiclass features.
 *
 * @example php artisan test:multiclass-combinations --combinations="wizard:5,cleric:5"
 * @example php artisan test:multiclass-combinations --combinations="wizard:5,cleric:5|fighter:10,rogue:10"
 * @example php artisan test:multiclass-combinations --combinations="wizard:6,cleric:7,fighter:7" --cleanup
 * @example php artisan test:multiclass-combinations --combinations="wizard:5,cleric:5" --export --cleanup
 */
class TestMulticlassCombinationsCommand extends Command
{
    protected $signature = 'test:multiclass-combinations
        {--combinations= : Class:level pairs, e.g., "wizard:5,cleric:5". Use | to separate multiple combos.}
        {--count=1 : Number of characters to create per combination}
        {--seed= : Random seed for reproducibility}
        {--cleanup : Delete characters after creation}
        {--force : Bypass multiclass prerequisites (default: true)}
        {--no-force : Respect multiclass prerequisites}
        {--export : Export characters as fixtures to storage/fixtures/multiclass-tests/}';

    protected $description = 'Create specific multiclass test characters for testing';

    public function handle(MulticlassCharacterBuilder $builder, CharacterExportService $exportService): int
    {
        $combinationsSpec = $this->option('combinations');

        if (empty($combinationsSpec)) {
            $this->error('--combinations is required');
            $this->newLine();
            $this->info('Usage examples:');
            $this->line('  --combinations="wizard:5,cleric:5"        # Wizard 5 / Cleric 5');
            $this->line('  --combinations="wizard:5,cleric:5|fighter:10,rogue:10"  # Multiple combos');
            $this->line('  --combinations="erlw:artificer:5,cleric:5" # With source prefix');
            $this->line('  --combinations="wizard:5,cleric:5" --export --cleanup  # Export as fixture');

            return Command::FAILURE;
        }

        $combinations = $this->parseCombinations($combinationsSpec);
        $count = (int) $this->option('count');
        $seed = $this->option('seed') ? (int) $this->option('seed') : random_int(1, 999999);
        $cleanup = $this->option('cleanup');
        $force = ! $this->option('no-force');
        $export = $this->option('export');

        // Ensure fixtures directory exists if exporting
        $fixturesDir = storage_path('fixtures/multiclass-tests');
        if ($export && ! File::exists($fixturesDir)) {
            File::makeDirectory($fixturesDir, 0755, true);
        }

        $this->info('Multiclass Test Character Generation');
        $this->info('====================================');
        $this->info("Seed: {$seed}");
        $this->info("Count per combination: {$count}");
        $this->info('Force (bypass prerequisites): '.($force ? 'yes' : 'no'));
        $this->info('Cleanup after creation: '.($cleanup ? 'yes' : 'no'));
        if ($export) {
            $this->info("Export to: {$fixturesDir}");
        }
        $this->newLine();

        $totalCreated = 0;
        $totalFailed = 0;
        $totalExported = 0;
        $createdCharacters = [];

        foreach ($combinations as $index => $combo) {
            $comboName = $this->formatComboName($combo);
            $this->info('Combination '.($index + 1).": {$comboName}");

            for ($i = 0; $i < $count; $i++) {
                $iterSeed = $seed + ($index * 1000) + $i;

                try {
                    $character = $builder->build($combo, $iterSeed, $force);

                    // Rename character to match fixture naming convention
                    $fixtureName = $this->generateFixtureName($combo);
                    $character->update(['name' => $fixtureName]);
                    $character->refresh();

                    $totalCreated++;
                    $createdCharacters[] = $character;

                    $this->outputCharacter($character);

                    // Export if requested
                    if ($export) {
                        $filename = $this->generateFilename($combo);
                        $exportData = $exportService->export($character);
                        $filepath = "{$fixturesDir}/{$filename}";
                        File::put($filepath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $this->line("    Exported: {$filename}");
                        $totalExported++;
                    }

                    if ($cleanup) {
                        $character->delete();
                        $this->line('    (cleaned up)');
                    }
                } catch (\Throwable $e) {
                    $totalFailed++;
                    $this->error("  ✗ Failed: {$e->getMessage()}");
                }
            }

            $this->newLine();
        }

        // Summary
        $this->info('Summary');
        $this->info('=======');
        $this->line("Created: {$totalCreated}");
        if ($export) {
            $this->line("Exported: {$totalExported}");
        }
        if ($totalFailed > 0) {
            $this->error("Failed: {$totalFailed}");
        }

        if (! $cleanup && ! empty($createdCharacters)) {
            $this->newLine();
            $this->info('Created character public_ids:');
            foreach ($createdCharacters as $character) {
                $this->line("  {$character->public_id}");
            }
        }

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Parse combinations specification into array of class levels.
     *
     * @param  string  $spec  e.g., "wizard:5,cleric:5|fighter:10,rogue:10"
     * @return array<array<array{class: string, level: int}>>
     */
    private function parseCombinations(string $spec): array
    {
        $combinations = [];

        foreach (explode('|', $spec) as $combo) {
            $combinations[] = MulticlassCharacterBuilder::parseClassLevels($combo);
        }

        return $combinations;
    }

    /**
     * Format combination for display.
     */
    private function formatComboName(array $combo): string
    {
        return collect($combo)
            ->map(fn ($c) => $this->formatClassName($c['class']).' '.$c['level'])
            ->join(' / ');
    }

    /**
     * Format class slug for display.
     */
    private function formatClassName(string $slug): string
    {
        // Remove source prefix for cleaner display
        $name = str_contains($slug, ':')
            ? substr($slug, strpos($slug, ':') + 1)
            : $slug;

        return ucfirst($name);
    }

    /**
     * Output character details.
     */
    private function outputCharacter(Character $character): void
    {
        $classInfo = $character->characterClasses
            ->map(fn ($pivot) => $this->formatClassName($pivot->class_slug).' '.$pivot->level)
            ->join(' / ');

        $this->line("  ✓ {$character->public_id} - {$character->name}");
        $this->line("    Classes: {$classInfo}");
        $this->line("    Total Level: {$character->total_level}");

        // Show spell slots if multiclass caster
        if ($character->max_spell_slots && array_sum($character->max_spell_slots) > 0) {
            $slots = collect($character->max_spell_slots)
                ->filter(fn ($v) => $v > 0)
                ->map(fn ($v, $k) => "L{$k}:{$v}")
                ->join(' ');
            $this->line("    Spell Slots: {$slots}");
        }
    }

    /**
     * Generate fixture name for character (e.g., "Wizard 5 / Cleric 5").
     */
    private function generateFixtureName(array $combo): string
    {
        return collect($combo)
            ->map(fn ($c) => ucfirst($this->formatClassName($c['class'])).' '.$c['level'])
            ->join(' / ');
    }

    /**
     * Generate filename for fixture export (e.g., "wizard-5-cleric-5.json").
     */
    private function generateFilename(array $combo): string
    {
        $parts = collect($combo)
            ->map(fn ($c) => strtolower($this->formatClassName($c['class'])).'-'.$c['level'])
            ->join('-');

        return "{$parts}.json";
    }
}
