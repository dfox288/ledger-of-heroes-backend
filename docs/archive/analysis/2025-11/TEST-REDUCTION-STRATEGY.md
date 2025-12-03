# Test Reduction Strategy

**Date:** 2025-11-22
**Current State:** 1,041 tests across 155 test files
**Goal:** Reduce to ~600-700 tests while maintaining >90% coverage

---

## Executive Summary

**Current Test Breakdown:**
- **Total Tests:** 1,041 tests
- **Total Files:** 155 test files
- **Feature Tests:** 91 files (35 API, 19 Importers, 16 Requests, 11 Models, 5 Migrations, 1 Seeders)
- **Unit Tests:** 62 files (24 Parsers, 14 Services, 9 Models, 5 Item Strategies, 5 Monster Strategies, 3 Exceptions, 2 Importers)

**Estimated Reduction:** 300-400 tests (30-40% reduction)

**Target:** 600-700 high-value tests with improved maintainability

---

## Redundancy Analysis

### 1. **CRITICAL: Duplicate Lookup API Tests (HIGH PRIORITY)**

**Problem:** Complete duplication between `LookupApiTest.php` and individual lookup entity test files.

**Files with Redundancy:**
- `LookupApiTest.php` (10 tests) - Generic tests for all lookup endpoints
- `SourceApiTest.php` (8 tests) - Duplicate coverage + specific features
- `SpellSchoolApiTest.php` (7 tests) - Duplicate coverage
- `DamageTypeApiTest.php` (4 tests) - Duplicate coverage
- `ConditionApiTest.php` (4 tests) - Duplicate coverage
- `ItemTypeApiTest.php` (4 tests) - Duplicate coverage
- `ItemPropertyApiTest.php` (4 tests) - Duplicate coverage
- `LanguageApiTest.php` (7 tests) - Duplicate coverage
- `ProficiencyTypeApiTest.php` (11 tests) - Duplicate coverage

**Overlap:**
- `LookupApiTest::test_can_get_all_sources()` duplicates `SourceApiTest::it_can_list_all_sources()`
- `LookupApiTest::test_can_get_single_source()` duplicates `SourceApiTest::it_can_get_a_single_source_by_id()`
- **Same pattern across ALL lookup entities**

**Recommendation: DELETE `LookupApiTest.php`**
- **Tests to Delete:** 10 tests
- **Justification:** Individual entity test files provide better coverage with entity-specific tests
- **Keep:** Individual files (SourceApiTest, etc.) - they test pagination, search, filtering
- **Delete:** LookupApiTest.php - 100% redundant

**Impact:** -10 tests, -1 file

---

### 2. **Search Test Duplication (MEDIUM PRIORITY)**

**Problem:** Search functionality tested in 3 places for each entity.

**Files:**
- Entity search tests: `SpellSearchTest.php`, `BackgroundSearchTest.php`, `ClassSearchTest.php`, `FeatSearchTest.php`, `ItemSearchTest.php`, `RaceSearchTest.php`, `MonsterSearchTest.php` (7 files)
- Entity API tests: `SpellApiTest.php`, `BackgroundApiTest.php`, etc. (already test list/show)
- Global search test: `GlobalSearchTest.php`

**Pattern per Entity:**
```php
// In BackgroundSearchTest.php (3 tests)
it_searches_backgrounds_using_scout_when_available()
it_validates_search_query_minimum_length()
it_handles_empty_search_query_gracefully()

// In BackgroundApiTest.php (10 tests)
// Already tests list endpoint, could include search as subset
```

**Current State:**
- 7 entity-specific search test files
- Each has 3-8 tests (average ~5 tests)
- Total: ~35-40 tests for search
- **Duplication:** Validation logic tested 7 times (once per entity)

**Recommendation: CONSOLIDATE to Entity API Tests**
- **Move** 1-2 search-specific tests INTO each entity's main API test file
- **Delete** separate `*SearchTest.php` files
- **Keep** `GlobalSearchTest.php` (tests cross-entity search)

**Example Consolidation:**
```php
// DELETE: BackgroundSearchTest.php (3 tests)
// ADD TO: BackgroundApiTest.php (2 new tests)

#[Test]
public function it_searches_backgrounds_with_scout(): void
{
    // Covers scout search + validation in one test
}

#[Test]
public function it_filters_search_results(): void
{
    // Covers filtered search
}
```

**Impact:**
- **Delete:** 7 files (BackgroundSearchTest, ClassSearchTest, FeatSearchTest, ItemSearchTest, RaceSearchTest, SpellSearchTest, MonsterSearchTest)
- **Tests Removed:** ~35 tests
- **Tests Added:** ~14 tests (2 per entity in main API test)
- **Net Reduction:** ~21 tests, -7 files

---

### 3. **XML Reconstruction Tests (MEDIUM-LOW PRIORITY)**

**Problem:** Comprehensive but potentially over-tested "round-trip" import tests.

**Files:**
- `SpellXmlReconstructionTest.php` (12 tests)
- `ClassXmlReconstructionTest.php` (9 tests)
- `FeatXmlReconstructionTest.php` (9 tests)
- `RaceXmlReconstructionTest.php` (9 tests)
- `BackgroundXmlReconstructionTest.php` (6 tests)
- `ItemXmlReconstructionTest.php` (30 tests) ⚠️ LARGEST

**Total:** 6 files, ~75 tests

**Purpose:** Verify XML → Import → Database → Export cycle preserves data fidelity

**Analysis:**
- **Value:** High for catching regression in complex parsing
- **Cost:** Slow tests (database + importer setup)
- **Overlap:** Already have dedicated Importer tests and Parser tests

**Recommendation: REDUCE by 50%**
- **Keep:** 1-2 complex "happy path" tests per entity (covers common cases)
- **Delete:** Edge cases already covered by Unit/Parser tests
- **Strategy:** Merge into main Importer test file (e.g., merge `SpellXmlReconstructionTest` → `SpellImporterTest`)

**Example:**
```php
// BEFORE: SpellXmlReconstructionTest.php (12 tests)
it_reconstructs_simple_cantrip()
it_reconstructs_spell_with_concentration()
it_reconstructs_spell_with_ritual()
it_reconstructs_spell_with_saving_throw()
it_reconstructs_spell_with_multiple_damage_types()
it_reconstructs_spell_with_effects()
it_reconstructs_spell_with_scaling_damage()
it_reconstructs_spell_with_classes()
it_reconstructs_spell_with_sources()
it_reconstructs_spell_with_components()
it_reconstructs_spell_with_range_self()
it_reconstructs_spell_with_duration_concentration()

// AFTER: Merged into SpellImporterTest.php (2-3 tests)
it_imports_complex_spell_with_all_features()  // Combined test
it_imports_spell_with_relationships()          // Classes, sources, effects
```

**Impact:**
- **Delete:** ~40 tests (keep ~35)
- **Net Reduction:** ~40 tests, 0 files deleted (merged into existing)

---

### 4. **Form Request Tests (LOW PRIORITY)**

**Problem:** 16 dedicated Form Request test files validating common patterns.

**Files:**
- `SpellIndexRequestTest.php` (9 tests)
- `SpellShowRequestTest.php` (6 tests)
- `RaceIndexRequestTest.php` (8 tests)
- `RaceShowRequestTest.php` (5 tests)
- `BackgroundIndexRequestTest.php` (8 tests)
- `BackgroundShowRequestTest.php` (4 tests)
- `FeatIndexRequestTest.php` (9 tests)
- `FeatShowRequestTest.php` (5 tests)
- `ItemIndexRequestTest.php` (9 tests)
- `ItemShowRequestTest.php` (6 tests)
- `ClassIndexRequestTest.php` (9 tests)
- `ClassShowRequestTest.php` (6 tests)
- `ClassSpellListRequestTest.php` (7 tests)
- `SourceIndexRequestTest.php` (6 tests)
- `SpellSchoolIndexRequestTest.php` (5 tests)
- `ProficiencyTypeIndexRequestTest.php` (7 tests)

**Total:** 16 files, ~109 tests

**Pattern:** Each tests same validation rules (per_page, sort_by, page, filters)

**Analysis:**
- **Repetition:** `it_validates_per_page_limit()` tested 9+ times (once per IndexRequest)
- **Value:** Ensures OpenAPI docs are accurate via Scramble
- **Overlap:** API tests already validate these parameters indirectly

**Recommendation: CONSOLIDATE Generic Validation**
- **Create:** `GenericRequestValidationTest.php` (1 file, ~10 tests)
  - Tests: per_page, sort_by, sort_direction, page validation
  - Uses data provider for multiple request classes
- **Keep:** Entity-specific tests (e.g., `level` for Spells, `challenge_rating` for Monsters)
- **Delete:** Redundant generic tests from individual files

**Example:**
```php
// NEW: GenericRequestValidationTest.php
#[Test]
#[DataProvider('indexRequestProvider')]
public function it_validates_per_page_limit(string $requestClass, string $endpoint): void
{
    // Generic test for all IndexRequest classes
}

public static function indexRequestProvider(): array
{
    return [
        [SpellIndexRequest::class, '/api/v1/spells'],
        [RaceIndexRequest::class, '/api/v1/races'],
        // ... all Index requests
    ];
}
```

**Impact:**
- **Delete:** ~60 generic validation tests
- **Add:** ~10 generic tests with data providers
- **Net Reduction:** ~50 tests, 0 files deleted

---

### 5. **Migration Tests (LOW PRIORITY)**

**Problem:** Testing schema changes that are stable and unlikely to change.

**Files:**
- `BackgroundsTableSimplifiedTest.php` (3 tests)
- `ClassLevelProgressionSpellsKnownTest.php` (2 tests)
- `MigrateItemStrengthRequirementTest.php` (2 tests)
- `ProficienciesChoiceSupportTest.php` (4 tests)
- `ModifierChoiceSupportTest.php` (3 tests)

**Total:** 5 files, ~14 tests

**Analysis:**
- **Purpose:** Verify migration ran correctly
- **Value:** High during development, LOW in stable codebase
- **Overlap:** Model tests already verify relationships work

**Recommendation: DELETE Most Migration Tests**
- **Keep:** None (migrations are tested by running them in CI)
- **Delete:** All 5 files
- **Rationale:**
  - Migrations are one-time operations
  - Schema is validated by Model tests
  - `php artisan migrate:fresh` in CI catches issues

**Impact:** -14 tests, -5 files

---

### 6. **Outdated/Unnecessary Tests**

**Files to Delete:**
- `ExampleTest.php` (1 test) - Laravel boilerplate, tests `/` route
- `DockerEnvironmentTest.php` (2 tests) - Infrastructure test, belongs in CI
- `ScrambleDocumentationTest.php` (1 test) - Validates OpenAPI generation works

**Total:** 3 files, ~4 tests

**Recommendation: DELETE All**
- **ExampleTest:** No value (placeholder)
- **DockerEnvironmentTest:** CI should verify environment, not tests
- **ScrambleDocumentationTest:** Scramble handles its own validation

**Impact:** -4 tests, -3 files

---

## Consolidation Opportunities

### 7. **Parser Tests (MEDIUM PRIORITY)**

**Files:** 24 parser test files in `tests/Unit/Parsers/`

**Analysis:**
- Well-organized, focused tests
- Good coverage of XML parsing edge cases
- Some overlap with Importer tests

**Recommendation: MINOR CONSOLIDATION**
- **Merge:** `ParsesTraitsTest.php` → `RaceXmlParserTest.php` (traits used primarily in Races)
- **Merge:** `ParsesRollsTest.php` → `SpellXmlParserTest.php` (rolls used primarily in Spells)
- **Keep:** Most parser tests (good unit test coverage)

**Impact:** -12 tests, -2 files

---

### 8. **Seeder Tests (LOW PRIORITY)**

**File:** `ConditionSeederTest.php` (4 tests)

**Analysis:**
- Tests seeder creates 15 conditions
- Seeder unlikely to change

**Recommendation: DELETE**
- Seeders are data fixtures, not business logic
- Validated by Integration tests that use seeded data

**Impact:** -4 tests, -1 file

---

## Summary of Recommendations

| Priority | Category | Action | Tests Removed | Files Removed | Effort |
|----------|----------|--------|---------------|---------------|--------|
| **HIGH** | Lookup API Duplication | Delete `LookupApiTest.php` | -10 | -1 | 5 min |
| **MEDIUM** | Search Test Duplication | Consolidate into API tests | -21 | -7 | 2 hours |
| **MEDIUM-LOW** | XML Reconstruction | Reduce by 50% | -40 | 0 | 3 hours |
| **LOW** | Form Request Generic | Consolidate validation | -50 | 0 | 4 hours |
| **LOW** | Migration Tests | Delete all | -14 | -5 | 10 min |
| **LOW** | Outdated Tests | Delete all | -4 | -3 | 5 min |
| **LOW** | Parser Consolidation | Merge trait/roll tests | -12 | -2 | 1 hour |
| **LOW** | Seeder Tests | Delete | -4 | -1 | 5 min |
| **TOTAL** | | | **-155 tests** | **-19 files** | **~10 hours** |

**Final Result:** 1,041 → ~886 tests (15% reduction with minimal effort)

**With Aggressive Consolidation:** 1,041 → ~640 tests (38% reduction, all priorities implemented)

---

## Implementation Plan

### Phase 1: Quick Wins (1 hour, -28 tests, -9 files)
1. ✅ Delete `ExampleTest.php`
2. ✅ Delete `DockerEnvironmentTest.php`
3. ✅ Delete `ScrambleDocumentationTest.php`
4. ✅ Delete `LookupApiTest.php`
5. ✅ Delete all Migration test files (5 files)
6. ✅ Delete `ConditionSeederTest.php`
7. ✅ Run tests to verify no regressions

**Result:** 1,041 → 1,013 tests (-2.7% reduction)

### Phase 2: Search Consolidation (2 hours, -21 tests, -7 files)
1. Add 2 search tests to each entity API test file
2. Delete all `*SearchTest.php` files (7 files)
3. Run tests to verify coverage maintained

**Result:** 1,013 → 992 tests (-4.7% total reduction)

### Phase 3: Form Request Consolidation (4 hours, -50 tests, 0 files)
1. Create `GenericRequestValidationTest.php` with data providers
2. Remove redundant validation tests from individual Request test files
3. Keep entity-specific validation tests
4. Run tests to verify coverage maintained

**Result:** 992 → 942 tests (-9.5% total reduction)

### Phase 4: XML Reconstruction Reduction (3 hours, -40 tests, 0 files)
1. Merge reconstruction tests into main Importer test files
2. Keep 2-3 comprehensive tests per entity
3. Delete granular edge-case tests
4. Run tests to verify coverage maintained

**Result:** 942 → 902 tests (-13.4% total reduction)

### Phase 5: Parser Consolidation (1 hour, -12 tests, -2 files)
1. Merge `ParsesTraitsTest` into `RaceXmlParserTest`
2. Merge `ParsesRollsTest` into `SpellXmlParserTest`
3. Run tests to verify coverage maintained

**Result:** 902 → 890 tests (-14.5% total reduction)

---

## Coverage Validation Strategy

**Before Any Deletions:**
```bash
docker compose exec php php artisan test --coverage-text > coverage-before.txt
```

**After Each Phase:**
```bash
docker compose exec php php artisan test --coverage-text > coverage-phase-N.txt
diff coverage-before.txt coverage-phase-N.txt
```

**Acceptance Criteria:**
- Total test count: 850-900 tests
- Coverage: ≥90% (maintain current level)
- Test duration: <45 seconds (currently ~52s)
- All tests passing

---

## Risks & Mitigations

### Risk 1: Loss of Edge Case Coverage
**Mitigation:** Keep tests for complex/critical features (importers, parsers, strategies)

### Risk 2: Reduced Documentation Value
**Mitigation:** Keep entity-specific tests, only remove generic validation duplication

### Risk 3: Regression Introduction
**Mitigation:** Run full test suite after each deletion, verify coverage metrics

### Risk 4: Scramble OpenAPI Validation
**Mitigation:** Keep at least 1 test per Form Request to ensure Scramble parses validation rules

---

## Alternative: Strategic Test Categorization

If full deletion is too risky, consider **test tagging**:

```php
#[Group('integration')]
#[Group('slow')]
public function it_imports_complex_spell(): void
{
    // Heavy integration test
}

#[Group('unit')]
#[Group('fast')]
public function it_parses_spell_level(): void
{
    // Fast unit test
}
```

**Benefits:**
- Run fast tests locally: `php artisan test --group=fast`
- Run full suite in CI: `php artisan test`
- Identify slow tests: `php artisan test --group=slow`

---

## Conclusion

**Recommended Approach:** Implement **Phase 1-2** for immediate 7% reduction with minimal risk.

**Aggressive Approach:** Implement all phases for 14-15% reduction.

**Ultra-Aggressive Approach:** Add Form Request consolidation for 38% reduction (requires significant refactoring).

**Key Principle:** Delete tests that verify the same behavior multiple times, keep tests that verify unique functionality.

---

## Appendix: Test File Inventory

### Feature Tests (91 files)
**Api (35 files):**
- BackgroundApiTest.php, BackgroundSearchTest.php
- ClassApiTest.php, ClassResourceCompleteTest.php, ClassSearchTest.php, ClassSpellListTest.php
- ConditionApiTest.php
- CorsTest.php
- DamageTypeApiTest.php
- FeatApiTest.php, FeatFilterTest.php, FeatPrerequisitesApiTest.php, FeatSearchTest.php
- GlobalSearchTest.php
- ItemFilterTest.php, ItemPrerequisitesApiTest.php, ItemPropertyApiTest.php, ItemSearchTest.php, ItemSpellsApiTest.php, ItemTypeApiTest.php
- LanguageApiTest.php
- LookupApiTest.php ⚠️ DELETE
- MonsterApiTest.php, MonsterSearchTest.php
- ProficiencyTypeApiTest.php
- RaceApiTest.php, RaceFilterTest.php, RaceSearchTest.php
- SourceApiTest.php
- SpellApiTest.php, SpellFilterExceptionTest.php, SpellMeilisearchFilterTest.php, SpellSchoolApiTest.php, SpellSearchTest.php
- TagIntegrationTest.php

**Importers (19 files):**
- BackgroundXmlReconstructionTest.php
- ClassImporterTest.php, ClassXmlReconstructionTest.php
- FeatImporterPrerequisitesTest.php, FeatImporterTest.php, FeatXmlReconstructionTest.php
- ImporterFileNotFoundTest.php
- ItemChargesImportTest.php, ItemConditionalSpeedModifierTest.php, ItemPrerequisitesImporterTest.php, ItemSpellsImportTest.php, ItemXmlReconstructionTest.php
- MonsterImporterTest.php
- RaceImporterTest.php, RaceXmlReconstructionTest.php
- SpellClassMappingImporterTest.php, SpellImporterTest.php, SpellRandomTableImportTest.php, SpellXmlReconstructionTest.php

**Requests (16 files):**
- BackgroundIndexRequestTest.php, BackgroundShowRequestTest.php
- ClassIndexRequestTest.php, ClassShowRequestTest.php, ClassSpellListRequestTest.php
- FeatIndexRequestTest.php, FeatShowRequestTest.php
- ItemIndexRequestTest.php, ItemShowRequestTest.php
- ProficiencyTypeIndexRequestTest.php
- RaceIndexRequestTest.php, RaceShowRequestTest.php
- SourceIndexRequestTest.php
- SpellIndexRequestTest.php, SpellSchoolIndexRequestTest.php, SpellShowRequestTest.php

**Models (11 files):**
- BackgroundModelTest.php
- ConditionModelTest.php
- EntityPrerequisiteModelTest.php
- EntitySourceTest.php
- EntitySpellModelTest.php
- FeatModelTest.php
- ModifierModelTest.php
- ProficiencyModelTest.php
- RaceModelTest.php
- RandomTableModelTest.php
- TraitModelTest.php

**Migrations (5 files):** ⚠️ DELETE ALL
- BackgroundsTableSimplifiedTest.php
- ClassLevelProgressionSpellsKnownTest.php
- MigrateItemStrengthRequirementTest.php
- ModifierChoiceSupportTest.php
- ProficienciesChoiceSupportTest.php

**Other (5 files):**
- Console/ImportMonstersCommandTest.php
- DockerEnvironmentTest.php ⚠️ DELETE
- ExampleTest.php ⚠️ DELETE
- ScrambleDocumentationTest.php ⚠️ DELETE
- Seeders/ConditionSeederTest.php ⚠️ DELETE

### Unit Tests (62 files)
**Parsers (24 files):**
- BackgroundXmlParserTest.php
- ClassXmlParserProficiencyChoicesTest.php, ClassXmlParserTest.php
- Concerns/ConvertsWordNumbersTest.php, Concerns/LookupsGameEntitiesTest.php, Concerns/MapsAbilityCodesTest.php, Concerns/MatchesProficiencyTypesTest.php, Concerns/ParsesRollsTest.php, Concerns/ParsesTraitsTest.php
- FeatXmlParserPrerequisitesTest.php, FeatXmlParserTest.php
- ItemChargesParserTest.php, ItemProficiencyParserTest.php, ItemXmlParserTest.php
- LuckBladeChargesTest.php
- MonsterXmlParserTest.php
- RaceXmlParserTest.php
- SpellRandomTableParserTest.php, SpellSavingThrowsParserTest.php

**Services (14 files):**
- Importers/Concerns/ImportsConditionsTest.php, Importers/Concerns/ImportsLanguagesTest.php, Importers/Concerns/ImportsModifiersTest.php
- Importers/Concerns/GeneratesSlugsTest.php, Importers/Concerns/ImportsRandomTablesTest.php
- ItemTableDetectorTest.php, ItemTableParserTest.php
- Parsers/MatchesProficiencyTypesTest.php
- Parsers/Strategies/AbstractItemStrategyTest.php, Parsers/Strategies/ChargedItemStrategyTest.php, Parsers/Strategies/LegendaryStrategyTest.php, Parsers/Strategies/PotionStrategyTest.php, Parsers/Strategies/ScrollStrategyTest.php, Parsers/Strategies/TattooStrategyTest.php

**Strategies (5 files - Monster):**
- Strategies/AbstractMonsterStrategyTest.php
- Strategies/DefaultStrategyTest.php
- Strategies/DragonStrategyTest.php
- Strategies/SwarmStrategyTest.php
- Strategies/UndeadStrategyTest.php

**Models (9 files):**
- BackgroundSearchableTest.php
- CharacterClassSearchableTest.php
- FeatSearchableTest.php
- ItemSearchableTest.php
- MonsterTest.php
- RaceSearchableTest.php
- SourceTest.php
- SpellSearchableTest.php
- SpellTest.php

**Factories (4 files):**
- PolymorphicFactoriesTest.php
- RandomTableFactoriesTest.php
- SpellEffectFactoryTest.php

**Exceptions (3 files):**
- Import/FileNotFoundExceptionTest.php
- Lookup/EntityNotFoundExceptionTest.php
- Search/InvalidFilterSyntaxExceptionTest.php

**Importers (2 files):**
- Concerns/GeneratesSlugsTest.php
- Concerns/ImportsRandomTablesTest.php

**Resources (1 file):**
- (None currently)

---

**End of Document**
