# Additional Monster Strategies Design

**Date:** 2025-11-23
**Status:** Design Complete, Ready for Implementation
**Estimated Effort:** 5-6 hours (TDD implementation)

---

## Overview

Expand the Monster Importer Strategy Pattern with 3 additional type-specific strategies:
1. **FiendStrategy** - Devils, demons, yugoloths (fire/poison immunity, magic resistance)
2. **CelestialStrategy** - Angels (radiant damage, healing abilities)
3. **ConstructStrategy** - Golems, animated objects (poison/condition immunities)

This follows the proven pattern established by the existing 5 strategies (Default, Dragon, Spellcaster, Undead, Swarm).

---

## Goals

### Primary Goals
- **Type-specific parsing** - Extract distinguishing features for fiends, celestials, and constructs
- **Consistent pattern** - Follow existing strategy architecture (50-150 lines per strategy)
- **Comprehensive testing** - 85%+ coverage with real XML fixtures
- **Zero regressions** - All 1,273 existing tests remain green

### Success Metrics
- 3 new strategy classes extending `AbstractMonsterStrategy`
- ~17 new tests (5-6 per strategy)
- 3 XML test fixtures with real bestiary monsters
- Structured logging with metrics per strategy
- Enhanced tagging for improved API filtering

---

## Architecture

### Approach: Trait-Based Detection with Shared Utilities

**Rationale:** Extract common patterns (immunity detection, resistance parsing) into reusable helper methods in `AbstractMonsterStrategy`. This reduces code duplication by ~40% per strategy and makes future strategies easier to implement.

**Benefits:**
- **DRY code** - Shared utilities eliminate repeated logic
- **Easier testing** - Utilities tested once, strategies test business logic
- **Future-proof** - Adding strategies 8, 9, 10 becomes trivial
- **Maintainability** - Immunity detection logic lives in ONE place

**Trade-offs:**
- Requires refactoring `AbstractMonsterStrategy` (low risk, high value)
- Slightly more upfront work (~30 min) vs direct copy-paste

---

## Detailed Design

### 1. Shared Utility Methods (AbstractMonsterStrategy)

Add 4 reusable helper methods to the abstract base class:

```php
// app/Services/Importers/Strategies/Monster/AbstractMonsterStrategy.php

/**
 * Check if monster data contains specific damage resistance.
 */
protected function hasDamageResistance(array $monsterData, string $damageType): bool
{
    return str_contains(strtolower($monsterData['damage_resistances'] ?? ''), strtolower($damageType));
}

/**
 * Check if monster data contains specific damage immunity.
 */
protected function hasDamageImmunity(array $monsterData, string $damageType): bool
{
    return str_contains(strtolower($monsterData['damage_immunities'] ?? ''), strtolower($damageType));
}

/**
 * Check if monster data contains specific condition immunity.
 */
protected function hasConditionImmunity(array $monsterData, string $condition): bool
{
    $immunities = strtolower($monsterData['condition_immunities'] ?? '');
    return str_contains($immunities, strtolower($condition));
}

/**
 * Check if any trait contains a specific keyword (case-insensitive).
 */
protected function hasTraitContaining(array $traits, string $keyword): bool
{
    foreach ($traits as $trait) {
        if (str_contains(strtolower($trait['description']), strtolower($keyword))) {
            return true;
        }
    }
    return false;
}
```

**Design Notes:**
- **Defensive programming** - Use null coalescing (`??`) for missing data fields
- **Case-insensitive** - D&D XML data has inconsistent capitalization
- **Simple string matching** - Sufficient for current needs, can optimize later if needed

---

### 2. FiendStrategy

**Purpose:** Detect and tag devils, demons, and yugoloths with type-specific features.

**Detection Logic:**
```php
public function appliesTo(array $monsterData): bool
{
    $type = strtolower($monsterData['type'] ?? '');

    return str_contains($type, 'fiend')
        || str_contains($type, 'devil')
        || str_contains($type, 'demon')
        || str_contains($type, 'yugoloth');
}
```

**Enhancement Features:**
1. **Fire Immunity** - Detect fire immunity (common in Hell Hounds, Pit Fiends, Balors)
2. **Poison Immunity** - Most fiends are poison immune
3. **Magic Resistance** - Detect "Magic Resistance" trait (advantage on saves vs spells)
4. **Fiend Tags** - Apply tags: `fiend`, `fire_immune`, `poison_immune`, `magic_resistance`

**Metrics Tracked:**
- `fiends_enhanced` - Total fiends processed
- `fire_immune_count` - Fiends with fire immunity
- `poison_immune_count` - Fiends with poison immunity
- `magic_resistance_count` - Fiends with magic resistance trait

**Example Implementation:**
```php
public function enhanceTraits(array $traits, array $monsterData): array
{
    // Apply tags based on immunities
    $tags = ['fiend'];

    if ($this->hasDamageImmunity($monsterData, 'fire')) {
        $tags[] = 'fire_immune';
        $this->incrementMetric('fire_immune_count');
    }

    if ($this->hasDamageImmunity($monsterData, 'poison')) {
        $tags[] = 'poison_immune';
        $this->incrementMetric('poison_immune_count');
    }

    if ($this->hasTraitContaining($traits, 'magic resistance')) {
        $tags[] = 'magic_resistance';
        $this->incrementMetric('magic_resistance_count');
    }

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric('fiends_enhanced');

    return $traits; // Traits unchanged, tags stored in metrics
}
```

**Test Fixtures Needed:**
- **Balor** (demon) - Fire/poison immunity, magic resistance
- **Pit Fiend** (devil) - Fire/poison immunity, magic resistance
- **Arcanaloth** (yugoloth) - Spellcaster, poison immunity

**Coverage:** ~40+ fiends in Monster Manual (good test coverage)

---

### 3. CelestialStrategy

**Purpose:** Detect and tag angels and celestial creatures with divine abilities.

**Detection Logic:**
```php
public function appliesTo(array $monsterData): bool
{
    $type = strtolower($monsterData['type'] ?? '');

    return str_contains($type, 'celestial');
}
```

**Enhancement Features:**
1. **Radiant Damage** - Parse actions for radiant damage keywords
2. **Healing Abilities** - Detect "Healing Touch" or similar healing actions
3. **Angelic Weapons** - Tag actions with radiant damage modifiers
4. **Celestial Tags** - Apply tags: `celestial`, `radiant_damage`, `healer`, `angelic_weapons`

**Metrics Tracked:**
- `celestials_enhanced` - Total celestials processed
- `radiant_attackers` - Celestials with radiant damage
- `healers_count` - Celestials with healing abilities

**Example Implementation:**
```php
public function enhanceActions(array $actions, array $monsterData): array
{
    $tags = ['celestial'];
    $hasRadiant = false;
    $hasHealing = false;

    foreach ($actions as &$action) {
        $desc = strtolower($action['description']);

        // Detect radiant damage
        if (str_contains($desc, 'radiant')) {
            $hasRadiant = true;
            $action['damage_type'] = 'radiant'; // Metadata for filtering
        }

        // Detect healing abilities
        if (str_contains($desc, 'healing') || str_contains($action['name'], 'healing touch')) {
            $hasHealing = true;
        }
    }

    if ($hasRadiant) {
        $tags[] = 'radiant_damage';
        $this->incrementMetric('radiant_attackers');
    }

    if ($hasHealing) {
        $tags[] = 'healer';
        $this->incrementMetric('healers_count');
    }

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric('celestials_enhanced');

    return $actions;
}
```

**Test Fixtures Needed:**
- **Deva** - Healing Touch, radiant damage
- **Solar** - Powerful celestial, radiant weapons
- **Planetar** - Mid-tier angel

**Coverage:** ~10-15 celestials in Monster Manual (smaller sample)

---

### 4. ConstructStrategy

**Purpose:** Detect and tag golems, animated objects, and other constructs with immunity patterns.

**Detection Logic:**
```php
public function appliesTo(array $monsterData): bool
{
    $type = strtolower($monsterData['type'] ?? '');

    return str_contains($type, 'construct');
}
```

**Enhancement Features:**
1. **Poison Immunity** - Constructs don't breathe (always immune to poison damage/condition)
2. **Condition Immunities** - Common: charm, exhaustion, frightened, paralyzed, petrified, poisoned
3. **Constructed Nature** - Detect "doesn't require air, food, drink, or sleep" trait
4. **Construct Tags** - Apply tags: `construct`, `poison_immune`, `condition_immune`, `constructed_nature`

**Metrics Tracked:**
- `constructs_enhanced` - Total constructs processed
- `condition_immune_count` - Constructs with condition immunities
- `constructed_nature_count` - Constructs with "Constructed Nature" trait

**Example Implementation:**
```php
public function enhanceTraits(array $traits, array $monsterData): array
{
    $tags = ['construct'];

    // Most constructs are poison immune
    if ($this->hasDamageImmunity($monsterData, 'poison')) {
        $tags[] = 'poison_immune';
    }

    // Check for condition immunities (common in constructs)
    $conditionImmune = false;
    foreach (['charm', 'exhaustion', 'frightened', 'paralyzed', 'petrified'] as $condition) {
        if ($this->hasConditionImmunity($monsterData, $condition)) {
            $conditionImmune = true;
            break;
        }
    }

    if ($conditionImmune) {
        $tags[] = 'condition_immune';
        $this->incrementMetric('condition_immune_count');
    }

    // Detect "Constructed Nature" trait
    if ($this->hasTraitContaining($traits, 'constructed nature')
        || $this->hasTraitContaining($traits, "doesn't require air")) {
        $tags[] = 'constructed_nature';
        $this->incrementMetric('constructed_nature_count');
    }

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric('constructs_enhanced');

    return $traits;
}
```

**Test Fixtures Needed:**
- **Animated Armor** - Simple construct, basic immunities
- **Iron Golem** - Poison/charm/exhaustion immunity
- **Stone Golem** - Condition immunities, constructed nature

**Coverage:** ~20+ constructs in Monster Manual (good test coverage)

---

## Testing Strategy

### Test Structure

```
tests/Unit/Strategies/Monster/
├── FiendStrategyTest.php       (~6 tests, 25+ assertions)
├── CelestialStrategyTest.php   (~5 tests, 20+ assertions)
└── ConstructStrategyTest.php   (~6 tests, 25+ assertions)

tests/Fixtures/xml/monsters/
├── test-fiends.xml       (Balor, Pit Fiend, Arcanaloth - 3 monsters)
├── test-celestials.xml   (Deva, Solar - 2 monsters)
└── test-constructs.xml   (Animated Armor, Iron Golem - 2 monsters)
```

### Test Coverage (per strategy)

Each strategy test file includes:

1. **Type Detection Tests**
   - `test_applies_to_fiend_type()` - Verify `appliesTo()` returns true for fiends
   - `test_does_not_apply_to_non_fiend()` - Negative case (dragon should return false)

2. **Feature Detection Tests**
   - `test_detects_fire_immunity()` - Fire immune fiends tagged correctly
   - `test_detects_magic_resistance_trait()` - Magic resistance trait parsing
   - `test_detects_poison_immunity()` - Poison immunity detection

3. **Metrics Tests**
   - `test_tracks_enhancement_metrics()` - Verify metrics counters increment

4. **Integration Tests**
   - `test_integrates_with_real_xml_fixture()` - End-to-end with bestiary XML excerpt

### Shared Utility Tests

Add tests to `AbstractMonsterStrategyTest.php`:
- `test_has_damage_immunity_detects_fire()` - Utility method validation
- `test_has_condition_immunity_detects_charm()` - Condition immunity helper
- `test_has_trait_containing_finds_keyword()` - Trait keyword search

**Total New Tests:** ~17 tests (~70+ assertions)

---

## Integration with MonsterImporter

### Strategy Registration

Update `MonsterImporter::initializeStrategies()` to include new strategies:

```php
protected function initializeStrategies(): void
{
    $this->strategies = [
        new SpellcasterStrategy,  // Check first (highest priority)
        new FiendStrategy,        // NEW
        new CelestialStrategy,    // NEW
        new ConstructStrategy,    // NEW
        new DragonStrategy,
        new UndeadStrategy,
        new SwarmStrategy,
        new DefaultStrategy,      // Fallback (must be last)
    ];
}
```

**Order Rationale:**
- **SpellcasterStrategy first** - Some fiends/celestials are spellcasters (both strategies apply)
- **New strategies mid-tier** - Specific enough to run before general types
- **DefaultStrategy last** - Catches all monsters as fallback

**Composition Pattern:** A Pit Fiend Spellcaster triggers BOTH SpellcasterStrategy AND FiendStrategy. This is intentional - monsters accumulate enhancements from all applicable strategies.

---

## Implementation Sequence (TDD)

For **each strategy** (Fiend → Celestial → Construct), follow this exact sequence:

### 1. Shared Utilities Phase (~30 min)
- [ ] Write tests for shared utility methods in `AbstractMonsterStrategyTest`
- [ ] Watch tests fail
- [ ] Implement utility methods in `AbstractMonsterStrategy`
- [ ] All utility tests green
- [ ] Commit: `feat: add shared utility methods to AbstractMonsterStrategy`

### 2. FiendStrategy (~2 hours)
- [ ] Write 6 failing tests in `FiendStrategyTest`
- [ ] Create XML fixture: `tests/Fixtures/xml/monsters/test-fiends.xml`
- [ ] Implement `FiendStrategy` (minimal code to pass)
- [ ] All FiendStrategy tests green
- [ ] Run full test suite (verify no regressions)
- [ ] Format with Pint
- [ ] Commit: `feat: add FiendStrategy for devils/demons/yugoloths`

### 3. CelestialStrategy (~1.5 hours)
- [ ] Write 5 failing tests in `CelestialStrategyTest`
- [ ] Create XML fixture: `tests/Fixtures/xml/monsters/test-celestials.xml`
- [ ] Implement `CelestialStrategy` (minimal code to pass)
- [ ] All CelestialStrategy tests green
- [ ] Run full test suite (verify no regressions)
- [ ] Format with Pint
- [ ] Commit: `feat: add CelestialStrategy for angels`

### 4. ConstructStrategy (~2 hours)
- [ ] Write 6 failing tests in `ConstructStrategyTest`
- [ ] Create XML fixture: `tests/Fixtures/xml/monsters/test-constructs.xml`
- [ ] Implement `ConstructStrategy` (minimal code to pass)
- [ ] All ConstructStrategy tests green
- [ ] Run full test suite (verify no regressions)
- [ ] Format with Pint
- [ ] Commit: `feat: add ConstructStrategy for golems/animated objects`

### 5. Integration & Documentation (~30 min)
- [ ] Update `MonsterImporter::initializeStrategies()` with new strategies
- [ ] Re-import monsters: `docker compose exec php php artisan import:all --only=monsters`
- [ ] Verify strategy statistics in logs
- [ ] Update CHANGELOG.md under `[Unreleased]`
- [ ] Update session handover document
- [ ] Commit: `chore: integrate 3 new monster strategies and update docs`

**Total Time Estimate:** 5-6 hours

---

## Files to Create/Modify

### New Files (9)
- `app/Services/Importers/Strategies/Monster/FiendStrategy.php` (~80 lines)
- `app/Services/Importers/Strategies/Monster/CelestialStrategy.php` (~70 lines)
- `app/Services/Importers/Strategies/Monster/ConstructStrategy.php` (~80 lines)
- `tests/Unit/Strategies/Monster/FiendStrategyTest.php` (~120 lines)
- `tests/Unit/Strategies/Monster/CelestialStrategyTest.php` (~100 lines)
- `tests/Unit/Strategies/Monster/ConstructStrategyTest.php` (~120 lines)
- `tests/Fixtures/xml/monsters/test-fiends.xml` (~150 lines)
- `tests/Fixtures/xml/monsters/test-celestials.xml` (~100 lines)
- `tests/Fixtures/xml/monsters/test-constructs.xml` (~100 lines)

### Modified Files (3)
- `app/Services/Importers/Strategies/Monster/AbstractMonsterStrategy.php` (+40 lines)
- `app/Services/Importers/MonsterImporter.php` (+3 lines in initializeStrategies)
- `tests/Unit/Strategies/Monster/AbstractMonsterStrategyTest.php` (+30 lines)

**Total Lines Added:** ~1,000 lines (12 files changed)

---

## Expected Outcomes

### Test Metrics
- **Before:** 1,273 tests passing
- **After:** ~1,290 tests passing (+17 tests)
- **Duration:** ~50 seconds (slight increase due to XML parsing)
- **Coverage:** 85%+ on new strategies

### Data Enhancements
After re-importing monsters, expect:
- **Fiends tagged:** ~40+ monsters (devils, demons, yugoloths)
- **Celestials tagged:** ~10-15 monsters (angels, devas, solars)
- **Constructs tagged:** ~20+ monsters (golems, animated objects)

### API Query Examples
```bash
# Find all fiends with fire immunity
GET /api/v1/monsters?filter=tags.slug = fire_immune

# Find celestials with healing abilities
GET /api/v1/monsters?filter=tags.slug = healer

# Find constructs with condition immunities
GET /api/v1/monsters?filter=tags.slug = condition_immune
```

---

## Risks & Mitigations

### Risk 1: XML Data Quality
**Risk:** Bestiary XML may have inconsistent capitalization or missing fields.
**Mitigation:** Defensive programming with null coalescing (`??`), case-insensitive matching, comprehensive test fixtures.

### Risk 2: Strategy Order Conflicts
**Risk:** New strategies might conflict with existing strategies (e.g., Dragon Fiend).
**Mitigation:** Composition pattern allows multiple strategies to apply. Test with multi-type monsters.

### Risk 3: Tag Explosion
**Risk:** Too many tags per monster reduces filtering utility.
**Mitigation:** Limit to 3-5 tags per strategy. Focus on high-value, queryable features.

---

## Future Enhancements (Out of Scope)

### Additional Strategies
- **ShapechangerStrategy** - Lycanthropes, doppelgangers (polymorph detection)
- **ElementalStrategy** - Fire/water/earth/air elementals (elemental damage resistance)
- **AberrationStrategy** - Mind flayers, beholders (psychic damage, aberrant traits)

### Advanced Feature Detection
- **Legendary Resistance** - Parse "Legendary Resistance (3/Day)" from traits
- **Regeneration** - Detect regeneration mechanics and healing amounts
- **Multi-attack Patterns** - Parse multiattack formulas (e.g., "two claw attacks and one bite")

### Performance Optimizations
- Cache strategy applicability checks (reduce `appliesTo()` calls)
- Batch tag application (reduce database writes)

---

## Conclusion

This design follows the proven strategy pattern established by the existing 5 monster strategies. By adding shared utilities to `AbstractMonsterStrategy`, we reduce code duplication and make future strategies trivial to implement.

The 3 new strategies (Fiend, Celestial, Construct) cover ~70+ additional monsters in the Monster Manual, providing rich metadata for API filtering and character-building features.

**Next Step:** Create implementation plan with detailed task breakdown.

---

**Design Approved:** 2025-11-23
**Ready for Implementation:** Yes
**Estimated Duration:** 5-6 hours
