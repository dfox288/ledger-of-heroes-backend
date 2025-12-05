# Importer & Parser Consolidation Plan

**Created:** 2025-12-05
**Status:** All Phases Complete
**Priority:** Medium
**Estimated Impact:** ~125+ lines of code reduced, improved maintainability
**GitHub Issue:** [#185](https://github.com/dfox288/dnd-rulebook-project/issues/185) (Phases 4-5)

## Progress

- [x] **Phase 1:** Extract `NormalizesSpellNames` trait ✅
- [x] **Phase 2:** Extract `TracksMetricsAndWarnings` trait ✅
- [x] **Phase 3:** Extract `FindsInDescription` trait ✅
- [x] **Phase 4:** Refactor Monster Tag Accumulation ✅ (3 of 7 strategies refactored - BeastStrategy, FiendStrategy, ConstructStrategy)
- [x] **Phase 5:** Standardize Cache Reset Pattern ✅

**Phase 4 Notes:** Added `applyConditionalTags()` to `AbstractMonsterStrategy` for consolidated tag accumulation.
Refactored 3 strategies that had clean trait/immunity/condition check patterns. The remaining 4 strategies
(ElementalStrategy, ShapechangerStrategy, AberrationStrategy, CelestialStrategy) have more complex logic
(name-based checks, language checks, action iteration) that doesn't fit the consolidated pattern cleanly.

---

## Executive Summary

This plan addresses duplicate code patterns identified during the importer/parser audit. The consolidation opportunities are organized by priority and effort, with clear extraction strategies for each.

---

## 1. Extract `NormalizesSpellNames` Trait

**Priority:** HIGH | **Effort:** Low | **Lines Saved:** ~40

### Problem

Identical spell normalization and lookup logic exists in two files:

| Location | Methods |
|----------|---------|
| `SpellcasterStrategy.php:78-110` | `parseSpellNames()`, `normalizeSpellName()`, `findSpell()` |
| `ChargedItemStrategy.php:70-127` | `extractSpells()`, `normalizeSpellName()`, `findSpell()` |

### Implementation

**File:** `app/Services/Concerns/NormalizesSpellNames.php`

```php
<?php

namespace App\Services\Concerns;

use App\Models\Spell;

/**
 * Provides spell name normalization and lookup with caching.
 *
 * Used by monster spellcaster strategy and charged item parser.
 */
trait NormalizesSpellNames
{
    /** @var array<string, Spell|null> Cache of spell lookups */
    private array $spellCache = [];

    /**
     * Normalize spell name to Title Case for database matching.
     */
    protected function normalizeSpellName(string $name): string
    {
        return mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Find spell by name (case-insensitive) with caching.
     */
    protected function findSpell(string $name): ?Spell
    {
        $cacheKey = mb_strtolower($name);

        if (!isset($this->spellCache[$cacheKey])) {
            $this->spellCache[$cacheKey] = Spell::whereRaw('LOWER(name) = ?', [$cacheKey])->first();
        }

        return $this->spellCache[$cacheKey];
    }

    /**
     * Clear the spell cache (useful for testing).
     */
    protected function clearSpellCache(): void
    {
        $this->spellCache = [];
    }
}
```

### Migration Steps

1. Create `app/Services/Concerns/NormalizesSpellNames.php`
2. Update `SpellcasterStrategy`:
   - Add `use NormalizesSpellNames;`
   - Remove `$spellCache` property
   - Remove `normalizeSpellName()` and `findSpell()` methods
   - Keep `parseSpellNames()` (comma-separated parsing is strategy-specific)
3. Update `ChargedItemStrategy`:
   - Add `use NormalizesSpellNames;`
   - Remove `normalizeSpellName()` and `findSpell()` methods
   - Keep `extractSpells()` (regex parsing is strategy-specific)
4. Add tests for the trait in `tests/Unit/Concerns/NormalizesSpellNamesTest.php`

### Tests Required

```php
#[Test]
public function it_normalizes_spell_names_to_title_case(): void
{
    // "cure wounds" → "Cure Wounds"
    // "FIREBALL" → "Fireball"
}

#[Test]
public function it_caches_spell_lookups(): void
{
    // Same spell looked up twice should hit cache
}

#[Test]
public function it_finds_spells_case_insensitively(): void
{
    // "CURE WOUNDS" should find "Cure Wounds"
}
```

---

## 2. Extract `TracksMetricsAndWarnings` Trait

**Priority:** MEDIUM | **Effort:** Low | **Lines Saved:** ~25

### Problem

Identical metric/warning management code exists in:

| Location | Lines |
|----------|-------|
| `AbstractImportStrategy.php:64-80` | `addWarning()`, `incrementMetric()`, `setMetric()` |
| `AbstractItemStrategy.php:59-82` | Same methods with identical implementation |

### Implementation

**File:** `app/Services/Concerns/TracksMetricsAndWarnings.php`

```php
<?php

namespace App\Services\Concerns;

/**
 * Provides warning and metric tracking for import/parse operations.
 */
trait TracksMetricsAndWarnings
{
    protected array $warnings = [];
    protected array $metrics = [];

    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    protected function incrementMetric(string $key, int $amount = 1): void
    {
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = 0;
        }
        $this->metrics[$key] += $amount;
    }

    protected function setMetric(string $key, mixed $value): void
    {
        $this->metrics[$key] = $value;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function reset(): void
    {
        $this->warnings = [];
        $this->metrics = [];
    }
}
```

### Migration Steps

1. Create `app/Services/Concerns/TracksMetricsAndWarnings.php`
2. Update `AbstractImportStrategy`:
   - Add `use TracksMetricsAndWarnings;`
   - Remove `$warnings`, `$metrics` properties
   - Remove `addWarning()`, `incrementMetric()`, `setMetric()`, `getWarnings()`, `getMetrics()`, `reset()` methods
   - Keep `extractMetadata()` (implementation differs slightly)
3. Update `AbstractItemStrategy`:
   - Add `use TracksMetricsAndWarnings;`
   - Remove duplicate methods
4. Update existing tests to work with trait

### Decision Point

**Option A:** Extract trait and use in both classes (recommended)
**Option B:** Have `AbstractItemStrategy` extend `AbstractImportStrategy` (creates coupling between parser and importer domains)

Recommend Option A to keep parsers and importers loosely coupled.

---

## 3. Extract `FindsInDescription` Trait

**Priority:** MEDIUM | **Effort:** Medium | **Lines Saved:** ~60+

### Problem

The "iterate array of keywords and check str_contains" pattern is repeated across multiple item strategies:

| Strategy | Methods | Pattern |
|----------|---------|---------|
| `LegendaryStrategy` | `extractAlignment()`, `extractPersonalityTraits()`, `isSentient()` | Array iteration + str_contains |
| `TattooStrategy` | `extractBodyLocation()`, `extractActivationMethods()` | Array iteration + str_contains |
| `PotionStrategy` | (various checks) | Inline str_contains chains |

### Implementation

**File:** `app/Services/Parsers/Concerns/FindsInDescription.php`

```php
<?php

namespace App\Services\Parsers\Concerns;

/**
 * Provides utilities for extracting keywords from item descriptions.
 */
trait FindsInDescription
{
    /**
     * Find first matching keyword in text.
     *
     * @param string $text Text to search in
     * @param array<string> $keywords Keywords to search for
     * @return string|null First matching keyword, or null
     */
    protected function findFirstKeyword(string $text, array $keywords): ?string
    {
        $textLower = strtolower($text);

        foreach ($keywords as $keyword) {
            if (str_contains($textLower, strtolower($keyword))) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Find all matching keywords in text.
     *
     * @param string $text Text to search in
     * @param array<string> $keywords Keywords to search for
     * @return array<string> All matching keywords
     */
    protected function findAllKeywords(string $text, array $keywords): array
    {
        $matches = [];
        $textLower = strtolower($text);

        foreach ($keywords as $keyword) {
            if (str_contains($textLower, strtolower($keyword))) {
                $matches[] = $keyword;
            }
        }

        return $matches;
    }

    /**
     * Check if any keyword exists in text.
     *
     * @param string $text Text to search in
     * @param array<string> $keywords Keywords to check
     */
    protected function hasAnyKeyword(string $text, array $keywords): bool
    {
        return $this->findFirstKeyword($text, $keywords) !== null;
    }
}
```

### Migration Examples

**Before (LegendaryStrategy):**
```php
private function extractAlignment(string $description, string $detail): ?string
{
    $text = strtolower($description.' '.$detail);
    $alignments = ['lawful good', 'lawful neutral', ...];

    foreach ($alignments as $alignment) {
        if (str_contains($text, $alignment)) {
            return $alignment;
        }
    }
    return null;
}
```

**After:**
```php
private function extractAlignment(string $description, string $detail): ?string
{
    $alignments = ['lawful good', 'lawful neutral', ...];
    return $this->findFirstKeyword($description . ' ' . $detail, $alignments);
}
```

### Migration Steps

1. Create `app/Services/Parsers/Concerns/FindsInDescription.php`
2. Update `LegendaryStrategy`:
   - Add `use FindsInDescription;`
   - Simplify `extractAlignment()`, `extractPersonalityTraits()`, `isSentient()`
3. Update `TattooStrategy`:
   - Simplify `extractBodyLocation()`, `extractActivationMethods()`
4. Add tests for trait

---

## 4. Refactor Monster Tag Accumulation

**Priority:** LOW | **Effort:** Medium | **Lines Saved:** ~50

### Problem

All monster type strategies follow the same pattern:

```php
$tags = ['beast'];
if ($this->hasTraitContaining($traits, 'keen smell')) {
    $tags[] = 'keen_senses';
    $this->incrementMetric('keen_senses_count');
}
$this->setMetric('tags_applied', $tags);
$this->incrementMetric('beasts_enhanced');
return $traits;
```

This is repeated in: `BeastStrategy`, `FiendStrategy`, `CelestialStrategy`, `ConstructStrategy`, `ElementalStrategy`, `ShapechangerStrategy`, `AberrationStrategy`

### Implementation

Add to `AbstractMonsterStrategy`:

```php
/**
 * Apply conditional tags based on trait/immunity checks.
 *
 * @param string $primaryTag The base tag (e.g., 'beast', 'fiend')
 * @param array $conditions Array of [check => tag] mappings
 * @param array $traits The monster's traits
 * @param array $monsterData The monster's data
 */
protected function applyConditionalTags(
    string $primaryTag,
    array $conditions,
    array $traits,
    array $monsterData
): void {
    $tags = [$primaryTag];

    foreach ($conditions as $check => $tagInfo) {
        $tag = is_array($tagInfo) ? $tagInfo['tag'] : $tagInfo;
        $metric = is_array($tagInfo) ? $tagInfo['metric'] : "{$tag}_count";

        $matches = match (true) {
            str_starts_with($check, 'trait:') =>
                $this->hasTraitContaining($traits, substr($check, 6)),
            str_starts_with($check, 'immunity:') =>
                $this->hasDamageImmunity($monsterData, substr($check, 9)),
            str_starts_with($check, 'resistance:') =>
                $this->hasDamageResistance($monsterData, substr($check, 11)),
            str_starts_with($check, 'condition:') =>
                $this->hasConditionImmunity($monsterData, substr($check, 10)),
            default => false,
        };

        if ($matches) {
            $tags[] = $tag;
            $this->incrementMetric($metric);
        }
    }

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric("{$primaryTag}s_enhanced");
}
```

### Migration Example

**Before (BeastStrategy):**
```php
public function enhanceTraits(array $traits, array $monsterData): array
{
    $tags = ['beast'];

    if ($this->hasTraitContaining($traits, 'keen smell') || ...) {
        $tags[] = 'keen_senses';
        $this->incrementMetric('keen_senses_count');
    }
    // ... more conditionals ...

    $this->setMetric('tags_applied', $tags);
    $this->incrementMetric('beasts_enhanced');
    return $traits;
}
```

**After:**
```php
public function enhanceTraits(array $traits, array $monsterData): array
{
    $this->applyConditionalTags('beast', [
        'trait:keen smell' => 'keen_senses',
        'trait:keen sight' => 'keen_senses',
        'trait:keen hearing' => 'keen_senses',
        'trait:pack tactics' => 'pack_tactics',
        'trait:charge' => 'charge',
        'trait:pounce' => 'charge',
        'trait:spider climb' => 'special_movement',
        'trait:amphibious' => 'special_movement',
    ], $traits, $monsterData);

    return $traits;
}
```

### Decision Point

This refactoring is more complex and provides less immediate value. Consider deferring until:
- A bug is found in tag accumulation logic
- New monster strategies need to be added
- Performance optimization is needed

---

## 5. Standardize Cache Reset Pattern

**Priority:** LOW | **Effort:** Low | **Lines Saved:** Minimal (quality improvement)

### Problem

Static caches in concerns have inconsistent reset patterns:

| Concern | Has Reset Method? |
|---------|------------------|
| `ImportsSenses` | Yes (`resetSenseCache()`) |
| `LookupsGameEntities` | No |
| `MatchesProficiencyCategories` | No (static map, not cache) |

### Implementation

Create `ClearsCaches` interface:

```php
<?php

namespace App\Services\Concerns;

interface ClearsCaches
{
    public static function clearAllCaches(): void;
}
```

Add to each concern that uses static caching. Call from test setup when needed.

### Decision

This is a **quality improvement** rather than code reduction. Defer unless test pollution issues emerge.

---

## Implementation Order

| Phase | Task | Effort | Impact |
|-------|------|--------|--------|
| 1 | Extract `NormalizesSpellNames` | 1 hour | High - removes exact duplication |
| 2 | Extract `TracksMetricsAndWarnings` | 1 hour | Medium - removes exact duplication |
| 3 | Extract `FindsInDescription` | 2 hours | Medium - reduces pattern repetition |
| 4 | Refactor Monster Tags | 3 hours | Low - defer until needed |
| 5 | Cache Reset Standardization | 30 min | Low - defer until needed |

---

## Success Criteria

- [ ] All tests pass after each extraction
- [ ] No duplicate `normalizeSpellName()` or `findSpell()` methods
- [ ] No duplicate metric/warning tracking code
- [ ] New traits have comprehensive test coverage
- [ ] Code formatted with Pint

---

## Files to Create

```
app/Services/Concerns/
├── NormalizesSpellNames.php      (Phase 1)
├── TracksMetricsAndWarnings.php  (Phase 2)
└── ClearsCaches.php              (Phase 5 - interface)

app/Services/Parsers/Concerns/
└── FindsInDescription.php        (Phase 3)

tests/Unit/Concerns/
├── NormalizesSpellNamesTest.php
├── TracksMetricsAndWarningsTest.php
└── FindsInDescriptionTest.php
```

---

## Files to Modify

**Phase 1:**
- `app/Services/Importers/Strategies/Monster/SpellcasterStrategy.php`
- `app/Services/Parsers/Strategies/ChargedItemStrategy.php`

**Phase 2:**
- `app/Services/Importers/Strategies/AbstractImportStrategy.php`
- `app/Services/Parsers/Strategies/AbstractItemStrategy.php`

**Phase 3:**
- `app/Services/Parsers/Strategies/LegendaryStrategy.php`
- `app/Services/Parsers/Strategies/TattooStrategy.php`
- `app/Services/Parsers/Strategies/PotionStrategy.php`

**Phase 4 (if implemented):**
- `app/Services/Importers/Strategies/Monster/AbstractMonsterStrategy.php`
- All monster type strategies (7 files)
