# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Spell Random Tables API Exposure** - Random tables now available in API responses
  - `SpellResource` includes `random_tables` field with nested entries
  - Eager-loaded by default on spell detail endpoint
  - Optional include support via `?include=randomTables.entries`
  - Returns structured dice tables with roll ranges and results
  - Example: Prismatic Spray's d8 ray color table, Confusion's d10 behavior table
- **Test Coverage for Item Lookup Endpoints** - Completed test coverage for `?q` search parameter
  - New test suite: `ItemTypeApiTest` (4 tests, 26 assertions)
  - New test suite: `ItemPropertyApiTest` (4 tests, 27 assertions)
  - Verifies search by name, case-insensitive search, empty results handling

### Changed
- Updated `SpellResource` to include `RandomTableResource::collection($this->whenLoaded('randomTables'))`
- Updated `SpellShowRequest` to allow `randomTables` and `randomTables.entries` in include list
- Updated `SpellController::show()` to eager-load random tables with entries by default
- Added 2 new tests for random table API exposure (17 assertions)

### Test Coverage
- **798 tests passing** (5,390 assertions)

`★ Insight ─────────────────────────────────────`
**API Design Pattern:**
Random tables use Laravel's `whenLoaded()` pattern, meaning they're only included when explicitly eager-loaded. This prevents N+1 queries while keeping the API flexible. By default, the spell detail endpoint includes them, but list endpoints can opt-in via `?include=randomTables.entries`.
`─────────────────────────────────────────────────`

### Planned
- Monster importer (7 bestiary XML files ready)
- Import remaining spell files (~300 spells)
- Additional API filtering and aggregation
- Rate limiting
- Caching strategy

## [2025-11-22] - Spell Random Tables & API Search Consistency

### Added
- **Spell Random Table Support** - Automatically parse and import random tables from spell descriptions
  - Detects pipe-delimited tables (e.g., Prismatic Spray's d8 power table, Confusion's d10 behavior table)
  - Reuses existing `ItemTableDetector` and `ItemTableParser` infrastructure
  - Stores tables in polymorphic `random_tables` + `random_table_entries`
  - New `randomTables()` relationship on Spell model
  - Supports multiple tables per spell
  - Handles roll ranges (e.g., "2-6") and single rolls (e.g., "1")
- **9 new tests** for spell random table parsing and importing (69 assertions)
  - Parser tests: Verifies table detection, entry parsing, multiple tables
  - Importer tests: Verifies database persistence, re-import cleanup, edge cases

### Changed
- `SpellXmlParser::parseSpell()` now includes `random_tables` array in parsed data
- `SpellImporter` uses `ImportsRandomTables` trait for consistent table handling
- Spell description preserves table content (tables not stripped from text)

### Test Coverage
- **788 tests passing** (5,234 assertions)
- All spell random table tests green

`★ Technical Note ─────────────────────────────────────`
**Code Reuse FTW:**
This feature leveraged 100% existing infrastructure:
- `ItemTableDetector` - 3 regex patterns for table detection
- `ItemTableParser` - Parses roll ranges + result text
- `ImportsRandomTables` trait - Creates RandomTable + entries
- Polymorphic relationships - Spell → RandomTable (same as CharacterTrait → RandomTable)

Total new code: ~25 lines (parseRandomTables method + import call). Everything else was reusable!
`─────────────────────────────────────────────────────`

## [2025-11-22] - API Search Parameter Consistency

### Fixed
- **Standardized search parameter across all API endpoints**
  - All lookup/static table endpoints now use `?q=` parameter instead of `?search=`
  - Consistent with main entity endpoints (Spells, Items, Races, Classes, Backgrounds, Feats)
  - Affected endpoints: Sources, Languages, Spell Schools, Damage Types, Conditions, Proficiency Types, Sizes, Ability Scores, Skills, Item Types, Item Properties

### Changed
- Updated 11 controllers to use `q` parameter for search queries
- Updated `BaseLookupIndexRequest` validation rules to accept `q` instead of `search`
- All search functionality uses SQL LIKE queries (appropriate for small static tables)

### Added
- **Comprehensive test coverage for lookup endpoint search**
  - New test suites: `SourceApiTest`, `LanguageApiTest`, `SpellSchoolApiTest`, `DamageTypeApiTest`, `ConditionApiTest`
  - Tests verify search by name, search by code (where applicable), case-insensitive search, empty results, and pagination
  - Updated existing tests to use `q` parameter

### Test Coverage
- **779 tests passing** (5,165 assertions)
- All API search endpoints now thoroughly tested

`★ Insight ─────────────────────────────────────`
**Why This Matters:**
API consistency is critical for developer experience. Before this fix, developers had to remember two different parameter names (`?q=` for entities, `?search=` for lookups). Now **all** endpoints use `?q=`, making the API intuitive and predictable. This also fixes the bug where `?q=xanathar` on `/api/v1/sources` would return ALL results instead of filtering.
`─────────────────────────────────────────────────`

## [2025-11-21] - Saving Throw Modifiers & Universal Tags

### Added
- **Saving Throw Modifiers** - Track advantage/disadvantage on spell saving throws
  - New `save_modifier` enum: 'none', 'advantage', 'disadvantage'
  - Enables filtering buff spells and conditional saves
  - Character builders can optimize spell selection
- **Universal Tag System** - All 6 main entities now support Spatie Tags
  - Tags available on: Spells, Races, Items, Backgrounds, Classes, Feats
  - TagResource included by default in API responses
  - Consistent categorization across all entity types
- 4 new migrations for saving throw schema enhancements
- `SavingThrowResource` API resource

### Changed
- Updated SpellResource to include saving throw modifiers
- Enhanced SpellXmlParser to detect advantage/disadvantage patterns
- SpellImporter now processes save_modifier data

### Documentation
- Added `docs/SAVE-EFFECTS-PATTERN-ANALYSIS.md`
- Added `docs/SESSION-HANDOVER-2025-11-21-ADVANTAGE-DISADVANTAGE.md`
- Added `docs/SESSION-HANDOVER-2025-11-21-SAVING-THROWS.md`

### Test Coverage
- **750 tests passing** (4,828 assertions)
- Added `tests/Unit/Parsers/SpellSavingThrowsParserTest.php`

## [2025-11-20] - Custom Exceptions & Error Handling

### Added
- **Custom Exception System** (Phase 1)
  - `InvalidFilterSyntaxException` (422) - Meilisearch filter validation
  - `FileNotFoundException` (404) - Missing XML files
  - `EntityNotFoundException` (404) - Missing lookup entities
- Service-layer exception pattern with single-return controllers
- Auto-rendering via Laravel exception handler

### Documentation
- Added `docs/recommendations/CUSTOM-EXCEPTIONS-ANALYSIS.md`

## [Previous Features] - Core System Implementation

### Database & Schema
- **63 migrations** - Complete schema design
  - Dual ID/slug routing for all entities
  - Polymorphic relationships (traits, modifiers, proficiencies, tags, prerequisites)
  - Multi-source citations via `entity_sources`
  - Language system with choice slots
  - Random tables (d6/d8/d100)
- **23 models** with HasFactory trait
- **12 database seeders**
  - Sources, spell schools, damage types, conditions
  - 82 proficiency types
  - 30 D&D languages
  - Sizes, ability scores, skills, item types/properties

### API Layer
- **RESTful API** with `/api/v1` base path
- **17 controllers** (6 entity + 11 lookup)
- **25 API Resources** for consistent serialization
- **26 Form Requests** for validation
  - Naming convention: `{Entity}{Action}Request`
  - OpenAPI documentation integration
- **CORS enabled** for cross-origin requests
- **OpenAPI/Swagger documentation** via Scramble (306KB spec)
  - Auto-generated from code
  - Available at `http://localhost:8080/docs/api`

### Entity Endpoints
- **Spells** - 477 imported from 9 XML files
  - Spell schools, damage types, components, casting time
  - Spell effects, conditions, saving throws
  - Class associations, sourcebook citations
- **Classes** - 131 classes/subclasses from 35 XML files
  - Class spell lists endpoint
  - Hit dice, proficiencies, equipment
- **Races** - Races and subraces (5 XML files ready)
  - Traits, ability score increases, languages
  - Speed, size, darkvision
- **Items** - Equipment and magic items (25 XML files ready)
  - Item types, properties, rarity
  - Weight, cost, attunement requirements
- **Backgrounds** - Character backgrounds (4 XML files ready)
  - Proficiencies, equipment, features
- **Feats** - Character feats (4 XML files ready)
  - Prerequisites, ability score increases

### Search System
- **Laravel Scout + Meilisearch**
  - 6 searchable entity types
  - 3,002 documents indexed
  - Global search endpoint: `/api/v1/search`
  - Typo-tolerant search ("firebll" → "Fireball")
  - Performance: <50ms average, <100ms p95
- **Advanced Meilisearch Filtering**
  - Range queries: `level >= 1 AND level <= 3`
  - Logical operators: `school_code = EV OR school_code = C`
  - Combined search + filter queries
  - Graceful fallback to MySQL FULLTEXT
- Search configuration artisan command

### XML Import System
- **6 working importers**
  - `import:spells` - 9 XML files available
  - `import:races` - 5 XML files available
  - `import:items` - 25 XML files available
  - `import:backgrounds` - 4 XML files available
  - `import:classes` - 35 XML files available
  - `import:feats` - 4 XML files available
- **15 reusable traits** for DRY code
  - Parser traits: `ParsesSourceCitations`, `ParsesTraits`, `ParsesRolls`, `MatchesProficiencyTypes`, `MatchesLanguages`
  - Importer traits: `ImportsSources`, `ImportsTraits`, `ImportsProficiencies`, `ImportsModifiers`, `ImportsLanguages`, `ImportsConditions`, `ImportsRandomTables`, `CachesLookupTables`, `GeneratesSlugs`

### Architecture Patterns
- **Service Layer Pattern** - Controllers delegate business logic to services
- **Form Request Pattern** - Dedicated validation classes per controller action
- **Resource Pattern** - Consistent API serialization via JsonResource
- **Polymorphic Factory Pattern** - Factory-based test data creation
  - 12 model factories
  - Support for polymorphic relationships

### Testing
- **PHPUnit 11+** with attributes (no doc-comment annotations)
- **750 tests** (4,828 assertions)
- **40s test duration**
- Test categories:
  - Feature: API endpoints, importers, models, migrations, Scramble docs
  - Unit: Parsers, factories, services, exceptions
- 100% pass rate

### Development Standards
- **Test-Driven Development (TDD)** - Mandatory for all features
  1. Write tests first (watch fail)
  2. Write minimal code to pass
  3. Refactor while green
  4. Update API Resources/Controllers
  5. Run full test suite
  6. Format with Pint
  7. Commit with clear message
- **Code Formatting** - Laravel Pint integration
- **Git Workflow**
  - Conventional commit messages
  - PR templates with test plan
  - Co-authored by Claude Code

### Documentation
- Comprehensive `CLAUDE.md` for AI assistance
- Session handover documents
- Search system documentation (`docs/SEARCH.md`)
- Meilisearch filter syntax guide (`docs/MEILISEARCH-FILTERS.md`)
- Database architecture plans
- Implementation strategy documents

### Tech Stack
- Laravel 12.x
- PHP 8.4
- MySQL 8.0
- PHPUnit 11+
- Docker + Laravel Sail
- Laravel Scout
- Meilisearch
- Spatie Tags
- Scramble (OpenAPI)

---

## Version History Notes

This project follows semantic versioning:
- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

Note: Backwards compatibility is **not a priority** for this project (as documented in CLAUDE.md).
