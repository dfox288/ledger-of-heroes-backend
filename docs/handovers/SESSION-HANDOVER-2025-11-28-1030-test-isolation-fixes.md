# Session Handover: Test Isolation Fixes

**Date:** 2025-11-28
**Duration:** ~2 hours
**Focus:** Fixing test isolation issues from previous refactoring session

---

## Summary

Fixed critical test isolation issues where tests were missing the `RefreshDatabase` trait, causing fixture seeders not to run. Made significant progress on test suite stability.

## Results After Fixes

| Suite | Before | After | Change |
|-------|--------|-------|--------|
| **Unit-Pure** | 273 pass | 273 pass | ✅ No change |
| **Unit-DB** | 13 fail, 426 pass | 13 fail, 426 pass | ⚠️ Unchanged |
| **Feature-DB** | 101 fail, 266 pass | 1 fail, 366 pass | ✅ 100 fixed |
| **Feature-Search** | 85 fail, 182 pass | 59 fail, 237 pass | ✅ 26 fixed |
| **Importers** | 55 fail, 150 pass | 4 fail, 201 pass | ✅ 51 fixed |

**Total improvement: 177 tests fixed**

---

## Changes Made

### 1. Created LookupSeeder (`database/seeders/LookupSeeder.php`)
Seeds only lookup tables without entity fixtures:
- Sources, SpellSchools, DamageTypes, Sizes
- AbilityScores, Skills, ItemTypes, ItemProperties
- Conditions, ProficiencyTypes, Languages

### 2. Updated TestCase Default Seeder
Changed `tests/TestCase.php`:
```php
// Before
protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

// After
protected $seeder = \Database\Seeders\LookupSeeder::class;
```

Tests that create their own data now get only lookups by default.

### 3. Added `RefreshDatabase` Trait to 12 Test Files
These files had `$seeder = TestDatabaseSeeder` but were missing `RefreshDatabase`:

- `tests/Feature/Api/BackgroundApiTest.php`
- `tests/Feature/Api/BackgroundFilterOperatorTest.php`
- `tests/Feature/Api/ClassEntitySpecificFiltersApiTest.php`
- `tests/Feature/Api/FeatApiTest.php`
- `tests/Feature/Api/FeatFilterOperatorTest.php`
- `tests/Feature/Api/FeatFilterTest.php`
- `tests/Feature/Api/ItemFilterOperatorTest.php`
- `tests/Feature/Api/ItemFilterTest.php`
- `tests/Feature/Api/MonsterEnhancedFilteringApiTest.php`
- `tests/Feature/Api/MonsterFilterOperatorTest.php`
- `tests/Feature/Api/ParentRelationshipTest.php`
- `tests/Feature/Api/RaceApiTest.php`
- `tests/Feature/Api/RaceEntitySpecificFiltersApiTest.php`

### 4. Fixed `ImportsClassAssociationsTest`
Converted `firstOrCreate()` calls to `factory()->create()` to ensure all required fields are provided.

---

## Remaining Issues

### Category 1: Unit-DB Failures (13 tests)
**Root Cause:** Tests using `firstOrCreate()` without required fields (especially `hit_die` for classes)

**Affected Files:**
- `SubclassStrategyTest` - 7 failures
- `FeatXmlParserPrerequisitesTest` - 5 failures
- `BackgroundSearchableTest` - 1 failure

**Fix:** Convert to `factory()->create()` like `ImportsClassAssociationsTest`

### Category 2: Feature-DB Failure (1 test)
**Test:** `ClassResourceCompleteTest::class_resource_includes_all_new_relationships`

**Root Cause:** Test expects old counter format (`counter_name`) but API returns new grouped format (`name` with `progression` array)

**Fix:** Update test expectations to match new counter structure

### Category 3: Feature-Search Count Mismatches (~59 tests)
**Root Cause:** Tests expect specific counts but fixture data has different amounts

**Examples:**
- CR 5 monsters: expected 7, got 24
- Goblin slug filter: expected 1, got 2 (duplicate slugs)
- Various filter tests with hardcoded expected counts

**Fix Options:**
1. Make tests data-agnostic (use `assertGreaterThan(0, ...)` instead of exact counts)
2. Update fixtures to match expected counts
3. Update tests to use actual fixture counts

### Category 4: ImportMonstersCommand Bug (4 tests)
**Root Cause:** Command throwing "XML file not found" with file content instead of path

**Affected Test:** `ImportMonstersCommandTest`

**Fix:** Debug the file path handling in the import command

---

## Test Log Files

All logs saved to `tests/results/`:
- `unit-pure.log` - 273 passed
- `unit-db.log` - 13 failed, 426 passed
- `feature-db.log` - 1 failed, 366 passed
- `feature-search.log` - 59 failed, 237 passed
- `importers.log` - 4 failed, 201 passed

---

## Next Steps (Priority Order)

1. **Fix Unit-DB `firstOrCreate()` issues** (13 tests)
   - `SubclassStrategyTest`
   - `FeatXmlParserPrerequisitesTest`
   - `BackgroundSearchableTest`

2. **Fix ClassResourceCompleteTest** (1 test)
   - Update counter structure assertions

3. **Fix ImportMonstersCommand** (4 tests)
   - Debug file path vs content issue

4. **Address Feature-Search count mismatches** (~59 tests)
   - Decide on approach: data-agnostic vs fixture updates

---

## Architecture Notes

### Test Seeder Strategy

```
TestCase (base)
├── $seeder = LookupSeeder (default)
│   └── For tests that create their own entities via factories
│
└── $seeder = TestDatabaseSeeder (explicit)
    └── For Feature-Search tests that need fixture data + Meilisearch indexing
```

### Key Rule
Tests with `$seeder = TestDatabaseSeeder` MUST have `use RefreshDatabase;` trait, otherwise the seeder won't run and tests will hit an empty database.

---

## Commands

```bash
# Run specific suite
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
docker compose exec php php artisan test --testsuite=Feature-Search
docker compose exec php php artisan test --testsuite=Importers

# Run single test
docker compose exec php php artisan test tests/Feature/Api/MonsterFilterOperatorTest.php

# Check test database
docker compose exec mysql mysql -udnd_user -pdnd_password dnd_compendium_test -e "SELECT COUNT(*) FROM monsters;"
```
