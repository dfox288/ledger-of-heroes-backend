# Fixture-Based Test Data Design

**Date:** 2025-01-27
**Status:** Approved
**Goal:** Eliminate MySQL imported data dependency; switch to JSON fixtures + seeders for test isolation

## Problem Statement

Current test suite has 31 tests (15%) that require:
- Full XML import via `import:all` command
- Real MySQL database with ~500+ spells, ~1000+ monsters
- `SearchTestExtension` auto-importing if data missing
- ~180s import time on first run

This creates:
- Slow CI/CD pipelines
- Complex new developer setup
- Non-deterministic test data
- Coupling between test data and production import logic

## Solution Overview

Replace XML import dependency with JSON fixture files loaded by seeders. Tests use `RefreshDatabase` + `TestDatabaseSeeder` for predictable, isolated test data.

### Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Data format | JSON fixtures | Reviewable, diffable, separate from PHP code |
| Data source | Extract from production DB | Real D&D content, zero manual curation |
| Data volume | Coverage-based | Only entities tests reference + edge cases |
| Extraction tooling | Artisan command | Reusable for future fixture updates |

## Architecture

### Fixture File Structure

```
tests/fixtures/
├── entities/
│   ├── spells.json           # ~50-100 curated spells
│   ├── monsters.json         # ~50-100 curated monsters
│   ├── classes.json          # All 13 classes with features
│   ├── races.json            # ~20 races with traits
│   ├── items.json            # ~50 items across categories
│   ├── feats.json            # ~30 feats
│   ├── backgrounds.json      # ~15 backgrounds
│   └── optional-features.json
├── lookups/
│   ├── sources.json
│   ├── spell-schools.json
│   ├── damage-types.json
│   └── ...
└── README.md
```

### JSON Format

Uses slugs for relationships (resolved at seed time):

```json
[
  {
    "name": "Fireball",
    "slug": "fireball",
    "level": 3,
    "school": "evocation",
    "casting_time": "1 action",
    "range": "150 feet",
    "components": ["V", "S", "M"],
    "material": "a tiny ball of bat guano and sulfur",
    "duration": "Instantaneous",
    "concentration": false,
    "ritual": false,
    "description": "A bright streak flashes from your pointing finger...",
    "classes": ["sorcerer", "wizard"],
    "source": "phb",
    "page": 241
  }
]
```

### Seeder Structure

```
database/seeders/
├── DatabaseSeeder.php          # Production (unchanged)
├── TestDatabaseSeeder.php      # Test orchestrator
└── Testing/
    ├── FixtureSeeder.php       # Base class
    ├── SpellFixtureSeeder.php
    ├── MonsterFixtureSeeder.php
    ├── ClassFixtureSeeder.php
    ├── RaceFixtureSeeder.php
    ├── ItemFixtureSeeder.php
    ├── FeatFixtureSeeder.php
    ├── BackgroundFixtureSeeder.php
    └── OptionalFeatureFixtureSeeder.php
```

### TestDatabaseSeeder Order

Matches `import:all` dependency order:

```php
class TestDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: Lookup tables
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

        // Step 2: Entity fixtures (import:all order)
        $this->call([
            Testing\ItemFixtureSeeder::class,           // Items first (equipment refs)
            Testing\ClassFixtureSeeder::class,          // Classes (spell refs)
            Testing\SpellFixtureSeeder::class,          // Spells
            Testing\RaceFixtureSeeder::class,           // Races
            Testing\BackgroundFixtureSeeder::class,     // Backgrounds
            Testing\FeatFixtureSeeder::class,           // Feats
            Testing\MonsterFixtureSeeder::class,        // Monsters
            Testing\OptionalFeatureFixtureSeeder::class,// Optional features
        ]);

        // Step 3: Index for Meilisearch
        $this->indexSearchableModels();
    }
}
```

### Base FixtureSeeder

```php
abstract class FixtureSeeder extends Seeder
{
    abstract protected function fixturePath(): string;
    abstract protected function model(): string;
    abstract protected function createFromFixture(array $item): void;

    public function run(): void
    {
        $data = json_decode(
            file_get_contents(base_path($this->fixturePath())),
            associative: true
        );

        foreach ($data as $item) {
            $this->createFromFixture($item);
        }
    }
}
```

## Extraction Strategy

### Artisan Command: `fixtures:extract`

```bash
php artisan fixtures:extract [--entity=spells] [--output=tests/fixtures]
```

**Logic:**
1. Analyze test files for entity references (grep for slugs, names)
2. Query referenced entities from production DB
3. Add edge cases for filter coverage:
   - One spell per level (0-9)
   - One spell per school (8 schools)
   - Spells with/without concentration, ritual
   - Monsters at various CR ranges
   - Items of each rarity/type
4. Export with relationships resolved to slugs
5. Pretty-print JSON for readability

### Edge Case Coverage

| Entity | Edge Cases to Include |
|--------|----------------------|
| Spells | Each level 0-9, each school, concentration/ritual variants |
| Monsters | CR 0, 1/8, 1/4, 1/2, 1-5, 10, 15, 20, 25, 30 |
| Items | Each rarity, each type, magic/mundane |
| Classes | All 13 base classes + 2-3 subclasses each |
| Races | Each size category, with/without subraces |
| Feats | With/without prerequisites, ASI options |

## Test Suite Changes

### phpunit.xml Updates

- Merge `Feature-Search-Imported` into `Feature-Search`
- Remove `search-imported` group
- All search tests use same seeder

### Files to Remove

```
tests/Support/SearchTestExtension.php
tests/Support/SearchTestSubscriber.php
tests/Concerns/UsesImportedTestData.php
```

### Test Updates

**Before:**
```php
#[Group('search-imported')]
class SpellSearchTest extends TestCase
{
    // No RefreshDatabase - relied on import:all
}
```

**After:**
```php
#[Group('feature-search')]
class SpellSearchTest extends TestCase
{
    use RefreshDatabase;

    // Deterministic fixture data
}
```

### Assertion Updates

With known fixture data, assertions become deterministic:

```php
#[Test]
public function it_filters_spells_by_level()
{
    $response = $this->getJson('/api/v1/spells?filter=level = 3');

    $response->assertOk();
    $response->assertJsonCount(5, 'data'); // Known count from fixtures
}
```

## Implementation Phases

### Phase 1: Build Extraction Tooling
- [ ] Create `php artisan fixtures:extract` command
- [ ] Implement test file analysis for entity references
- [ ] Build edge case detection logic
- [ ] Export JSON with relationship resolution

### Phase 2: Create Fixture Infrastructure
- [ ] Create `tests/fixtures/` directory structure
- [ ] Build `FixtureSeeder` base class
- [ ] Create `TestDatabaseSeeder` orchestrator
- [ ] Create entity-specific fixture seeders

### Phase 3: Extract Fixture Data
- [ ] Run extraction for each entity type
- [ ] Review and validate JSON fixtures
- [ ] Commit fixtures to repository

### Phase 4: Migrate Tests Incrementally
- [ ] Update one test file (SpellSearchTest) as proof of concept
- [ ] Add RefreshDatabase, update assertions
- [ ] Verify passes with fixture data
- [ ] Migrate remaining search tests

### Phase 5: Remove Legacy Infrastructure
- [ ] Delete SearchTestExtension, SearchTestSubscriber
- [ ] Remove UsesImportedTestData trait
- [ ] Update phpunit.xml suite definitions
- [ ] Remove search-imported group annotations

### Phase 6: Validate & Document
- [ ] Run full test suite
- [ ] Update CLAUDE.md test instructions
- [ ] Document fixture refresh process
- [ ] Update docs/PROJECT-STATUS.md

## Expected Outcomes

| Metric | Before | After |
|--------|--------|-------|
| Search test setup | ~180s (import:all) | ~10s (seeder) |
| Data predictability | Variable (XML changes) | Deterministic |
| New dev setup | Complex (import required) | Simple (just migrate) |
| Test isolation | Shared imported data | Fresh per-test data |
| CI/CD time | +180s first run | Consistent ~10s |

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Fixture data becomes stale | Artisan command allows re-extraction |
| Missing edge cases | Coverage analysis + manual review |
| Meilisearch index timing | Index after seeding in TestDatabaseSeeder |
| Large fixture files | Coverage-based extraction keeps size minimal |

## Success Criteria

1. All 205 tests pass with fixture data
2. No test requires `import:all` to run
3. `SearchTestExtension` and related files deleted
4. Test suite runs in <300s total (down from ~400s)
5. New developers can run tests without XML import
