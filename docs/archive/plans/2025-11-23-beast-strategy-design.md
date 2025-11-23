# BeastStrategy Design

**Date:** 2025-11-23
**Status:** Design Complete - Ready for Implementation
**Strategy:** BeastStrategy (Single Strategy)

---

## Overview

Implement BeastStrategy to tag 102 beast-type monsters (17% of all monsters - highest single type) with D&D 5e mechanical features for tactical encounter design and filtering.

**Current State:**
- 11 existing strategies (8 Phase 1 + 3 Phase 2)
- 119 monsters (20%) have semantic tags
- Beast is the most common monster type (102 beasts)

**Goal:**
- Add BeastStrategy covering all 102 beasts
- Tag common beast mechanics: keen senses, pack tactics, charge, special movement
- Enable tactical queries for DMs and players
- Increase tagged monster coverage to ~140 (23%)

**Expected Impact:**
- Largest single-strategy impact (102 monsters)
- High tactical value for encounter design
- Enables ranger/druid optimization queries
- Completes coverage of top 5 monster types

---

## Architecture

### Strategy Pattern (Type-Specific)

BeastStrategy is a **type-specific strategy** (not cross-cutting) that detects pure beast type.

**Strategy Order:**
```php
$this->strategies = [
    new SpellcasterStrategy,   // 1. Highest priority
    new FiendStrategy,         // 2-4. Type-specific (Phase 1)
    new CelestialStrategy,
    new ConstructStrategy,
    new ElementalStrategy,     // 5-7. Type-specific (Phase 2)
    new AberrationStrategy,
    new BeastStrategy,         // 8. NEW - Type-specific
    new ShapechangerStrategy,  // 9. Cross-cutting (after type-specific)
    new DragonStrategy,        // 10-12. Type-specific (existing)
    new UndeadStrategy,
    new SwarmStrategy,
    new DefaultStrategy,       // 13. Fallback (always last)
];
```

**Rationale:** Type-specific strategies run before cross-cutting ShapechangerStrategy to enable composition (e.g., a shapechanger beast would get both tags).

---

## Detection Logic

### Type Detection

```php
public function appliesTo(array $monsterData): bool
{
    return str_contains(strtolower($monsterData['type'] ?? ''), 'beast');
}
```

**Simple type-based detection** - matches all monsters with `type = 'beast'` (case-insensitive).

**Coverage:** 102 beasts (17% of all monsters)

---

## Enhancement Method - Single Phase (Traits Only)

### Trait Enhancement

```php
public function enhanceTraits(array $traits, array $monsterData): array
{
    $tags = ['beast'];

    // 1. Keen Senses - heightened perception/tracking
    if ($this->hasTraitContaining($traits, 'keen smell')
        || $this->hasTraitContaining($traits, 'keen sight')
        || $this->hasTraitContaining($traits, 'keen hearing')) {
        $tags[] = 'keen_senses';
        $this->incrementMetric('keen_senses_count');
    }

    // 2. Pack Tactics - cooperative hunting
    if ($this->hasTraitContaining($traits, 'pack tactics')) {
        $tags[] = 'pack_tactics';
        $this->incrementMetric('pack_tactics_count');
    }

    // 3. Charge/Pounce - movement-based attacks
    if ($this->hasTraitContaining($traits, 'charge')
        || $this->hasTraitContaining($traits, 'pounce')
        || $this->hasTraitContaining($traits, 'trampling charge')) {
        $tags[] = 'charge';
        $this->incrementMetric('charge_count');
    }

    // 4. Special Movement - spider climb, web walker, amphibious
    if ($this->hasTraitContaining($traits, 'spider climb')
        || $this->hasTraitContaining($traits, 'web walker')
        || $this->hasTraitContaining($traits, 'amphibious')) {
        $tags[] = 'special_movement';
        $this->incrementMetric('special_movement_count');
    }

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric('beasts_enhanced');

    return $traits;
}
```

**Why Single-Phase?**
- All beast mechanics are **trait-based** (no action-specific detection needed)
- Simpler than AberrationStrategy's two-phase approach
- Follows pattern of ElementalStrategy and ShapechangerStrategy

---

## Tags Applied

| Tag | Detection Signal | D&D Meaning | Tactical Use Case |
|-----|-----------------|-------------|-------------------|
| `beast` | Always applied | Natural animal type | Filter all beasts |
| `keen_senses` | "Keen Smell", "Keen Sight", "Keen Hearing" traits | Heightened perception, hard to surprise | Rangers (tracking), stealth encounters, ambush prevention |
| `pack_tactics` | "Pack Tactics" trait | Advantage on attacks when ally nearby | Tactical combat positioning, pack encounters (wolves, hyenas) |
| `charge` | "Charge", "Pounce", "Trampling Charge" traits | Movement attack bonus damage | Mounted combat, open terrain, kiting tactics |
| `special_movement` | "Spider Climb", "Web Walker", "Amphibious" traits | Non-standard movement modes | Dungeon verticality, underwater encounters, difficult terrain |

---

## Expected Coverage

Based on 102 beasts in the bestiary files:

| Tag | Estimated Count | Examples |
|-----|----------------|----------|
| `beast` | 102 (100%) | All beasts |
| `keen_senses` | ~40 (39%) | Wolf, Brown Bear, Giant Eagle, Lion, Panther |
| `pack_tactics` | ~15 (15%) | Wolf, Dire Wolf, Hyena, Jackal, Velociraptor |
| `charge` | ~25 (25%) | Lion, Boar, Rhinoceros, Elk, Allosaurus |
| `special_movement` | ~10 (10%) | Giant Spider, Octopus, Giant Frog, Crocodile |

**Notes:**
- Many beasts will have **no subtypes** (basic creatures like rats, cats, horses)
- Some beasts will have **multiple tags** (Wolf: keen_senses + pack_tactics)
- Estimated ~60-70 beasts (60%) will have at least one subtype tag

---

## Detection Patterns

### 1. Keen Senses Detection

**Keywords:** "keen smell", "keen sight", "keen hearing"

**Examples:**
- **Wolf:** "Keen Hearing and Smell" → `keen_senses`
- **Brown Bear:** "Keen Smell" → `keen_senses`
- **Giant Eagle:** "Keen Sight" → `keen_senses`

**D&D Mechanics:** Advantage on Perception checks, harder to surprise, better tracking

---

### 2. Pack Tactics Detection

**Keyword:** "pack tactics"

**Examples:**
- **Wolf:** "Pack Tactics" → `pack_tactics`
- **Dire Wolf:** "Pack Tactics" → `pack_tactics`
- **Hyena:** "Pack Tactics" → `pack_tactics`

**D&D Mechanics:** Advantage on attacks when ally within 5 feet of target

---

### 3. Charge/Pounce Detection

**Keywords:** "charge", "pounce", "trampling charge"

**Examples:**
- **Lion:** "Pounce" → `charge`
- **Boar:** "Charge" → `charge`
- **Elephant:** "Trampling Charge" → `charge`

**D&D Mechanics:** Extra damage if creature moves 20+ feet before attacking

---

### 4. Special Movement Detection

**Keywords:** "spider climb", "web walker", "amphibious"

**Examples:**
- **Giant Spider:** "Spider Climb", "Web Walker" → `special_movement`
- **Crocodile:** "Amphibious" (can breathe air/water) → `special_movement`
- **Giant Frog:** "Amphibious" → `special_movement`

**D&D Mechanics:** Non-standard movement modes (walls, webs, water breathing)

---

## Test Coverage

### Test Structure (8 Tests)

Following the proven Phase 2 pattern:

```php
class BeastStrategyTest extends TestCase
{
    #[Test]
    public function it_applies_to_beast_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'beast']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Beast']));
    }

    #[Test]
    public function it_does_not_apply_to_non_beast_type(): void
    {
        $this->assertFalse($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'fiend']));
    }

    #[Test]
    public function it_detects_keen_senses(): void
    {
        // Test "Keen Smell", "Keen Sight", "Keen Hearing"
    }

    #[Test]
    public function it_detects_pack_tactics(): void
    {
        // Test "Pack Tactics" trait
    }

    #[Test]
    public function it_detects_charge_mechanics(): void
    {
        // Test "Charge", "Pounce", "Trampling Charge"
    }

    #[Test]
    public function it_detects_special_movement(): void
    {
        // Test "Spider Climb", "Web Walker", "Amphibious"
    }

    #[Test]
    public function it_tracks_beast_metrics(): void
    {
        // Verify metrics: beasts_enhanced, keen_senses_count, etc.
    }

    #[Test]
    public function it_integrates_with_real_xml_fixture(): void
    {
        // End-to-end test with real XML parsing
    }
}
```

**Total:** 8 tests, ~25 assertions

---

### XML Test Fixture (4 Beasts)

**File:** `tests/Fixtures/xml/monsters/test-beasts.xml`

**Monsters:**
1. **Wolf** - Keen Hearing and Smell + Pack Tactics (multi-tag)
2. **Brown Bear** - Keen Smell only (single subtype)
3. **Lion** - Pounce + Keen Smell (charge + senses)
4. **Giant Spider** - Spider Climb + Web Sense + Web Walker (special movement)

**Coverage:**
- ✅ All 4 tag types tested
- ✅ Multi-tag beasts (Wolf, Lion)
- ✅ Single-tag beasts (Brown Bear, Giant Spider)
- ✅ Real D&D 5e stat blocks from Monster Manual

---

## Shared Utilities Usage

**Utilities Used:**
- ✅ `hasTraitContaining(array $traits, string $keyword): bool` - 10 times
- ✅ `setMetric(string $key, mixed $value): void` - 1 time
- ✅ `incrementMetric(string $key): void` - 5 times

**No new utility methods needed** - continues to prove AbstractMonsterStrategy abstraction scales well.

---

## Implementation Estimate

| Task | Duration | Complexity |
|------|----------|-----------|
| Create XML fixture | 15 min | Low |
| Write failing tests | 20 min | Low |
| Implement BeastStrategy | 15 min | Low |
| Verify tests pass | 5 min | Low |
| Format & commit | 5 min | Low |
| **Total** | **~1 hour** | **Low** |

**Risk Level:** Very Low
- Identical pattern to ElementalStrategy (single-phase)
- All detection uses existing shared utilities
- No new complexity introduced

---

## API Query Examples (After Implementation)

### Basic Queries

```bash
# Find all beasts
GET /api/v1/monsters?filter=tags.slug = beast

# Find pack tactics beasts (wolves, hyenas, raptors)
GET /api/v1/monsters?filter=tags.slug = pack_tactics

# Find beasts with keen senses (tracking, stealth encounters)
GET /api/v1/monsters?filter=tags.slug = keen_senses

# Find charging beasts (mounted combat, open terrain)
GET /api/v1/monsters?filter=tags.slug = charge

# Find beasts with special movement (spiders, aquatic)
GET /api/v1/monsters?filter=tags.slug = special_movement
```

### Advanced Queries

```bash
# Wolf pack encounters (pack tactics + keen senses)
GET /api/v1/monsters?filter=tags.slug = pack_tactics AND tags.slug = keen_senses

# Mounted combat chargers (charge + CR 2-5)
GET /api/v1/monsters?filter=tags.slug = charge AND challenge_rating >= 2 AND challenge_rating <= 5

# Dungeon vertical encounters (special movement + CR 1-3)
GET /api/v1/monsters?filter=tags.slug = special_movement AND challenge_rating <= 3

# Stealth encounter design (keen senses to avoid surprise)
GET /api/v1/monsters?filter=tags.slug = keen_senses AND challenge_rating <= 4
```

### Use Cases

**For DMs:**
- Design pack encounters (wolves, hyenas) with pack tactics
- Plan mounted combat with charging beasts
- Create stealth encounters accounting for keen senses
- Design vertical dungeons with climbing beasts

**For Players:**
- Rangers: Find trackable beasts (keen senses)
- Druids: Optimize Wild Shape choices by mechanics
- Fighters: Identify mounted combat targets (charge)
- Rogues: Avoid beasts with keen senses for stealth

---

## Metrics Tracked

**Metrics in `extractMetadata()`:**
```php
[
    'metrics' => [
        'beasts_enhanced' => 1,           // Total beasts processed
        'keen_senses_count' => 1,         // Beasts with keen senses
        'pack_tactics_count' => 1,        // Beasts with pack tactics
        'charge_count' => 1,              // Beasts with charge/pounce
        'special_movement_count' => 1,    // Beasts with spider climb/amphibious/etc
        'tags_applied' => ['beast', 'keen_senses', 'pack_tactics'], // Tags applied
    ],
    'warnings' => [], // Any detection issues
]
```

**Logged to:** `storage/logs/import-strategy-{date}.log`

**Sample Log Entry:**
```json
{
    "monster": "Wolf",
    "warnings": [],
    "metrics": {
        "beasts_enhanced": 1,
        "keen_senses_count": 1,
        "pack_tactics_count": 1,
        "tags_applied": ["beast", "keen_senses", "pack_tactics"]
    }
}
```

---

## Success Criteria

- ✅ All 102 beasts tagged with `beast`
- ✅ ~40 beasts tagged with `keen_senses`
- ✅ ~15 beasts tagged with `pack_tactics`
- ✅ ~25 beasts tagged with `charge`
- ✅ ~10 beasts tagged with `special_movement`
- ✅ 8 tests passing (all green)
- ✅ Code formatted with Pint
- ✅ Integration with MonsterImporter (strategy registration)
- ✅ Tags persisted to database via `syncTags()`
- ✅ Import logs show strategy statistics

---

## Future Enhancements (Not in Scope)

**Additional Beast Tags (if needed):**
- `ambusher` - Surprise attack mechanics
- `grappler` - Grappling attacks
- `venomous` - Poison attacks
- `swarm_creature` - Individual swarm components

**Performance Optimization:**
- Cache beast tag queries (Redis, 3600s TTL)
- Meilisearch integration for tag filtering

**API Features:**
- Tag-based filtering in MonsterController
- Combine beast tags with CR/size/alignment filters

---

## Conclusion

BeastStrategy is the **highest-impact single strategy** remaining (102 monsters, 17% of total). The design follows the proven Phase 2 pattern with single-phase enhancement, comprehensive tag coverage for D&D 5e beast mechanics, and full test coverage.

**Implementation is straightforward** (~1 hour) with very low risk due to pattern consistency with existing strategies.

**Status:** Ready for implementation
**Next Step:** Create implementation plan with task-by-task breakdown

---

**Design Approved By:** [User Confirmation Pending]
**Implementation Plan:** TBD (use superpowers:writing-plans skill)
