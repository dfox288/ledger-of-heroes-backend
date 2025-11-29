# TODO

Active tasks and priorities for this project.

---

## In Progress

_Tasks currently being worked on_

_None_

## Ready to Execute

_Planned tasks with implementation details ready_

_None_

## Deferred

_Tasks intentionally postponed_

- [ ] Issue #12: Filter irrelevant progression columns (class-specific)
  - Analysis: Counters starting at level 14+ create mostly-empty columns
  - Solution: Add `MIN_COUNTER_START_LEVEL = 14` threshold filter
  - Deferred: Low priority, minimal user impact

---

## Next Up

_Prioritized tasks ready to start_

_None - backlog items available_

---

## Backlog

_Future tasks, not yet prioritized_

### Parser/Importer Hardcoded Values (Medium Priority)
- [ ] Config for default source code in `ParsesSourceCitations.php` (lines 85, 143)
  - Currently falls back to hardcoded 'PHB'
  - Move to `config('import.default_source_code', 'PHB')`
- [ ] Config for default publisher in `SourceXmlParser.php` (line 62)
  - Currently falls back to 'Wizards of the Coast'
  - Move to `config('import.default_publisher', 'Wizards of the Coast')`
- [ ] Dynamic condition regex in `ParsesSavingThrows.php` (line 191)
  - Build regex from `Condition::pluck('slug')` instead of hardcoded list
- [ ] Dynamic condition regex in `ParsesItemSavingThrows.php` (line 92)
  - Same fix as ParsesSavingThrows

### Feature Backlog
- [ ] Character Builder API (see `plans/2025-11-23-character-builder-api-proposal.md`)
- [ ] Search result caching (Phase 4)
- [ ] Additional Monster Strategies
- [ ] Frontend application

---

## Completed

_Recently completed tasks (move to CHANGELOG.md after release)_

- [x] **Fix Progression Table Columns** (2025-11-29)
  - Barbarian: Added `rage_damage` synthetic progression (+2/+3/+4 from PHB prose)
  - Monk: Added `martial_arts` column from parsed level-ordinal text tables; excluded `wholeness_of_body`
  - Rogue: Added `sneak_attack` column from `<roll>` element data tables; excluded `stroke_of_luck`
  - Added Pattern 4 to `ItemTableDetector` for level-ordinal tables
  - Added `ItemTableParser::parseLevelProgression()` for ordinal parsing
  - `ClassProgressionTableGenerator` now pulls from `EntityDataTable` PROGRESSION and DAMAGE types

- [x] **Entity Data Tables Refactor** (2025-11-29)
  - Renamed `random_tables` → `entity_data_tables`
  - Added `DataTableType` enum (random, damage, modifier, lookup, progression)
  - **BREAKING API CHANGE:** JSON key `random_tables` → `data_tables`
  - 62 files changed, all tests passing
  - See `docs/handovers/SESSION-HANDOVER-2025-11-29-1700-entity-data-tables-refactor-complete.md`

- [x] **API Documentation Standardization - Complete** (2025-11-29)
  - Enhanced PHPDoc for all 17 lookup controllers following SpellController gold standard
  - Phase 1: SkillController, SpellSchoolController, ProficiencyTypeController, ItemTypeController, ItemPropertyController
  - Phase 2: ConditionController, LanguageController, DamageTypeController, SizeController
  - Phase 3: SourceController, AlignmentController, ArmorTypeController, MonsterTypeController, OptionalFeatureTypeController, RarityController, TagController, AbilityScoreController
  - Added Scramble `#[QueryParameter]` annotations for OpenAPI docs

- [x] **Laravel Sanctum Authentication** (2025-11-29)
  - Installed Sanctum v4.2, created `personal_access_tokens` migration
  - Created User model with `HasApiTokens` trait and UserFactory
  - Auth endpoints: login, register, logout under `/api/v1/auth/`
  - 27 comprehensive tests (TDD approach, written first)
  - Custom `InvalidCredentialsException` following ApiException pattern
  - Comprehensive PHPDoc for Scramble documentation

- [x] **Optional Features API Test Coverage** (2025-11-28)
  - Created OptionalFeatureApiTest.php (13 tests) - basic endpoints
  - Created OptionalFeatureFilterOperatorTest.php (27 tests) - all filter operators
  - Created OptionalFeatureSearchTest.php (8 tests) - full-text search
  - Total: 48 new tests, 475+ assertions
  - Added to Feature-Search suite in phpunit.xml
- [x] **Issue #13: Remove duplicate hit_points from inherited_data** (2025-11-28)
  - Removed `hit_points` from `inherited_data` section for subclasses
  - Use `computed.hit_points` as single source of truth (resolves inheritance automatically)
  - Reduces API payload size, eliminates data duplication
- [x] **MonsterXmlParser fix** - Changed parser to use `fromString()` for consistency (2025-11-28)
  - Updated 8 test files that were passing file paths instead of XML content
  - Fixed ImportMonstersCommandTest (5 tests) and 7 monster strategy tests
- [x] **SQLite test migration** - Tests use in-memory SQLite instead of MySQL (2025-11-28)
  - ~10x faster test execution (39s total vs ~400s)
  - Unit-Pure: ~3s, Unit-DB: ~7s, Feature-DB: ~9s, Feature-Search: ~20s
  - Fixed MySQL-specific migration (parent_feature_id) for SQLite compatibility
- [x] **Test fixture migration** - Feature-Search tests use fixtures only (2025-11-28)
  - [x] Unit-DB suite: 13 failures fixed (replaced `firstOrCreate()` with `factory()->create()`)
  - [x] Feature-DB suite: 1 failure fixed (updated counter assertions)
  - [x] Feature-Search suite: **37 failures fixed** (was 37 fail, 257 pass)
  - [x] Final status: **0 fail, 286 pass, 28 skipped** (2 incomplete)
  - [x] Made FilterOperatorTest assertions data-agnostic
  - [x] Replaced hardcoded slugs with dynamic fixture queries
  - [x] Added skip logic for missing fixture relationships
  - [x] Updated SpellImportToApiTest API structure
  - See `docs/handovers/SESSION-HANDOVER-2025-11-28-1430-test-fixture-complete.md`
- [x] Fixture-based test data extraction (2025-11-28)
  - [x] Created `fixtures:extract` command
  - [x] Migrated tests from XML imports to JSON fixtures
  - [x] Improved test performance (~30s for Unit-Pure + Unit-DB)
  - [x] Fixed ImportsClassAssociationsTest to use correct slugs
- [x] Class API enhancements (2025-11-26)
  - [x] Subclass level accessor (#7)
  - [x] Nested feature choice options (#8)
  - [x] Feature count excludes choices (#9)
  - [x] Progression table excludes choices (#10)
  - [x] Counter grouping by name (#11)
- [x] Import command refactoring (BaseImportCommand)
- [x] Test suite reorganization (6 independent suites)
- [x] Parser architecture refactoring
- [x] Filter operator testing (100% coverage)

---

## Notes

- See `PROJECT-STATUS.md` for current metrics and milestones
- See `LATEST-HANDOVER.md` for most recent session context
- See `TECH-DEBT.md` for technical debt items
- Update this file when starting/completing tasks
