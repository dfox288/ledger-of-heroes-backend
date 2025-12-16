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
                            {--subclass= : Specific subclass slug (e.g., erlw:artificer-alchemist)}
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
        $subclassFilter = $this->option('subclass');
        $levelFilter = $this->option('level') ? (int) $this->option('level') : null;
        $seed = (int) $this->option('seed');
        $dryRun = $this->option('dry-run');

        // Verify database has imported data
        if (CharacterClass::count() < 10) {
            $this->error('Database not seeded with imported classes. Run import:all first.');

            return Command::FAILURE;
        }

        // Ensure fixtures directory exists
        $fixturesDir = storage_path('fixtures/class-tests');
        if (! $dryRun && ! File::exists($fixturesDir)) {
            File::makeDirectory($fixturesDir, 0755, true);
        }

        // Build configs from subclass filter or class filter or static configs
        $configs = $this->buildConfigs($classFilter, $subclassFilter);

        if (empty($configs)) {
            $this->error('No matching class/subclass found.');
            $this->info('Use --class=<slug> or --subclass=<slug>');

            return Command::FAILURE;
        }

        $this->info("Fixture Export (seed: {$seed})");
        $this->info('================================');

        $created = 0;
        $failed = 0;

        foreach ($configs as $config) {
            $levels = $levelFilter !== null ? [$levelFilter] : $config['levels'];

            foreach ($levels as $level) {
                $filename = sprintf('%s-L%02d.json', $config['name'], $level);

                if ($dryRun) {
                    $this->line("  [DRY-RUN] Would create: {$filename}");
                    $created++;

                    continue;
                }

                $this->line("Creating {$config['name']} level {$level}...");

                try {
                    // Use unique seed per character for variety
                    $charSeed = $seed + crc32("{$config['class_slug']}:{$config['subclass_slug']}:{$level}");
                    $randomizer = new CharacterRandomizer($charSeed);

                    // Create character via wizard flow
                    $character = $this->createCharacter($config['class_slug'], $config['subclass_slug'], $randomizer);

                    // Level up if needed
                    if ($level > 1) {
                        $this->levelUpCharacter($character, $level, $randomizer, $config['subclass_slug']);
                    }

                    // Rename character to match fixture name
                    $character->update(['name' => $config['display_name']." L{$level}"]);

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

    /**
     * Build export configurations from filters or database.
     */
    private function buildConfigs(?string $classFilter, ?string $subclassFilter): array
    {
        // Default milestone levels (most classes)
        $defaultLevels = [1, 3, 5, 10, 15, 20];

        // If subclass specified, build config for that specific subclass
        if ($subclassFilter) {
            $subclass = CharacterClass::where('slug', $subclassFilter)->first();
            if (! $subclass || ! $subclass->parent_class_id) {
                return [];
            }

            $parent = $subclass->parentClass;
            $subclassName = $this->extractSubclassName($subclass->name, $parent->name);

            return [[
                'name' => strtolower($parent->name).'-'.strtolower(str_replace(' ', '-', $subclassName)),
                'display_name' => $subclassName,
                'class_slug' => $parent->slug,
                'subclass_slug' => $subclass->slug,
                'levels' => $defaultLevels,
            ]];
        }

        // If class specified, build configs for all subclasses of that class
        if ($classFilter) {
            $class = CharacterClass::where('slug', $classFilter)->first();
            if (! $class) {
                return [];
            }

            // If it's a base class, get all its subclasses
            if (! $class->parent_class_id) {
                $subclasses = CharacterClass::where('parent_class_id', $class->id)->get();

                return $subclasses->map(function ($subclass) use ($class, $defaultLevels) {
                    $subclassName = $this->extractSubclassName($subclass->name, $class->name);

                    return [
                        'name' => strtolower($class->name).'-'.strtolower(str_replace(' ', '-', $subclassName)),
                        'display_name' => $subclassName,
                        'class_slug' => $class->slug,
                        'subclass_slug' => $subclass->slug,
                        'levels' => $defaultLevels,
                    ];
                })->toArray();
            }
        }

        // Fall back to static configs for base classes only
        return collect(self::CLASS_CONFIGS)->map(function ($config, $slug) {
            return [
                'name' => $config['name'],
                'display_name' => ucfirst($config['name']),
                'class_slug' => $slug,
                'subclass_slug' => $config['subclass'],
                'levels' => $config['levels'],
            ];
        })->values()->toArray();
    }

    /**
     * Extract subclass name from full name.
     * e.g., "Alchemist (Artificer)" -> "Alchemist"
     */
    private function extractSubclassName(string $fullName, string $className): string
    {
        // Remove class name in parentheses: "Alchemist (Artificer)" -> "Alchemist"
        $name = preg_replace('/\s*\([^)]+\)\s*$/', '', $fullName);

        // Remove class prefix: "Divine Domain: Life Domain" -> "Life Domain"
        $patterns = [
            '/^Sacred Oath:\s*/i',
            '/^Divine Domain:\s*/i',
            '/^Druid Circle:\s*/i',
            '/^Martial Archetype:\s*/i',
            '/^Monastic Tradition:\s*/i',
            '/^Ranger Archetype:\s*/i',
            '/^Roguish Archetype:\s*/i',
            '/^Sorcerous Origin:\s*/i',
            '/^Otherworldly Patron:\s*/i',
            '/^Arcane Tradition:\s*/i',
            '/^Primal Path:\s*/i',
            '/^Bard College:\s*/i',
            '/^Artificer Specialist:\s*/i',
        ];

        foreach ($patterns as $pattern) {
            $name = preg_replace($pattern, '', $name);
        }

        return trim($name);
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

    private function levelUpCharacter(Character $character, int $targetLevel, CharacterRandomizer $randomizer, ?string $subclassSlug = null): void
    {
        $executor = new LevelUpFlowExecutor;

        $result = $executor->execute(
            characterId: $character->id,
            targetLevel: $targetLevel,
            randomizer: $randomizer,
            iteration: 1,
            mode: 'linear',
            forceSubclass: $subclassSlug,
        );

        if ($result->hasError() || $result->hasFailed()) {
            throw new \RuntimeException("Level-up failed: {$result->getSummary()}");
        }
    }
}
