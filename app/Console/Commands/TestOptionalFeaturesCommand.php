<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\LevelUpFlowTesting\LevelUpFlowExecutor;
use App\Services\WizardFlowTesting\CharacterRandomizer;
use App\Services\WizardFlowTesting\FlowExecutor;
use App\Services\WizardFlowTesting\FlowGenerator;
use Illuminate\Console\Command;

/**
 * Test optional feature progression against D&D 5e rules.
 *
 * Creates characters, levels them up, and validates they have
 * the correct number of optional features at each milestone.
 *
 * @example php artisan test:optional-features
 * @example php artisan test:optional-features --class=phb:warlock --level=20
 * @example php artisan test:optional-features --cleanup
 */
class TestOptionalFeaturesCommand extends Command
{
    protected $signature = 'test:optional-features
        {--class= : Test specific class only (e.g., phb:warlock)}
        {--level=20 : Target level to test}
        {--seed= : Random seed for reproducibility}
        {--cleanup : Delete test characters after run}
        {--verbose-steps : Show detailed step output}';

    protected $description = 'Test optional feature progression against D&D 5e rules';

    /**
     * Test configurations: class/subclass combinations with their feature types.
     *
     * @var array<string, array{class: string, subclass: string|null, feature_type: string}>
     */
    private const TEST_CONFIGS = [
        'warlock' => [
            'class' => 'phb:warlock',
            'subclass' => null,
            'feature_type' => 'eldritch_invocation',
        ],
        'sorcerer' => [
            'class' => 'phb:sorcerer',
            'subclass' => null,
            'feature_type' => 'metamagic',
        ],
        'artificer' => [
            'class' => 'erlw:artificer',
            'subclass' => null,
            'feature_type' => 'artificer_infusion',
        ],
        'battle_master' => [
            'class' => 'phb:fighter',
            'subclass' => 'phb:fighter-battle-master',
            'feature_type' => 'maneuver',
        ],
        'arcane_archer' => [
            'class' => 'phb:fighter',
            'subclass' => 'xge:fighter-arcane-archer',
            'feature_type' => 'arcane_shot',
        ],
        'rune_knight' => [
            'class' => 'phb:fighter',
            'subclass' => 'tce:fighter-rune-knight',
            'feature_type' => 'rune',
        ],
        'four_elements' => [
            'class' => 'phb:monk',
            'subclass' => 'phb:monk-way-of-the-four-elements',
            'feature_type' => 'elemental_discipline',
        ],
    ];

    /** @var array<int> */
    private array $createdCharacterIds = [];

    /** @var array<array{config: string, level: int, expected: int, actual: int, passed: bool}> */
    private array $results = [];

    public function handle(): int
    {
        $seed = $this->option('seed') ? (int) $this->option('seed') : random_int(1, 999999);
        $targetLevel = (int) $this->option('level');
        $specificClass = $this->option('class');

        $this->info('Optional Feature Progression Testing');
        $this->info('=====================================');
        $this->info("Seed: {$seed}");
        $this->info("Target Level: {$targetLevel}");
        $this->newLine();

        // Filter configs if specific class requested
        $configs = self::TEST_CONFIGS;
        if ($specificClass) {
            $configs = array_filter($configs, fn ($c) => $c['class'] === $specificClass);
            if (empty($configs)) {
                $this->error("No test config found for class: {$specificClass}");

                return Command::FAILURE;
            }
        }

        // Run tests
        $configIndex = 0;
        foreach ($configs as $name => $config) {
            $this->testConfig($name, $config, $targetLevel, $seed + $configIndex);
            $configIndex++;
        }

        // Display results
        $this->displayResults();

        // Cleanup if requested
        if ($this->option('cleanup')) {
            $this->cleanup();
        }

        // Return status
        $failures = count(array_filter($this->results, fn ($r) => ! $r['passed']));

        return $failures > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Test a single class/subclass configuration.
     *
     * @param  array{class: string, subclass: string|null, feature_type: string}  $config
     */
    private function testConfig(string $name, array $config, int $targetLevel, int $seed): void
    {
        $this->info("Testing {$name}...");

        $randomizer = new CharacterRandomizer($seed);

        // Create character
        $character = $this->createCharacter($config['class'], $config['subclass'], $randomizer);

        if ($character === null) {
            $this->error("  Failed to create character for {$name}");

            return;
        }

        $this->createdCharacterIds[] = $character->id;

        // If a specific subclass is required and not yet set, force it
        // This handles classes like Fighter/Monk that choose subclass at level 3
        if ($config['subclass'] !== null) {
            $this->forceSubclass($character, $config['subclass']);
        }

        // Get progression milestones
        $progression = config("dnd-rules.optional_features.{$config['feature_type']}.progression", []);

        if (empty($progression)) {
            $this->warn("  No progression config found for {$config['feature_type']}");

            return;
        }

        // Level up and validate at each milestone
        $currentLevel = 1;
        $levelExecutor = new LevelUpFlowExecutor;

        foreach ($progression as $milestoneLevel => $expectedCount) {
            if ($milestoneLevel > $targetLevel) {
                break;
            }

            // Level up to milestone
            if ($milestoneLevel > $currentLevel) {
                $result = $levelExecutor->execute(
                    characterId: $character->id,
                    targetLevel: $milestoneLevel,
                    randomizer: $randomizer,
                    iteration: 1,
                    mode: 'linear'
                );

                if ($result->hasError()) {
                    $error = $result->toArray()['error'];
                    $this->error("  Level-up failed at level {$error['at_level']}: {$error['message']}");

                    return;
                }

                $currentLevel = $milestoneLevel;
            }

            // Validate feature count
            $character->refresh();
            $actualCount = $this->countOptionalFeatures($character, $config['feature_type']);
            $passed = $actualCount === $expectedCount;

            $this->results[] = [
                'config' => $name,
                'level' => $milestoneLevel,
                'expected' => $expectedCount,
                'actual' => $actualCount,
                'passed' => $passed,
            ];

            $status = $passed ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$status} Level {$milestoneLevel}: {$actualCount}/{$expectedCount} {$config['feature_type']}");
        }

        // Continue to target level if not reached
        if ($currentLevel < $targetLevel) {
            $result = $levelExecutor->execute(
                characterId: $character->id,
                targetLevel: $targetLevel,
                randomizer: $randomizer,
                iteration: 1,
                mode: 'linear'
            );

            if ($result->hasError()) {
                $error = $result->toArray()['error'];
                $this->error("  Level-up failed at level {$error['at_level']}: {$error['message']}");

                return;
            }

            // Final count at target level
            $character->refresh();
            $actualCount = $this->countOptionalFeatures($character, $config['feature_type']);
            $expectedCount = $this->getExpectedCountAtLevel($progression, $targetLevel);

            $passed = $actualCount === $expectedCount;
            $this->results[] = [
                'config' => $name,
                'level' => $targetLevel,
                'expected' => $expectedCount,
                'actual' => $actualCount,
                'passed' => $passed,
            ];

            $status = $passed ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$status} Level {$targetLevel}: {$actualCount}/{$expectedCount} {$config['feature_type']} (final)");
        }
    }

    /**
     * Force a specific subclass on a character.
     *
     * Directly sets the subclass_slug on the character's class pivot.
     * This bypasses the normal choice system for testing purposes.
     */
    private function forceSubclass(Character $character, string $subclassSlug): void
    {
        $characterClass = $character->characterClasses()->first();

        if ($characterClass === null) {
            return;
        }

        // Only set if not already set
        if ($characterClass->subclass_slug !== null) {
            return;
        }

        // Verify subclass exists and belongs to this class
        $subclass = CharacterClass::where('slug', $subclassSlug)->first();
        if ($subclass === null) {
            $this->warn("  Subclass not found: {$subclassSlug}");

            return;
        }

        // Update the pivot record
        $characterClass->subclass_slug = $subclassSlug;
        $characterClass->save();

        if ($this->option('verbose-steps')) {
            $this->line("  Forced subclass: {$subclassSlug}");
        }
    }

    /**
     * Create a character via wizard flow.
     */
    private function createCharacter(
        string $classSlug,
        ?string $subclassSlug,
        CharacterRandomizer $randomizer
    ): ?Character {
        $generator = new FlowGenerator;
        $executor = new FlowExecutor;

        $flow = $generator->linear();

        foreach ($flow as &$step) {
            if ($step['action'] === 'set_class') {
                $step['force_class'] = $classSlug;
            }
            if ($step['action'] === 'set_subclass' && $subclassSlug) {
                $step['force_subclass'] = $subclassSlug;
            }
        }

        $result = $executor->execute($flow, $randomizer);

        if (! $result->isPassed()) {
            if ($this->option('verbose-steps')) {
                $this->warn("Wizard flow failed: {$result->getSummary()}");
            }

            return null;
        }

        return Character::find($result->getCharacterId());
    }

    /**
     * Count optional features of a type for a character.
     */
    private function countOptionalFeatures(Character $character, string $featureType): int
    {
        return $character->featureSelections()
            ->with('optionalFeature')
            ->get()
            ->filter(fn ($fs) => $fs->optionalFeature !== null)
            ->filter(fn ($fs) => $fs->optionalFeature->feature_type?->value === $featureType)
            ->count();
    }

    /**
     * Get expected count at a level from progression config.
     *
     * @param  array<int, int>  $progression
     */
    private function getExpectedCountAtLevel(array $progression, int $level): int
    {
        $count = 0;
        foreach ($progression as $progressionLevel => $progressionCount) {
            if ($progressionLevel <= $level) {
                $count = $progressionCount;
            }
        }

        return $count;
    }

    /**
     * Display test results summary.
     */
    private function displayResults(): void
    {
        $this->newLine();
        $this->info('Results Summary');
        $this->info('===============');

        $passed = count(array_filter($this->results, fn ($r) => $r['passed']));
        $total = count($this->results);
        $failed = $total - $passed;

        $this->table(
            ['Config', 'Level', 'Expected', 'Actual', 'Status'],
            array_map(fn ($r) => [
                $r['config'],
                $r['level'],
                $r['expected'],
                $r['actual'],
                $r['passed'] ? 'PASS' : 'FAIL',
            ], $this->results)
        );

        $this->newLine();
        if ($failed === 0) {
            $this->info("All {$total} checks passed!");
        } else {
            $this->error("{$failed}/{$total} checks failed.");
        }

        $this->newLine();
        $this->info('Characters created: '.count($this->createdCharacterIds));
    }

    /**
     * Clean up test characters.
     */
    private function cleanup(): void
    {
        $this->info('Cleaning up test characters...');

        $deleted = 0;
        foreach ($this->createdCharacterIds as $id) {
            try {
                Character::where('id', $id)->delete();
                $deleted++;
            } catch (\Throwable $e) {
                $this->warn("Failed to delete character {$id}: {$e->getMessage()}");
            }
        }

        $this->info("Deleted {$deleted} test characters.");
    }
}
