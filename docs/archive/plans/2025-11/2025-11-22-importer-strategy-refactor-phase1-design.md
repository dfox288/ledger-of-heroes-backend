# Importer Strategy Pattern Refactoring - Phase 1 Design

**Date:** 2025-11-22
**Type:** Architectural Refactoring
**Status:** Design Complete, Ready for Implementation
**Estimated Duration:** 8-12 hours

---

## Executive Summary

Refactor RaceImporter and ClassImporter to use the Strategy Pattern, achieving architectural consistency with ItemImporter and MonsterImporter while reducing code complexity by 48-54%.

**Goal:** Uniform architecture across all importers for maintainability and future extensibility.

**Scope:** Phase 1 of 3-phase rollout focusing on high-value importers with clear type variations.

---

## Background

### Current State

**Strategy Pattern Already Implemented:**
- ✅ ItemImporter - 5 strategies (Charged, Scroll, Potion, Tattoo, Legendary)
- ✅ MonsterImporter - 5 strategies (Default, Dragon, Spellcaster, Undead, Swarm)

**Remaining Importers (6 total):**
- RaceImporter (347 lines) - Complex base/subrace/variant logic
- ClassImporter (263 lines) - Dual-mode base/subclass handling
- SpellImporter (216 lines) - Uniform structure, extensibility opportunity
- BackgroundImporter (149 lines) - Simple, uniform
- FeatImporter (150 lines) - Simple, uniform
- SpellClassMappingImporter (172 lines) - Simple pivot logic

### Motivation

**Architectural Consistency:**
- Uniform pattern across all importers
- Easier onboarding for new developers
- Consistent testing patterns
- Clear extension points for future enhancements

**Code Quality Benefits:**
- Reduced importer complexity (347→180 lines, 263→120 lines)
- Type-specific logic isolated and testable
- Single responsibility per strategy
- Eliminates conditional branching

---

## Phased Rollout Strategy

### Phase 1: High-Value Importers (THIS DESIGN)
**Target:** RaceImporter + ClassImporter
**Duration:** 8-12 hours
**Value:** Significant code reduction, clear type variations
**Deliverables:**
- 5 strategies (3 race, 2 class)
- ~58 new tests
- Code reduction: 610 lines → 300 lines (-51%)

### Phase 2: Extensibility Enhancement (FUTURE)
**Target:** SpellImporter
**Duration:** 4-6 hours
**Value:** Future-proofing for spell variants
**Strategies:** Default, Ritual, Concentration, Upcast

### Phase 3: Consistency Completion (FUTURE)
**Target:** BackgroundImporter, FeatImporter, SpellClassMappingImporter
**Duration:** 3-4 hours
**Value:** Complete architectural consistency
**Approach:** Minimal strategies (DefaultStrategy only)

**Total Estimated Time:** 15-22 hours across 3 phases

---

## Phase 1 Architecture

### Directory Structure

```
app/Services/Importers/Strategies/
├── Race/
│   ├── AbstractRaceStrategy.php      # Base class with metadata tracking
│   ├── BaseRaceStrategy.php          # Handles base races (Elf, Dwarf, Human)
│   ├── SubraceStrategy.php           # Handles subraces (High Elf, Mountain Dwarf)
│   └── RacialVariantStrategy.php     # Handles variants (Dragonborn colors)
└── Class/
    ├── AbstractClassStrategy.php     # Base class with metadata tracking
    ├── BaseClassStrategy.php         # Handles base classes (Wizard, Fighter)
    └── SubclassStrategy.php          # Handles subclasses (School of Evocation)
```

---

## RaceImporter Refactoring

### Current Complexity (347 lines)

**Problems:**
- Mixed logic for base races, subraces, and racial variants
- Complex conditional branching for parent race creation
- Duplicate code for ability score modifiers
- Hard to test individual race type behaviors
- `getOrCreateBaseRace()` method handles multiple responsibilities

**Key Sections:**
```php
// Lines 25-85: importEntity() - main logic with conditionals
// Lines 87-130: getOrCreateBaseRace() - complex parent resolution
// Lines 132-170: generateSlugForRace() - variant naming logic
// Lines 172-200: Relationship imports (modifiers, languages, etc.)
```

### Strategy Breakdown

#### 1. BaseRaceStrategy

**Applies To:** Base races without parent (Elf, Dwarf, Human, Dragonborn)

**Detection:**
```php
public function appliesTo(array $data): bool {
    return empty($data['base_race_name']) && empty($data['variant_of']);
}
```

**Enhancements:**
- Validates required fields: size, speed, description
- Sets parent_race_id = null
- Tracks base race creation
- No special slug logic (uses name directly)

**Metrics Tracked:**
- `base_races_processed`: Count of base races enhanced
- `missing_required_fields`: Count of validation warnings

**Example:**
```php
Input: ['name' => 'Elf', 'size_code' => 'M', 'speed' => 30, ...]
Output: ['name' => 'Elf', 'parent_race_id' => null, 'slug' => 'elf', ...]
```

#### 2. SubraceStrategy

**Applies To:** Subraces with base race reference (High Elf, Mountain Dwarf)

**Detection:**
```php
public function appliesTo(array $data): bool {
    return !empty($data['base_race_name']) && empty($data['variant_of']);
}
```

**Enhancements:**
- Ensures base race exists (creates stub if missing)
- Resolves parent_race_id
- Generates compound slug (base-race-subrace)
- Tracks base race creation statistics

**Metrics Tracked:**
- `subraces_processed`: Count of subraces enhanced
- `base_races_created`: Count of stub base races auto-created
- `base_races_resolved`: Count of existing base races found

**Example:**
```php
Input: ['name' => 'High Elf', 'base_race_name' => 'Elf', ...]
Output: ['name' => 'High Elf', 'parent_race_id' => 5, 'slug' => 'elf-high-elf', ...]
```

**Base Race Stub Creation:**
```php
// If "Elf" doesn't exist, create minimal stub
Race::firstOrCreate(['slug' => 'elf'], [
    'name' => 'Elf',
    'size_id' => $subrace->size_id, // Inherit from subrace
    'speed' => $subrace->speed,
    'description' => 'Base race (auto-created)',
]);
```

#### 3. RacialVariantStrategy

**Applies To:** Racial variants like Dragonborn colors, Tiefling bloodlines

**Detection:**
```php
public function appliesTo(array $data): bool {
    return !empty($data['variant_of']);
}
```

**Enhancements:**
- Parses variant type from name: "Dragonborn (Gold)" → variant_type: "Gold"
- Links to parent race via variant_of
- Extracts variant-specific abilities (e.g., breath weapon damage type)
- Generates variant slug (dragonborn-gold)

**Metrics Tracked:**
- `variants_processed`: Count of variants enhanced
- `variant_abilities_extracted`: Count of variant-specific abilities parsed
- `parent_races_missing`: Warnings for missing parent races

**Example:**
```php
Input: ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn', ...]
Output: [
    'name' => 'Dragonborn (Gold)',
    'parent_race_id' => 12,
    'slug' => 'dragonborn-gold',
    'variant_type' => 'Gold',
    'breath_weapon_damage_type' => 'fire',
    ...
]
```

### Refactored RaceImporter Structure

**Before (347 lines):**
```php
class RaceImporter extends BaseImporter {
    protected function importEntity(array $raceData): Race {
        // 60+ lines of conditional logic
        if (empty($raceData['base_race_name'])) {
            // Base race logic
        } else {
            // Subrace logic
            $baseRace = $this->getOrCreateBaseRace(...); // 40+ lines
        }

        if (!empty($raceData['variant_of'])) {
            // Variant logic
        }

        // Slug generation with conditionals
        $slug = $this->generateSlugForRace(...); // 30+ lines

        // Race creation + relationship imports
    }

    private function getOrCreateBaseRace(...) { /* 40+ lines */ }
    private function generateSlugForRace(...) { /* 30+ lines */ }
}
```

**After (~180 lines):**
```php
class RaceImporter extends BaseImporter {
    use ImportsConditions, ImportsEntitySpells, ImportsLanguages, ImportsModifiers;

    private array $strategies = [];

    public function __construct(RaceXmlParser $parser) {
        parent::__construct($parser);
        $this->initializeStrategies();
    }

    private function initializeStrategies(): void {
        $this->strategies = [
            new BaseRaceStrategy(),
            new SubraceStrategy(),
            new RacialVariantStrategy(),
        ];
    }

    protected function importEntity(array $raceData): Race {
        // Apply all applicable strategies
        foreach ($this->strategies as $strategy) {
            if ($strategy->appliesTo($raceData)) {
                $raceData = $strategy->enhance($raceData);
                $this->logStrategyApplication($strategy, $raceData);
            }
        }

        // Core import logic (simplified - no conditionals)
        $race = Race::updateOrCreate(
            ['slug' => $raceData['slug']],
            [
                'name' => $raceData['name'],
                'parent_race_id' => $raceData['parent_race_id'] ?? null,
                'size_id' => $raceData['size_id'],
                'speed' => $raceData['speed'],
                'description' => $raceData['description'],
            ]
        );

        // Import relationships (using existing traits - no changes)
        $this->importModifiers($race, $raceData['modifiers'] ?? []);
        $this->importLanguages($race, $raceData['languages'] ?? []);
        $this->importConditions($race, $raceData['condition_immunities'] ?? []);
        $this->importEntitySpells($race, $raceData['spell_references'] ?? []);

        return $race;
    }

    private function logStrategyApplication(AbstractRaceStrategy $strategy, array $data): void {
        Log::channel('import-strategy')->info('Strategy applied', [
            'race' => $data['name'],
            'strategy' => class_basename($strategy),
            'warnings' => $strategy->getWarnings(),
            'metrics' => $strategy->getMetrics(),
        ]);
    }
}
```

**Code Reduction:** 347 lines → ~180 lines (-48%)

---

## ClassImporter Refactoring

### Current Complexity (263 lines)

**Problems:**
- Dual-mode logic: full class data vs supplemental subclass data
- `$hasBaseClassData = ($data['hit_die'] ?? 0) > 0` scattered throughout
- Complex spellcasting ability lookup mixed with class creation
- Level progression handling mixed with class import
- Hard to distinguish base class vs subclass test scenarios

**Key Sections:**
```php
// Lines 17-50: importEntity() - dual-mode branching
// Lines 52-90: Base class creation path
// Lines 92-130: Subclass resolution and creation
// Lines 132-180: Level progression import
// Lines 182-220: Feature import
```

### Strategy Breakdown

#### 1. BaseClassStrategy

**Applies To:** Base classes with complete data (Wizard, Fighter, Cleric)

**Detection:**
```php
public function appliesTo(array $data): bool {
    return ($data['hit_die'] ?? 0) > 0; // Has base class data
}
```

**Enhancements:**
- Validates required fields: hit_die, description
- Resolves spellcasting ability (INT/WIS/CHA → ability_score.id)
- Sets parent_class_id = null
- Detects spellcaster vs martial classes
- Tracks spellcasting ability distribution

**Metrics Tracked:**
- `base_classes_processed`: Count of base classes enhanced
- `spellcasters_detected`: Count with spellcasting ability
- `martial_classes`: Count without spellcasting
- `missing_hit_die`: Validation warnings

**Example:**
```php
Input: ['name' => 'Wizard', 'hit_die' => 6, 'spellcasting_ability' => 'Intelligence', ...]
Output: [
    'name' => 'Wizard',
    'hit_die' => 6,
    'parent_class_id' => null,
    'spellcasting_ability_id' => 4, // Intelligence
    'is_spellcaster' => true,
    ...
]
```

#### 2. SubclassStrategy

**Applies To:** Subclasses with supplemental data only (School of Evocation, Champion)

**Detection:**
```php
public function appliesTo(array $data): bool {
    return ($data['hit_die'] ?? 0) === 0; // Supplemental data only
}
```

**Enhancements:**
- Resolves parent class from name or context
- Inherits hit_die from parent class
- Extracts subclass features from traits array
- Generates subclass-specific slug
- Tracks parent resolution statistics

**Metrics Tracked:**
- `subclasses_processed`: Count of subclasses enhanced
- `parent_classes_resolved`: Count of successful parent lookups
- `parent_classes_missing`: Warnings for missing parents
- `features_extracted`: Count of subclass features parsed

**Example:**
```php
Input: ['name' => 'School of Evocation', 'hit_die' => 0, 'traits' => [...], ...]
Output: [
    'name' => 'School of Evocation',
    'parent_class_id' => 8, // Wizard
    'hit_die' => 6, // Inherited from Wizard
    'slug' => 'wizard-school-of-evocation',
    'features' => ['Evocation Savant', 'Sculpt Spells', ...],
    ...
]
```

**Parent Resolution Logic:**
```php
// Detect parent from name patterns
if (str_contains($name, 'School of')) {
    $parentName = 'Wizard';
} elseif (str_contains($name, 'Oath of')) {
    $parentName = 'Paladin';
} elseif (str_contains($name, 'Circle of')) {
    $parentName = 'Druid';
}

$parent = CharacterClass::where('slug', Str::slug($parentName))->first();
```

### Refactored ClassImporter Structure

**Before (263 lines):**
```php
class ClassImporter extends BaseImporter {
    protected function importEntity(array $data): CharacterClass {
        // Dual-mode branching
        $hasBaseClassData = ($data['hit_die'] ?? 0) > 0;

        // Spellcasting ability lookup (scattered)
        $spellcastingAbilityId = null;
        if (!empty($data['spellcasting_ability'])) {
            $ability = AbilityScore::where(...)->first();
            $spellcastingAbilityId = $ability?->id;
        }

        if ($hasBaseClassData) {
            // Base class path (40+ lines)
            $class = CharacterClass::updateOrCreate(...);
        } else {
            // Subclass path (40+ lines)
            $parent = $this->resolveParentClass($data['name']);
            $class = CharacterClass::updateOrCreate(...);
        }

        // Level progression (40+ lines)
        // Features (30+ lines)
    }

    private function resolveParentClass(...) { /* 30+ lines */ }
}
```

**After (~120 lines):**
```php
class ClassImporter extends BaseImporter {
    use ImportsTraits, ImportsProficiencies, ImportsSources;

    private array $strategies = [];

    public function __construct(ClassXmlParser $parser) {
        parent::__construct($parser);
        $this->initializeStrategies();
    }

    private function initializeStrategies(): void {
        $this->strategies = [
            new BaseClassStrategy(),
            new SubclassStrategy(),
        ];
    }

    protected function importEntity(array $data): CharacterClass {
        // Apply all applicable strategies
        foreach ($this->strategies as $strategy) {
            if ($strategy->appliesTo($data)) {
                $data = $strategy->enhance($data);
                $this->logStrategyApplication($strategy, $data);
            }
        }

        // Core import logic (simplified - no branching)
        $class = CharacterClass::updateOrCreate(
            ['slug' => $data['slug']],
            [
                'name' => $data['name'],
                'parent_class_id' => $data['parent_class_id'] ?? null,
                'hit_die' => $data['hit_die'],
                'description' => $data['description'],
                'spellcasting_ability_id' => $data['spellcasting_ability_id'] ?? null,
            ]
        );

        // Import relationships (using existing traits)
        $this->importTraits($class, $data['traits'] ?? []);
        $this->importProficiencies($class, $data['proficiencies'] ?? []);
        $this->importSources($class, $data['sources'] ?? []);

        // Import level progression
        $this->importLevelProgression($class, $data['level_progression'] ?? []);

        return $class;
    }

    private function importLevelProgression(...) { /* Unchanged */ }

    private function logStrategyApplication(...) { /* Same as RaceImporter */ }
}
```

**Code Reduction:** 263 lines → ~120 lines (-54%)

---

## Strategy Base Classes

### AbstractRaceStrategy

```php
<?php

namespace App\Services\Importers\Strategies\Race;

abstract class AbstractRaceStrategy
{
    protected array $warnings = [];
    protected array $metrics = [];

    /**
     * Determine if this strategy applies to the given race data.
     */
    abstract public function appliesTo(array $data): bool;

    /**
     * Enhance race data with strategy-specific logic.
     */
    abstract public function enhance(array $data): array;

    /**
     * Get warnings generated during enhancement.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get metrics tracked during enhancement.
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Reset warnings and metrics for next entity.
     */
    public function reset(): void
    {
        $this->warnings = [];
        $this->metrics = [];
    }

    /**
     * Add a warning message.
     */
    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Increment a metric counter.
     */
    protected function incrementMetric(string $key): void
    {
        $this->metrics[$key] = ($this->metrics[$key] ?? 0) + 1;
    }
}
```

### AbstractClassStrategy

```php
<?php

namespace App\Services\Importers\Strategies\CharacterClass;

abstract class AbstractClassStrategy
{
    // Identical structure to AbstractRaceStrategy
    // (reuse pattern established with Item/Monster strategies)
}
```

---

## Testing Strategy

### Unit Tests (58 new tests)

**Race Strategies:**
```
tests/Unit/Strategies/Race/
├── AbstractRaceStrategyTest.php      (8 tests)
│   ├── test_warnings_tracking
│   ├── test_metrics_tracking
│   ├── test_reset_clears_state
│   └── ...
│
├── BaseRaceStrategyTest.php          (10 tests)
│   ├── test_applies_to_base_races
│   ├── test_does_not_apply_to_subraces
│   ├── test_validates_required_fields
│   ├── test_sets_parent_race_id_null
│   ├── test_generates_simple_slug
│   ├── test_tracks_base_races_processed_metric
│   └── ...
│
├── SubraceStrategyTest.php           (12 tests)
│   ├── test_applies_to_subraces
│   ├── test_resolves_existing_base_race
│   ├── test_creates_stub_base_race_if_missing
│   ├── test_generates_compound_slug
│   ├── test_tracks_base_races_created_metric
│   ├── test_warns_if_base_race_data_incomplete
│   └── ...
│
└── RacialVariantStrategyTest.php     (8 tests)
    ├── test_applies_to_variants
    ├── test_parses_variant_type_from_name
    ├── test_extracts_breath_weapon_damage_type
    ├── test_generates_variant_slug
    ├── test_tracks_variants_processed_metric
    └── ...
```

**Class Strategies:**
```
tests/Unit/Strategies/CharacterClass/
├── AbstractClassStrategyTest.php     (8 tests)
│   └── (same structure as AbstractRaceStrategyTest)
│
├── BaseClassStrategyTest.php         (10 tests)
│   ├── test_applies_to_base_classes
│   ├── test_detects_spellcasting_ability
│   ├── test_resolves_ability_score_id
│   ├── test_validates_hit_die_present
│   ├── test_tracks_spellcasters_metric
│   └── ...
│
└── SubclassStrategyTest.php          (12 tests)
    ├── test_applies_to_subclasses
    ├── test_resolves_parent_from_name_pattern
    ├── test_inherits_hit_die_from_parent
    ├── test_extracts_features_from_traits
    ├── test_warns_if_parent_missing
    └── ...
```

### Integration Tests (update existing)

**Verify strategies work together:**
```
tests/Feature/Importers/
├── RaceImporterTest.php              (update existing 15 tests)
│   ├── test_imports_base_race_with_BaseRaceStrategy
│   ├── test_imports_subrace_with_SubraceStrategy
│   ├── test_imports_variant_with_RacialVariantStrategy
│   └── test_applies_multiple_strategies_correctly
│
└── ClassImporterTest.php             (update existing 12 tests)
    ├── test_imports_base_class_with_BaseClassStrategy
    ├── test_imports_subclass_with_SubclassStrategy
    └── test_detects_spellcasting_ability_correctly
```

### Test Fixtures

**Real XML samples:**
```
tests/Fixtures/xml/races/
├── base-race.xml          (Elf - base race)
├── subrace.xml            (High Elf - subrace)
└── variant.xml            (Dragonborn (Gold) - variant)

tests/Fixtures/xml/classes/
├── base-class.xml         (Wizard - full data)
└── subclass.xml           (School of Evocation - supplemental)
```

---

## Implementation Workflow (TDD)

### Phase 1a: RaceImporter (4-6 hours)

**Step 1: Strategy Infrastructure (1 hour)**
1. Create `AbstractRaceStrategy` base class
2. Write 8 unit tests for metadata tracking
3. Verify tests pass
4. Commit: `feat: add AbstractRaceStrategy base class`

**Step 2: BaseRaceStrategy (45 min)**
1. Write 10 failing tests with real XML fixture
2. Implement `BaseRaceStrategy`
3. Verify tests pass
4. Commit: `feat: add BaseRaceStrategy for base races`

**Step 3: SubraceStrategy (1 hour)**
1. Write 12 failing tests with real XML fixture
2. Implement `SubraceStrategy` with parent resolution
3. Verify tests pass
4. Commit: `feat: add SubraceStrategy for subraces`

**Step 4: RacialVariantStrategy (45 min)**
1. Write 8 failing tests with real XML fixture
2. Implement `RacialVariantStrategy`
3. Verify tests pass
4. Commit: `feat: add RacialVariantStrategy for variants`

**Step 5: RaceImporter Integration (1.5 hours)**
1. Refactor `RaceImporter` to use strategies
2. Update existing integration tests
3. Verify ALL tests pass (1,141 + 38 new = 1,179)
4. Run actual import: `php artisan import:races import-files/races-phb.xml`
5. Verify strategy statistics display
6. Commit: `refactor: integrate strategy pattern into RaceImporter`

**Step 6: Documentation (30 min)**
1. Update inline code comments
2. Add strategy logging examples
3. Commit: `docs: add RaceImporter strategy documentation`

### Phase 1b: ClassImporter (3-4 hours)

**Step 1: Strategy Infrastructure (30 min)**
1. Create `AbstractClassStrategy` base class (reuse Race pattern)
2. Write 8 unit tests
3. Commit: `feat: add AbstractClassStrategy base class`

**Step 2: BaseClassStrategy (45 min)**
1. Write 10 failing tests
2. Implement `BaseClassStrategy`
3. Commit: `feat: add BaseClassStrategy for base classes`

**Step 3: SubclassStrategy (1 hour)**
1. Write 12 failing tests
2. Implement `SubclassStrategy` with parent resolution
3. Commit: `feat: add SubclassStrategy for subclasses`

**Step 4: ClassImporter Integration (1 hour)**
1. Refactor `ClassImporter` to use strategies
2. Update existing integration tests
3. Verify ALL tests pass (1,179 + 20 new = 1,199)
4. Run actual import: `php artisan import:classes import-files/class-phb.xml`
5. Verify strategy statistics display
6. Commit: `refactor: integrate strategy pattern into ClassImporter`

**Step 5: Documentation (30 min)**
1. Update inline code comments
2. Commit: `docs: add ClassImporter strategy documentation`

### Phase 1c: Final Documentation (1-2 hours)

1. Update `CLAUDE.md` with strategy details
2. Update `CHANGELOG.md` with Phase 1 changes
3. Create session handover document
4. Commit: `docs: complete Phase 1 strategy refactoring documentation`

---

## Success Criteria

### Code Quality Metrics

**Before Phase 1:**
- RaceImporter: 347 lines
- ClassImporter: 263 lines
- Total: 610 lines
- Tests: 1,141 passing

**After Phase 1:**
- RaceImporter: ~180 lines + 3 strategies (~150 lines)
- ClassImporter: ~120 lines + 2 strategies (~120 lines)
- Total: ~570 lines (distributed across focused files)
- Code reduction: -48% (RaceImporter), -54% (ClassImporter)
- Tests: 1,141 + 58 new = 1,199 passing
- All existing tests pass: ✅
- Laravel Pint formatted: ✅
- No new PHPStan errors: ✅

### Architecture Quality

- ✅ Consistent with Item/Monster patterns
- ✅ Each strategy <100 lines
- ✅ Clear separation of concerns
- ✅ Single responsibility per strategy
- ✅ Reusable strategy infrastructure
- ✅ Testable in isolation

### Functional Verification

**Import Commands Work:**
```bash
# Test with existing data
php artisan import:races import-files/races-phb.xml
php artisan import:classes import-files/class-phb.xml

# Verify counts match previous imports
docker compose exec php php artisan tinker --execute="echo 'Races: ' . \App\Models\Race::count();"
docker compose exec php php artisan tinker --execute="echo 'Classes: ' . \App\Models\CharacterClass::count();"
```

**Strategy Statistics Display:**
```
✓ Successfully imported 115 races

Strategy Statistics:
+------------------------+----------------+----------+
| Strategy               | Races Enhanced | Warnings |
+------------------------+----------------+----------+
| BaseRaceStrategy       | 12             | 0        |
| SubraceStrategy        | 95             | 2        |
| RacialVariantStrategy  | 8              | 0        |
+------------------------+----------------+----------+
⚠ Detailed logs: storage/logs/import-strategy-2025-11-22.log
```

---

## Risk Mitigation

### Risk: Breaking Existing Race/Class Imports

**Mitigation:**
- TDD approach ensures existing tests pass at every step
- Real XML fixtures validate against actual data
- Can test import with `--only=races` / `--only=classes` before full rollout
- Existing integration tests serve as regression suite
- Run imports in isolated worktree before merging to main

**Rollback Plan:**
- Git worktree allows easy abandonment without affecting main branch
- Each commit is atomic and can be reverted individually

### Risk: Over-Engineering Simple Logic

**Mitigation:**
- Only applying strategies where clear type-specific behavior exists
- BaseRaceStrategy is minimal (just validation + metadata)
- Not using PassthroughStrategy or other "fake" patterns
- Each strategy has real, distinct logic

**Evidence of Real Variations:**
- Base races: No parent, simple slug
- Subraces: Parent resolution, compound slug, trait inheritance
- Variants: Variant type extraction, ability parsing
- Base classes: Spellcasting detection, full data validation
- Subclasses: Parent resolution, hit_die inheritance, feature extraction

### Risk: Time Estimates Too Optimistic

**Mitigation:**
- Can deliver RaceImporter first (4-6 hours) as standalone value
- Validate pattern success before continuing to ClassImporter
- Each importer independently valuable
- If time runs out, can defer ClassImporter to separate session

**Incremental Delivery:**
- Step 1-6 (RaceImporter) → Complete, documented, merged
- Step 7-11 (ClassImporter) → Separate PR if needed
- Documentation can be split or combined as needed

### Risk: Strategy Detection Logic Bugs

**Mitigation:**
- Comprehensive unit tests for `appliesTo()` method
- Test edge cases: missing fields, malformed data
- Integration tests verify strategies don't overlap incorrectly
- Real XML fixtures catch unexpected data patterns

**Test Coverage:**
```php
// Example edge case tests
test_base_race_strategy_does_not_apply_to_subraces()
test_subrace_strategy_does_not_apply_to_base_races()
test_variant_strategy_does_not_apply_to_regular_subraces()
test_multiple_strategies_can_apply_to_same_race() // Should NOT happen
```

---

## Future Extensibility (Phases 2-3)

### Phase 2: SpellImporter (4-6 hours)

**Potential Strategies:**
1. **DefaultStrategy** - Standard spell handling
2. **RitualStrategy** - Ritual tag, casting time variations
3. **ConcentrationStrategy** - Concentration metadata extraction
4. **UpcastStrategy** - Higher level effects parsing

**Value:** Future-proofing for spell variant features

### Phase 3: Simple Importers (3-4 hours)

**Targets:**
- BackgroundImporter (149 lines) → DefaultStrategy only
- FeatImporter (150 lines) → DefaultStrategy only
- SpellClassMappingImporter (172 lines) → DefaultStrategy only

**Approach:**
```php
class DefaultBackgroundStrategy extends AbstractBackgroundStrategy {
    public function appliesTo(array $data): bool {
        return true; // Applies to all backgrounds
    }

    public function enhance(array $data): array {
        // Minimal validation, no transformations
        return $data;
    }
}
```

**Value:** Architectural consistency without over-engineering

---

## Documentation Deliverables

### 1. CLAUDE.md Updates

**Section: Import System → Strategy Pattern**
```markdown
### Strategy Pattern (All Importers)

**Architecture:** Type-specific parsing strategies for extensibility

**Importers Using Strategies:**
- ✅ ItemImporter (5 strategies: Charged, Scroll, Potion, Tattoo, Legendary)
- ✅ MonsterImporter (5 strategies: Default, Dragon, Spellcaster, Undead, Swarm)
- ✅ RaceImporter (3 strategies: BaseRace, Subrace, RacialVariant)
- ✅ ClassImporter (2 strategies: BaseClass, Subclass)

**Benefits:**
- Uniform architecture across all importers
- Type-specific logic isolated and testable
- Easy to add new strategies without modifying core importer
- Consistent logging and statistics
```

### 2. CHANGELOG.md Entry

```markdown
### [Unreleased]

#### Changed - Phase 1 Strategy Refactoring (2025-11-22)
- **RaceImporter:** Refactored to use Strategy Pattern (3 strategies)
  - BaseRaceStrategy: Handles base races (Elf, Dwarf, Human)
  - SubraceStrategy: Handles subraces with parent resolution (High Elf, Mountain Dwarf)
  - RacialVariantStrategy: Handles variants (Dragonborn colors, Tiefling bloodlines)
  - Code reduction: 347 → 180 lines (-48%)
- **ClassImporter:** Refactored to use Strategy Pattern (2 strategies)
  - BaseClassStrategy: Handles base classes with spellcasting detection (Wizard, Fighter)
  - SubclassStrategy: Handles subclasses with parent resolution (School of Evocation)
  - Code reduction: 263 → 120 lines (-54%)

#### Added
- 5 new strategy classes (3 race, 2 class)
- 58 new strategy unit tests with real XML fixtures
- Strategy statistics logging and display
- AbstractRaceStrategy and AbstractClassStrategy base classes
```

### 3. Session Handover Document

**File:** `docs/SESSION-HANDOVER-2025-11-22-IMPORTER-STRATEGY-REFACTOR-PHASE1.md`

**Contents:**
- Executive summary
- What was accomplished (detailed)
- Code metrics (before/after)
- Test results
- Files created/modified
- Commits from session
- Verification steps
- Next steps (Phases 2-3)
- Lessons learned

---

## Metrics & KPIs

### Code Metrics

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| RaceImporter lines | 347 | ~180 | -48% |
| ClassImporter lines | 263 | ~120 | -54% |
| Total importer lines | 610 | ~300 | -51% |
| Strategy lines | 0 | ~270 | New code (focused) |
| Test count | 1,141 | 1,199 | +58 tests |
| Test assertions | ~6,328 | ~6,510 | +182 assertions |

### Quality Metrics

- **Test Coverage:** 85%+ on all strategies (unit tests with real fixtures)
- **Cyclomatic Complexity:** Reduced by ~40% (fewer conditionals)
- **Single Responsibility:** Each strategy <100 lines, single purpose
- **Maintainability Index:** Improved (smaller, focused files)

### Time Metrics

- **Estimated:** 8-12 hours total
- **Breakdown:**
  - RaceImporter: 4-6 hours
  - ClassImporter: 3-4 hours
  - Documentation: 1-2 hours

---

## Conclusion

Phase 1 refactoring delivers significant code quality improvements while establishing architectural consistency with existing Item/Monster importers. The Strategy Pattern provides clear extension points for future enhancements without modifying core import logic.

**Ready for Implementation:** All design decisions made, test strategy defined, workflow documented.

**Next Step:** Set up git worktree and begin TDD implementation following the documented workflow.

---

**Design Approved:** 2025-11-22
**Implementation Start:** Pending worktree setup
**Expected Completion:** 8-12 hours from start
**Branch Strategy:** Isolated worktree, merge to main when complete
