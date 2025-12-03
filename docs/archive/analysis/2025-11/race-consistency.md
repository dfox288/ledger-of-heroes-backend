# Race Entity API Consistency Audit

**Date:** 2025-11-25
**Entity:** Race
**Scope:** Compare 4 sources for API consistency:
1. Model `searchableOptions()` filterableAttributes
2. Model `toSearchableArray()` keys
3. Controller `#[QueryParameter]` documentation
4. Form Request validation rules

---

## Executive Summary

✅ **Overall Status:** CONSISTENT
✅ No critical violations
⚠️ Minor discrepancies in field documentation (3 mentions in controller not in filterableAttributes)

**Key Finding:** Controller PHPDoc mentions `has_darkvision` and `darkvision_range` as filterable fields, but these fields are NOT in `toSearchableArray()` or `searchableOptions()` → **These are not actually filterable**.

---

## Source 1: Model `searchableOptions()['filterableAttributes']`

**Location:** `/Users/dfox/Development/dnd/importer/app/Models/Race.php` (lines 153-163)

```
1. id
2. slug
3. size_name
4. size_code
5. speed
6. source_codes
7. is_subrace
8. parent_race_name
9. tag_slugs
```

**Total:** 9 filterable attributes

---

## Source 2: Model `toSearchableArray()` Keys

**Location:** `/Users/dfox/Development/dnd/importer/app/Models/Race.php` (lines 112-131)

```
1. id
2. name
3. slug
4. size_name
5. size_code
6. speed
7. sources
8. source_codes
9. is_subrace
10. parent_race_name
11. tag_slugs
```

**Total:** 11 keys indexed

**Analysis:**
- `sources` (line 124) is indexed but NOT filterable (good - it's searchable only)
- `name` (line 119) is indexed but NOT filterable (good - it's searchable only)
- All 9 filterable attributes are present ✅

---

## Source 3: Controller `#[QueryParameter]` Annotation

**Location:** `/Users/dfox/Development/dnd/importer/app/Http/Controllers/Api/RaceController.php` (lines 79-79)

**From Attribute (line 79):**
```
size_code (string: T, S, M, L, H, G)
speed (int)
has_darkvision (bool)
darkvision_range (int)
spell_slugs (array)
tag_slugs (array)
source_codes (array)
```

**From PHPDoc Examples (lines 26-50):**
- size_code ✅
- speed ✅
- has_darkvision ⚠️
- darkvision_range ⚠️
- spell_slugs ⚠️
- tag_slugs ✅
- source_codes ✅

**Total Mentioned:** 7 unique fields

---

## Source 4: Form Request Validation Rules

**Location:** `/Users/dfox/Development/dnd/importer/app/Http/Requests/RaceIndexRequest.php` (lines 13-22)

```php
'q' => ['sometimes', 'string', 'min:2', 'max:255'],
'filter' => ['sometimes', 'string', 'max:1000'],
```

**Analysis:**
- Only generic filter/search parameters (no field-specific validation)
- This is correct for Meilisearch implementation ✅
- No validation of filterable field names (Meilisearch handles validation)

**Sortable Columns (line 29):**
```
name
size
speed
created_at
updated_at
```

---

## Consistency Comparison Matrix

| Field | In `filterableAttributes` | In `toSearchableArray()` | In Controller Doc | Status |
|-------|--------------------------|-------------------------|-------------------|--------|
| id | ✅ | ✅ | ❌ | INCONSISTENCY |
| slug | ✅ | ✅ | ❌ | INCONSISTENCY |
| size_name | ✅ | ✅ | ❌ | INCONSISTENCY |
| size_code | ✅ | ✅ | ✅ | CONSISTENT |
| speed | ✅ | ✅ | ✅ | CONSISTENT |
| source_codes | ✅ | ✅ | ✅ | CONSISTENT |
| is_subrace | ✅ | ✅ | ❌ | INCONSISTENCY |
| parent_race_name | ✅ | ✅ | ❌ | INCONSISTENCY |
| tag_slugs | ✅ | ✅ | ✅ | CONSISTENT |
| name | ❌ | ✅ (searchable) | ❌ | CORRECT (not filterable) |
| sources | ❌ | ✅ (searchable) | ❌ | CORRECT (not filterable) |
| **has_darkvision** | ❌ | ❌ | ✅ | **CRITICAL INCONSISTENCY** |
| **darkvision_range** | ❌ | ❌ | ✅ | **CRITICAL INCONSISTENCY** |
| **spell_slugs** | ❌ | ❌ | ✅ | **CRITICAL INCONSISTENCY** |

---

## Critical Findings

### 🚨 CRITICAL: Fields in Controller Doc but Not Implemented

The controller `#[QueryParameter]` annotation (line 79) lists filterable fields that **do not exist**:

**1. `has_darkvision` (boolean)**
- **Mentioned in:** Controller line 28 and QueryParameter line 48
- **Example:** `GET /api/v1/races?filter=has_darkvision = true`
- **Problem:** `has_darkvision` is NOT in `toSearchableArray()` → **NOT INDEXED by Meilisearch**
- **Impact:** Users will get Meilisearch errors trying to filter by this field
- **Status:** ❌ Non-functional

**2. `darkvision_range` (integer)**
- **Mentioned in:** Controller QueryParameter line 48
- **Problem:** `darkvision_range` is NOT in `toSearchableArray()` → **NOT INDEXED by Meilisearch**
- **Impact:** Users will get Meilisearch errors trying to filter by this field
- **Status:** ❌ Non-functional

**3. `spell_slugs` (array)**
- **Mentioned in:** Controller lines 29, 35-37, 49, and examples throughout
- **Problem:** `spell_slugs` is NOT in `toSearchableArray()` → **NOT INDEXED by Meilisearch**
- **Impact:** **Major feature broken** - Users cannot filter races by innate spells
- **Example that will fail:** `GET /api/v1/races?filter=spell_slugs IN [misty-step]` (Eladrin)
- **Status:** ❌ Non-functional

---

### ⚠️ Minor Inconsistencies (Documentation Gaps)

The following fields are correctly implemented but **NOT documented in the controller**:

| Field | Status | Reason |
|-------|--------|--------|
| `id` | ✅ Implemented | Missing from controller doc |
| `slug` | ✅ Implemented | Missing from controller doc |
| `size_name` | ✅ Implemented | Missing from controller doc |
| `is_subrace` | ✅ Implemented | Missing from controller doc |
| `parent_race_name` | ✅ Implemented | Missing from controller doc |

**Impact:** Low - these fields work but aren't communicated to API consumers

---

## Recommendations

### Priority 1: Fix Critical Missing Fields (3-4 hours)

**Option A: Add Missing Fields to Model (Recommended)**
1. Add `has_darkvision`, `darkvision_range`, `spell_slugs` to `toSearchableArray()`
2. Add to `searchableOptions()['filterableAttributes']`
3. These fields should come from relationships:
   - `has_darkvision`: Check if any trait has "Darkvision" name
   - `darkvision_range`: Parse darkvision range from traits (e.g., "Darkvision (120 feet)")
   - `spell_slugs`: Collect from `entitySpells` relationship (like other entities)
4. Re-index with `php artisan scout:import "App\Models\Race"`
5. Update tests to verify filters work

**Option B: Remove from Controller Doc (Not Recommended)**
- Delete mentions of `has_darkvision`, `darkvision_range`, `spell_slugs` from controller
- Less valuable API (users want to filter by innate spells)
- Breaks stated use cases

**Recommendation:** **Choose Option A** - implement the missing fields

### Priority 2: Update Controller Documentation

Add these missing fields to the controller's `#[QueryParameter]` description:
```php
// Add these to line 79 description:
// - `id` (int)
// - `slug` (string)
// - `size_name` (string)
// - `is_subrace` (bool)
// - `parent_race_name` (string)
```

### Priority 3: Verify Sortable Attributes

**In Model:** `searchableOptions()['sortableAttributes']` (lines 164-167)
```
name
speed
```

**In Form Request:** `getSortableColumns()` returns (line 29)
```
name
size
speed
created_at
updated_at
```

**Issue:** Form Request allows sorting by `size`, `created_at`, `updated_at` but only `name` and `speed` are in Meilisearch sortable attributes.

**Impact:** Sorting by `size`, `created_at`, `updated_at` will use database sorting instead of Meilisearch. This is acceptable but inconsistent.

**Recommendation:** Either:
1. Add `created_at`, `updated_at` to model's sortableAttributes, OR
2. Remove `size`, `created_at`, `updated_at` from Form Request sortable columns

---

## Detailed Field Documentation

### Correctly Implemented Fields

| Field | Type | Source | Searchable | Filterable | Sortable |
|-------|------|--------|-----------|-----------|----------|
| `id` | int | toSearchableArray | No | Yes | No |
| `name` | string | toSearchableArray | Yes | No | Yes |
| `slug` | string | toSearchableArray | No | Yes | No |
| `size_name` | string | toSearchableArray | Yes | Yes | No |
| `size_code` | string | toSearchableArray | No | Yes | No |
| `speed` | int | toSearchableArray | No | Yes | Yes |
| `sources` | array | toSearchableArray | Yes | No | No |
| `source_codes` | array | toSearchableArray | No | Yes | No |
| `is_subrace` | bool | toSearchableArray | No | Yes | No |
| `parent_race_name` | string | toSearchableArray | Yes | Yes | No |
| `tag_slugs` | array | toSearchableArray | No | Yes | No |

### Missing Fields (Not Implemented)

| Field | Type | In Controller Doc | Status |
|-------|------|-------------------|--------|
| `has_darkvision` | bool | Yes (line 28, 48) | ❌ NOT IN MODEL |
| `darkvision_range` | int | Yes (line 48) | ❌ NOT IN MODEL |
| `spell_slugs` | array | Yes (lines 29, 35-50) | ❌ NOT IN MODEL |

---

## Test Coverage Impact

**Current Status:** The race entity has tests passing, but these tests **do NOT verify**:
- `has_darkvision` filtering
- `darkvision_range` filtering
- `spell_slugs` filtering

**Tests to Add (After Implementing):**
```php
// When implement has_darkvision
$this->getJson('/api/v1/races?filter=has_darkvision = true')
    ->assertJsonCount(x);  // Drow, Tiefling, etc.

// When implement spell_slugs
$this->getJson('/api/v1/races?filter=spell_slugs IN [misty-step]')
    ->assertJsonCount(1);  // Eladrin

// When implement darkvision_range
$this->getJson('/api/v1/races?filter=darkvision_range >= 60')
    ->assertJsonCount(x);  // Races with 60+ ft darkvision
```

---

## Files Involved

1. **Model:** `/Users/dfox/Development/dnd/importer/app/Models/Race.php`
   - Lines 112-131: `toSearchableArray()` - Needs 3 new fields
   - Lines 150-175: `searchableOptions()` - Needs 3 new fields in filterableAttributes

2. **Controller:** `/Users/dfox/Development/dnd/importer/app/Http/Controllers/Api/RaceController.php`
   - Lines 18-78: PHPDoc - Contains examples using missing fields
   - Line 79: `#[QueryParameter]` - Lists missing fields

3. **Form Request:** `/Users/dfox/Development/dnd/importer/app/Http/Requests/RaceIndexRequest.php`
   - Lines 28-29: Sortable columns - Mismatch with model

4. **Tests:** Tests should be added/updated to verify missing fields work correctly

---

## Conclusion

**Severity:** HIGH - API contract violations (3 fields documented but non-functional)

**Fix Effort:** 3-4 hours
- Implement missing fields in Race model (2-3 hours)
- Update controller documentation (30 mins)
- Write tests and verify (1 hour)

**Blocking Issue:** Until `spell_slugs` is implemented, a major documented feature (filtering races by innate spells) is broken.

---

**Generated:** 2025-11-25
