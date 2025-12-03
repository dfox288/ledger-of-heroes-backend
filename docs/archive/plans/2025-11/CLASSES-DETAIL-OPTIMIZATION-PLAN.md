# Implementation Plan: Classes Detail Page Optimization

**Date:** 2025-11-26
**Status:** Ready for Execution
**Runner:** Docker Compose (not Sail)
**Branch:** `main` (no worktrees)
**Estimated Effort:** ~11 hours

---

## Overview

Enhance the `/api/v1/classes/{slug}` endpoint with pre-computed, display-ready data to eliminate frontend calculation and transformation logic. Also add a new `/api/v1/classes/{slug}/progression` endpoint for lazy-loading progression tables.

### Goals
1. Pre-compute D&D 5e formulas (proficiency bonus, HP averages)
2. Resolve subclass inheritance server-side (`effective_data`)
3. Provide section counts for lazy-loading accordions
4. Generate complete progression tables with interpolated data
5. Update API documentation (Scramble PHPDoc)

---

## Phase 1: Model Accessors (Tasks 1-3)

### Task 1: Add `hit_points` Accessor to CharacterClass

**File:** `app/Models/CharacterClass.php`

Add accessor that pre-computes D&D 5e hit point calculations:

```php
/**
 * Get pre-computed hit points data for display.
 *
 * @return array{
 *   hit_die: string,
 *   hit_die_numeric: int,
 *   first_level: array{value: int, description: string},
 *   higher_levels: array{roll: string, average: int, description: string}
 * }|null
 */
public function getHitPointsAttribute(): ?array
{
    if (!$this->hit_die) {
        return null;
    }

    $average = (int) floor($this->hit_die / 2) + 1;
    $className = strtolower($this->name);

    return [
        'hit_die' => "d{$this->hit_die}",
        'hit_die_numeric' => $this->hit_die,
        'first_level' => [
            'value' => $this->hit_die,
            'description' => "{$this->hit_die} + your Constitution modifier",
        ],
        'higher_levels' => [
            'roll' => "1d{$this->hit_die}",
            'average' => $average,
            'description' => "1d{$this->hit_die} (or {$average}) + your Constitution modifier per {$className} level after 1st",
        ],
    ];
}
```

**Test File:** `tests/Feature/Api/ClassDetailOptimizationTest.php`

```php
#[Test]
public function it_returns_hit_points_accessor_for_base_class(): void
{
    $class = CharacterClass::factory()->create([
        'name' => 'Fighter',
        'hit_die' => 10,
    ]);

    $hitPoints = $class->hit_points;

    $this->assertNotNull($hitPoints);
    $this->assertEquals('d10', $hitPoints['hit_die']);
    $this->assertEquals(10, $hitPoints['hit_die_numeric']);
    $this->assertEquals(10, $hitPoints['first_level']['value']);
    $this->assertEquals(6, $hitPoints['higher_levels']['average']); // floor(10/2) + 1 = 6
    $this->assertStringContainsString('fighter', $hitPoints['higher_levels']['description']);
}

#[Test]
public function it_returns_null_hit_points_for_subclass_without_hit_die(): void
{
    $parent = CharacterClass::factory()->create(['hit_die' => 10]);
    $subclass = CharacterClass::factory()->create([
        'parent_class_id' => $parent->id,
        'hit_die' => null,
    ]);

    $this->assertNull($subclass->hit_points);
}
```

**Verification:**
```bash
docker compose exec php php artisan test --filter=hit_points
```

---

### Task 2: Add `spell_slot_summary` Accessor to CharacterClass

**File:** `app/Models/CharacterClass.php`

Add accessor that summarizes spellcasting capabilities:

```php
/**
 * Get spell slot summary for display optimization.
 *
 * Tells frontend which spell slot columns to render without scanning all rows.
 *
 * @return array{
 *   has_spell_slots: bool,
 *   max_spell_level: int|null,
 *   available_levels: array<int>,
 *   has_cantrips: bool,
 *   caster_type: string|null
 * }|null
 */
public function getSpellSlotSummaryAttribute(): ?array
{
    // Must have level progression loaded
    if (!$this->relationLoaded('levelProgression')) {
        return null;
    }

    $progression = $this->levelProgression;
    if ($progression->isEmpty()) {
        return null;
    }

    // Determine max spell level with slots
    $maxLevel = 0;
    for ($i = 1; $i <= 9; $i++) {
        $column = "spell_slots_{$i}";
        if ($progression->max($column) > 0) {
            $maxLevel = $i;
        }
    }

    // Determine caster type based on max spell level
    $casterType = match ($maxLevel) {
        9 => 'full',      // Wizard, Cleric, etc.
        5 => 'half',      // Paladin, Ranger
        4 => 'third',     // Eldritch Knight, Arcane Trickster
        0 => null,        // Non-caster
        default => 'other',
    };

    return [
        'has_spell_slots' => $maxLevel > 0,
        'max_spell_level' => $maxLevel > 0 ? $maxLevel : null,
        'available_levels' => $maxLevel > 0 ? range(1, $maxLevel) : [],
        'has_cantrips' => ($progression->max('cantrips_known') ?? 0) > 0,
        'caster_type' => $casterType,
    ];
}
```

**Test additions to** `tests/Feature/Api/ClassDetailOptimizationTest.php`:

```php
#[Test]
public function it_returns_spell_slot_summary_for_full_caster(): void
{
    $class = CharacterClass::factory()->create(['name' => 'Wizard']);

    // Create level progression with 9th level spells
    for ($level = 1; $level <= 20; $level++) {
        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => $level,
            'cantrips_known' => min(3 + floor($level / 4), 5),
            'spell_slots_1' => $level >= 1 ? 4 : 0,
            'spell_slots_9' => $level >= 17 ? 1 : 0,
        ]);
    }

    $class->load('levelProgression');
    $summary = $class->spell_slot_summary;

    $this->assertTrue($summary['has_spell_slots']);
    $this->assertEquals(9, $summary['max_spell_level']);
    $this->assertEquals(range(1, 9), $summary['available_levels']);
    $this->assertTrue($summary['has_cantrips']);
    $this->assertEquals('full', $summary['caster_type']);
}

#[Test]
public function it_returns_spell_slot_summary_for_non_caster(): void
{
    $class = CharacterClass::factory()->create(['name' => 'Fighter']);

    // Create level progression without spell slots
    for ($level = 1; $level <= 20; $level++) {
        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => $level,
            'cantrips_known' => 0,
            'spell_slots_1' => 0,
        ]);
    }

    $class->load('levelProgression');
    $summary = $class->spell_slot_summary;

    $this->assertFalse($summary['has_spell_slots']);
    $this->assertNull($summary['max_spell_level']);
    $this->assertEmpty($summary['available_levels']);
    $this->assertFalse($summary['has_cantrips']);
    $this->assertNull($summary['caster_type']);
}

#[Test]
public function it_returns_null_spell_slot_summary_when_progression_not_loaded(): void
{
    $class = CharacterClass::factory()->create();

    // Don't load levelProgression
    $this->assertNull($class->spell_slot_summary);
}
```

**Verification:**
```bash
docker compose exec php php artisan test --filter=spell_slot_summary
```

---

### Task 3: Add `proficiency_bonus_by_level` Static Method

**File:** `app/Models/CharacterClass.php`

Add static helper for D&D 5e proficiency bonus calculation:

```php
/**
 * Calculate proficiency bonus for a given level.
 *
 * D&D 5e formula: floor((level - 1) / 4) + 2
 * Level 1-4: +2, Level 5-8: +3, Level 9-12: +4, Level 13-16: +5, Level 17-20: +6
 */
public static function proficiencyBonusForLevel(int $level): int
{
    return (int) floor(($level - 1) / 4) + 2;
}

/**
 * Get formatted proficiency bonus string.
 */
public static function formattedProficiencyBonus(int $level): string
{
    return '+' . self::proficiencyBonusForLevel($level);
}
```

**Test:**

```php
#[Test]
public function it_calculates_proficiency_bonus_correctly(): void
{
    // Level 1-4: +2
    $this->assertEquals(2, CharacterClass::proficiencyBonusForLevel(1));
    $this->assertEquals(2, CharacterClass::proficiencyBonusForLevel(4));

    // Level 5-8: +3
    $this->assertEquals(3, CharacterClass::proficiencyBonusForLevel(5));
    $this->assertEquals(3, CharacterClass::proficiencyBonusForLevel(8));

    // Level 9-12: +4
    $this->assertEquals(4, CharacterClass::proficiencyBonusForLevel(9));
    $this->assertEquals(4, CharacterClass::proficiencyBonusForLevel(12));

    // Level 13-16: +5
    $this->assertEquals(5, CharacterClass::proficiencyBonusForLevel(13));
    $this->assertEquals(5, CharacterClass::proficiencyBonusForLevel(16));

    // Level 17-20: +6
    $this->assertEquals(6, CharacterClass::proficiencyBonusForLevel(17));
    $this->assertEquals(6, CharacterClass::proficiencyBonusForLevel(20));
}

#[Test]
public function it_formats_proficiency_bonus_with_plus_sign(): void
{
    $this->assertEquals('+2', CharacterClass::formattedProficiencyBonus(1));
    $this->assertEquals('+6', CharacterClass::formattedProficiencyBonus(20));
}
```

---

## Phase 2: Progression Table Service (Tasks 4-5)

### Task 4: Create ClassProgressionTableGenerator Service

**File:** `app/Services/ClassProgressionTableGenerator.php`

```php
<?php

namespace App\Services;

use App\Models\CharacterClass;
use Illuminate\Support\Collection;

final class ClassProgressionTableGenerator
{
    /**
     * Generate a complete progression table for a class.
     *
     * @return array{
     *   columns: array<array{key: string, label: string, type: string}>,
     *   rows: array<array<string, mixed>>
     * }
     */
    public function generate(CharacterClass $class): array
    {
        // Get the effective class for progression (parent for subclasses)
        $progressionClass = $class->is_base_class ? $class : $class->parentClass;

        if (!$progressionClass) {
            return ['columns' => [], 'rows' => []];
        }

        // Ensure relationships are loaded
        $progressionClass->loadMissing(['levelProgression', 'counters', 'features']);

        $columns = $this->buildColumns($progressionClass);
        $rows = $this->buildRows($progressionClass, $columns);

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * Build dynamic columns based on class data.
     */
    private function buildColumns(CharacterClass $class): array
    {
        $columns = [
            ['key' => 'level', 'label' => 'Level', 'type' => 'integer'],
            ['key' => 'proficiency_bonus', 'label' => 'Proficiency Bonus', 'type' => 'bonus'],
            ['key' => 'features', 'label' => 'Features', 'type' => 'string'],
        ];

        // Add counter columns (Sneak Attack, Ki Points, Rage Damage, etc.)
        $counters = $class->counters->pluck('counter_name')->unique();
        foreach ($counters as $counterName) {
            $columns[] = [
                'key' => $this->slugify($counterName),
                'label' => $counterName,
                'type' => $this->getCounterType($counterName),
            ];
        }

        // Add spell slot columns if applicable
        $progression = $class->levelProgression;
        if ($progression->isNotEmpty()) {
            // Check for cantrips
            if ($progression->max('cantrips_known') > 0) {
                $columns[] = ['key' => 'cantrips_known', 'label' => 'Cantrips Known', 'type' => 'integer'];
            }

            // Check each spell slot level
            for ($i = 1; $i <= 9; $i++) {
                $column = "spell_slots_{$i}";
                if ($progression->max($column) > 0) {
                    $columns[] = [
                        'key' => $column,
                        'label' => $this->ordinal($i) . ' Level Slots',
                        'type' => 'integer',
                    ];
                }
            }
        }

        return $columns;
    }

    /**
     * Build rows for levels 1-20.
     */
    private function buildRows(CharacterClass $class, array $columns): array
    {
        $rows = [];
        $features = $class->features->groupBy('level');
        $counters = $this->buildCounterLookup($class->counters);
        $progression = $class->levelProgression->keyBy('level');

        for ($level = 1; $level <= 20; $level++) {
            $row = [
                'level' => $level,
                'proficiency_bonus' => CharacterClass::formattedProficiencyBonus($level),
                'features' => $this->getFeaturesForLevel($features, $level),
            ];

            // Add counter values (interpolated)
            foreach ($counters as $counterKey => $counterData) {
                $row[$counterKey] = $this->getCounterValue($counterData, $level);
            }

            // Add spell slots from progression
            $prog = $progression->get($level);
            if ($prog) {
                foreach ($columns as $col) {
                    if (str_starts_with($col['key'], 'spell_slots_') || $col['key'] === 'cantrips_known') {
                        $row[$col['key']] = $prog->{$col['key']} ?? 0;
                    }
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Get features for a specific level as comma-separated string.
     */
    private function getFeaturesForLevel(Collection $features, int $level): string
    {
        $levelFeatures = $features->get($level, collect());
        return $levelFeatures->pluck('feature_name')->join(', ') ?: '—';
    }

    /**
     * Build lookup table for counter values by level.
     */
    private function buildCounterLookup(Collection $counters): array
    {
        $lookup = [];

        foreach ($counters->groupBy('counter_name') as $name => $values) {
            $key = $this->slugify($name);
            $lookup[$key] = [
                'name' => $name,
                'values' => $values->pluck('counter_value', 'level')->toArray(),
            ];
        }

        return $lookup;
    }

    /**
     * Get interpolated counter value for a level.
     *
     * Counters are often sparse (only defined at certain levels).
     * We find the most recent defined value at or before the given level.
     */
    private function getCounterValue(array $counterData, int $level): ?string
    {
        $values = $counterData['values'];
        $name = $counterData['name'];

        // Find the most recent value at or before this level
        $value = null;
        for ($l = $level; $l >= 1; $l--) {
            if (isset($values[$l])) {
                $value = $values[$l];
                break;
            }
        }

        if ($value === null) {
            return '—';
        }

        // Format based on counter type
        return $this->formatCounterValue($name, $value);
    }

    /**
     * Format counter value based on counter name.
     */
    private function formatCounterValue(string $name, int|string $value): string
    {
        // Dice-based counters
        if (str_contains(strtolower($name), 'sneak attack')) {
            return "{$value}d6";
        }
        if (str_contains(strtolower($name), 'martial arts')) {
            return "1d{$value}";
        }

        return (string) $value;
    }

    /**
     * Get counter column type.
     */
    private function getCounterType(string $name): string
    {
        if (str_contains(strtolower($name), 'sneak attack') ||
            str_contains(strtolower($name), 'martial arts')) {
            return 'dice';
        }
        return 'integer';
    }

    /**
     * Convert string to slug format.
     */
    private function slugify(string $name): string
    {
        return strtolower(str_replace([' ', "'"], ['_', ''], $name));
    }

    /**
     * Get ordinal suffix for a number.
     */
    private function ordinal(int $number): string
    {
        $suffixes = ['th', 'st', 'nd', 'rd'];
        $mod = $number % 100;

        return $number . ($suffixes[($mod - 20) % 10] ?? $suffixes[$mod] ?? $suffixes[0]);
    }
}
```

**Test File:** `tests/Unit/Services/ClassProgressionTableGeneratorTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use App\Services\ClassProgressionTableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassProgressionTableGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private ClassProgressionTableGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ClassProgressionTableGenerator();
    }

    #[Test]
    public function it_generates_basic_progression_table(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
        ]);

        $result = $this->generator->generate($class);

        $this->assertArrayHasKey('columns', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(20, $result['rows']);

        // Check level 1 row
        $row1 = $result['rows'][0];
        $this->assertEquals(1, $row1['level']);
        $this->assertEquals('+2', $row1['proficiency_bonus']);
        $this->assertStringContainsString('Fighting Style', $row1['features']);
        $this->assertStringContainsString('Second Wind', $row1['features']);
    }

    #[Test]
    public function it_includes_counter_columns_with_interpolation(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Rogue']);

        // Sneak Attack: 1d6 at 1, 2d6 at 3, 3d6 at 5, etc.
        ClassCounter::factory()->create(['class_id' => $class->id, 'counter_name' => 'Sneak Attack', 'level' => 1, 'counter_value' => 1]);
        ClassCounter::factory()->create(['class_id' => $class->id, 'counter_name' => 'Sneak Attack', 'level' => 3, 'counter_value' => 2]);
        ClassCounter::factory()->create(['class_id' => $class->id, 'counter_name' => 'Sneak Attack', 'level' => 5, 'counter_value' => 3]);

        $result = $this->generator->generate($class);

        // Check that counter column exists
        $counterColumns = array_filter($result['columns'], fn($c) => $c['key'] === 'sneak_attack');
        $this->assertNotEmpty($counterColumns);

        // Check interpolation: level 2 should have 1d6 (from level 1)
        $row2 = $result['rows'][1];
        $this->assertEquals('1d6', $row2['sneak_attack']);

        // Level 3 should have 2d6
        $row3 = $result['rows'][2];
        $this->assertEquals('2d6', $row3['sneak_attack']);

        // Level 4 should still have 2d6 (interpolated from level 3)
        $row4 = $result['rows'][3];
        $this->assertEquals('2d6', $row4['sneak_attack']);
    }

    #[Test]
    public function it_includes_spell_slot_columns_for_casters(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);

        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'cantrips_known' => 3,
            'spell_slots_1' => 2,
            'spell_slots_2' => 0,
        ]);

        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => 3,
            'cantrips_known' => 3,
            'spell_slots_1' => 4,
            'spell_slots_2' => 2,
        ]);

        $result = $this->generator->generate($class);

        // Check spell slot columns exist
        $columnKeys = array_column($result['columns'], 'key');
        $this->assertContains('cantrips_known', $columnKeys);
        $this->assertContains('spell_slots_1', $columnKeys);
        $this->assertContains('spell_slots_2', $columnKeys);

        // Check values
        $row1 = $result['rows'][0];
        $this->assertEquals(3, $row1['cantrips_known']);
        $this->assertEquals(2, $row1['spell_slots_1']);
    }

    #[Test]
    public function it_uses_parent_class_progression_for_subclasses(): void
    {
        $parent = CharacterClass::factory()->create(['name' => 'Fighter', 'hit_die' => 10]);
        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'parent_class_id' => $parent->id,
            'hit_die' => null,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $parent->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);

        $result = $this->generator->generate($subclass);

        // Should use parent's features
        $row1 = $result['rows'][0];
        $this->assertStringContainsString('Fighting Style', $row1['features']);
    }
}
```

**Verification:**
```bash
docker compose exec php php artisan test --filter=ClassProgressionTableGenerator
```

---

### Task 5: Create ClassProgressionResource

**File:** `app/Http/Resources/ClassProgressionResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array $columns
 * @property-read array $rows
 */
class ClassProgressionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{columns: array, rows: array}
     */
    public function toArray(Request $request): array
    {
        return [
            'columns' => $this->resource['columns'],
            'rows' => $this->resource['rows'],
        ];
    }
}
```

---

## Phase 3: Update ClassResource (Tasks 6-7)

### Task 6: Add New Fields to ClassResource

**File:** `app/Http/Resources/ClassResource.php`

Update to include all new computed fields:

```php
<?php

namespace App\Http\Resources;

use App\Services\ClassProgressionTableGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Determine if we should include base class features for subclasses
        $includeBaseFeatures = $request->boolean('include_base_features', true);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'hit_die' => $this->hit_die,
            'description' => $this->description,
            'primary_ability' => $this->primary_ability,
            'spellcasting_ability' => $this->when($this->spellcasting_ability_id, function () {
                return new AbilityScoreResource($this->whenLoaded('spellcastingAbility'));
            }),
            'parent_class_id' => $this->parent_class_id,
            'is_base_class' => $this->is_base_class,
            'parent_class' => $this->when($this->parent_class_id, function () {
                return new ClassResource($this->whenLoaded('parentClass'));
            }),
            'subclasses' => ClassResource::collection($this->whenLoaded('subclasses')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'traits' => TraitResource::collection($this->whenLoaded('traits')),

            // Features: Use getAllFeatures() to merge base + subclass features when appropriate
            'features' => $this->when($this->relationLoaded('features'), function () use ($includeBaseFeatures) {
                return ClassFeatureResource::collection($this->getAllFeatures($includeBaseFeatures));
            }),

            'level_progression' => ClassLevelProgressionResource::collection($this->whenLoaded('levelProgression')),
            'counters' => ClassCounterResource::collection($this->whenLoaded('counters')),
            'spells' => SpellResource::collection($this->whenLoaded('spells')),
            'optional_features' => OptionalFeatureResource::collection($this->whenLoaded('optionalFeatures')),
            'equipment' => EntityItemResource::collection($this->whenLoaded('equipment')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),

            // ===== NEW COMPUTED FIELDS =====

            // Pre-computed hit points (D&D 5e formulas)
            'hit_points' => $this->hit_points,

            // Spell slot summary for frontend column visibility
            'spell_slot_summary' => $this->when(
                $this->relationLoaded('levelProgression'),
                fn () => $this->spell_slot_summary
            ),

            // Section counts for lazy-loading accordions
            'section_counts' => $this->when(
                $this->features_count !== null || $this->proficiencies_count !== null,
                fn () => [
                    'features' => $this->features_count,
                    'proficiencies' => $this->proficiencies_count,
                    'traits' => $this->traits_count,
                    'subclasses' => $this->subclasses_count,
                    'spells' => $this->spells_count,
                    'counters' => $this->counters_count,
                    'optional_features' => $this->optional_features_count,
                ]
            ),

            // Effective data for subclasses (pre-resolved inheritance)
            'effective_data' => $this->when(
                !$this->is_base_class && $this->relationLoaded('parentClass') && $this->parentClass,
                function () {
                    $parent = $this->parentClass;
                    return [
                        'hit_die' => $parent->hit_die,
                        'hit_points' => $parent->hit_points,
                        'counters' => $parent->relationLoaded('counters')
                            ? ClassCounterResource::collection($parent->counters)
                            : null,
                        'traits' => $parent->relationLoaded('traits')
                            ? TraitResource::collection($parent->traits)
                            : null,
                        'level_progression' => $parent->relationLoaded('levelProgression')
                            ? ClassLevelProgressionResource::collection($parent->levelProgression)
                            : null,
                        'equipment' => $parent->relationLoaded('equipment')
                            ? EntityItemResource::collection($parent->equipment)
                            : null,
                        'proficiencies' => $parent->relationLoaded('proficiencies')
                            ? ProficiencyResource::collection($parent->proficiencies)
                            : null,
                        'spell_slot_summary' => $parent->relationLoaded('levelProgression')
                            ? $parent->spell_slot_summary
                            : null,
                    ];
                }
            ),

            // Pre-computed progression table (for detail views)
            'progression_table' => $this->when(
                $request->routeIs('classes.show') || $request->routeIs('classes.progression'),
                function () {
                    $generator = app(ClassProgressionTableGenerator::class);
                    return $generator->generate($this->resource);
                }
            ),
        ];
    }
}
```

---

### Task 7: Update ClassController with Relationship Counts and New Endpoint

**File:** `app/Http/Controllers/Api/ClassController.php`

Update `show()` method and add `progression()` endpoint:

```php
/**
 * Get a single class
 *
 * Returns detailed information about a specific class or subclass including parent class,
 * subclasses, proficiencies, traits, features, level progression, spell slot tables,
 * and counters. Supports selective relationship loading via the 'include' parameter.
 *
 * ## New Computed Fields (2025-11-26)
 *
 * The response now includes pre-computed, display-ready data:
 *
 * **hit_points** - Pre-calculated hit point formulas:
 * ```json
 * {
 *   "hit_die": "d10",
 *   "hit_die_numeric": 10,
 *   "first_level": {"value": 10, "description": "10 + your Constitution modifier"},
 *   "higher_levels": {"roll": "1d10", "average": 6, "description": "..."}
 * }
 * ```
 *
 * **spell_slot_summary** - Spellcasting overview for UI optimization:
 * ```json
 * {
 *   "has_spell_slots": true,
 *   "max_spell_level": 9,
 *   "available_levels": [1, 2, 3, 4, 5, 6, 7, 8, 9],
 *   "has_cantrips": true,
 *   "caster_type": "full"
 * }
 * ```
 *
 * **section_counts** - Counts for lazy-loading accordion labels:
 * ```json
 * {
 *   "features": 34,
 *   "proficiencies": 12,
 *   "traits": 8,
 *   "subclasses": 7,
 *   "spells": 89,
 *   "counters": 3,
 *   "optional_features": 0
 * }
 * ```
 *
 * **effective_data** (subclasses only) - Pre-resolved inherited data:
 * ```json
 * {
 *   "hit_die": 10,
 *   "hit_points": {...},
 *   "counters": [...],
 *   "traits": [...],
 *   "level_progression": [...],
 *   "equipment": [...],
 *   "proficiencies": [...]
 * }
 * ```
 *
 * **progression_table** - Complete 20-level progression table:
 * ```json
 * {
 *   "columns": [
 *     {"key": "level", "label": "Level", "type": "integer"},
 *     {"key": "proficiency_bonus", "label": "Proficiency Bonus", "type": "bonus"},
 *     {"key": "features", "label": "Features", "type": "string"},
 *     {"key": "sneak_attack", "label": "Sneak Attack", "type": "dice"}
 *   ],
 *   "rows": [
 *     {"level": 1, "proficiency_bonus": "+2", "features": "Expertise, Sneak Attack", "sneak_attack": "1d6"},
 *     {"level": 2, "proficiency_bonus": "+2", "features": "Cunning Action", "sneak_attack": "1d6"}
 *   ]
 * }
 * ```
 *
 * **Feature Inheritance for Subclasses:**
 * - By default, subclasses return ALL features (inherited base class features + subclass-specific features)
 * - Use `?include_base_features=false` to return only subclass-specific features
 * - Base classes are unaffected by this parameter
 *
 * **Examples:**
 * - Get Arcane Trickster with all 40 features: `GET /classes/rogue-arcane-trickster`
 * - Get only Arcane Trickster's 6 unique features: `GET /classes/rogue-arcane-trickster?include_base_features=false`
 * - Get base Rogue (always 34 features): `GET /classes/rogue`
 */
public function show(ClassShowRequest $request, CharacterClass $class, EntityCacheService $cache, ClassSearchService $service)
{
    $validated = $request->validated();

    // Default relationships from service
    $defaultRelationships = $service->getShowRelationships();

    // Try cache first
    $cachedClass = $cache->getClass($class->id);

    if ($cachedClass) {
        // If include parameter provided, use it; otherwise load defaults
        $includes = $validated['include'] ?? $defaultRelationships;

        // Ensure parentClass relationship is loaded if features are requested
        // (Resource will use getAllFeatures() to handle inheritance)
        if (in_array('features', $includes) && ! in_array('parentClass', $includes)) {
            $includes[] = 'parentClass';
        }

        $cachedClass->load($includes);

        // Load counts for section_counts field
        $cachedClass->loadCount([
            'features',
            'proficiencies',
            'traits',
            'subclasses',
            'spells',
            'counters',
            'optionalFeatures',
        ]);

        return new ClassResource($cachedClass);
    }

    // Fallback to route model binding result (should rarely happen)
    $includes = $validated['include'] ?? $defaultRelationships;

    // Ensure parentClass relationship is loaded if features are requested
    // (Resource will use getAllFeatures() to handle inheritance)
    if (in_array('features', $includes) && ! in_array('parentClass', $includes)) {
        $includes[] = 'parentClass';
    }

    $class->load($includes);

    // Load counts for section_counts field
    $class->loadCount([
        'features',
        'proficiencies',
        'traits',
        'subclasses',
        'spells',
        'counters',
        'optionalFeatures',
    ]);

    return new ClassResource($class);
}

/**
 * Get the progression table for a class
 *
 * Returns a pre-computed progression table showing level-by-level advancement
 * including proficiency bonus, features gained, class-specific counters (like
 * Sneak Attack dice, Ki Points, Rage uses), and spell slots if applicable.
 *
 * This endpoint is useful for lazy-loading the progression table separately
 * from the main class detail response.
 *
 * ## Response Structure
 *
 * **columns** - Dynamic column definitions based on class features:
 * - Always includes: level, proficiency_bonus, features
 * - Counter columns: sneak_attack, ki_points, rage_damage, etc. (varies by class)
 * - Spell slot columns: cantrips_known, spell_slots_1 through spell_slots_9 (for casters)
 *
 * **rows** - 20 rows, one per level, with all column values pre-computed:
 * - Counter values are interpolated (sparse data filled in)
 * - Sneak Attack formatted as "Xd6"
 * - Proficiency bonus formatted as "+X"
 * - Features joined with commas
 *
 * ## Example Response
 *
 * ```json
 * {
 *   "data": {
 *     "columns": [
 *       {"key": "level", "label": "Level", "type": "integer"},
 *       {"key": "proficiency_bonus", "label": "Proficiency Bonus", "type": "bonus"},
 *       {"key": "features", "label": "Features", "type": "string"},
 *       {"key": "ki_points", "label": "Ki Points", "type": "integer"}
 *     ],
 *     "rows": [
 *       {"level": 1, "proficiency_bonus": "+2", "features": "Unarmored Defense, Martial Arts", "ki_points": "—"},
 *       {"level": 2, "proficiency_bonus": "+2", "features": "Ki, Unarmored Movement", "ki_points": "2"}
 *     ]
 *   }
 * }
 * ```
 *
 * ## For Subclasses
 *
 * When called on a subclass, returns the parent class's progression table
 * since subclasses inherit the base class progression mechanics.
 */
public function progression(CharacterClass $class, ClassProgressionTableGenerator $generator)
{
    // Load required relationships for progression table
    $progressionClass = $class->is_base_class ? $class : $class->parentClass;

    if (!$progressionClass) {
        return response()->json(['data' => ['columns' => [], 'rows' => []]], 200);
    }

    $progressionClass->load(['levelProgression', 'counters', 'features']);

    $table = $generator->generate($class);

    return response()->json(['data' => $table]);
}
```

---

### Task 8: Add Route for Progression Endpoint

**File:** `routes/api.php`

Add after the existing classes routes (around line 167):

```php
// Classes
Route::apiResource('classes', ClassController::class)->only(['index', 'show']);
Route::get('classes/{class}/spells', [ClassController::class, 'spells'])
    ->name('classes.spells');
Route::get('classes/{class}/progression', [ClassController::class, 'progression'])
    ->name('classes.progression');
```

---

## Phase 4: Feature Tests (Tasks 9-10)

### Task 9: Create Comprehensive Feature Tests

**File:** `tests/Feature/Api/ClassDetailOptimizationTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassDetailOptimizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_endpoint_returns_hit_points_for_base_class(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk()
            ->assertJsonPath('data.hit_points.hit_die', 'd10')
            ->assertJsonPath('data.hit_points.hit_die_numeric', 10)
            ->assertJsonPath('data.hit_points.first_level.value', 10)
            ->assertJsonPath('data.hit_points.higher_levels.average', 6);
    }

    #[Test]
    public function show_endpoint_returns_null_hit_points_for_subclass(): void
    {
        $parent = CharacterClass::factory()->create(['hit_die' => 10, 'slug' => 'fighter']);
        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion',
            'parent_class_id' => $parent->id,
            'hit_die' => null,
        ]);

        $response = $this->getJson("/api/v1/classes/{$subclass->slug}");

        $response->assertOk()
            ->assertJsonPath('data.hit_points', null);
    }

    #[Test]
    public function show_endpoint_returns_effective_data_for_subclass(): void
    {
        $parent = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        ClassCounter::factory()->create([
            'class_id' => $parent->id,
            'counter_name' => 'Action Surge',
            'level' => 2,
            'counter_value' => 1,
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion',
            'parent_class_id' => $parent->id,
            'hit_die' => null,
        ]);

        $response = $this->getJson("/api/v1/classes/{$subclass->slug}");

        $response->assertOk()
            ->assertJsonPath('data.effective_data.hit_die', 10)
            ->assertJsonStructure([
                'data' => [
                    'effective_data' => [
                        'hit_die',
                        'hit_points',
                        'counters',
                    ],
                ],
            ]);
    }

    #[Test]
    public function show_endpoint_does_not_return_effective_data_for_base_class(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk()
            ->assertJsonMissing(['effective_data']);
    }

    #[Test]
    public function show_endpoint_returns_section_counts(): void
    {
        $class = CharacterClass::factory()->create(['slug' => 'fighter']);

        ClassFeature::factory()->count(5)->create(['class_id' => $class->id]);
        ClassCounter::factory()->count(2)->create(['class_id' => $class->id]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk()
            ->assertJsonPath('data.section_counts.features', 5)
            ->assertJsonPath('data.section_counts.counters', 2);
    }

    #[Test]
    public function show_endpoint_returns_spell_slot_summary_for_caster(): void
    {
        $class = CharacterClass::factory()->spellcaster('INT')->create(['slug' => 'wizard']);

        for ($level = 1; $level <= 20; $level++) {
            ClassLevelProgression::factory()->create([
                'class_id' => $class->id,
                'level' => $level,
                'cantrips_known' => 3,
                'spell_slots_1' => 4,
                'spell_slots_9' => $level >= 17 ? 1 : 0,
            ]);
        }

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk()
            ->assertJsonPath('data.spell_slot_summary.has_spell_slots', true)
            ->assertJsonPath('data.spell_slot_summary.max_spell_level', 9)
            ->assertJsonPath('data.spell_slot_summary.has_cantrips', true)
            ->assertJsonPath('data.spell_slot_summary.caster_type', 'full');
    }

    #[Test]
    public function show_endpoint_returns_progression_table(): void
    {
        $class = CharacterClass::factory()->create(['slug' => 'rogue']);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Sneak Attack',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Sneak Attack',
            'level' => 1,
            'counter_value' => 1,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'progression_table' => [
                        'columns',
                        'rows',
                    ],
                ],
            ])
            ->assertJsonPath('data.progression_table.rows.0.level', 1)
            ->assertJsonPath('data.progression_table.rows.0.proficiency_bonus', '+2');
    }

    #[Test]
    public function progression_endpoint_returns_table(): void
    {
        $class = CharacterClass::factory()->create(['slug' => 'fighter']);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}/progression");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'columns',
                    'rows',
                ],
            ])
            ->assertJsonCount(20, 'data.rows');
    }

    #[Test]
    public function progression_endpoint_uses_parent_for_subclass(): void
    {
        $parent = CharacterClass::factory()->create(['slug' => 'fighter']);

        ClassFeature::factory()->create([
            'class_id' => $parent->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);

        $subclass = CharacterClass::factory()->create([
            'slug' => 'champion',
            'parent_class_id' => $parent->id,
        ]);

        $response = $this->getJson("/api/v1/classes/{$subclass->slug}/progression");

        $response->assertOk()
            ->assertJsonPath('data.rows.0.features', 'Fighting Style');
    }
}
```

---

### Task 10: Create Form Request for Progression Endpoint

**File:** `app/Http/Requests/ClassProgressionRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassProgressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
```

---

## Phase 5: Quality Gates (Tasks 11-12)

### Task 11: Run Full Test Suite

```bash
docker compose exec php php artisan test
```

Expected: All 1,489+ tests passing.

### Task 12: Run Code Formatting

```bash
docker compose exec php ./vendor/bin/pint
```

---

## Phase 6: Documentation (Tasks 13-14)

### Task 13: Update CHANGELOG.md

Add under `[Unreleased]`:

```markdown
### Added
- `hit_points` computed field on ClassResource with pre-calculated D&D 5e HP formulas
- `spell_slot_summary` computed field for frontend spell slot column optimization
- `section_counts` field with relationship counts for lazy-loading accordion labels
- `effective_data` field for subclasses with pre-resolved parent class inheritance
- `progression_table` field with complete 20-level progression table
- `GET /api/v1/classes/{slug}/progression` endpoint for lazy-loading progression tables
- `CharacterClass::proficiencyBonusForLevel()` static method for D&D 5e proficiency bonus calculation
- `ClassProgressionTableGenerator` service for generating progression tables with counter interpolation
```

### Task 14: Archive Completed Proposal

Move `docs/proposals/BLOCKED-CLASSES-PROFICIENCY-FILTERS-2025-11-25.md` to `docs/archive/` and update status to COMPLETED (proficiency filters were already implemented).

---

## Execution Order

1. **Phase 1** (Tasks 1-3): Model accessors - can be done in parallel
2. **Phase 2** (Tasks 4-5): Service and resource - depends on Phase 1
3. **Phase 3** (Tasks 6-8): Resource and controller updates - depends on Phase 2
4. **Phase 4** (Tasks 9-10): Feature tests - should be written alongside implementation (TDD)
5. **Phase 5** (Tasks 11-12): Quality gates - run after all code complete
6. **Phase 6** (Tasks 13-14): Documentation - final step

---

## Verification Commands

```bash
# Run specific test groups
docker compose exec php php artisan test --filter=ClassDetailOptimization
docker compose exec php php artisan test --filter=ClassProgressionTableGenerator

# Run full test suite
docker compose exec php php artisan test

# Format code
docker compose exec php ./vendor/bin/pint

# Test API manually
curl -s "http://localhost:8080/api/v1/classes/fighter" | jq '.data.hit_points'
curl -s "http://localhost:8080/api/v1/classes/fighter" | jq '.data.progression_table'
curl -s "http://localhost:8080/api/v1/classes/champion" | jq '.data.effective_data'
curl -s "http://localhost:8080/api/v1/classes/fighter/progression" | jq '.data'
```

---

## Rollback Plan

All changes are additive (new accessors, new fields, new endpoint). Existing API behavior is unchanged. If issues arise:

1. Remove new fields from ClassResource
2. Remove new route
3. Keep accessors (no breaking change)

No database migrations required. No cache invalidation needed beyond normal cache TTL.
