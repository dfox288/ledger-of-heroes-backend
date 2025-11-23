# Additional Monster Strategies - Phase 2 Design

**Date:** 2025-11-23
**Status:** Design Complete - Ready for Implementation
**Strategies:** ElementalStrategy, ShapechangerStrategy, AberrationStrategy

---

## Overview

Implement 3 additional monster strategies to expand type-specific tagging and enable advanced API filtering. This is Phase 2 following the successful implementation of FiendStrategy, CelestialStrategy, and ConstructStrategy.

**Current State:**
- 8 existing strategies (Fiend, Celestial, Construct, Dragon, Spellcaster, Undead, Swarm, Default)
- 4 shared utility methods in AbstractMonsterStrategy
- 1,303 passing tests
- 72 monsters enhanced with Phase 1 tags

**Goals:**
- Add 3 new strategies covering ~70 additional monsters
- Enable elemental subtype filtering (fire/water/earth/air)
- Detect cross-cutting shapechanger trait
- Tag aberration mechanics (psychic, telepathy, mind control, antimagic)

**Expected Impact:**
- ~142 monsters total with type-specific tags (72 Phase 1 + 70 Phase 2)
- Advanced queries: "fire elementals", "shapechangers", "psychic damage monsters"
- Comprehensive monster categorization for tactical planning

---

## Architecture

### Strategy Pattern (Composition-Based)

Each strategy is independent and composable. A single monster can trigger multiple strategies:
- **Water Elemental Spellcaster** → SpellcasterStrategy + ElementalStrategy
- **Doppelganger Mind Flayer** → ShapechangerStrategy + AberrationStrategy (hypothetical)

**Strategy Order:**
```php
$this->strategies = [
    new SpellcasterStrategy,   // 1. Highest priority (has spells)
    new FiendStrategy,         // 2. Type-specific
    new CelestialStrategy,     // 3. Type-specific
    new ConstructStrategy,     // 4. Type-specific
    new ElementalStrategy,     // 5. NEW - Type-specific
    new AberrationStrategy,    // 6. NEW - Type-specific
    new ShapechangerStrategy,  // 7. NEW - Cross-cutting (after type-specific)
    new DragonStrategy,        // 8. Type-specific
    new UndeadStrategy,        // 9. Type-specific
    new SwarmStrategy,         // 10. Type-specific
    new DefaultStrategy,       // 11. Fallback (always last)
];
```

**Rationale:** Shapechanger runs after type-specific strategies so monsters like "humanoid (shapechanger)" get both base type handling AND shapechanger tagging.

---

## Strategy 1: ElementalStrategy

**Priority:** Implement First (Simplest)
**Coverage:** ~25 monsters
**Complexity:** Low (pure type + name/immunity-based subtypes)

### Detection

```php
public function appliesTo(array $monsterData): bool
{
    return str_contains(strtolower($monsterData['type'] ?? ''), 'elemental');
}
```

### Subtype Detection Logic

Three signals in priority order:

1. **Monster Name** - "Fire Elemental", "Water Weird", "Earth Elemental"
2. **Damage Immunity** - Fire immunity → fire elemental
3. **Languages** - Ignan → fire, Aquan → water, Terran → earth, Auran → air

**Example:**
```php
// Fire elemental detection
if (str_contains($name, 'fire')
    || $this->hasDamageImmunity($monsterData, 'fire')
    || str_contains($languages, 'ignan')) {
    $tags[] = 'fire_elemental';
    $this->incrementMetric('fire_elementals');
}
```

### Enhancement Method

```php
public function enhanceTraits(array $traits, array $monsterData): array
{
    $tags = ['elemental'];
    $name = strtolower($monsterData['name'] ?? '');
    $languages = strtolower($monsterData['languages'] ?? '');

    // Fire elemental
    if (str_contains($name, 'fire')
        || $this->hasDamageImmunity($monsterData, 'fire')
        || str_contains($languages, 'ignan')) {
        $tags[] = 'fire_elemental';
        $this->incrementMetric('fire_elementals');
    }

    // Water elemental
    if (str_contains($name, 'water')
        || $this->hasDamageImmunity($monsterData, 'cold')
        || str_contains($languages, 'aquan')) {
        $tags[] = 'water_elemental';
        $this->incrementMetric('water_elementals');
    }

    // Earth elemental
    if (str_contains($name, 'earth')
        || str_contains($languages, 'terran')) {
        $tags[] = 'earth_elemental';
        $this->incrementMetric('earth_elementals');
    }

    // Air elemental
    if (str_contains($name, 'air')
        || str_contains($languages, 'auran')) {
        $tags[] = 'air_elemental';
        $this->incrementMetric('air_elementals');
    }

    // Common elemental immunity
    if ($this->hasDamageImmunity($monsterData, 'poison')) {
        $tags[] = 'poison_immune';
    }

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric('elementals_enhanced');
    return $traits;
}
```

### Tags Applied

- `elemental` (always)
- `fire_elemental` (subtype)
- `water_elemental` (subtype)
- `earth_elemental` (subtype)
- `air_elemental` (subtype)
- `poison_immune` (common trait)

### Test Coverage

**XML Fixture:** `tests/Fixtures/xml/monsters/test-elementals.xml`
- Fire Elemental (name + fire immunity + Ignan language)
- Water Elemental (name + cold immunity + Aquan language)
- Earth Elemental (name + Terran + poison immunity)
- Air Elemental (name + Auran language)

**Tests:**
1. `it_applies_to_elemental_type()`
2. `it_does_not_apply_to_non_elemental_type()`
3. `it_detects_fire_elemental_subtype()`
4. `it_detects_water_elemental_subtype()`
5. `it_detects_earth_elemental_subtype()`
6. `it_detects_air_elemental_subtype()`
7. `it_detects_poison_immunity()`
8. `it_tracks_elemental_metrics()`
9. `it_integrates_with_real_xml_fixture()`

**Total:** 9 tests (~25 assertions)

---

## Strategy 2: ShapechangerStrategy

**Priority:** Implement Second (Moderate Complexity)
**Coverage:** ~18 monsters (cross-cutting across types)
**Complexity:** Moderate (cross-cutting detection + subtypes)

### Detection

```php
public function appliesTo(array $monsterData): bool
{
    $type = strtolower($monsterData['type'] ?? '');

    // Check type field for explicit (shapechanger) marker
    return str_contains($type, 'shapechanger');
}
```

**Note:** Type field is primary detection. Trait-based detection would require parsing traits during `appliesTo()`, which happens before trait parsing. We rely on XML `<type>` field having "(shapechanger)" marker.

### Enhancement Method

```php
public function enhanceTraits(array $traits, array $monsterData): array
{
    $tags = ['shapechanger'];
    $type = strtolower($monsterData['type'] ?? '');
    $name = strtolower($monsterData['name'] ?? '');

    // Lycanthrope detection (werewolves, wereboars, etc.)
    if (str_contains($type, 'lycanthr')
        || str_contains($name, 'were')
        || $this->hasTraitContaining($traits, 'lycanthropy')
        || $this->hasTraitContaining($traits, 'curse of lycanthropy')) {
        $tags[] = 'lycanthrope';
        $this->incrementMetric('lycanthropes');
    }

    // Mimic detection (adhesive, false appearance)
    if (str_contains($name, 'mimic')
        || $this->hasTraitContaining($traits, 'adhesive')
        || ($this->hasTraitContaining($traits, 'false appearance')
            && str_contains($type, 'monstrosity'))) {
        $tags[] = 'mimic';
        $this->incrementMetric('mimics');
    }

    // Doppelganger detection
    if (str_contains($name, 'doppelganger')
        || $this->hasTraitContaining($traits, 'read thoughts')) {
        $tags[] = 'doppelganger';
        $this->incrementMetric('doppelgangers');
    }

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric('shapechangers_enhanced');
    return $traits;
}
```

### Tags Applied

- `shapechanger` (always)
- `lycanthrope` (werewolves, wereboars, wererats, etc.)
- `mimic` (mimics, rug of smothering)
- `doppelganger` (doppelgangers)

### Test Coverage

**XML Fixture:** `tests/Fixtures/xml/monsters/test-shapechangers.xml`
- Werewolf (humanoid shapechanger with lycanthropy curse)
- Doppelganger (monstrosity shapechanger with read thoughts)
- Mimic (monstrosity shapechanger with adhesive + false appearance)

**Tests:**
1. `it_applies_to_shapechanger_type()`
2. `it_does_not_apply_to_non_shapechanger_type()`
3. `it_detects_lycanthrope_subtype()`
4. `it_detects_mimic_subtype()`
5. `it_detects_doppelganger_subtype()`
6. `it_tracks_shapechanger_metrics()`
7. `it_integrates_with_real_xml_fixture()`

**Total:** 7 tests (~20 assertions)

---

## Strategy 3: AberrationStrategy

**Priority:** Implement Third (Most Complex)
**Coverage:** ~27 monsters
**Complexity:** High (multiple mechanical features across traits + actions)

### Detection

```php
public function appliesTo(array $monsterData): bool
{
    return str_contains(strtolower($monsterData['type'] ?? ''), 'aberration');
}
```

### Enhancement Method - Two Phases

**Phase 1: Trait Enhancement**
```php
public function enhanceTraits(array $traits, array $monsterData): array
{
    $tags = ['aberration'];
    $languages = strtolower($monsterData['languages'] ?? '');

    // Telepathy detection (common in aberrations)
    if (str_contains($languages, 'telepathy')) {
        $tags[] = 'telepathy';
        $this->incrementMetric('telepaths');
    }

    // Antimagic detection (beholder antimagic cone)
    if ($this->hasTraitContaining($traits, 'antimagic')) {
        $tags[] = 'antimagic';
        $this->incrementMetric('antimagic_users');
    }

    // Mind control in traits (dominate, enslave)
    if ($this->hasTraitContaining($traits, 'dominate')
        || $this->hasTraitContaining($traits, 'enslave')) {
        $tags[] = 'mind_control';
        $this->incrementMetric('mind_controllers');
    }

    $this->setMetric('tags_applied', $tags);
    return $traits;
}
```

**Phase 2: Action Enhancement**
```php
public function enhanceActions(array $actions, array $monsterData): array
{
    $tags = $this->getMetric('tags_applied') ?? ['aberration'];

    // Psychic damage detection
    foreach ($actions as $action) {
        $desc = strtolower($action['description'] ?? '');

        if (str_contains($desc, 'psychic damage')) {
            $tags[] = 'psychic_damage';
            $this->incrementMetric('psychic_attackers');
            break; // Only tag once
        }

        // Mind control in actions (charm ray, etc.)
        if (!in_array('mind_control', $tags)
            && (str_contains($desc, 'charm') || str_contains($desc, 'dominated'))) {
            $tags[] = 'mind_control';
            $this->incrementMetric('mind_controllers');
        }
    }

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric('aberrations_enhanced');
    return $actions;
}
```

### Mechanical Features Detected

1. **Psychic Damage** - Actions containing "psychic damage"
2. **Telepathy** - Languages field contains "telepathy"
3. **Mind Control** - Traits/actions with "charm", "dominate", "enslave"
4. **Antimagic** - Traits with "antimagic field/cone"

### Tags Applied

- `aberration` (always)
- `psychic_damage` (mind blast, psychic crush)
- `telepathy` (mind flayers, aboleths, intellect devourers)
- `mind_control` (charm, dominate, enslave abilities)
- `antimagic` (beholder antimagic cone)

### Test Coverage

**XML Fixture:** `tests/Fixtures/xml/monsters/test-aberrations.xml`
- Mind Flayer (telepathy + psychic damage via Mind Blast + dominate)
- Beholder (antimagic cone + charm ray)
- Aboleth (telepathy + enslave + psychic damage)

**Tests:**
1. `it_applies_to_aberration_type()`
2. `it_does_not_apply_to_non_aberration_type()`
3. `it_detects_telepathy()`
4. `it_detects_psychic_damage_in_actions()`
5. `it_detects_mind_control_in_traits()`
6. `it_detects_mind_control_in_actions()`
7. `it_detects_antimagic_abilities()`
8. `it_tracks_aberration_metrics()`
9. `it_integrates_with_real_xml_fixture()`

**Total:** 9 tests (~28 assertions)

---

## Shared Utilities

**No new shared methods needed!** All three strategies use existing AbstractMonsterStrategy utilities:

- ✅ `hasDamageImmunity(array $monsterData, string $damageType): bool`
- ✅ `hasTraitContaining(array $traits, string $keyword): bool`
- ✅ `setMetric(string $key, mixed $value): void`
- ✅ `incrementMetric(string $key): void`
- ✅ `getMetric(string $key): mixed`

---

## Testing Strategy

Each strategy follows proven TDD workflow:

1. **Create XML fixture** with 2-4 representative monsters from real bestiary data
2. **Write 7-9 tests** per strategy:
   - Type detection (positive + negative)
   - Feature detection (2-4 tests for main features)
   - Metric tracking
   - End-to-end integration with real XML
3. **Watch tests fail** → Implement minimal code → **Watch tests pass**
4. **Format with Pint** → **Commit with clear message**

**Total New Tests:** ~25 tests (~73 assertions)

---

## Integration into MonsterImporter

### Strategy Registration

```php
protected function initializeStrategies(): void
{
    $this->strategies = [
        new SpellcasterStrategy,
        new FiendStrategy,
        new CelestialStrategy,
        new ConstructStrategy,
        new ElementalStrategy,     // NEW
        new AberrationStrategy,    // NEW
        new ShapechangerStrategy,  // NEW (cross-cutting, after type-specific)
        new DragonStrategy,
        new UndeadStrategy,
        new SwarmStrategy,
        new DefaultStrategy,
    ];
}
```

### Import Process

1. **Run full test suite** - Verify no regressions
2. **Re-import monsters** - `php artisan import:all --only=monsters --skip-migrate`
3. **Verify strategy statistics** - Check `storage/logs/import-strategy-*.log`
4. **Validate tag counts** - Query database for new tags

---

## Expected Outcomes

### Coverage Metrics

| Strategy | Monster Count | Primary Tags | Subtypes |
|----------|---------------|--------------|----------|
| ElementalStrategy | ~25 | elemental, poison_immune | fire/water/earth/air_elemental |
| ShapechangerStrategy | ~18 | shapechanger | lycanthrope, mimic, doppelganger |
| AberrationStrategy | ~27 | aberration, telepathy | psychic_damage, mind_control, antimagic |
| **Total Phase 2** | **~70** | **3 new base types** | **10 new subtypes** |

### API Query Examples

```bash
# Find fire elementals
GET /api/v1/monsters?filter=tags.slug = fire_elemental

# Find all shapechangers
GET /api/v1/monsters?filter=tags.slug = shapechanger

# Find lycanthropes specifically
GET /api/v1/monsters?filter=tags.slug = lycanthrope

# Find monsters with psychic damage
GET /api/v1/monsters?filter=tags.slug = psychic_damage

# Find telepathic aberrations
GET /api/v1/monsters?filter=tags.slug = telepathy AND type LIKE '%aberration%'

# Find mind controllers (useful for resistance planning)
GET /api/v1/monsters?filter=tags.slug = mind_control
```

### Combined with Phase 1 Tags

Total enhanced monsters: **~142** (72 Phase 1 + 70 Phase 2)

**Phase 1 Tags:** fiend, celestial, construct, fire_immune, poison_immune, magic_resistance, radiant_damage, healer, condition_immune, constructed_nature

**Phase 2 Tags:** elemental, shapechanger, aberration, fire_elemental, water_elemental, earth_elemental, air_elemental, lycanthrope, mimic, doppelganger, psychic_damage, telepathy, mind_control, antimagic

**Total Unique Tags:** 24 semantic tags for tactical monster queries

---

## Implementation Timeline

**Estimated Duration:** ~4-6 hours

| Task | Duration | Deliverables |
|------|----------|--------------|
| ElementalStrategy | 1-1.5h | Strategy class, 9 tests, XML fixture |
| ShapechangerStrategy | 1-1.5h | Strategy class, 7 tests, XML fixture |
| AberrationStrategy | 1.5-2h | Strategy class, 9 tests, XML fixture |
| Integration | 0.5h | MonsterImporter update, full test suite |
| Re-import & Validation | 0.5h | Import monsters, verify statistics |
| Documentation | 1h | CHANGELOG, SESSION-HANDOVER, PROJECT-STATUS |

**Total:** 4-6 hours for complete implementation

---

## Success Criteria

- ✅ All ~1,328 tests passing (1,303 existing + ~25 new)
- ✅ 3 new strategy classes (~50-70 lines each)
- ✅ 3 new test files (~120-150 lines each)
- ✅ 3 new XML fixtures (~100-150 lines each)
- ✅ ~70 monsters enhanced with Phase 2 tags
- ✅ Import logs show strategy statistics
- ✅ Code formatted with Pint
- ✅ Documentation updated (CHANGELOG, SESSION-HANDOVER, PROJECT-STATUS)
- ✅ All commits pushed to GitHub

---

## Risk Mitigation

**Risk:** Cross-cutting shapechanger detection misses monsters without `(shapechanger)` in type field
**Mitigation:** Focus on type field detection initially. If coverage is low after import, add trait-based fallback detection.

**Risk:** Elemental subtype detection has false positives (e.g., "Fire Giant" tagged as fire elemental)
**Mitigation:** Require `type = 'elemental'` before subtype detection. Only pure elementals get subtype tags.

**Risk:** Aberration detection complexity leads to bugs
**Mitigation:** TDD with real XML fixtures. Each feature tested independently before integration.

**Risk:** Strategy order affects tag application
**Mitigation:** Shapechanger runs last among new strategies to ensure type-specific tags apply first.

---

## Future Enhancements (Not in Scope)

- **BeastStrategy** - 102 beasts (most common type)
- **FeyStrategy** - 20+ fey creatures
- **OozeStrategy** - 4+ oozes
- **PlantStrategy** - 18+ plant creatures
- **Tag-based filtering in API** - Enable `?filter=tags.slug = fire_immune` in MonsterController
- **Performance optimization** - Cache tag queries with Redis

---

## Conclusion

This design extends the proven Strategy Pattern with 3 new strategies covering ~70 monsters and 14 new semantic tags. The composition-based architecture enables flexible, targeted monster queries for tactical planning and encounter design.

**Status:** Ready for implementation
**Next Step:** Create implementation plan with task-by-task breakdown

---

**Design Approved By:** [User Confirmation Pending]
**Implementation Plan:** TBD (use superpowers:writing-plans skill)
