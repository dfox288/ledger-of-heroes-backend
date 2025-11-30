# Race Speed and Sense Filtering Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add `fly_speed`, `swim_speed`, and darkvision filtering to the Race API, enabling frontend filtering by movement types and senses.

**Architecture:** Add database columns for alternate speeds (mirroring Monster model pattern), extract values from trait descriptions during import, and index in Meilisearch for filtering. Senses are already stored via relationships - just need indexing.

**Tech Stack:** Laravel 12.x, MySQL/SQLite, Meilisearch, PHPUnit 11

**GitHub Issue:** #26

---

## Summary of Changes

| Component | Change |
|-----------|--------|
| Migration | Add `fly_speed`, `swim_speed` nullable integer columns |
| Race Model | Add to `$fillable`, `$casts`, `toSearchableArray()`, `searchableOptions()` |
| RaceFactory | Add `fly_speed`, `swim_speed` states |
| RaceImporter | Add `extractSpeedsFromTraits()` method |
| RaceResource | Add `fly_speed`, `swim_speed` fields |
| Tests | Unit tests for model, importer extraction, API filtering |

---

## Task 1: Add Migration for Speed Columns

**Files:**
- Create: `database/migrations/2025_11_30_XXXXXX_add_speed_columns_to_races_table.php`

**Step 1: Create the migration**

```bash
docker compose exec php php artisan make:migration add_speed_columns_to_races_table
```

**Step 2: Edit migration with column definitions**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->unsignedSmallInteger('fly_speed')->nullable()->after('speed');
            $table->unsignedSmallInteger('swim_speed')->nullable()->after('fly_speed');
        });
    }

    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn(['fly_speed', 'swim_speed']);
        });
    }
};
```

**Step 3: Run migration**

```bash
docker compose exec php php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*add_speed_columns_to_races_table.php
git commit -m "feat: add fly_speed and swim_speed columns to races table"
```

---

## Task 2: Update Race Model - Fillable and Casts

**Files:**
- Modify: `app/Models/Race.php:18-30`

**Step 1: Write failing test**

Create test in `tests/Unit/Models/RaceTest.php` (add to existing file):

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_has_speed_columns_fillable(): void
{
    $race = new Race();

    $this->assertContains('fly_speed', $race->getFillable());
    $this->assertContains('swim_speed', $race->getFillable());
}

#[\PHPUnit\Framework\Attributes\Test]
public function it_casts_speed_columns_to_integer(): void
{
    $race = new Race();
    $casts = $race->getCasts();

    $this->assertEquals('integer', $casts['fly_speed']);
    $this->assertEquals('integer', $casts['swim_speed']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter="it_has_speed_columns_fillable|it_casts_speed_columns_to_integer" tests/Unit/Models/RaceTest.php
```

Expected: FAIL

**Step 3: Update Race model**

In `app/Models/Race.php`, update `$fillable` array (line ~18):

```php
protected $fillable = [
    'slug',
    'name',
    'size_id',
    'speed',
    'fly_speed',
    'swim_speed',
    'parent_race_id',
];
```

Update `$casts` array (line ~26):

```php
protected $casts = [
    'size_id' => 'integer',
    'speed' => 'integer',
    'fly_speed' => 'integer',
    'swim_speed' => 'integer',
    'parent_race_id' => 'integer',
];
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter="it_has_speed_columns_fillable|it_casts_speed_columns_to_integer" tests/Unit/Models/RaceTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Race.php tests/Unit/Models/RaceTest.php
git commit -m "feat: add fly_speed and swim_speed to Race model fillable and casts"
```

---

## Task 3: Update RaceFactory with Speed States

**Files:**
- Modify: `database/factories/RaceFactory.php`

**Step 1: Write failing test**

Add to `tests/Unit/Models/RaceTest.php`:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function factory_can_create_race_with_fly_speed(): void
{
    $race = Race::factory()->withFlySpeed(50)->create();

    $this->assertEquals(50, $race->fly_speed);
}

#[\PHPUnit\Framework\Attributes\Test]
public function factory_can_create_race_with_swim_speed(): void
{
    $race = Race::factory()->withSwimSpeed(30)->create();

    $this->assertEquals(30, $race->swim_speed);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter="factory_can_create_race_with_fly_speed|factory_can_create_race_with_swim_speed" tests/Unit/Models/RaceTest.php
```

Expected: FAIL with "Call to undefined method"

**Step 3: Add factory states**

In `database/factories/RaceFactory.php`, add after `definition()`:

```php
/**
 * Create a race with flying speed.
 */
public function withFlySpeed(int $speed = 50): static
{
    return $this->state(fn (array $attributes) => [
        'fly_speed' => $speed,
    ]);
}

/**
 * Create a race with swimming speed.
 */
public function withSwimSpeed(int $speed = 30): static
{
    return $this->state(fn (array $attributes) => [
        'swim_speed' => $speed,
    ]);
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter="factory_can_create_race_with_fly_speed|factory_can_create_race_with_swim_speed" tests/Unit/Models/RaceTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add database/factories/RaceFactory.php tests/Unit/Models/RaceTest.php
git commit -m "feat: add fly_speed and swim_speed factory states for Race"
```

---

## Task 4: Update Race toSearchableArray for Senses

**Files:**
- Modify: `app/Models/Race.php:132-164` (toSearchableArray method)
- Modify: `tests/Unit/Models/RaceSearchableTest.php`

**Step 1: Write failing test**

Add to `tests/Unit/Models/RaceSearchableTest.php`:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_indexes_darkvision_fields_in_searchable_array(): void
{
    $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
    $sense = \App\Models\Sense::firstOrCreate(['slug' => 'darkvision'], ['name' => 'Darkvision']);

    $race = Race::factory()->create(['size_id' => $size->id]);

    \App\Models\EntitySense::create([
        'reference_type' => Race::class,
        'reference_id' => $race->id,
        'sense_id' => $sense->id,
        'range_feet' => 60,
        'is_limited' => false,
    ]);

    $race->refresh();
    $searchable = $race->toSearchableArray();

    $this->assertTrue($searchable['has_darkvision']);
    $this->assertEquals(60, $searchable['darkvision_range']);
}

#[\PHPUnit\Framework\Attributes\Test]
public function it_indexes_speed_fields_in_searchable_array(): void
{
    $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

    $race = Race::factory()->create([
        'size_id' => $size->id,
        'fly_speed' => 50,
        'swim_speed' => 30,
    ]);

    $searchable = $race->toSearchableArray();

    $this->assertEquals(50, $searchable['fly_speed']);
    $this->assertEquals(30, $searchable['swim_speed']);
    $this->assertTrue($searchable['has_fly_speed']);
    $this->assertTrue($searchable['has_swim_speed']);
}

#[\PHPUnit\Framework\Attributes\Test]
public function it_indexes_false_for_missing_speeds(): void
{
    $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

    $race = Race::factory()->create([
        'size_id' => $size->id,
        'fly_speed' => null,
        'swim_speed' => null,
    ]);

    $searchable = $race->toSearchableArray();

    $this->assertNull($searchable['fly_speed']);
    $this->assertNull($searchable['swim_speed']);
    $this->assertFalse($searchable['has_fly_speed']);
    $this->assertFalse($searchable['has_swim_speed']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter="it_indexes_darkvision_fields|it_indexes_speed_fields|it_indexes_false_for_missing_speeds" tests/Unit/Models/RaceSearchableTest.php
```

Expected: FAIL

**Step 3: Update toSearchableArray in Race model**

Replace the `toSearchableArray()` method in `app/Models/Race.php`:

```php
public function toSearchableArray(): array
{
    // Load relationships if not already loaded
    $this->loadMissing(['tags', 'spells.spell', 'modifiers.abilityScore', 'senses.sense']);

    // Extract ability score bonuses from modifiers
    $abilityBonuses = $this->modifiers->where('modifier_category', 'ability_score');

    return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'size_name' => $this->size?->name,
        'size_code' => $this->size?->code,
        'speed' => $this->speed,
        // Alternate movement speeds
        'fly_speed' => $this->fly_speed,
        'swim_speed' => $this->swim_speed,
        'has_fly_speed' => $this->fly_speed !== null,
        'has_swim_speed' => $this->swim_speed !== null,
        'sources' => $this->sources->pluck('source.name')->unique()->values()->all(),
        'source_codes' => $this->sources->pluck('source.code')->unique()->values()->all(),
        'is_subrace' => $this->parent_race_id !== null,
        'parent_race_name' => $this->parent?->name,
        // Tag slugs for filtering (e.g., darkvision, fey_ancestry)
        'tag_slugs' => $this->tags->pluck('slug')->all(),
        // Phase 3: Spell filtering
        'spell_slugs' => $this->spells->pluck('spell.slug')->filter()->values()->all(),
        'has_innate_spells' => $this->spells->isNotEmpty(),
        // Phase 4: Ability score bonuses (cast to int for Meilisearch filtering)
        'ability_str_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'STR')?->value ?? 0),
        'ability_dex_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'DEX')?->value ?? 0),
        'ability_con_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'CON')?->value ?? 0),
        'ability_int_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'INT')?->value ?? 0),
        'ability_wis_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'WIS')?->value ?? 0),
        'ability_cha_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'CHA')?->value ?? 0),
        // Senses (darkvision range for filtering)
        'has_darkvision' => $this->senses->contains(fn ($s) => $s->sense?->slug === 'darkvision'),
        'darkvision_range' => $this->senses->firstWhere(fn ($s) => $s->sense?->slug === 'darkvision')?->range_feet,
    ];
}
```

Also update `searchableWith()` to include senses:

```php
public function searchableWith(): array
{
    return ['size', 'sources.source', 'parent', 'tags', 'spells.spell', 'modifiers.abilityScore', 'senses.sense'];
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter="it_indexes_darkvision_fields|it_indexes_speed_fields|it_indexes_false_for_missing_speeds" tests/Unit/Models/RaceSearchableTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Race.php tests/Unit/Models/RaceSearchableTest.php
git commit -m "feat: add senses and speed fields to Race toSearchableArray"
```

---

## Task 5: Update Race searchableOptions for Filtering

**Files:**
- Modify: `app/Models/Race.php:183-218` (searchableOptions method)

**Step 1: Write failing test**

Add to `tests/Unit/Models/RaceSearchableTest.php`:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_includes_speed_and_sense_fields_in_filterable_attributes(): void
{
    $race = new Race();
    $options = $race->searchableOptions();

    $filterable = $options['filterableAttributes'];

    $this->assertContains('fly_speed', $filterable);
    $this->assertContains('swim_speed', $filterable);
    $this->assertContains('has_fly_speed', $filterable);
    $this->assertContains('has_swim_speed', $filterable);
    $this->assertContains('has_darkvision', $filterable);
    $this->assertContains('darkvision_range', $filterable);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter="it_includes_speed_and_sense_fields_in_filterable_attributes" tests/Unit/Models/RaceSearchableTest.php
```

Expected: FAIL

**Step 3: Update searchableOptions**

In `app/Models/Race.php`, update `searchableOptions()`:

```php
public function searchableOptions(): array
{
    return [
        'filterableAttributes' => [
            'id',
            'slug',
            'size_name',
            'size_code',
            'speed',
            // Alternate movement speeds
            'fly_speed',
            'swim_speed',
            'has_fly_speed',
            'has_swim_speed',
            'source_codes',
            'is_subrace',
            'parent_race_name',
            'tag_slugs',
            // Phase 3: Spell filtering
            'spell_slugs',
            'has_innate_spells',
            // Phase 4: Ability score bonuses
            'ability_str_bonus',
            'ability_dex_bonus',
            'ability_con_bonus',
            'ability_int_bonus',
            'ability_wis_bonus',
            'ability_cha_bonus',
            // Senses
            'has_darkvision',
            'darkvision_range',
        ],
        'sortableAttributes' => [
            'name',
            'speed',
            'fly_speed',
            'swim_speed',
            'darkvision_range',
        ],
        'searchableAttributes' => [
            'name',
            'size_name',
            'parent_race_name',
            'sources',
        ],
    ];
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter="it_includes_speed_and_sense_fields_in_filterable_attributes" tests/Unit/Models/RaceSearchableTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Race.php tests/Unit/Models/RaceSearchableTest.php
git commit -m "feat: add speed and sense fields to Race searchableOptions"
```

---

## Task 6: Update RaceResource with Speed Fields

**Files:**
- Modify: `app/Http/Resources/RaceResource.php:17-37`

**Step 1: Write failing test**

Create `tests/Feature/Api/RaceResourceTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Http\Resources\RaceResource;
use App\Models\Race;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class RaceResourceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_speed_fields_in_resource(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $race = Race::factory()->create([
            'size_id' => $size->id,
            'fly_speed' => 50,
            'swim_speed' => 30,
        ]);

        $resource = (new RaceResource($race))->toArray(request());

        $this->assertArrayHasKey('fly_speed', $resource);
        $this->assertArrayHasKey('swim_speed', $resource);
        $this->assertEquals(50, $resource['fly_speed']);
        $this->assertEquals(30, $resource['swim_speed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_missing_speeds(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $race = Race::factory()->create([
            'size_id' => $size->id,
            'fly_speed' => null,
            'swim_speed' => null,
        ]);

        $resource = (new RaceResource($race))->toArray(request());

        $this->assertArrayHasKey('fly_speed', $resource);
        $this->assertArrayHasKey('swim_speed', $resource);
        $this->assertNull($resource['fly_speed']);
        $this->assertNull($resource['swim_speed']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test tests/Feature/Api/RaceResourceTest.php
```

Expected: FAIL

**Step 3: Update RaceResource**

In `app/Http/Resources/RaceResource.php`, add after `'speed' => $this->speed,`:

```php
'fly_speed' => $this->fly_speed,
'swim_speed' => $this->swim_speed,
```

Full updated `toArray` method:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'slug' => $this->slug,
        'name' => $this->name,
        'size' => new SizeResource($this->whenLoaded('size')),
        'speed' => $this->speed,
        'fly_speed' => $this->fly_speed,
        'swim_speed' => $this->swim_speed,
        'is_subrace' => $this->is_subrace,
        'traits' => TraitResource::collection($this->whenLoaded('traits')),
        'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
        'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
        'parent_race' => $this->when($this->parent_race_id, function () {
            return new RaceResource($this->whenLoaded('parent'));
        }),
        'subraces' => RaceResource::collection($this->whenLoaded('subraces')),
        'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
        'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),
        'conditions' => EntityConditionResource::collection($this->whenLoaded('conditions')),
        'spells' => EntitySpellResource::collection($this->whenLoaded('spells')),
        'senses' => EntitySenseResource::collection($this->whenLoaded('senses')),
        'tags' => TagResource::collection($this->whenLoaded('tags')),

        // === INHERITED DATA (subraces only) ===
        'inherited_data' => $this->when(
            $this->is_subrace && $this->relationLoaded('parent') && $this->parent,
            function () {
                $parent = $this->parent;

                return [
                    'traits' => $parent->relationLoaded('traits')
                        ? TraitResource::collection($parent->traits)
                        : null,
                    'modifiers' => $parent->relationLoaded('modifiers')
                        ? ModifierResource::collection($parent->modifiers)
                        : null,
                    'proficiencies' => $parent->relationLoaded('proficiencies')
                        ? ProficiencyResource::collection($parent->proficiencies)
                        : null,
                    'languages' => $parent->relationLoaded('languages')
                        ? EntityLanguageResource::collection($parent->languages)
                        : null,
                    'conditions' => $parent->relationLoaded('conditions')
                        ? EntityConditionResource::collection($parent->conditions)
                        : null,
                    'senses' => $parent->relationLoaded('senses')
                        ? EntitySenseResource::collection($parent->senses)
                        : null,
                ];
            }
        ),
    ];
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test tests/Feature/Api/RaceResourceTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Http/Resources/RaceResource.php tests/Feature/Api/RaceResourceTest.php
git commit -m "feat: add fly_speed and swim_speed to RaceResource"
```

---

## Task 7: Add Speed Extraction to RaceImporter

**Files:**
- Modify: `app/Services/Importers/RaceImporter.php`

**Step 1: Write failing test**

Add to `tests/Feature/Importers/RaceImporterTest.php`:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_extracts_fly_speed_from_flight_trait(): void
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Aarakocra</name>
        <size>M</size>
        <speed>25</speed>
        <trait>
            <name>Flight</name>
            <text>You have a flying speed of 50 feet. To use this speed, you can't be wearing medium or heavy armor.</text>
        </trait>
    </race>
</compendium>
XML;

    $parser = new \App\Services\Parsers\RaceXmlParser();
    $races = $parser->parse($xml);

    $importer = new \App\Services\Importers\RaceImporter();
    $importer->import($races[0]);

    $race = \App\Models\Race::where('name', 'Aarakocra')->first();

    $this->assertNotNull($race);
    $this->assertEquals(50, $race->fly_speed);
}

#[\PHPUnit\Framework\Attributes\Test]
public function it_extracts_swim_speed_from_swim_speed_trait(): void
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Triton</name>
        <size>M</size>
        <speed>30</speed>
        <trait>
            <name>Swim Speed</name>
            <text>You have a swimming speed of 30 feet.</text>
        </trait>
    </race>
</compendium>
XML;

    $parser = new \App\Services\Parsers\RaceXmlParser();
    $races = $parser->parse($xml);

    $importer = new \App\Services\Importers\RaceImporter();
    $importer->import($races[0]);

    $race = \App\Models\Race::where('name', 'Triton')->first();

    $this->assertNotNull($race);
    $this->assertEquals(30, $race->swim_speed);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter="it_extracts_fly_speed_from_flight_trait|it_extracts_swim_speed_from_swim_speed_trait" tests/Feature/Importers/RaceImporterTest.php
```

Expected: FAIL

**Step 3: Add extractSpeedsFromTraits method and update importEntity**

In `app/Services/Importers/RaceImporter.php`, add method after `extractSensesFromTraits()`:

```php
/**
 * Extract alternate movement speeds from race traits.
 *
 * Looks for traits like "Flight" or "Swim Speed" and extracts
 * the speed value from the description text.
 *
 * @param  array  $traits  Array of trait data from parser
 * @return array Array with 'fly_speed' and 'swim_speed' keys (nullable)
 */
private function extractSpeedsFromTraits(array $traits): array
{
    $speeds = [
        'fly_speed' => null,
        'swim_speed' => null,
    ];

    foreach ($traits as $trait) {
        $name = $trait['name'] ?? '';
        $description = $trait['description'] ?? '';

        // Check for Flight trait
        if (stripos($name, 'flight') !== false || stripos($name, 'flying') !== false) {
            if (preg_match('/flying speed of (\d+) feet/i', $description, $matches)) {
                $speeds['fly_speed'] = (int) $matches[1];
            }
        }

        // Check for Swim Speed trait
        if (stripos($name, 'swim') !== false) {
            if (preg_match('/swimming speed of (\d+) feet/i', $description, $matches)) {
                $speeds['swim_speed'] = (int) $matches[1];
            }
        }
    }

    return $speeds;
}
```

Update `importEntity()` method to extract and save speeds. Find this section (around line 60):

```php
// Create or update race using slug as unique key
$race = Race::updateOrCreate(
    ['slug' => $raceData['slug']],
    [
        'name' => $raceData['name'],
        'parent_race_id' => $raceData['parent_race_id'] ?? null,
        'size_id' => $size->id,
        'speed' => $raceData['speed'],
        'description' => $raceData['description'] ?? '',
    ]
);
```

Replace with:

```php
// Extract alternate movement speeds from traits
$extractedSpeeds = $this->extractSpeedsFromTraits($raceData['traits'] ?? []);

// Create or update race using slug as unique key
$race = Race::updateOrCreate(
    ['slug' => $raceData['slug']],
    [
        'name' => $raceData['name'],
        'parent_race_id' => $raceData['parent_race_id'] ?? null,
        'size_id' => $size->id,
        'speed' => $raceData['speed'],
        'fly_speed' => $extractedSpeeds['fly_speed'],
        'swim_speed' => $extractedSpeeds['swim_speed'],
        'description' => $raceData['description'] ?? '',
    ]
);
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter="it_extracts_fly_speed_from_flight_trait|it_extracts_swim_speed_from_swim_speed_trait" tests/Feature/Importers/RaceImporterTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Importers/RaceImporter.php tests/Feature/Importers/RaceImporterTest.php
git commit -m "feat: extract fly_speed and swim_speed from race traits during import"
```

---

## Task 8: Run Full Test Suite and Verify

**Step 1: Run Unit-DB suite**

```bash
docker compose exec php php artisan test --testsuite=Unit-DB
```

Expected: All tests pass

**Step 2: Run Feature-DB suite**

```bash
docker compose exec php php artisan test --testsuite=Feature-DB
```

Expected: All tests pass

**Step 3: Format with Pint**

```bash
docker compose exec php ./vendor/bin/pint
```

**Step 4: Commit any formatting changes**

```bash
git add -A
git commit -m "style: format code with Pint"
```

---

## Task 9: Re-import Races and Update Index

**Step 1: Re-import races to populate new columns**

```bash
docker compose exec php php artisan import:races
```

**Step 2: Sync Meilisearch index settings**

```bash
docker compose exec php php artisan scout:sync-index-settings
```

**Step 3: Re-index races**

```bash
docker compose exec php php artisan scout:import "App\Models\Race"
```

**Step 4: Verify data in database**

```bash
docker compose exec php php artisan tinker --execute="
\$aarakocra = \App\Models\Race::where('name', 'Aarakocra')->first();
echo 'Aarakocra fly_speed: ' . \$aarakocra?->fly_speed . PHP_EOL;

\$triton = \App\Models\Race::where('name', 'Triton')->first();
echo 'Triton swim_speed: ' . \$triton?->swim_speed . PHP_EOL;

\$dwarf = \App\Models\Race::with('senses.sense')->where('name', 'Dwarf')->first();
echo 'Dwarf darkvision: ' . \$dwarf?->senses->first()?->range_feet . ' ft' . PHP_EOL;
"
```

Expected output:
```
Aarakocra fly_speed: 50
Triton swim_speed: 30
Dwarf darkvision: 60 ft
```

---

## Task 10: Update Test Fixtures and Re-import

**Step 1: Re-import test database for search tests**

```bash
docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:all --env=testing
```

**Step 2: Run Feature-Search suite**

```bash
docker compose exec php php artisan test --testsuite=Feature-Search
```

Expected: All tests pass

---

## Task 11: Update CHANGELOG and Create Final Commit

**Step 1: Update CHANGELOG.md**

Add under `[Unreleased]`:

```markdown
### Added
- Race API: `fly_speed` and `swim_speed` fields for alternate movement types
- Race API: `darkvision_range` now filterable via Meilisearch
- Race filtering: `has_fly_speed`, `has_swim_speed`, `has_darkvision` boolean filters
- Race sorting: Can now sort by `fly_speed`, `swim_speed`, `darkvision_range`
```

**Step 2: Final commit**

```bash
git add CHANGELOG.md
git commit -m "docs: update CHANGELOG for race speed and sense filtering"
```

**Step 3: Push to remote**

```bash
git push
```

---

## Task 12: Update GitHub Issue

**Step 1: Add implementation comment to issue**

```bash
gh issue comment 26 --repo dfox288/dnd-rulebook-project --body "## Implementation Complete

### Added Fields
- \`fly_speed\` - Integer, nullable (e.g., Aarakocra: 50)
- \`swim_speed\` - Integer, nullable (e.g., Triton: 30)
- \`darkvision_range\` - Now indexed for filtering

### New Filters Available
\`\`\`
filter=has_fly_speed = true
filter=fly_speed >= 30
filter=has_swim_speed = true
filter=swim_speed >= 30
filter=has_darkvision = true
filter=darkvision_range >= 60
\`\`\`

### API Response
\`\`\`json
{
  \"id\": 1,
  \"name\": \"Aarakocra\",
  \"speed\": 25,
  \"fly_speed\": 50,
  \"swim_speed\": null,
  \"senses\": [...]
}
\`\`\`"
```

**Step 2: Close the issue**

```bash
gh issue close 26 --repo dfox288/dnd-rulebook-project --comment "Implemented in main branch. All fields now available for filtering."
```

---

## Verification Checklist

- [ ] Migration creates `fly_speed` and `swim_speed` columns
- [ ] Race model has fields in `$fillable` and `$casts`
- [ ] RaceFactory has `withFlySpeed()` and `withSwimSpeed()` states
- [ ] `toSearchableArray()` includes speed and sense fields
- [ ] `searchableOptions()` has all new filterable attributes
- [ ] RaceResource includes `fly_speed` and `swim_speed`
- [ ] RaceImporter extracts speeds from trait descriptions
- [ ] Unit-DB tests pass
- [ ] Feature-DB tests pass
- [ ] Feature-Search tests pass (after re-import)
- [ ] Pint formatting applied
- [ ] CHANGELOG updated
- [ ] GitHub issue #26 closed
