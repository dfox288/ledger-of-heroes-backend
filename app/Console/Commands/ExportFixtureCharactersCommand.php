<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\CharacterExportService;
use App\Services\LevelUpFlowTesting\LevelUpFlowExecutor;
use App\Services\WizardFlowTesting\CharacterRandomizer;
use App\Services\WizardFlowTesting\FlowExecutor;
use App\Services\WizardFlowTesting\FlowGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Export complete fixture characters at milestone levels.
 *
 * Creates characters through the wizard flow, levels them up,
 * and exports them to storage/fixtures for testing purposes.
 */
class ExportFixtureCharactersCommand extends Command
{
    protected $signature = 'fixtures:export-characters
                            {--class= : Specific class slug (e.g., phb:sorcerer)}
                            {--level= : Specific level (default: all milestone levels)}
                            {--seed=42 : Random seed for reproducibility}
                            {--dry-run : Show what would be created without exporting}';

    protected $description = 'Export complete fixture characters at milestone levels';

    /**
     * Class configurations for export.
     * Key levels where significant spell/feature changes occur.
     */
    private const CLASS_CONFIGS = [
        'phb:sorcerer' => [
            'name' => 'sorcerer',
            'levels' => [1, 5, 10, 20],
            'subclass' => null,
        ],
        'phb:bard' => [
            'name' => 'bard',
            'levels' => [1, 5, 10, 20],
            'subclass' => null,
        ],
        'phb:warlock' => [
            'name' => 'warlock',
            'levels' => [1, 5, 11, 20],
            'subclass' => null,
        ],
        'phb:wizard' => [
            'name' => 'wizard',
            'levels' => [1, 5, 10, 20],
            'subclass' => null,
        ],
        'phb:cleric' => [
            'name' => 'cleric',
            'levels' => [1, 5, 10, 20],
            'subclass' => null,
        ],
        'phb:druid' => [
            'name' => 'druid',
            'levels' => [1, 5, 10, 20],
            'subclass' => null,
        ],
        'phb:ranger' => [
            'name' => 'ranger',
            'levels' => [2, 5, 10, 20],
            'subclass' => null,
        ],
        'phb:paladin' => [
            'name' => 'paladin',
            'levels' => [2, 5, 10, 20],
            'subclass' => null,
        ],
        'erlw:artificer' => [
            'name' => 'artificer',
            'levels' => [1, 5, 10, 20],
            'subclass' => null,
        ],
    ];

    public function __construct(
        private CharacterExportService $exportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $classFilter = $this->option('class');
        $levelFilter = $this->option('level') ? (int) $this->option('level') : null;
        $seed = (int) $this->option('seed');
        $dryRun = $this->option('dry-run');

        // Verify database has imported data
        if (CharacterClass::count() < 10) {
            $this->error('Database not seeded with imported classes. Run import:all first.');

            return Command::FAILURE;
        }

        // Ensure fixtures directory exists
        $fixturesDir = storage_path('fixtures/characters');
        if (! $dryRun && ! File::exists($fixturesDir)) {
            File::makeDirectory($fixturesDir, 0755, true);
        }

        $configs = $classFilter
            ? array_filter(self::CLASS_CONFIGS, fn ($_, $slug) => $slug === $classFilter, ARRAY_FILTER_USE_BOTH)
            : self::CLASS_CONFIGS;

        if (empty($configs)) {
            $this->error("Unknown class: {$classFilter}");
            $this->info('Available classes: '.implode(', ', array_keys(self::CLASS_CONFIGS)));

            return Command::FAILURE;
        }

        $this->info("Fixture Export (seed: {$seed})");
        $this->info('================================');

        $created = 0;
        $failed = 0;

        foreach ($configs as $classSlug => $config) {
            $levels = $levelFilter !== null ? [$levelFilter] : $config['levels'];

            foreach ($levels as $level) {
                $filename = "{$config['name']}-l{$level}.json";

                if ($dryRun) {
                    $this->line("  [DRY-RUN] Would create: {$filename}");
                    $created++;

                    continue;
                }

                $this->line("Creating {$config['name']} level {$level}...");

                try {
                    // Use unique seed per character for variety
                    $charSeed = $seed + crc32("{$classSlug}:{$level}");
                    $randomizer = new CharacterRandomizer($charSeed);

                    // Create character via wizard flow
                    $character = $this->createCharacter($classSlug, $config['subclass'], $randomizer);

                    // Level up if needed
                    if ($level > 1) {
                        $this->levelUpCharacter($character, $level, $randomizer);
                    }

                    // Export character
                    $exportData = $this->exportService->export($character->fresh());

                    // Write to file
                    $filepath = "{$fixturesDir}/{$filename}";
                    File::put($filepath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    $this->info("  ✓ Exported: {$filename}");
                    $created++;

                    // Clean up - delete the character after export
                    $character->delete();

                } catch (\Exception $e) {
                    $this->error("  ✗ Failed: {$e->getMessage()}");
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info("Summary: {$created} exported, {$failed} failed");

        if (! $dryRun && $created > 0) {
            $this->info("Files saved to: {$fixturesDir}");
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function createCharacter(string $classSlug, ?string $subclassSlug, CharacterRandomizer $randomizer): Character
    {
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
            throw new \RuntimeException("Wizard flow failed: {$result->getSummary()}");
        }

        $character = Character::findOrFail($result->getCharacterId());

        if (! $character->is_complete) {
            throw new \RuntimeException('Character created but not complete');
        }

        return $character;
    }

    private function levelUpCharacter(Character $character, int $targetLevel, CharacterRandomizer $randomizer): void
    {
        $executor = new LevelUpFlowExecutor;

        $result = $executor->execute(
            characterId: $character->id,
            targetLevel: $targetLevel,
            randomizer: $randomizer,
            iteration: 1,
            mode: 'linear'
        );

        if ($result->hasError() || $result->hasFailed()) {
            throw new \RuntimeException("Level-up failed: {$result->getSummary()}");
        }
    }
}
