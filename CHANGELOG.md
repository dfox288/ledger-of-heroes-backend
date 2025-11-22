# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Monster Search with Meilisearch** - Fast, typo-tolerant search for 598 monsters
  - Laravel Scout integration with Monster model
  - MonsterSearchService for Scout/Meilisearch/database queries
  - Global search support: `GET /api/v1/search?q=dragon&types[]=monster`
  - Advanced filtering: CR range, type, size, alignment combined with search
  - Meilisearch filter syntax: `filter=challenge_rating >= 5 AND type = dragon`
  - Searchable fields: name, description, type, size_name, sources
  - Filterable fields: type, size_code, alignment, challenge_rating, armor_class, HP, XP
  - Sortable fields: name, challenge_rating, armor_class, HP, XP
  - 8 comprehensive search tests (1,040 total tests passing)
  - 598 monsters indexed in Meilisearch (~2.5MB index)
- **Monster API Endpoints** - RESTful API for 598 imported monsters with comprehensive filtering
  - `GET /api/v1/monsters` - List monsters with pagination, search, sorting
  - `GET /api/v1/monsters/{id|slug}` - Get single monster by ID or slug
  - **Filters:** Challenge rating (exact, min/max range), type (dragon, humanoid, undead, etc.), size (T/S/M/L/H/G), alignment
  - **Relationships:** Size, traits, actions, legendary actions, spellcasting, modifiers, conditions, sources
  - **Resources:** 5 API Resources (Monster, MonsterTrait, MonsterAction, MonsterLegendaryAction, MonsterSpellcasting)
  - **Validation:** 2 Form Requests (MonsterIndexRequest, MonsterShowRequest)
  - **Route Binding:** Dual ID/slug routing support
  - **Tests:** 20 comprehensive API tests (1,032 total tests passing)
  - **CR Range Filtering:** CAST to DECIMAL for proper numeric comparison of challenge_rating strings
- **Item Parser Strategy Pattern** - Refactored ItemXmlParser from 481-line monolith into 5 composable type-specific strategies
  - `ChargedItemStrategy`: Extracts spell references and charge costs from staves/wands/rods (spell matching, variable costs)
  - `ScrollStrategy`: Spell level extraction + protection vs spell scroll detection
  - `PotionStrategy`: Duration extraction + effect categorization (healing, resistance, buff, debuff, utility)
  - `TattooStrategy`: Tattoo type extraction, activation methods, body location detection
  - `LegendaryStrategy`: Sentience detection, alignment extraction, personality traits, artifact destruction methods
- **Strategy Statistics Display** - Import command now shows per-strategy metrics table (items enhanced, warnings)
- **StrategyStatistics Service** - Parses import-strategy logs and aggregates metrics by strategy
- **Structured Strategy Logging** - Dedicated `import-strategy` log channel with JSON format, cleared per import
- **44 New Strategy Tests** - Comprehensive test coverage for all 5 strategies (85%+ coverage each)
- **ItemTypeStrategy Interface** - Granular enhancement methods for modifiers, abilities, relationships, and metadata
- **AbstractItemStrategy Base Class** - Shared metadata tracking (warnings, metrics) and default implementations

### Added
- **Spell Usage Limit Tracking** - Items that cast spells "at will" now store usage information
  - New pivot column: `entity_spells.usage_limit` (VARCHAR 50)
  - Parser detects "at will", "1/day", "3/day" patterns
  - Enhanced 8 items: Hat of Disguise, Boots of Levitation, Helm of Comprehending Languages, etc.
  - API exposure: Usage limits appear in item spell pivot data
  - Tests: 3 new parser tests verify usage limit detection
- **Set Ability Score Modifiers** - Magic items that override ability scores use `set:X` notation
  - Pattern: "Your Intelligence score is 19 while you wear this headband"
  - Uses existing `entity_modifiers` infrastructure with `set:19` notation
  - Enhanced 3 iconic items: Headband of Intellect, Gauntlets of Ogre Power, Amulet of Health
  - Self-documenting values distinguish from traditional +2 bonuses
  - API usage: Parse with `str_starts_with($value, 'set:')` pattern
  - Tests: 4 new parser tests verify set score detection and prevent false positives
- **Potion Resistance Modifiers** - Damage resistance potions track specific types and duration
  - Detects "resistance to [type] damage for [duration]" patterns
  - Special case: Potion of Invulnerability uses `resistance:all` notation with NULL damage_type_id
  - Enhanced 12 potions: All resistance types plus Invulnerability
  - Duration tracking: "for 1 hour", "for 1 minute" stored in condition field
  - Single database record for "all damage types" (not 13 separate records)
  - Tests: 4 new parser tests verify standard and special resistance patterns

### Changed
- **ItemXmlParser Refactoring** - Reduced from 481-line monolith to ~200 lines base + 5 focused strategies
  - Base parser handles common fields (name, rarity, cost, damage, etc.)
  - Type-specific logic delegated to strategies via Strategy Pattern
  - Each strategy ~100-150 lines (focused and maintainable)
  - Strategies can be combined (items can be both Legendary + Charged)
  - Real XML fixtures used in all strategy tests for realistic coverage
- **Spell References from Charged Items** - Now creates entity_spells relationships automatically
  - ChargedItemStrategy extracts spells from item descriptions
  - Case-insensitive matching: "cure wounds" matches "Cure Wounds" in database
  - Variable charge costs: "1 charge per spell level, up to 4th" → min:1, max:4
  - Warnings logged when spells not found in database
  - Example: Staff of Fire → 3 spell relationships (Burning Hands, Fireball, Wall of Fire)
- **DRY Refactoring: Damage Type Mapping** - Eliminated duplicate mapping code
  - Removed 20 lines from `ItemXmlParser::mapDamageTypeNameToCode()`
  - Parser now passes damage_type_name directly (e.g., "Acid")
  - Importer queries database by name instead of code
  - Single source of truth: `DamageTypeSeeder` is canonical
  - Backward compatible: damage_type_code still supported as fallback

### Fixed
- **Item Importer Duplicate Source Bug** - Fixed crash when importing items with multiple citations to the same source
  - **Root Cause:** Items like "Instrument of Illusions" cited same source twice with different pages (XGE p.137, XGE p.83)
  - **Error:** Unique constraint violation on `entity_sources(reference_type, reference_id, source_id)`
  - **Solution:** Deduplicate sources by source_id and merge page numbers
  - **Result:** XGE p.137 + XGE p.83 → XGE p.137, 83 (single entity_sources record)
  - **Impact:** Fixes import of 43+ items from items-xge.xml (including Wand of Smiles)
  - **Modified:** `app/Services/Importers/ItemImporter.php` - Enhanced `importSources()` method
  - **Testing:** 835 tests passing (no regressions)

### Added
- **Magic Item Charge Mechanics** - Automatically parses and stores charge-based item mechanics
  - **NEW Columns:** `items.charges_max`, `items.recharge_formula`, `items.recharge_timing`
  - **Parser:** `ParsesCharges` trait with 6 regex patterns
  - **Patterns Detected:**
    - Max capacity: "has 3 charges", "starts with 36 charges" → `charges_max`
    - Dice recharge: "regains 1d6+1 expended charges" → `recharge_formula`
    - Full recharge: "regains all expended charges" → `recharge_formula: "all"`
    - Timing: "daily at dawn", "after a long rest" → `recharge_timing`
  - **Coverage:** ~70 items (Wands, Staffs, Rings, Helms, Cubes)
  - **Examples:**
    - Wand of Smiles: 3 charges, all at dawn
    - Wand of Binding: 7 charges, 1d6+1 at dawn
    - Cubic Gate: 36 charges, 1d20 at dawn
  - **API Response:** Exposed via `ItemResource` (charges_max, recharge_formula, recharge_timing)
  - **Use Cases:** Character sheet automation, item filtering, charge tracking
  - **Testing:** 15 new tests (10 parser + 5 importer) = 850 total tests passing
  - **Documentation:** `docs/MAGIC-ITEM-CHARGES-ANALYSIS.md` (comprehensive analysis)
- **Item Detail Field** - Stores raw subcategory information from XML `<detail>` elements
  - **NEW Column:** `items.detail` VARCHAR(255) NULL
  - **Preserves Subcategories:** "firearm, renaissance", "druidic focus", "artisan tools", etc.
  - **188 Unique Values:** Covers weapon types, tool categories, containers, clothing types
  - **Use Cases:**
    - Filter firearms by era (renaissance vs modern vs futuristic)
    - Distinguish spellcasting focus types (arcane, druidic, holy symbol)
    - Categorize tools (artisan, gaming, musical)
    - Search/display additional item context
  - **Migration:** `2025_11_21_225238_add_detail_to_items_table.php`
  - **Example:** Pistol now shows `{"detail": "firearm, renaissance", "rarity": "common"}`
  - **Flexible:** Raw string can be parsed client-side; can be structured later if patterns emerge
  - **Testing:** 3 new parser tests verify detail field preservation
- **Conditional Speed Modifier System** - Heavy armor now tracks speed penalties when strength requirement not met
  - **NEW `speed` Modifier Category** - Tracks movement speed bonuses/penalties
  - **Conditional Modifiers** - Uses `condition` field for prerequisite-based penalties
  - **Example:** Plate Armor (STR 15) creates modifier: `{category: 'speed', value: -10, condition: 'strength < 15'}`
  - **D&D 5e Semantics:** Distinguishes between "can't equip" (prerequisite) vs "penalty if equipped" (conditional modifier)
  - **Parser Enhancement:** Automatically detects "speed is reduced by X feet" patterns in item descriptions
  - **Benefits:**
    - Character builders can calculate actual speed based on STR score
    - Query-friendly: Filter all items that reduce speed
    - Distinguishes Plate Armor (has penalty) from Plate Barding (no penalty for mounts)
    - Reusable pattern for other conditional effects (caltrops, spells, exhaustion)
  - **API Response:** Modifiers exposed via `/api/v1/items/{id}?include=modifiers`
  - **Testing:** 11 new tests verify parser + importer + API integration
- **Test Output Logging Workflow** - Documented standard procedure for capturing test output to files
  - Added section to CLAUDE.md explaining `tee` command for logging test results
  - Created `tests/results/` directory for storing test logs (gitignored)
  - Benefits: No re-runs needed to review failures, easier debugging, shareable test output
  - Example: `docker compose exec php php artisan test 2>&1 | tee tests/results/test-output.log`
  - Can grep log files for failures: `grep -E "(FAIL|FAILED)" tests/results/test-output.log`

### Changed
- **Removed Timestamps from Static Tables** - Dropped `created_at`/`updated_at` columns from reference data
  - **Affected Tables:** `items`, `entity_spells`
  - **Rationale:** D&D 5e content is static reference data that doesn't require change tracking
  - **Benefits:** Cleaner API responses, reduced storage overhead, faster queries
  - **Models Updated:** Added `public $timestamps = false` to `Item` and `EntitySpell`
  - **Resources Updated:** Removed timestamp fields from `ItemResource`
  - **Migration:** `2025_11_21_224033_remove_timestamps_from_static_tables.php`
  - **Note:** Other entities (Spell, Race, Class, Background, Feat) already had timestamps disabled
- **Verified API Resource Completeness** - Audited all 6 main entity resources against models
  - Confirmed all relationships are exposed in API responses
  - All controllers properly eager-load related data
  - Resources include: Spell, Race, Item, Background, Class, Feat
  - All polymorphic relationships (tags, sources, modifiers, proficiencies) are exposed
  - All entity-specific relationships (saving throws, random tables, prerequisites) are included
- **Renamed `modifiers` → `entity_modifiers` Table** - For consistency with other polymorphic tables
  - Renamed via migration `2025_11_21_214255_rename_modifiers_to_entity_modifiers.php`
  - Updated `Modifier` model to specify `$table = 'entity_modifiers'`
  - Aligns with naming convention: `entity_sources`, `entity_saving_throws`, `entity_modifiers`
  - All existing modifiers preserved during rename (zero data loss)
- **Item Stealth Disadvantage via Skill Modifiers** - Heavy armor stealth penalties now use `entity_modifiers` table
  - `<stealth>YES</stealth>` XML element creates skill modifier with `disadvantage` value
  - `ItemXmlParser::parseModifiers()` adds Stealth (DEX) skill modifier when stealth=YES
  - `ImportsModifiers` trait enhanced to resolve skill/ability lookups from names/codes
  - **Correct D&D 5e Semantics:** Stealth disadvantage is a SKILL CHECK penalty, not a saving throw
  - **Backwards Compatible:** `stealth_disadvantage` column remains unchanged
  - **Query Example:** `Item::whereHas('modifiers', fn($q) => $q->where('modifier_category', 'skill')->where('value', 'disadvantage'))`
  - **Testing:** 2 tests verify skill modifier creation for items with/without stealth penalty
- **Reusable Parser/Importer Traits** - Extracted saving throw and random table logic into traits
  - **Parser Traits:**
    - `ParsesSavingThrows` - Parses saving throw requirements with advantage/disadvantage detection
    - `ParsesRandomTables` - Parses pipe-delimited d6/d8/d100 tables from descriptions
  - **Importer Traits:**
    - `ImportsSavingThrows` - Persists saving throws to polymorphic `entity_saving_throws` table
  - **Benefits:**
    - Makes logic reusable across all entity types (Spell, Item, Monster, etc.)
    - Single source of truth for complex regex patterns and detection logic
    - Ready for Monster importer (Priority 1 task)
    - Zero code duplication - follows existing pattern of 15 reusable traits
  - **Refactored:**
    - `SpellXmlParser` now uses `ParsesSavingThrows` and `ParsesRandomTables` traits
    - `SpellImporter` now uses `ImportsSavingThrows` trait
    - Removed 240 lines of duplicate code from spell parser/importer
  - **Testing:** All 757 tests still passing - zero regression
- **AC Modifier Category System** - Distinct categories for different AC modifier types
  - `ac_base` - Base armor AC (replaces natural AC, includes DEX modifier rules)
  - `ac_bonus` - Equipment AC bonuses (shields, always additive)
  - `ac_magic` - Magic enchantment bonuses (always additive)
  - **Fixes Shield +2 Bug** - Previously shield +2 only had one modifier because base (+2) and magic (+2) had same value
  - **Armor DEX Modifiers** - Stores DEX modifier rules in `condition` field:
    - Light Armor (LA): `"dex_modifier: full"` - Full DEX bonus
    - Medium Armor (MA): `"dex_modifier: max_2"` - DEX bonus capped at +2
    - Heavy Armor (HA): `"dex_modifier: none"` - No DEX bonus
  - Regular shields: `armor_class=2` + auto-created modifier(ac_bonus, 2)
  - Magic shields: Two distinct modifiers - base (ac_bonus) + enchantment (ac_magic)
  - Light armor: `armor_class=11` + auto-created modifier(ac_base, 11, condition: "dex_modifier: full")
  - Medium armor: `armor_class=14` + auto-created modifier(ac_base, 14, condition: "dex_modifier: max_2")
  - Heavy armor: `armor_class=18` + auto-created modifier(ac_base, 18, condition: "dex_modifier: none")
  - Example: Shield +1 has `armor_class=2` + modifiers(ac_bonus, 2) + modifiers(ac_magic, 1) = +3 total AC
  - Example: Shield +2 has `armor_class=2` + modifiers(ac_bonus, 2) + modifiers(ac_magic, 2) = +4 total AC
  - Example: Plate + Shield +1 = ac_base(18) + ac_bonus(2) + ac_magic(1) = 21 AC
  - Migration `2025_11_21_191858_add_ac_modifiers_for_shields.php` backfilled existing shields
  - `ItemImporter::importShieldAcModifier()` auto-creates base AC bonuses on import
  - `ItemImporter::importArmorAcModifier()` auto-creates base AC with DEX rules on import
  - `ItemXmlParser::parseModifierText()` now distinguishes magic AC bonuses (`category="bonus"` + `ac` = `ac_magic`)
  - Includes duplicate prevention logic for re-imports
- **Comprehensive AC Modifier Tests** - Added 13 new tests for shield and armor AC modifiers
  - **Shield tests (8):** Regular shields, magic shields (Shield +1, +2, +3), duplicate prevention
  - **Armor tests (5):** Light/medium/heavy armor with DEX modifier rules, magic armor
  - Tests that non-armor items don't get AC modifiers
  - Tests that items without AC values don't get modifiers
  - Tests re-import idempotency and multiple modifier types
  - **Validates distinct categories** - Tests verify `ac_base` vs `ac_bonus` vs `ac_magic` separation
  - **Validates DEX rules** - Tests verify `condition` field stores correct DEX modifier rules
  - Total: 28 tests in `ItemXmlReconstructionTest` (176 assertions)
  - Updated 2 unit tests in `ItemXmlParserTest` to expect `ac_magic` category
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
- **823 tests passing** (5,513 assertions)

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
