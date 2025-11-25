# Item Entity API Consistency Audit

**Date:** 2025-11-25
**Purpose:** Verify alignment between Model, Controller documentation, Form Request validation, and searchable fields

---

## 1. Model: `searchableOptions()['filterableAttributes']`

**Location:** `app/Models/Item.php:232-255`

```php
'filterableAttributes' => [
    'id',
    'slug',
    'type_name',
    'type_code',
    'rarity',
    'requires_attunement',
    'is_magic',
    'weight',
    'cost_cp',
    'source_codes',
    'damage_dice',
    'versatile_damage',
    'damage_type',
    'range_normal',
    'range_long',
    'armor_class',
    'strength_requirement',
    'stealth_disadvantage',
    'charges_max',
    'has_charges',
    'spell_slugs',
    'tag_slugs',
]
```

**Count:** 22 filterable attributes

---

## 2. Model: `toSearchableArray()` Keys

**Location:** `app/Models/Item.php:167-204`

```php
'id',
'name',
'slug',
'type_name',
'type_code',
'description',
'rarity',
'requires_attunement',
'is_magic',
'weight',
'cost_cp',
'sources',
'source_codes',
'damage_dice',
'versatile_damage',
'damage_type',
'range_normal',
'range_long',
'armor_class',
'strength_requirement',
'stealth_disadvantage',
'charges_max',
'has_charges',
'spell_slugs',
'tag_slugs'
```

**Count:** 25 keys (includes 'name', 'description', 'sources' which are not in filterableAttributes)

---

## 3. Controller: QueryParameter Annotation

**Location:** `app/Http/Controllers/Api/ItemController.php:47`

**Fields Mentioned in PHPDoc:**
- `is_magic` (bool)
- `requires_attunement` (bool)
- `has_charges` (bool)
- `rarity` (string: common, uncommon, rare, very_rare, legendary, artifact)
- `type_code` (string: WD, ST, RD, SCR, P, etc.)
- `weight` (float)
- `cost_cp` (int)
- `spell_slugs` (array)
- `tag_slugs` (array)

**Filter Examples from PHPDoc:**
1. By rarity: `filter=rarity IN [rare, legendary]`
2. By type: `filter=type_code = WD`
3. Magic items: `filter=is_magic = true`
4. Requires attunement: `filter=requires_attunement = true`
5. Has charges: `filter=has_charges = true`
6. By spell: `filter=spell_slugs IN [fireball]`
7. By cost: `filter=cost_cp >= 5000`
8. By weight: `filter=weight <= 1.0`
9. Combined: Multiple filters with AND

**Count:** 9 filterable fields documented + 8 practical examples

---

## 4. Form Request: Validation Rules

**Location:** `app/Http/Requests/ItemIndexRequest.php:10-27`

```php
'q' => ['sometimes', 'string', 'min:2', 'max:255'],
'filter' => ['sometimes', 'string', 'max:1000'],
```

**Sortable columns defined:**
```php
['name', 'type', 'rarity', 'created_at', 'updated_at']
```

**Note:** Form Request only validates generic 'filter' parameter (Meilisearch expression), not individual filter fields.

---

## Consistency Audit Results

### ✅ Strengths

1. **Comprehensive searchable fields:** Model exposes 25 searchable keys with 22 filterable attributes
2. **Well-documented PHPDoc:** Controller includes detailed filter examples and field descriptions
3. **Meilisearch-first design:** All filtering delegated to Meilisearch via generic `filter=` parameter
4. **Practical examples:** Controller PHPDoc covers most common use cases

### ⚠️ Gaps & Inconsistencies

#### Gap 1: Form Request Sortable Columns vs. Model's sortableAttributes
- **Form Request says:** `['name', 'type', 'rarity', 'created_at', 'updated_at']`
- **Model declares:** `['name', 'weight', 'cost_cp', 'armor_class', 'range_normal']`
- **Issue:** Mismatch in sortable fields definition
- **Impact:** Users might try to sort by `type` or `rarity` (from Form Request) but model only supports `name, weight, cost_cp, armor_class, range_normal`
- **Recommendation:** Update Form Request to match model's `sortableAttributes`

#### Gap 2: QueryParameter Documentation Incompleteness
- **Model filterable attributes:** 22 fields
- **Controller documented:** Only 9 fields explicitly listed in QueryParameter annotation
- **Missing from docs:** `id`, `slug`, `type_name`, `damage_dice`, `versatile_damage`, `damage_type`, `range_normal`, `range_long`, `armor_class`, `strength_requirement`, `stealth_disadvantage`, `charges_max`
- **Impact:** API users won't know about weapon/armor/charge-specific filter options
- **Recommendation:** Expand QueryParameter annotation to include all 22 filterable fields with examples

#### Gap 3: Sources Not Exposed in toSearchableArray
- **Searchable:** `'sources'` is in searchableAttributes (text search)
- **Not filterable:** Missing from `filterableAttributes` (no `source_codes` filtering in model docs)
- **Conflict:** Model has `source_codes` in filterableAttributes but `sources` in searchableAttributes
- **Status:** ✅ Actually correct - `source_codes` is the filterable version

#### Gap 4: Form Request Sortable Columns Use Different Names
- **Form Request:** `type`, `rarity`
- **Model searchable:** `type_name`, `type_code` (no `type` alone)
- **Issue:** Sort parameter `type` doesn't map to any model field
- **Recommendation:** Update to `type_code` or `type_name`

---

## Detailed Field Cross-Reference

| Field | FilterableAttribute | toSearchableArray | QueryParameter | Sortable | Status |
|-------|:---:|:---:|:---:|:---:|---|
| id | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| name | ❌ | ✅ | ❌ | ✅ | Only searchable, not filterable |
| slug | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| type_name | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| type_code | ✅ | ✅ | ✅ | ❌ | ✅ Documented |
| description | ❌ | ✅ | ❌ | ❌ | Only searchable |
| rarity | ✅ | ✅ | ✅ | ❌ (Form says ✅) | ⚠️ Sortable mismatch |
| requires_attunement | ✅ | ✅ | ✅ | ❌ | ✅ Documented |
| is_magic | ✅ | ✅ | ✅ | ❌ | ✅ Documented |
| weight | ✅ | ✅ | ✅ | ✅ | ✅ Complete |
| cost_cp | ✅ | ✅ | ✅ | ✅ | ✅ Complete |
| source_codes | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| sources | ❌ | ✅ (searchable) | ❌ | ❌ | Only text searchable |
| damage_dice | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| versatile_damage | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| damage_type | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| range_normal | ✅ | ✅ | ❌ | ✅ | Defined but not documented |
| range_long | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| armor_class | ✅ | ✅ | ❌ | ✅ | Defined but not documented |
| strength_requirement | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| stealth_disadvantage | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| charges_max | ✅ | ✅ | ❌ | ❌ | Defined but not documented |
| has_charges | ✅ | ✅ | ✅ | ❌ | ✅ Documented |
| spell_slugs | ✅ | ✅ | ✅ | ❌ | ✅ Documented |
| tag_slugs | ✅ | ✅ | ✅ | ❌ | ✅ Documented |

---

## Summary

**Total Filterable Fields:** 22
**Documented in QueryParameter:** 9 (41%)
**Sortable Fields in Model:** 5
**Sortable Fields in Form Request:** 5 (with 2 mismatched names)

### Critical Issues to Fix

1. **Form Request sortable columns** - Update `['name', 'type', 'rarity', 'created_at', 'updated_at']` to match model's actual sortable attributes `['name', 'weight', 'cost_cp', 'armor_class', 'range_normal']`

2. **QueryParameter annotation** - Expand to document all 22 filterable fields and their types:
   - Weapon/Armor specific: `damage_dice`, `versatile_damage`, `damage_type`, `range_normal`, `range_long`, `armor_class`, `strength_requirement`, `stealth_disadvantage`
   - Infrastructure: `id`, `slug`, `type_name`, `type_code`, `source_codes`
   - Charge mechanics: `charges_max`, `has_charges`

### Recommendations

- [ ] Update `ItemIndexRequest::getSortableColumns()` to return `['name', 'weight', 'cost_cp', 'armor_class', 'range_normal']`
- [ ] Expand ItemController QueryParameter annotation to include all 22 filterable fields with type and example values
- [ ] Add documentation comment explaining that `type_code` is the primary filter (not `type_name`)
- [ ] Document that `source_codes` uses model codes (e.g., 'PHB', 'DMG') while `sources` is text-searchable

---

**Status:** ⚠️ Minor inconsistencies - Documentation gaps, no breaking issues
