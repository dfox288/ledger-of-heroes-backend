# Operator Test Matrix

**Purpose:** Define which Meilisearch filter operators to test for each entity based on data types, not individual fields.

**Strategy:** Test operators by data type (integer, string, boolean, array) using one representative field per type per entity. This keeps test count manageable (~110 tests) instead of testing every operator on every field (500+ tests).

---

## Operator Coverage by Data Type

| Data Type | Operators | Count |
|-----------|-----------|-------|
| **Integer** | `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO` | 7 |
| **String** | `=`, `!=` | 2 |
| **Boolean** | `=` (true), `=` (false), `!=` (true), `!=` (false), `IS NULL` | 5 |
| **Array** | `IN`, `NOT IN`, `IS EMPTY` | 3 |

**Note:** Some boolean fields may skip `IS NULL` if they have database-level `NOT NULL` constraints (reduces to 4 operators).

---

## Entity Test Matrix

### 1. Spells (19 tests)

| Field | Type | Operators | Tests |
|-------|------|-----------|-------|
| `level` | Integer | `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO` | 7 |
| `school_code` | String | `=`, `!=` | 2 |
| `concentration` | Boolean | `=` (true), `=` (false), `!=` (true), `!=` (false), `IS NULL` | 5 |
| `class_slugs` | Array | `IN`, `NOT IN`, `IS EMPTY` | 3 |
| `ritual` | Boolean | `=` (true), `!=` (false) | 2 |

**Subtotal:** 19 tests

**Rationale:**
- `level`: Primary filter field (0-9 range) - test all integer operators
- `school_code`: Enum-like string field (abjuration, evocation, etc.)
- `concentration`: Critical boolean for spell selection
- `class_slugs`: Array field for multi-class filtering
- `ritual`: Secondary boolean field (demonstrates multiple boolean support)

---

### 2. Classes (16 tests)

| Field | Type | Operators | Tests |
|-------|------|-----------|-------|
| `hit_die` | Integer | `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO` | 7 |
| `primary_ability` | String | `=`, `!=` | 2 |
| `is_base_class` | Boolean | `=` (true), `=` (false), `!=` (true), `!=` (false) | 4 |
| `tag_slugs` | Array | `IN`, `NOT IN`, `IS EMPTY` | 3 |

**Subtotal:** 16 tests

**Rationale:**
- `hit_die`: Numeric class attribute (d6-d12 range)
- `primary_ability`: String enum (STR, DEX, INT, WIS, CHA)
- `is_base_class`: Boolean filter (NOT NULL constraint - skip IS NULL)
- `tag_slugs`: Array field for taxonomy filtering

---

### 3. Monsters (20 tests)

| Field | Type | Operators | Tests |
|-------|------|-----------|-------|
| `challenge_rating` | Integer | `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO` | 7 |
| `type` | String | `=`, `!=` | 2 |
| `can_hover` | Boolean | `=` (true), `=` (false), `!=` (true), `!=` (false), `IS NULL` | 5 |
| `spell_slugs` | Array | `IN`, `NOT IN`, `IS EMPTY` | 3 |
| `legendary` | Boolean | `=` (true), `!=` (false), `IS NULL` | 3 |

**Subtotal:** 20 tests

**Rationale:**
- `challenge_rating`: Critical numeric filter (0-30 range)
- `type`: String enum (beast, humanoid, dragon, etc.)
- `can_hover`: Nullable boolean (combat mechanic)
- `spell_slugs`: Array field for spellcasting monsters
- `legendary`: Nullable boolean (demonstrates IS NULL handling)

---

### 4. Races (16 tests)

| Field | Type | Operators | Tests |
|-------|------|-----------|-------|
| `speed` | Integer | `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO` | 7 |
| `size_code` | String | `=`, `!=` | 2 |
| `is_subrace` | Boolean | `=` (true), `=` (false), `!=` (true), `!=` (false) | 4 |
| `tag_slugs` | Array | `IN`, `NOT IN`, `IS EMPTY` | 3 |

**Subtotal:** 16 tests

**Rationale:**
- `speed`: Numeric attribute (25-50 range)
- `size_code`: String enum (S, M, L, etc.)
- `is_subrace`: Boolean filter (NOT NULL constraint)
- `tag_slugs`: Array field for taxonomy filtering

---

### 5. Items (17 tests)

| Field | Type | Operators | Tests |
|-------|------|-----------|-------|
| `weight` | Integer | `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO` | 7 |
| `rarity` | String | `=`, `!=` | 2 |
| `requires_attunement` | Boolean | `=` (true), `=` (false), `!=` (true), `!=` (false), `IS NULL` | 5 |
| `source_codes` | Array | `IN`, `NOT IN`, `IS EMPTY` | 3 |

**Subtotal:** 17 tests

**Rationale:**
- `weight`: Numeric attribute (0-300+ range)
- `rarity`: String enum (common, uncommon, rare, etc.)
- `requires_attunement`: Nullable boolean (magic item mechanic)
- `source_codes`: Array field for book filtering

---

### 6. Backgrounds (15 tests)

| Field | Type | Operators | Tests |
|-------|------|-----------|-------|
| `id` | Integer | `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO` | 7 |
| `slug` | String | `=`, `!=` | 2 |
| `grants_language_choice` | Boolean | `=` (true), `=` (false), `!=` (true), `!=` (false) | 4 |
| `skill_proficiencies` | Array | `IN`, `NOT IN` | 2 |

**Subtotal:** 15 tests

**Rationale:**
- `id`: Integer primary key (demonstrates numeric filtering)
- `slug`: String unique identifier
- `grants_language_choice`: Boolean filter (NOT NULL constraint)
- `skill_proficiencies`: Array field (skip IS EMPTY - all backgrounds grant skills)

---

### 7. Feats (15 tests)

| Field | Type | Operators | Tests |
|-------|------|-----------|-------|
| `id` | Integer | `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO` | 7 |
| `slug` | String | `=`, `!=` | 2 |
| `has_prerequisites` | Boolean | `=` (true), `=` (false), `!=` (true), `!=` (false) | 4 |
| `tag_slugs` | Array | `IN`, `NOT IN` | 2 |

**Subtotal:** 15 tests

**Rationale:**
- `id`: Integer primary key
- `slug`: String unique identifier
- `has_prerequisites`: Boolean filter (NOT NULL constraint)
- `tag_slugs`: Array field (skip IS EMPTY - most feats have tags)

---

## Summary

| Entity | Integer Tests | String Tests | Boolean Tests | Array Tests | **Total** |
|--------|--------------|--------------|---------------|-------------|-----------|
| Spells | 7 | 2 | 7 (2 fields) | 3 | **19** |
| Classes | 7 | 2 | 4 | 3 | **16** |
| Monsters | 7 | 2 | 8 (2 fields) | 3 | **20** |
| Races | 7 | 2 | 4 | 3 | **16** |
| Items | 7 | 2 | 5 | 3 | **17** |
| Backgrounds | 7 | 2 | 4 | 2 | **15** |
| Feats | 7 | 2 | 4 | 2 | **15** |

**Grand Total:** 118 tests

---

## Test Organization

**Test Files:**
- `tests/Feature/Api/SpellFilterOperatorTest.php` (19 tests)
- `tests/Feature/Api/ClassFilterOperatorTest.php` (16 tests)
- `tests/Feature/Api/MonsterFilterOperatorTest.php` (20 tests)
- `tests/Feature/Api/RaceFilterOperatorTest.php` (16 tests)
- `tests/Feature/Api/ItemFilterOperatorTest.php` (17 tests)
- `tests/Feature/Api/BackgroundFilterOperatorTest.php` (15 tests)
- `tests/Feature/Api/FeatFilterOperatorTest.php` (15 tests)

**Naming Convention:**
```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_filters_by_{field}_with_{operator}() { }

// Examples:
public function it_filters_by_level_with_equals() { }
public function it_filters_by_level_with_greater_than() { }
public function it_filters_by_school_code_with_not_equals() { }
public function it_filters_by_concentration_with_equals_true() { }
public function it_filters_by_class_slugs_with_in() { }
```

---

## Why This Approach?

**Benefits:**
1. **Manageable test count:** 118 tests vs 500+ if testing all operators on all fields
2. **Complete operator coverage:** Every operator tested for every data type
3. **Representative sampling:** One field per data type proves Meilisearch indexing works
4. **Fast execution:** Smaller test suite runs faster
5. **Easy maintenance:** Clear matrix shows what's tested and why

**What we DON'T need to test:**
- Every integer field with all 7 operators (if `level` works with `>`, `hit_die` will too)
- Every string field with all 2 operators (if `school_code` works with `=`, `slug` will too)
- Every boolean field with all 5 operators (if `concentration` works, `ritual` will too)
- Every array field with all 3 operators (if `class_slugs` works with `IN`, `tag_slugs` will too)

**Assumption:** Meilisearch operator behavior is consistent across fields of the same data type. We're testing integration (indexing + filtering), not Meilisearch's operator logic.

---

**Next Steps:**
1. Create operator test files per entity
2. Implement tests following this matrix
3. Verify 118 tests pass
4. Document results in session handover
