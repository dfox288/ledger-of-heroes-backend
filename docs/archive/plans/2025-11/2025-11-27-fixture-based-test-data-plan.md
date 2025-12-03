# Fixture-Based Test Data Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace XML import dependency with JSON fixtures + seeders for deterministic, isolated test data.

**Architecture:** JSON fixture files in `tests/fixtures/` loaded by entity-specific seeders orchestrated by `TestDatabaseSeeder`. An Artisan command extracts coverage-based data from production DB.

**Tech Stack:** Laravel 12.x, PHPUnit 11, Meilisearch, JSON fixtures

**Design Doc:** `docs/plans/2025-01-27-fixture-based-test-data-design.md`

---

## Phase 1: Fixture Infrastructure

### Task 1.1: Create Fixture Directory Structure

**Files:**
- Create: `tests/fixtures/entities/.gitkeep`
- Create: `tests/fixtures/lookups/.gitkeep`
- Create: `tests/fixtures/README.md`

**Step 1: Create directories**

```bash
mkdir -p tests/fixtures/entities tests/fixtures/lookups
```

**Step 2: Create README documenting fixture format**

Create `tests/fixtures/README.md`:

```markdown
# Test Fixtures

JSON fixture files for test data seeding. Extracted from production database.

## Structure

- `entities/` - Main entity fixtures (spells, monsters, classes, etc.)
- `lookups/` - Lookup table fixtures (sources, schools, damage types, etc.)

## Format

Uses slugs for relationships (resolved at seed time):

```json
{
  "name": "Fireball",
  "slug": "fireball",
  "school": "evocation",
  "classes": ["sorcerer", "wizard"],
  "source": "phb"
}
```

## Regenerating Fixtures

```bash
docker compose exec php php artisan fixtures:extract
```
```

**Step 3: Create .gitkeep files**

```bash
touch tests/fixtures/entities/.gitkeep
touch tests/fixtures/lookups/.gitkeep
```

**Step 4: Commit**

```bash
git add tests/fixtures/
git commit -m "chore: create fixture directory structure"
```

---

### Task 1.2: Create Base FixtureSeeder Class

**Files:**
- Create: `database/seeders/Testing/FixtureSeeder.php`
- Test: Manual verification (abstract class)

**Step 1: Create Testing directory**

```bash
mkdir -p database/seeders/Testing
```

**Step 2: Write the FixtureSeeder base class**

Create `database/seeders/Testing/FixtureSeeder.php`:

```php
<?php

namespace Database\Seeders\Testing;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

abstract class FixtureSeeder extends Seeder
{
    /**
     * Path to the JSON fixture file relative to base_path().
     */
    abstract protected function fixturePath(): string;

    /**
     * The model class this seeder populates.
     */
    abstract protected function model(): string;

    /**
     * Create a model instance from fixture data.
     */
    abstract protected function createFromFixture(array $item): void;

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $path = base_path($this->fixturePath());

        if (! File::exists($path)) {
            $this->command?->warn("Fixture file not found: {$this->fixturePath()}");

            return;
        }

        $data = json_decode(File::get($path), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command?->error("Invalid JSON in {$this->fixturePath()}: ".json_last_error_msg());

            return;
        }

        foreach ($data as $item) {
            $this->createFromFixture($item);
        }

        $count = count($data);
        $model = class_basename($this->model());
        $this->command?->info("Seeded {$count} {$model} records from fixtures.");
    }
}
```

**Step 3: Verify syntax**

```bash
docker compose exec php php -l database/seeders/Testing/FixtureSeeder.php
```

Expected: `No syntax errors detected`

**Step 4: Commit**

```bash
git add database/seeders/Testing/FixtureSeeder.php
git commit -m "feat: add FixtureSeeder base class for JSON fixtures"
```

---

### Task 1.3: Create TestDatabaseSeeder

**Files:**
- Create: `database/seeders/TestDatabaseSeeder.php`
- Test: `tests/Unit/Seeders/TestDatabaseSeederTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Seeders/TestDatabaseSeederTest.php`:

```php
<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false; // Don't auto-seed, we're testing the seeder

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_exists_and_is_runnable(): void
    {
        $seeder = new TestDatabaseSeeder();

        $this->assertInstanceOf(\Illuminate\Database\Seeder::class, $seeder);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_seeds_lookup_tables(): void
    {
        $this->seed(TestDatabaseSeeder::class);

        // Verify lookup tables are populated
        $this->assertDatabaseHas('spell_schools', ['slug' => 'evocation']);
        $this->assertDatabaseHas('damage_types', ['slug' => 'fire']);
        $this->assertDatabaseHas('sizes', ['slug' => 'medium']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test tests/Unit/Seeders/TestDatabaseSeederTest.php -v
```

Expected: FAIL with "Class 'Database\Seeders\TestDatabaseSeeder' not found"

**Step 3: Write TestDatabaseSeeder**

Create `database/seeders/TestDatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    /**
     * Seed the test database with fixture data.
     *
     * Order matches import:all dependency chain:
     * 1. Lookups (sources, schools, damage types, etc.)
     * 2. Items (required by classes/backgrounds for equipment)
     * 3. Classes (required by spells for class lists)
     * 4. Spells
     * 5. Races
     * 6. Backgrounds
     * 7. Feats
     * 8. Monsters
     * 9. Optional Features
     */
    public function run(): void
    {
        // Step 1: Lookup tables (required by all entities)
        $this->call([
            SourceSeeder::class,
            SpellSchoolSeeder::class,
            DamageTypeSeeder::class,
            SizeSeeder::class,
            AbilityScoreSeeder::class,
            SkillSeeder::class,
            ItemTypeSeeder::class,
            ItemPropertySeeder::class,
            ConditionSeeder::class,
            ProficiencyTypeSeeder::class,
            LanguageSeeder::class,
        ]);

        // Step 2: Entity fixtures (will be added in Phase 2)
        // Commented out until fixture seeders exist:
        // $this->call([
        //     Testing\ItemFixtureSeeder::class,
        //     Testing\ClassFixtureSeeder::class,
        //     Testing\SpellFixtureSeeder::class,
        //     Testing\RaceFixtureSeeder::class,
        //     Testing\BackgroundFixtureSeeder::class,
        //     Testing\FeatFixtureSeeder::class,
        //     Testing\MonsterFixtureSeeder::class,
        //     Testing\OptionalFeatureFixtureSeeder::class,
        // ]);
    }

    /**
     * Index all searchable models for Meilisearch.
     * Called after entity fixtures are seeded.
     */
    protected function indexSearchableModels(): void
    {
        $models = [
            \App\Models\Spell::class,
            \App\Models\Monster::class,
            \App\Models\CharacterClass::class,
            \App\Models\Race::class,
            \App\Models\Item::class,
            \App\Models\Feat::class,
            \App\Models\Background::class,
            \App\Models\OptionalFeature::class,
        ];

        foreach ($models as $model) {
            if ($model::count() > 0) {
                $model::all()->searchable();
            }
        }
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test tests/Unit/Seeders/TestDatabaseSeederTest.php -v
```

Expected: PASS

**Step 5: Commit**

```bash
git add database/seeders/TestDatabaseSeeder.php tests/Unit/Seeders/TestDatabaseSeederTest.php
git commit -m "feat: add TestDatabaseSeeder for fixture-based test data"
```

---

## Phase 2: Fixture Extraction Command

### Task 2.1: Create ExtractFixtures Command - Core Structure

**Files:**
- Create: `app/Console/Commands/ExtractFixturesCommand.php`
- Test: `tests/Feature/Console/ExtractFixturesCommandTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Console/ExtractFixturesCommandTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExtractFixturesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up test fixture directory
        $testPath = base_path('tests/fixtures/test-output');
        if (File::isDirectory($testPath)) {
            File::deleteDirectory($testPath);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_the_command_registered(): void
    {
        $this->artisan('fixtures:extract', ['--help' => true])
            ->assertSuccessful();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_entity_type_argument(): void
    {
        $this->artisan('fixtures:extract')
            ->assertFailed();
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test tests/Feature/Console/ExtractFixturesCommandTest.php -v
```

Expected: FAIL with "Command 'fixtures:extract' not found"

**Step 3: Write the command skeleton**

Create `app/Console/Commands/ExtractFixturesCommand.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExtractFixturesCommand extends Command
{
    protected $signature = 'fixtures:extract
                            {entity : Entity type to extract (spells, monsters, classes, races, items, feats, backgrounds, optionalfeatures, all)}
                            {--output=tests/fixtures : Output directory}
                            {--analyze-tests : Analyze test files for referenced entities}
                            {--limit=100 : Maximum entities per type}';

    protected $description = 'Extract fixture data from database for test seeding';

    public function handle(): int
    {
        $entity = $this->argument('entity');
        $output = $this->option('output');

        if (! File::isDirectory(base_path($output))) {
            File::makeDirectory(base_path($output), 0755, true);
        }

        $entities = $entity === 'all'
            ? ['spells', 'monsters', 'classes', 'races', 'items', 'feats', 'backgrounds', 'optionalfeatures']
            : [$entity];

        foreach ($entities as $entityType) {
            $this->extractEntity($entityType, $output);
        }

        return self::SUCCESS;
    }

    protected function extractEntity(string $entity, string $output): void
    {
        $this->info("Extracting {$entity}...");

        $extractor = $this->getExtractor($entity);

        if (! $extractor) {
            $this->error("Unknown entity type: {$entity}");

            return;
        }

        $data = $extractor();
        $path = base_path("{$output}/entities/{$entity}.json");

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("  Extracted ".count($data)." {$entity} to {$path}");
    }

    protected function getExtractor(string $entity): ?\Closure
    {
        return match ($entity) {
            'spells' => fn () => $this->extractSpells(),
            'monsters' => fn () => $this->extractMonsters(),
            'classes' => fn () => $this->extractClasses(),
            'races' => fn () => $this->extractRaces(),
            'items' => fn () => $this->extractItems(),
            'feats' => fn () => $this->extractFeats(),
            'backgrounds' => fn () => $this->extractBackgrounds(),
            'optionalfeatures' => fn () => $this->extractOptionalFeatures(),
            default => null,
        };
    }

    // Placeholder extractors - will be implemented in subsequent tasks
    protected function extractSpells(): array
    {
        return [];
    }

    protected function extractMonsters(): array
    {
        return [];
    }

    protected function extractClasses(): array
    {
        return [];
    }

    protected function extractRaces(): array
    {
        return [];
    }

    protected function extractItems(): array
    {
        return [];
    }

    protected function extractFeats(): array
    {
        return [];
    }

    protected function extractBackgrounds(): array
    {
        return [];
    }

    protected function extractOptionalFeatures(): array
    {
        return [];
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test tests/Feature/Console/ExtractFixturesCommandTest.php -v
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/ExtractFixturesCommand.php tests/Feature/Console/ExtractFixturesCommandTest.php
git commit -m "feat: add fixtures:extract command skeleton"
```

---

### Task 2.2: Implement Spell Extraction with Edge Cases

**Files:**
- Modify: `app/Console/Commands/ExtractFixturesCommand.php`
- Test: `tests/Feature/Console/ExtractFixturesCommandTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Console/ExtractFixturesCommandTest.php`:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_extracts_spells_with_coverage_based_selection(): void
{
    // Create test spells covering edge cases
    $source = \App\Models\Source::factory()->create(['slug' => 'test-phb']);
    $school = \App\Models\SpellSchool::first();
    $class = \App\Models\CharacterClass::factory()->create(['slug' => 'wizard']);

    // Create spells at different levels
    foreach (range(0, 3) as $level) {
        $spell = \App\Models\Spell::factory()->create([
            'level' => $level,
            'spell_school_id' => $school->id,
            'source_id' => $source->id,
        ]);
        $spell->classes()->attach($class->id);
    }

    // Extract
    $this->artisan('fixtures:extract', [
        'entity' => 'spells',
        '--output' => 'tests/fixtures/test-output',
    ])->assertSuccessful();

    // Verify JSON created
    $path = base_path('tests/fixtures/test-output/entities/spells.json');
    $this->assertFileExists($path);

    $data = json_decode(File::get($path), true);
    $this->assertIsArray($data);
    $this->assertGreaterThanOrEqual(4, count($data));

    // Verify structure
    $spell = $data[0];
    $this->assertArrayHasKey('name', $spell);
    $this->assertArrayHasKey('slug', $spell);
    $this->assertArrayHasKey('level', $spell);
    $this->assertArrayHasKey('school', $spell);
    $this->assertArrayHasKey('classes', $spell);
    $this->assertArrayHasKey('source', $spell);

    // Verify relationships are slugs, not IDs
    $this->assertIsString($spell['school']);
    $this->assertIsArray($spell['classes']);
    $this->assertIsString($spell['source']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test tests/Feature/Console/ExtractFixturesCommandTest.php --filter=it_extracts_spells -v
```

Expected: FAIL (empty array or missing keys)

**Step 3: Implement extractSpells**

Update `extractSpells()` in `ExtractFixturesCommand.php`:

```php
protected function extractSpells(): array
{
    $limit = (int) $this->option('limit');

    // Get coverage-based selection:
    // 1. One spell per level (0-9)
    // 2. One spell per school
    // 3. Concentration and ritual variants
    // 4. Additional random spells up to limit

    $spellIds = collect();

    // One per level
    foreach (range(0, 9) as $level) {
        $spell = \App\Models\Spell::where('level', $level)->first();
        if ($spell) {
            $spellIds->push($spell->id);
        }
    }

    // One per school
    \App\Models\SpellSchool::all()->each(function ($school) use ($spellIds) {
        $spell = \App\Models\Spell::where('spell_school_id', $school->id)
            ->whereNotIn('id', $spellIds->toArray())
            ->first();
        if ($spell) {
            $spellIds->push($spell->id);
        }
    });

    // Concentration spells
    $concentration = \App\Models\Spell::where('concentration', true)
        ->whereNotIn('id', $spellIds->toArray())
        ->take(3)
        ->pluck('id');
    $spellIds = $spellIds->merge($concentration);

    // Ritual spells
    $ritual = \App\Models\Spell::where('ritual', true)
        ->whereNotIn('id', $spellIds->toArray())
        ->take(3)
        ->pluck('id');
    $spellIds = $spellIds->merge($ritual);

    // Fill remaining with random spells
    $remaining = $limit - $spellIds->count();
    if ($remaining > 0) {
        $additional = \App\Models\Spell::whereNotIn('id', $spellIds->toArray())
            ->inRandomOrder()
            ->take($remaining)
            ->pluck('id');
        $spellIds = $spellIds->merge($additional);
    }

    // Load full models with relationships
    $spells = \App\Models\Spell::whereIn('id', $spellIds->unique())
        ->with(['school', 'classes', 'source', 'damageTypes', 'conditions'])
        ->get();

    return $spells->map(fn ($spell) => $this->formatSpell($spell))->toArray();
}

protected function formatSpell(\App\Models\Spell $spell): array
{
    return [
        'name' => $spell->name,
        'slug' => $spell->slug,
        'level' => $spell->level,
        'school' => $spell->school->slug,
        'casting_time' => $spell->casting_time,
        'range' => $spell->range,
        'components' => $spell->components,
        'material' => $spell->material,
        'duration' => $spell->duration,
        'concentration' => $spell->concentration,
        'ritual' => $spell->ritual,
        'description' => $spell->description,
        'higher_levels' => $spell->higher_levels,
        'classes' => $spell->classes->pluck('slug')->toArray(),
        'damage_types' => $spell->damageTypes->pluck('slug')->toArray(),
        'conditions' => $spell->conditions->pluck('slug')->toArray(),
        'source' => $spell->source->slug,
        'page' => $spell->page,
    ];
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test tests/Feature/Console/ExtractFixturesCommandTest.php --filter=it_extracts_spells -v
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/ExtractFixturesCommand.php tests/Feature/Console/ExtractFixturesCommandTest.php
git commit -m "feat: implement spell extraction with coverage-based selection"
```

---

### Task 2.3: Implement Monster Extraction

**Files:**
- Modify: `app/Console/Commands/ExtractFixturesCommand.php`
- Modify: `tests/Feature/Console/ExtractFixturesCommandTest.php`

**Step 1: Write the failing test**

Add to test file:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_extracts_monsters_with_cr_coverage(): void
{
    $source = \App\Models\Source::factory()->create(['slug' => 'test-mm']);

    // Create monsters at different CRs
    foreach ([0, 0.125, 0.25, 0.5, 1, 5, 10, 20] as $cr) {
        \App\Models\Monster::factory()->create([
            'challenge_rating' => $cr,
            'source_id' => $source->id,
        ]);
    }

    $this->artisan('fixtures:extract', [
        'entity' => 'monsters',
        '--output' => 'tests/fixtures/test-output',
    ])->assertSuccessful();

    $path = base_path('tests/fixtures/test-output/entities/monsters.json');
    $this->assertFileExists($path);

    $data = json_decode(File::get($path), true);
    $this->assertGreaterThanOrEqual(8, count($data));

    // Verify structure
    $monster = $data[0];
    $this->assertArrayHasKey('name', $monster);
    $this->assertArrayHasKey('slug', $monster);
    $this->assertArrayHasKey('challenge_rating', $monster);
    $this->assertArrayHasKey('size', $monster);
    $this->assertArrayHasKey('type', $monster);
    $this->assertArrayHasKey('source', $monster);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test tests/Feature/Console/ExtractFixturesCommandTest.php --filter=it_extracts_monsters -v
```

**Step 3: Implement extractMonsters**

```php
protected function extractMonsters(): array
{
    $limit = (int) $this->option('limit');
    $monsterIds = collect();

    // Coverage-based: one per CR tier
    $crTiers = [0, 0.125, 0.25, 0.5, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30];

    foreach ($crTiers as $cr) {
        $monster = \App\Models\Monster::where('challenge_rating', $cr)->first();
        if ($monster) {
            $monsterIds->push($monster->id);
        }
    }

    // One per size
    \App\Models\Size::all()->each(function ($size) use ($monsterIds) {
        $monster = \App\Models\Monster::where('size_id', $size->id)
            ->whereNotIn('id', $monsterIds->toArray())
            ->first();
        if ($monster) {
            $monsterIds->push($monster->id);
        }
    });

    // One per type
    \App\Models\Monster::distinct('type')->pluck('type')->each(function ($type) use ($monsterIds) {
        $monster = \App\Models\Monster::where('type', $type)
            ->whereNotIn('id', $monsterIds->toArray())
            ->first();
        if ($monster) {
            $monsterIds->push($monster->id);
        }
    });

    // Fill remaining
    $remaining = $limit - $monsterIds->count();
    if ($remaining > 0) {
        $additional = \App\Models\Monster::whereNotIn('id', $monsterIds->toArray())
            ->inRandomOrder()
            ->take($remaining)
            ->pluck('id');
        $monsterIds = $monsterIds->merge($additional);
    }

    $monsters = \App\Models\Monster::whereIn('id', $monsterIds->unique())
        ->with(['size', 'source', 'damageVulnerabilities', 'damageResistances', 'damageImmunities', 'conditionImmunities'])
        ->get();

    return $monsters->map(fn ($m) => $this->formatMonster($m))->toArray();
}

protected function formatMonster(\App\Models\Monster $monster): array
{
    return [
        'name' => $monster->name,
        'slug' => $monster->slug,
        'size' => $monster->size->slug,
        'type' => $monster->type,
        'subtype' => $monster->subtype,
        'alignment' => $monster->alignment,
        'armor_class' => $monster->armor_class,
        'armor_type' => $monster->armor_type,
        'hit_points' => $monster->hit_points,
        'hit_dice' => $monster->hit_dice,
        'speed' => $monster->speed,
        'strength' => $monster->strength,
        'dexterity' => $monster->dexterity,
        'constitution' => $monster->constitution,
        'intelligence' => $monster->intelligence,
        'wisdom' => $monster->wisdom,
        'charisma' => $monster->charisma,
        'challenge_rating' => $monster->challenge_rating,
        'experience_points' => $monster->experience_points,
        'damage_vulnerabilities' => $monster->damageVulnerabilities->pluck('slug')->toArray(),
        'damage_resistances' => $monster->damageResistances->pluck('slug')->toArray(),
        'damage_immunities' => $monster->damageImmunities->pluck('slug')->toArray(),
        'condition_immunities' => $monster->conditionImmunities->pluck('slug')->toArray(),
        'senses' => $monster->senses,
        'languages' => $monster->languages,
        'traits' => $monster->traits,
        'actions' => $monster->actions,
        'legendary_actions' => $monster->legendary_actions,
        'source' => $monster->source->slug,
        'page' => $monster->page,
    ];
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test tests/Feature/Console/ExtractFixturesCommandTest.php --filter=it_extracts_monsters -v
```

**Step 5: Commit**

```bash
git add app/Console/Commands/ExtractFixturesCommand.php tests/Feature/Console/ExtractFixturesCommandTest.php
git commit -m "feat: implement monster extraction with CR coverage"
```

---

### Task 2.4-2.9: Implement Remaining Entity Extractors

Follow the same TDD pattern for each entity type:
- **Task 2.4:** Classes (all 13 base classes + subclasses)
- **Task 2.5:** Races (each size, with/without subraces)
- **Task 2.6:** Items (each rarity, each type)
- **Task 2.7:** Feats (with/without prerequisites)
- **Task 2.8:** Backgrounds
- **Task 2.9:** Optional Features (by feature type)

Each task follows steps:
1. Write failing test for extraction
2. Run to verify failure
3. Implement extractor + formatter
4. Run to verify pass
5. Commit

---

## Phase 3: Create Entity Fixture Seeders

### Task 3.1: Create SpellFixtureSeeder

**Files:**
- Create: `database/seeders/Testing/SpellFixtureSeeder.php`
- Test: `tests/Unit/Seeders/SpellFixtureSeederTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Seeders/SpellFixtureSeederTest.php`:

```php
<?php

namespace Tests\Unit\Seeders;

use App\Models\Spell;
use App\Models\SpellSchool;
use Database\Seeders\Testing\SpellFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SpellFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture
        $fixturePath = base_path('tests/fixtures/entities/spells.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Fireball',
                'slug' => 'test-fireball',
                'level' => 3,
                'school' => 'evocation',
                'casting_time' => '1 action',
                'range' => '150 feet',
                'components' => ['V', 'S', 'M'],
                'material' => 'bat guano',
                'duration' => 'Instantaneous',
                'concentration' => false,
                'ritual' => false,
                'description' => 'A fireball spell.',
                'higher_levels' => null,
                'classes' => [],
                'damage_types' => ['fire'],
                'conditions' => [],
                'source' => 'players-handbook',
                'page' => 241,
            ],
        ]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_spells_from_fixture(): void
    {
        $this->assertDatabaseMissing('spells', ['slug' => 'test-fireball']);

        $seeder = new SpellFixtureSeeder();
        $seeder->run();

        $this->assertDatabaseHas('spells', [
            'slug' => 'test-fireball',
            'name' => 'Test Fireball',
            'level' => 3,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_school_by_slug(): void
    {
        $seeder = new SpellFixtureSeeder();
        $seeder->run();

        $spell = Spell::where('slug', 'test-fireball')->first();
        $this->assertNotNull($spell);
        $this->assertEquals('evocation', $spell->school->slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_attaches_damage_types(): void
    {
        $seeder = new SpellFixtureSeeder();
        $seeder->run();

        $spell = Spell::where('slug', 'test-fireball')->first();
        $this->assertTrue($spell->damageTypes->contains('slug', 'fire'));
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test tests/Unit/Seeders/SpellFixtureSeederTest.php -v
```

Expected: FAIL with "Class 'Database\Seeders\Testing\SpellFixtureSeeder' not found"

**Step 3: Implement SpellFixtureSeeder**

Create `database/seeders/Testing/SpellFixtureSeeder.php`:

```php
<?php

namespace Database\Seeders\Testing;

use App\Models\CharacterClass;
use App\Models\Condition;
use App\Models\DamageType;
use App\Models\Source;
use App\Models\Spell;
use App\Models\SpellSchool;

class SpellFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/spells.json';
    }

    protected function model(): string
    {
        return Spell::class;
    }

    protected function createFromFixture(array $item): void
    {
        $spell = Spell::create([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'level' => $item['level'],
            'spell_school_id' => SpellSchool::where('slug', $item['school'])->first()?->id,
            'casting_time' => $item['casting_time'],
            'range' => $item['range'],
            'components' => $item['components'],
            'material' => $item['material'],
            'duration' => $item['duration'],
            'concentration' => $item['concentration'],
            'ritual' => $item['ritual'],
            'description' => $item['description'],
            'higher_levels' => $item['higher_levels'],
            'source_id' => Source::where('slug', $item['source'])->first()?->id,
            'page' => $item['page'],
        ]);

        // Attach classes
        if (! empty($item['classes'])) {
            $classIds = CharacterClass::whereIn('slug', $item['classes'])->pluck('id');
            $spell->classes()->attach($classIds);
        }

        // Attach damage types
        if (! empty($item['damage_types'])) {
            $damageTypeIds = DamageType::whereIn('slug', $item['damage_types'])->pluck('id');
            $spell->damageTypes()->attach($damageTypeIds);
        }

        // Attach conditions
        if (! empty($item['conditions'])) {
            $conditionIds = Condition::whereIn('slug', $item['conditions'])->pluck('id');
            $spell->conditions()->attach($conditionIds);
        }
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test tests/Unit/Seeders/SpellFixtureSeederTest.php -v
```

Expected: PASS

**Step 5: Commit**

```bash
git add database/seeders/Testing/SpellFixtureSeeder.php tests/Unit/Seeders/SpellFixtureSeederTest.php
git commit -m "feat: add SpellFixtureSeeder for loading spell fixtures"
```

---

### Task 3.2-3.8: Create Remaining Fixture Seeders

Follow same TDD pattern for each:
- **Task 3.2:** MonsterFixtureSeeder
- **Task 3.3:** ClassFixtureSeeder
- **Task 3.4:** RaceFixtureSeeder
- **Task 3.5:** ItemFixtureSeeder
- **Task 3.6:** FeatFixtureSeeder
- **Task 3.7:** BackgroundFixtureSeeder
- **Task 3.8:** OptionalFeatureFixtureSeeder

---

### Task 3.9: Wire Up TestDatabaseSeeder

**Files:**
- Modify: `database/seeders/TestDatabaseSeeder.php`
- Modify: `tests/Unit/Seeders/TestDatabaseSeederTest.php`

**Step 1: Update TestDatabaseSeeder to call fixture seeders**

Uncomment the fixture seeder calls in `TestDatabaseSeeder.php`:

```php
// Step 2: Entity fixtures
$this->call([
    Testing\ItemFixtureSeeder::class,
    Testing\ClassFixtureSeeder::class,
    Testing\SpellFixtureSeeder::class,
    Testing\RaceFixtureSeeder::class,
    Testing\BackgroundFixtureSeeder::class,
    Testing\FeatFixtureSeeder::class,
    Testing\MonsterFixtureSeeder::class,
    Testing\OptionalFeatureFixtureSeeder::class,
]);

// Step 3: Index for search
$this->indexSearchableModels();
```

**Step 2: Add integration test**

Add to `TestDatabaseSeederTest.php`:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_seeds_all_entity_fixtures(): void
{
    $this->seed(TestDatabaseSeeder::class);

    // Verify entities exist (assuming fixtures have data)
    $this->assertGreaterThan(0, \App\Models\Spell::count());
    $this->assertGreaterThan(0, \App\Models\Monster::count());
    // ... etc
}
```

**Step 3: Run test**

```bash
docker compose exec php php artisan test tests/Unit/Seeders/TestDatabaseSeederTest.php -v
```

**Step 4: Commit**

```bash
git add database/seeders/TestDatabaseSeeder.php tests/Unit/Seeders/TestDatabaseSeederTest.php
git commit -m "feat: wire up fixture seeders in TestDatabaseSeeder"
```

---

## Phase 4: Extract Production Fixtures

### Task 4.1: Extract All Fixtures from Production DB

**Prerequisite:** Production database must have imported data.

**Step 1: Run extraction command**

```bash
docker compose exec php php artisan fixtures:extract all --limit=100
```

**Step 2: Review generated fixtures**

```bash
ls -la tests/fixtures/entities/
cat tests/fixtures/entities/spells.json | head -100
```

**Step 3: Commit fixtures**

```bash
git add tests/fixtures/entities/*.json
git commit -m "feat: extract test fixtures from production database"
```

---

## Phase 5: Migrate Tests

### Task 5.1: Update TestCase to Use TestDatabaseSeeder

**Files:**
- Modify: `tests/TestCase.php`

**Step 1: Update seeder reference**

```php
// tests/TestCase.php
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Use TestDatabaseSeeder for fixture-based test data.
     */
    protected string $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected bool $seed = true;

    // ... rest of TestCase
}
```

**Step 2: Run unit tests to verify no breakage**

```bash
docker compose exec php php artisan test --testsuite=Unit-DB -v
```

**Step 3: Commit**

```bash
git add tests/TestCase.php
git commit -m "refactor: use TestDatabaseSeeder in base TestCase"
```

---

### Task 5.2: Migrate SpellSearchTest

**Files:**
- Modify: `tests/Feature/Api/SpellSearchTest.php`

**Step 1: Update test to use RefreshDatabase**

```php
<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class SpellSearchTest extends TestCase
{
    use RefreshDatabase;

    // Remove #[Group('search-imported')] if present

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_spells_by_name(): void
    {
        // Fixtures provide predictable data
        $response = $this->getJson('/api/v1/spells?q=fireball');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Fireball']);
    }

    // Update assertions to match fixture data counts
}
```

**Step 2: Run migrated test**

```bash
docker compose exec php php artisan test tests/Feature/Api/SpellSearchTest.php -v
```

**Step 3: Fix any assertion failures based on fixture data**

**Step 4: Commit**

```bash
git add tests/Feature/Api/SpellSearchTest.php
git commit -m "refactor: migrate SpellSearchTest to fixture-based data"
```

---

### Task 5.3-5.15: Migrate Remaining Search Tests

Repeat migration pattern for each test file:
- Add `use RefreshDatabase;`
- Remove `#[Group('search-imported')]`
- Update assertions to match fixture data
- Run and verify
- Commit

Files to migrate:
- `SpellFilterOperatorTest.php`
- `SpellEnhancedFilteringTest.php`
- `SpellMeilisearchFilterTest.php`
- `MonsterSearchTest.php`
- `MonsterFilterOperatorTest.php`
- `ClassSearchTest.php`
- `ClassFilterOperatorTest.php`
- `RaceSearchTest.php`
- `RaceFilterOperatorTest.php`
- `ItemSearchTest.php`
- `FeatSearchTest.php`
- `BackgroundSearchTest.php`
- `GlobalSearchTest.php`

---

## Phase 6: Remove Legacy Infrastructure

### Task 6.1: Delete SearchTestExtension

**Files:**
- Delete: `tests/Support/SearchTestExtension.php`
- Delete: `tests/Support/SearchTestSubscriber.php`
- Delete: `tests/Concerns/UsesImportedTestData.php`
- Modify: `phpunit.xml` (remove extension)

**Step 1: Remove extension from phpunit.xml**

Remove this block from `phpunit.xml`:

```xml
<extensions>
    <bootstrap class="Tests\Support\SearchTestExtension"/>
</extensions>
```

**Step 2: Delete files**

```bash
rm tests/Support/SearchTestExtension.php
rm tests/Support/SearchTestSubscriber.php
rm tests/Concerns/UsesImportedTestData.php
```

**Step 3: Run full test suite**

```bash
docker compose exec php php artisan test
```

**Step 4: Commit**

```bash
git add -A
git commit -m "chore: remove SearchTestExtension and imported data infrastructure"
```

---

### Task 6.2: Update phpunit.xml Test Suites

**Files:**
- Modify: `phpunit.xml`

**Step 1: Merge Feature-Search-Imported into Feature-Search**

Update suite definitions to remove `search-imported` distinction.

**Step 2: Run tests with new suite config**

```bash
docker compose exec php php artisan test --testsuite=Feature-Search -v
```

**Step 3: Commit**

```bash
git add phpunit.xml
git commit -m "refactor: simplify test suites, remove search-imported"
```

---

## Phase 7: Validate & Document

### Task 7.1: Run Full Test Suite

```bash
docker compose exec php php artisan test
```

All 205 tests should pass.

### Task 7.2: Update CLAUDE.md

Update test suite documentation to reflect new fixture-based approach.

### Task 7.3: Update PROJECT-STATUS.md

Note migration to fixture-based test data.

### Task 7.4: Final Commit

```bash
git add docs/
git commit -m "docs: update documentation for fixture-based test data"
```

---

## Summary

| Phase | Tasks | Estimated Commits |
|-------|-------|-------------------|
| 1. Infrastructure | 3 | 3 |
| 2. Extraction Command | 9 | 9 |
| 3. Fixture Seeders | 9 | 9 |
| 4. Extract Data | 1 | 1 |
| 5. Migrate Tests | 15 | 15 |
| 6. Remove Legacy | 2 | 2 |
| 7. Validate | 4 | 2 |
| **Total** | **43** | **~41** |
