# Meilisearch Filter Configuration Audit Report

**Date:** 2025-11-24
**Scope:** All 7 searchable entities (Spell, Monster, Item, Race, CharacterClass, Background, Feat)
**Purpose:** Identify filter/sort configuration violations and type mismatches

---

## Executive Summary

✅ **Good News:** Overall Meilisearch configurations are well-designed
🚨 **CRITICAL Issue:** Monster `challenge_rating` (STRING vs numeric expectation)
⚠️ **MEDIUM Issue:** Item `charges_max` contains dice formulas (can't filter/sort)
📝 **LOW Issues:** Some string fields in filterable attributes (documented behavior)

---

## Critical Findings

### 🚨 Monster: `challenge_rating` Type Mismatch

**Problem:**
- Database stores as **STRING**: "0", "1/8", "1/4", "1/2", "1", "2", ... "30"
- Marked as **filterable AND sortable** in Meilisearch
- Users expect **numeric** filtering: `challenge_rating >= 5`

**Impact:**
- ❌ Range operators (`>=`, `<=`, `<`, `>`, `TO`) **DO NOT WORK** correctly
- ❌ Sorting is **alphabetic**, not numeric: "10" comes before "2"
- ✅ Equality works: `challenge_rating = "5"` (but requires exact string match)
- **Severity:** HIGH - This is a core D&D filtering field

**Example of broken behavior:**
```
User Query: ?filter=challenge_rating >= 5 AND challenge_rating <= 10
Expected: CR 5, 6, 7, 8, 9, 10 monsters
Actual: UNPREDICTABLE (string comparison: "5" > "10" is true!)
```

**Solution:** See `docs/TODO-CHALLENGE-RATING-NUMERIC.md`

---

### ⚠️ Item: `charges_max` Contains Dice Formulas

**Problem:**
- Database column: **STRING** (to support dice formulas)
- Actual data includes: `"1d4-1"`, `"1d8+1"` (confirmed via database query)
- Marked as **filterable AND sortable** in Meilisearch
- Model indexes as **integer** (direct property access)

**Impact:**
- ⚠️ Dice formula values **cannot be sorted numerically** in Meilisearch
- ⚠️ Range operators **will fail** for formula values
- ✅ Static integer values ("3", "7", "10") work fine
- **Severity:** MEDIUM - Affects ~12 of 516 items (Luck Blade, Nine Lives Stealer variants)

**Affected Items (12 total):**
- Luck Blade variants (6): `"1d4-1"` charges
- Nine Lives Stealer variants (6): `"1d8+1"` charges

**Solution Options:**
1. **Remove from filterable/sortable** (safest - filter by `has_charges` boolean instead)
2. **Parse dice formulas to average** (complex - "1d4-1" → 1.5)
3. **Add computed column** `charges_max_numeric` for filtering

---

## Detailed Entity Analysis

### 1. Spell Entity ✅

**Status:** CLEAN - No violations

**Filterable Attributes:**
- ✅ `id` (integer)
- ✅ `level` (integer 0-9)
- ⚠️ `school_name` (string - equality/IN only, no ranges)
- ⚠️ `school_code` (string - equality/IN only)
- ✅ `concentration` (boolean)
- ✅ `ritual` (boolean)
- ✅ `source_codes` (array)
- ✅ `class_slugs` (array)
- ✅ `tag_slugs` (array)

**Sortable Attributes:**
- ⚠️ `name` (string - alphabetic sort only)
- ✅ `level` (integer)

**Recommendations:** None - configuration is sound

---

### 2. Monster Entity 🚨

**Status:** CRITICAL VIOLATION - Challenge Rating

**Filterable Attributes:**
- ✅ `id`, `armor_class`, `hit_points_average`, `experience_points` (integers)
- ✅ `speed_walk`, `speed_fly`, `speed_swim`, `speed_burrow`, `speed_climb` (integers)
- ✅ `strength`, `dexterity`, `constitution`, `intelligence`, `wisdom`, `charisma` (integers)
- ✅ `passive_perception` (integer)
- ✅ `can_hover`, `is_npc` (booleans)
- ✅ `source_codes`, `spell_slugs`, `tag_slugs` (arrays)
- ⚠️ `slug`, `type`, `size_code`, `size_name`, `alignment`, `armor_type` (strings - equality only)
- 🚨 **`challenge_rating` (STRING - breaks range operators!)**

**Sortable Attributes:**
- ⚠️ `name` (string - alphabetic sort)
- ✅ `armor_class`, `hit_points_average`, `experience_points` (integers)
- ✅ `speed_walk`, `strength`, `dexterity`, `passive_perception` (integers)
- 🚨 **`challenge_rating` (STRING - alphabetic sort, not numeric!)**

**Recommendations:** See TODO-CHALLENGE-RATING-NUMERIC.md

---

### 3. Item Entity ⚠️

**Status:** MEDIUM VIOLATION - Charges Max

**Filterable Attributes:**
- ✅ `id`, `cost_cp`, `range_normal`, `range_long`, `armor_class`, `strength_requirement` (integers)
- ✅ `weight` (decimal)
- ✅ `requires_attunement`, `is_magic`, `stealth_disadvantage`, `has_charges` (booleans)
- ✅ `source_codes`, `spell_slugs`, `tag_slugs` (arrays)
- ⚠️ `slug`, `type_name`, `type_code`, `rarity`, `damage_type` (strings - equality only)
- ⚠️ `damage_dice`, `versatile_damage` (strings - dice notation, equality only)
- ⚠️ **`charges_max` (STRING with dice formulas - breaks numeric operations!)**

**Sortable Attributes:**
- ⚠️ `name` (string)
- ✅ `weight`, `cost_cp`, `armor_class`, `range_normal` (numeric)

**Actual `charges_max` Values:**
```json
{
  "Static Integers (OK)": ["3", "4", "5", "6", "7", "8", "10", "12", "20", "24", "36", "50"],
  "Dice Formulas (BROKEN)": ["1d4-1", "1d8+1"]
}
```

**Recommendations:**
1. **SHORT-TERM:** Remove `charges_max` from filterable/sortable (users can still filter by `has_charges` boolean)
2. **LONG-TERM:** Add `charges_max_average` computed column for filtering

---

### 4. Race Entity ✅

**Status:** CLEAN - No violations

**Filterable Attributes:**
- ✅ `id`, `speed` (integers)
- ✅ `is_subrace` (boolean)
- ✅ `source_codes`, `tag_slugs` (arrays)
- ⚠️ `slug`, `size_name`, `size_code`, `parent_race_name` (strings - equality only)

**Sortable Attributes:**
- ⚠️ `name` (string)
- ✅ `speed` (integer)

**Recommendations:** None

---

### 5. CharacterClass Entity ✅

**Status:** CLEAN - No violations

**Filterable Attributes:**
- ✅ `id`, `hit_die` (integers: 6, 8, 10, 12)
- ✅ `is_subclass` (boolean)
- ✅ `source_codes`, `tag_slugs` (arrays)
- ⚠️ `slug`, `primary_ability`, `spellcasting_ability`, `parent_class_name` (strings - equality only)

**Sortable Attributes:**
- ⚠️ `name` (string)
- ✅ `hit_die` (integer)

**Recommendations:** None

---

### 6. Background Entity ✅

**Status:** CLEAN - Minimal configuration

**Filterable Attributes:**
- ✅ `id` (integer)
- ✅ `source_codes`, `tag_slugs` (arrays)
- ⚠️ `slug` (string - equality only)

**Sortable Attributes:**
- ⚠️ `name` (string)

**Recommendations:** None

---

### 7. Feat Entity ✅

**Status:** CLEAN - Minimal configuration

**Filterable Attributes:**
- ✅ `id` (integer)
- ✅ `source_codes`, `tag_slugs` (arrays)
- ⚠️ `slug` (string - equality only)

**Sortable Attributes:**
- ⚠️ `name` (string)

**Recommendations:** None

---

## Meilisearch Filter Operator Reference

### Operators by Type Compatibility

| Operator | Integer | Decimal | Boolean | String | Array | Notes |
|----------|---------|---------|---------|--------|-------|-------|
| `=`, `!=` | ✅ | ✅ | ✅ | ✅ | ✅ | Works on all types |
| `IN`, `NOT IN` | ✅ | ✅ | ✅ | ✅ | ✅ | Works on all types |
| `>`, `>=`, `<`, `<=` | ✅ | ✅ | ❌ | ❌ | ❌ | **Numeric types ONLY** |
| `TO` (range) | ✅ | ✅ | ❌ | ❌ | ❌ | **Numeric types ONLY** |
| `IS NULL`, `IS NOT NULL` | ✅ | ✅ | ✅ | ✅ | ✅ | Works on all types |
| `IS EMPTY`, `IS NOT EMPTY` | ❌ | ❌ | ❌ | ✅ | ✅ | **String/Array only** |

### String Filterable Fields (Equality/IN Only)

**These fields support `=`, `!=`, `IN`, `NOT IN` but NOT range operators:**

- **Spell:** `school_name`, `school_code`
- **Monster:** `slug`, `type`, `size_code`, `size_name`, `alignment`, `armor_type`
- **Item:** `slug`, `type_name`, `type_code`, `rarity`, `damage_dice`, `versatile_damage`, `damage_type`
- **Race:** `slug`, `size_name`, `size_code`, `parent_race_name`
- **CharacterClass:** `slug`, `primary_ability`, `spellcasting_ability`, `parent_class_name`
- **Background:** `slug`
- **Feat:** `slug`

**Valid queries:**
```
?filter=type = "dragon"
?filter=type IN [dragon, aberration, fiend]
?filter=alignment != "neutral"
```

**Invalid queries (will fail or behave unexpectedly):**
```
?filter=type >= "dragon"  // ❌ Type mismatch
?filter=alignment > "evil" // ❌ String comparison doesn't make sense
```

---

## Priority Action Items

### 1. URGENT: Fix Monster `challenge_rating`

**Timeline:** Week 1
**Effort:** ~4 hours (migration + backfill + model update + tests)
**Impact:** Enables core monster filtering by CR range

**Steps:**
1. Create migration for `challenge_rating_numeric` column
2. Backfill with conversion logic ("1/8" → 0.125, "1/2" → 0.5, etc.)
3. Update `Monster::toSearchableArray()` and `Monster::searchableOptions()`
4. Update tests
5. Re-index monsters: `php artisan scout:flush "App\Models\Monster" && php artisan scout:import "App\Models\Monster"`

**Reference:** `docs/TODO-CHALLENGE-RATING-NUMERIC.md`

---

### 2. MEDIUM: Fix Item `charges_max`

**Timeline:** Week 2
**Effort:** ~2 hours
**Impact:** Prevents filter/sort errors on magic items with dice formula charges

**Option A (Recommended):** Remove from filterable/sortable
```php
// Monster.php searchableOptions()
'filterableAttributes' => [
    // Remove 'charges_max'
    'has_charges',  // Users can still filter "has charges: yes/no"
    // ...
],
'sortableAttributes' => [
    // Remove 'charges_max' entirely
    // ...
]
```

**Option B:** Add computed `charges_max_average` field
- Parse dice formulas: "1d4-1" → 1.5, "1d8+1" → 5.5
- Index numeric average in `toSearchableArray()`
- Keep original `charges_max` string for display

---

### 3. LOW: Document String Field Operators

**Timeline:** Week 3
**Effort:** ~1 hour
**Impact:** Prevents user confusion about string field capabilities

**Action:** Update API documentation (OpenAPI/Scramble annotations) to clarify:
- String fields support equality/IN operators only
- No range operators on strings
- Sorting on strings is alphabetic

---

## Testing Recommendations

### After Each Fix, Test:

1. **Range Queries:**
```bash
# After CR fix
curl "http://localhost:8080/api/v1/monsters?filter=challenge_rating_numeric >= 5 AND challenge_rating_numeric <= 10"
```

2. **Sorting:**
```bash
# After CR fix - should be numeric order
curl "http://localhost:8080/api/v1/monsters?sort_by=challenge_rating_numeric&sort_order=asc"
```

3. **Equality Queries (should still work):**
```bash
curl "http://localhost:8080/api/v1/monsters?filter=type = dragon"
curl "http://localhost:8080/api/v1/items?filter=rarity IN [rare, very-rare, legendary]"
```

---

## Conclusions

### Strengths

✅ **Well-designed filterable attributes** - Most entities expose appropriate filters
✅ **Proper boolean/array handling** - Booleans and arrays are correctly filterable
✅ **Consistent patterns** - `source_codes`, `tag_slugs` used consistently across entities
✅ **Type-safe numerics** - Integer/decimal fields correctly configured (except CR)

### Weaknesses

🚨 **Challenge Rating** - Critical user-facing issue affecting core D&D gameplay
⚠️ **Charges Max** - Medium issue affecting magic item filtering
📝 **String documentation** - Low priority documentation gap

### Overall Assessment

**8/10** - Configurations are mostly excellent with 2 actionable issues to fix. The refactor to single source of truth (model `searchableOptions()`) makes future maintenance much easier.

---

**Report Generated:** 2025-11-24
**Next Review:** After CR fix implementation

🤖 Generated with [Claude Code](https://claude.com/claude-code)
