# TODO: Refactor MeilisearchIndexConfigurator to Use Model Configuration

**Priority:** Medium
**Estimated Effort:** 1-2 hours
**Impact:** High - Single source of truth for search configuration, eliminates duplication

---

## Problem

Currently, we have **duplicate configuration** for Meilisearch indexes:

1. **Model's `searchableOptions()` method** - Defines what SHOULD be filterable/sortable/searchable
2. **MeilisearchIndexConfigurator service** - Hardcoded arrays that may drift out of sync

**Example of duplication:**

**Model (`Monster.php`):**
```php
public function searchableOptions(): array
{
    return [
        'filterableAttributes' => ['id', 'slug', 'type', 'strength', 'speed_fly', ...],
        'sortableAttributes' => ['name', 'challenge_rating', 'strength', ...],
        'searchableAttributes' => ['name', 'description', ...],
    ];
}
```

**Configurator (`MeilisearchIndexConfigurator.php`):**
```php
public function configureMonstersIndex(): void
{
    // DUPLICATE! Must manually keep in sync with model
    $index->updateFilterableAttributes(['id', 'type', 'strength', 'speed_fly', ...]);
    $index->updateSortableAttributes(['name', 'challenge_rating', ...]);
    $index->updateSearchableAttributes(['name', 'description', ...]);
}
```

**This causes:**
- ❌ Configuration drift (model says one thing, configurator does another)
- ❌ Maintenance burden (update in two places)
- ❌ Human error (forgetting to update configurator when model changes)
- ❌ Debugging confusion (which is the source of truth?)

---

## Proposed Solution

**Refactor `MeilisearchIndexConfigurator` to read configuration from models directly.**

### New Generic Configuration Method

```php
class MeilisearchIndexConfigurator
{
    /**
     * Configure index using model's searchableOptions()
     *
     * @param class-string $modelClass Fully qualified model class name
     */
    private function configureIndexFromModel(string $modelClass): void
    {
        $model = new $modelClass;
        $indexName = $model->searchableAs();
        $index = $this->client->index($indexName);

        // Get configuration from model's searchableOptions()
        $options = $model->searchableOptions();

        // Apply searchable attributes
        if (isset($options['searchableAttributes'])) {
            $index->updateSearchableAttributes($options['searchableAttributes']);
        }

        // Apply filterable attributes
        if (isset($options['filterableAttributes'])) {
            $index->updateFilterableAttributes($options['filterableAttributes']);
        }

        // Apply sortable attributes
        if (isset($options['sortableAttributes'])) {
            $index->updateSortableAttributes($options['sortableAttributes']);
        }
    }
}
```

### Simplified Index Configuration Methods

**Before (hardcoded):**
```php
public function configureSpellsIndex(): void
{
    $indexName = (new Spell)->searchableAs();
    $index = $this->client->index($indexName);

    $index->updateSearchableAttributes([
        'name',
        'description',
        'at_higher_levels',
        'school_name',
        'sources',
        'classes',
    ]);

    $index->updateFilterableAttributes([
        'id',
        'level',
        'school_code',
        'school_name',
        'concentration',
        'ritual',
        'source_codes',
        'class_slugs',
        'tag_slugs',
    ]);

    $index->updateSortableAttributes([
        'name',
        'level',
    ]);
}
```

**After (reads from model):**
```php
public function configureSpellsIndex(): void
{
    $this->configureIndexFromModel(Spell::class);
}
```

---

## Implementation Steps

### 1. Add Generic Configuration Method (30 min)

**File:** `app/Services/Search/MeilisearchIndexConfigurator.php`

```php
/**
 * Configure a Meilisearch index using the model's searchableOptions()
 *
 * This eliminates duplication by reading configuration directly from the model.
 *
 * @param class-string $modelClass Fully qualified model class name (e.g., Spell::class)
 * @throws \Exception If model doesn't have searchableOptions() method
 */
private function configureIndexFromModel(string $modelClass): void
{
    // Instantiate model
    $model = new $modelClass;

    // Verify model has searchableOptions() method
    if (!method_exists($model, 'searchableOptions')) {
        throw new \Exception("Model {$modelClass} must have searchableOptions() method");
    }

    // Get index name (respects Scout prefix for testing)
    $indexName = $model->searchableAs();
    $index = $this->client->index($indexName);

    // Get configuration from model
    $options = $model->searchableOptions();

    // Apply searchable attributes (fields to search in)
    if (isset($options['searchableAttributes']) && is_array($options['searchableAttributes'])) {
        $index->updateSearchableAttributes($options['searchableAttributes']);
    }

    // Apply filterable attributes (fields that can be filtered)
    if (isset($options['filterableAttributes']) && is_array($options['filterableAttributes'])) {
        $index->updateFilterableAttributes($options['filterableAttributes']);
    }

    // Apply sortable attributes (fields that can be sorted)
    if (isset($options['sortableAttributes']) && is_array($options['sortableAttributes'])) {
        $index->updateSortableAttributes($options['sortableAttributes']);
    }
}
```

### 2. Refactor All Index Configuration Methods (30 min)

Replace hardcoded configuration with model reads:

```php
public function configureSpellsIndex(): void
{
    $this->configureIndexFromModel(Spell::class);
}

public function configureItemsIndex(): void
{
    $this->configureIndexFromModel(Item::class);
}

public function configureMonstersIndex(): void
{
    $this->configureIndexFromModel(Monster::class);
}

public function configureRacesIndex(): void
{
    $this->configureIndexFromModel(Race::class);
}

public function configureClassesIndex(): void
{
    $this->configureIndexFromModel(CharacterClass::class);
}

public function configureBackgroundsIndex(): void
{
    $this->configureIndexFromModel(Background::class);
}

public function configureFeatsIndex(): void
{
    $this->configureIndexFromModel(Feat::class);
}
```

### 3. Verify All Models Have searchableOptions() (10 min)

Check that all 7 entity models define `searchableOptions()`:

- ✅ Spell - has searchableOptions()
- ✅ Monster - has searchableOptions()
- ✅ Item - has searchableOptions()
- ✅ CharacterClass - has searchableOptions()
- ✅ Race - has searchableOptions()
- ✅ Background - has searchableOptions()
- ✅ Feat - has searchableOptions()

All models already have this method!

### 4. Run Tests (10 min)

```bash
# Configure indexes using new method
docker compose exec php php artisan search:configure-indexes

# Re-import data
docker compose exec php php artisan scout:import "App\Models\Spell"
docker compose exec php php artisan scout:import "App\Models\Monster"
# ... etc

# Run full test suite
docker compose exec php php artisan test
```

### 5. Add Tests (30 min)

**New test file:** `tests/Unit/Services/MeilisearchIndexConfiguratorTest.php`

```php
class MeilisearchIndexConfiguratorTest extends TestCase
{
    #[Test]
    public function it_configures_index_from_model_searchable_options()
    {
        $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
        $configurator = new MeilisearchIndexConfigurator($client);

        // Configure spell index
        $configurator->configureSpellsIndex();

        // Get index settings
        $index = $client->index((new Spell)->searchableAs());
        $settings = $index->getFilterableAttributes();

        // Verify settings match model's searchableOptions()
        $modelOptions = (new Spell)->searchableOptions();
        $this->assertEquals($modelOptions['filterableAttributes'], $settings);
    }

    #[Test]
    public function it_throws_exception_for_model_without_searchable_options()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('must have searchableOptions() method');

        $configurator = new MeilisearchIndexConfigurator($this->client);
        $configurator->configureIndexFromModel(User::class); // Doesn't have searchableOptions()
    }
}
```

---

## Benefits

### ✅ Single Source of Truth
- Model defines configuration once
- Configurator reads from model
- No duplication, no drift

### ✅ Easier Maintenance
- Add new attribute to model's `searchableOptions()` = done!
- No need to update configurator
- Reduces human error

### ✅ Better Discoverability
- Developers know to look at model for configuration
- Clear that `searchableOptions()` drives Meilisearch settings

### ✅ Reduced Code
- 7 methods become 7 one-liners
- ~200 lines of hardcoded arrays eliminated

---

## Before/After Comparison

### Before (Hardcoded - 40 lines per entity)
```php
public function configureMonstersIndex(): void
{
    $indexName = (new Monster)->searchableAs();
    $index = $this->client->index($indexName);

    $index->updateSearchableAttributes([
        'name',
        'description',
        'type',
        'size_name',
        'sources',
    ]);

    $index->updateFilterableAttributes([
        'id',
        'slug',
        'type',
        'size_code',
        'size_name',
        'alignment',
        'armor_class',
        'armor_type',
        'hit_points_average',
        'challenge_rating',
        'experience_points',
        'source_codes',
        'spell_slugs',
        'tag_slugs',
        'speed_walk',
        'speed_fly',
        // ... 20 more lines
    ]);

    $index->updateSortableAttributes([
        'name',
        'challenge_rating',
        'armor_class',
        // ... 10 more lines
    ]);
}
```

### After (Reads from model - 1 line!)
```php
public function configureMonstersIndex(): void
{
    $this->configureIndexFromModel(Monster::class);
}
```

---

## Testing Checklist

- [ ] Generic `configureIndexFromModel()` method created
- [ ] All 7 index configuration methods refactored
- [ ] All models have `searchableOptions()` verified
- [ ] `search:configure-indexes` command works
- [ ] All indexes configured correctly (check Meilisearch settings)
- [ ] All tests pass
- [ ] New unit tests for configurator added
- [ ] Documentation updated

---

## Files to Modify

1. ✅ `app/Services/Search/MeilisearchIndexConfigurator.php` - Add generic method, refactor all index methods
2. ✅ `tests/Unit/Services/MeilisearchIndexConfiguratorTest.php` - NEW file with tests

---

## Estimated Timeline

| Task | Time |
|------|------|
| Add generic configuration method | 30 min |
| Refactor 7 index methods | 30 min |
| Verify model methods | 10 min |
| Run tests & verify | 10 min |
| Add unit tests | 30 min |
| **Total** | **~2 hours** |

---

## Additional Future Enhancement

Once this is done, consider making it even more generic:

```php
public function configureAllIndexes(): void
{
    $models = [
        Spell::class,
        Monster::class,
        Item::class,
        CharacterClass::class,
        Race::class,
        Background::class,
        Feat::class,
    ];

    foreach ($models as $modelClass) {
        $this->info("Configuring {$modelClass}...");
        $this->configureIndexFromModel($modelClass);
    }
}
```

Then the command handle() method becomes:
```php
public function handle(): int
{
    $configurator->configureAllIndexes();
    return Command::SUCCESS;
}
```

---

**Created:** 2025-11-24
**Related to:** Phase 1 Meilisearch improvements - Eliminates configuration duplication

🤖 Generated with [Claude Code](https://claude.com/claude-code)
