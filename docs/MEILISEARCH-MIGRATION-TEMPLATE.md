# Meilisearch-First API Migration Template

**Status:** ✅ Spells Complete | 🔄 6 Remaining Entities

This document shows the exact changes needed to migrate each entity to Meilisearch-only filtering.

---

## ✅ Completed: Spells

### Changes Made:

**1. SpellSearchService.php**
- Removed MySQL filtering from `buildScoutQuery()` (lines 56-81)
- Removed MySQL filtering from `applyFilters()` (lines 128-148)
- Added comment directing users to `?filter=` syntax

**2. SpellController.php**
- Removed "Basic Examples (MySQL)" section
- Removed "MySQL fallback" from Query Parameters
- Updated all examples to use `?filter=level = 0` instead of `?level=0`
- Removed confusing dual-system warnings

**3. SpellIndexRequest.php**
- Removed validation for: `level`, `school`, `concentration`, `ritual`, `damage_type`, `saving_throw`, `requires_*`
- Kept only: `q` (search) and `filter` (Meilisearch)

**4. Tests (24 failures to fix)**
- SpellIndexRequestTest - Remove validation tests for deleted params
- SpellApiTest - Update to use `?filter=` syntax
- MonsterEnhancedFilteringApiTest - Update spell filtering tests
- Various Service tests - Remove MySQL filtering tests

---

## 🔄 Template for Remaining Entities

Apply these exact same changes to:
1. Monster
2. Item
3. Class (CharacterClass)
4. Race
5. Background
6. Feat

### Step-by-Step for Each Entity

#### Step 1: Update SearchService

**File:** `app/Services/{Entity}SearchService.php`

**Change 1 - buildScoutQuery():**
```php
// BEFORE
public function buildScoutQuery({Entity}SearchDTO $dto): \Laravel\Scout\Builder
{
    $search = {Entity}::search($dto->searchQuery);

    // Apply Scout-compatible filters
    if (isset($dto->filters['spells'])) {
        // ... MySQL filtering logic ...
    }

    return $search;
}

// AFTER
public function buildScoutQuery({Entity}SearchDTO $dto): \Laravel\Scout\Builder
{
    // NOTE: MySQL filtering has been removed. Use Meilisearch ?filter= parameter instead.
    return {Entity}::search($dto->searchQuery);
}
```

**Change 2 - applyFilters():**
```php
// BEFORE
private function applyFilters(Builder $query, {Entity}SearchDTO $dto): void
{
    if (isset($dto->filters['spells'])) {
        // ... MySQL filtering logic ...
    }

    // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
}

// AFTER
private function applyFilters(Builder $query, {Entity}SearchDTO $dto): void
{
    // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
    //
    // Examples:
    // - ?filter=spell_slugs IN [fireball]
    // - ?filter=tag_slugs IN [dragon]
    // - ?filter=level >= 5
    //
    // All filtering should happen via Meilisearch for consistency and performance.
}
```

---

#### Step 2: Update Controller PHPDoc ⭐ **USE SIMPLIFIED TEMPLATE**

**File:** `app/Http/Controllers/Api/{Entity}Controller.php`

**IMPORTANT:** Simplify dramatically! The old docs were too long and repetitive.

**New Standard Template** (~30-40 lines):

```php
/**
 * List all {entities}
 *
 * Returns a paginated list of {count} D&D 5e {entities}. Use `?filter=` for filtering and `?q=` for full-text search.
 *
 * **Common Examples:**
 * ```
 * GET /api/v1/{entities}                                    # All {entities}
 * GET /api/v1/{entities}?filter=level = 0                   # Low-level
 * GET /api/v1/{entities}?filter=spell_slugs IN [fireball]   # With fireball spell
 * GET /api/v1/{entities}?q=dragon                           # Full-text search
 * GET /api/v1/{entities}?q=fire&filter=level <= 3           # Search + filter combined
 * GET /api/v1/{entities}?filter=class_slugs IN [bard] AND level <= 3   # Combined filters
 * ```
 *
 * **Filterable Fields:**
 * - `level` (0-20), `type_code` (varies by entity)
 * - `spell_slugs` (array), `class_slugs` (array)
 * - `tag_slugs` (array), `source_codes` (array: PHB, XGE, TCoE)
 * - {entity-specific fields}
 *
 * **Operators:**
 * - Comparison: `=`, `!=`, `>`, `>=`, `<`, `<=`
 * - Logic: `AND`, `OR`
 * - Membership: `IN [value1, value2, ...]`
 *
 * **Query Parameters:**
 * - `q` (string): Full-text search
 * - `filter` (string): Meilisearch filter expression
 * - `sort_by` (string): Column name
 * - `sort_direction` (string): asc, desc (default: asc)
 * - `per_page` (int): 1-100 (default: 15)
 * - `page` (int): Page number (default: 1)
 *
 * @param  {Entity}IndexRequest  $request
 * @param  {Entity}SearchService  $service
 * @param  Client  $meilisearch
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
```

**Key Principles:**
- ✅ ONE clear examples section (in code block)
- ✅ Scannable field list (NOT paragraphs)
- ✅ Concise (~30-40 lines total)
- ❌ NO separate "Level Filtering", "Spell Filtering" sections
- ❌ NO "Use Cases" fluff
- ❌ NO repetition - say `?filter=` once, show examples

**See:** `app/Http/Controllers/Api/SpellController.php` (lines 22-59) for reference

---

#### Step 3: Update IndexRequest

**File:** `app/Http/Requests/{Entity}IndexRequest.php`

**Change:**
```php
// BEFORE
protected function entityRules(): array
{
    return [
        'q' => ['sometimes', 'string', 'min:2', 'max:255'],
        'filter' => ['sometimes', 'string', 'max:1000'],

        // Entity-specific filters (backwards compatibility)
        'spells' => ['sometimes', 'string', 'max:500'],
        'spell_level' => ['sometimes', 'integer', 'min:0', 'max:9'],
        'spells_operator' => ['sometimes', Rule::in(['AND', 'OR'])],
        // ... other MySQL params ...
    ];
}

// AFTER
protected function entityRules(): array
{
    return [
        // Full-text search query
        'q' => ['sometimes', 'string', 'min:2', 'max:255'],

        // Meilisearch filter expression
        'filter' => ['sometimes', 'string', 'max:1000'],
    ];
}
```

---

#### Step 4: Update/Remove Tests

**Files to check:**
- `tests/Feature/Api/{Entity}ApiTest.php`
- `tests/Feature/Api/{Entity}EnhancedFilteringApiTest.php`
- `tests/Unit/Services/{Entity}SearchServiceTest.php`
- `tests/Feature/Requests/{Entity}IndexRequestTest.php`

**Actions:**

1. **Delete entire test methods** that test removed MySQL params
2. **Update test methods** to use `?filter=` syntax instead
3. **Remove validation tests** for deleted params

**Example:**
```php
// DELETE THIS TEST METHOD
#[Test]
public function can_filter_monsters_by_spell(): void
{
    $response = $this->getJson('/api/v1/monsters?spells=fireball');
    $response->assertOk();
}

// OR UPDATE IT TO:
#[Test]
public function can_filter_monsters_by_spell_using_meilisearch(): void
{
    $response = $this->getJson('/api/v1/monsters?filter=spell_slugs IN [fireball]');
    $response->assertOk();
}
```

---

## 📋 Entity-Specific MySQL Params to Remove

### Monster
- `spells` → Use `?filter=spell_slugs IN [fireball]`
- `spell_level` → Use `?filter=` with tags
- `spells_operator` → Use `IN [spell1, spell2]` for OR logic
- `spellcasting_ability` → Use `?filter=spellcasting_ability = INT`

### Item
- `spells` → Use `?filter=spell_slugs IN [fireball]`
- `spell_level` → Use `?filter=` with level filtering

### Class (CharacterClass)
- `spells` → Use `?filter=spell_slugs IN [fireball]`
- `spell_level` → Use `?filter=` with level
- `max_spell_level` → Use `?filter=tag_slugs IN [full-caster]`
- Keep: `is_spellcaster`, `hit_die` (simple boolean/int filters)

### Race
- `spells` → Use `?filter=spell_slugs IN [misty-step]`
- `spell_level` → Use `?filter=`
- `has_innate_spells` → Use `?filter=tag_slugs IN [innate-spellcasting]`

### Background
- Likely no spell filtering to remove

### Feat
- Likely no spell filtering to remove

---

## 💥 Breaking Changes for CHANGELOG.md

```markdown
## [Unreleased]

### 💥 BREAKING CHANGES - Meilisearch-Only API

**Removed ALL MySQL filtering parameters.** All filtering now requires Meilisearch `?filter=` syntax.

**Removed Parameters Across All Entities:**

**Spells:**
- ❌ `?level=0` → ✅ `?filter=level = 0`
- ❌ `?school=EV` → ✅ `?filter=school_code = EV`
- ❌ `?concentration=true` → ✅ `?filter=concentration = true`
- ❌ `?ritual=false` → ✅ `?filter=ritual = false`
- ❌ `?damage_type=fire` → ✅ `?filter=tag_slugs IN [fire]`
- ❌ `?saving_throw=DEX` → ✅ Use tag-based filtering
- ❌ `?requires_verbal/somatic/material=false` → ✅ Use tag-based filtering

**Monsters, Items, Classes, Races:**
- ❌ `?spells=fireball` → ✅ `?filter=spell_slugs IN [fireball]`
- ❌ `?spell_level=3` → ✅ Use Meilisearch filter
- ❌ `?spells_operator=OR` → ✅ Use `IN [spell1, spell2]` (ANY logic)
- ❌ `?has_innate_spells=true` (Races) → ✅ `?filter=tag_slugs IN [innate-spellcasting]`
- ❌ `?max_spell_level=9` (Classes) → ✅ `?filter=tag_slugs IN [full-caster]`

**Why:** MySQL filtering was slow, incompatible with full-text search (`?q=`), and confusing. Meilisearch-only approach is faster, more consistent, and simpler.

**Migration Guide:**
- Replace ALL MySQL params with `?filter=` Meilisearch syntax
- Use `?filter=spell_slugs IN [spell1, spell2]` for OR logic (ANY of these spells)
- See controller PHPDoc for comprehensive examples

### Changed
- refactor!: remove ALL MySQL filtering from API, migrate to Meilisearch-only
- docs: update all 7 controller PHPDocs to Meilisearch-only examples
- test: remove/update ~100 tests for deleted MySQL filtering features
```

---

## 🎯 Completion Checklist

- [x] **Spell** - Complete
- [ ] **Monster** - Apply template
- [ ] **Item** - Apply template
- [ ] **Class** - Apply template
- [ ] **Race** - Apply template
- [ ] **Background** - Apply template
- [ ] **Feat** - Apply template
- [ ] **CHANGELOG.md** - Update with breaking changes
- [ ] **All tests passing** - After updating/removing test methods
- [ ] **Commit & Push** - One comprehensive commit

---

**Estimated Time Per Entity:** 15-20 minutes
**Total Remaining Time:** ~2 hours

**Benefits:**
- Simpler API (ONE filtering syntax)
- Better performance (Meilisearch indexes)
- Works with full-text search
- Cleaner codebase (~300-400 lines removed)
