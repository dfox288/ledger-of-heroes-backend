# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a Laravel 12.x application that imports D&D 5th Edition content from XML files and provides a RESTful API for accessing the data. The XML files follow the compendium format used by applications like Fight Club 5e and similar D&D companion apps.

**Current Status (2025-11-21 - Refactoring Session Complete):**
- ✅ **60 migrations** - Complete database schema with slug system + languages + prerequisites + spells_known
- ✅ **23 Eloquent models** - All with HasFactory trait
- ✅ **12 model factories** - Test data generation
- ✅ **12 database seeders** - Lookup/reference data (including 30 languages)
- ✅ **25 API Resources** - Standardized and 100% field-complete (includes SearchResource)
- ✅ **17 API Controllers** - 6 entity + 11 lookup endpoints (all properly documented)
- ✅ **26 Form Request classes** - Full validation layer with Scramble OpenAPI integration
- ✅ **769 tests passing** (4,711 assertions) - 100% pass rate ⭐
- ✅ **6 importers working** - Spells, Races, Items, Backgrounds, Classes (with spells_known), Feats
- ✅ **6 artisan commands** - `import:spells`, `import:races`, `import:items`, `import:backgrounds`, `import:classes`, `import:feats`
- ✅ **Slug system complete** - Dual ID/slug routing for all entities
- ✅ **15 reusable traits** - Parser + Importer traits for DRY code (3 NEW: ImportsModifiers, ImportsConditions, ImportsLanguages)
- ✅ **Code refactoring complete** - 285 lines eliminated, template method pattern in BaseImporter
- ✅ **Class enhancements** - Spells Known tracking + Proficiency Choice metadata
- ✅ **OpenAPI documentation** - Auto-generated via Scramble (306KB spec) - All 17 controllers ✅
- ✅ **Scramble documentation system** - Automated tests validate OpenAPI spec generation
- ✅ **Search system complete** - Laravel Scout + Meilisearch (3,002 documents indexed, typo-tolerant)
- ⚠️  **1 importer pending** - Monsters (7 bestiary files ready)
- 📋 **Refactoring progress** - 5 of 11 refactorings complete (see `docs/HANDOVER-2025-11-21-REFACTORING-SESSION.md`)

## Tech Stack

- **Framework:** Laravel 12.x
- **PHP Version:** 8.4
- **Database:** MySQL 8.0 (production), SQLite (testing)
- **Testing:** PHPUnit 11+ with Feature and Unit tests
- **Docker:** Multi-container setup (php, mysql, nginx)

## ⚠️ CRITICAL: Test-Driven Development (TDD) Mandate

**EVERY feature implementation MUST follow TDD:**

### Required Steps (Non-Negotiable):

0. **Backwards compatibility is NOT important** - Do not waste time on backwards compatibility
0. **Always use available Superpower Laravel skills**
1. **WRITE TESTS FIRST** - Before writing any implementation code
2. **Use PHPUnit 11 Attributes** - Use `#[\PHPUnit\Framework\Attributes\Test]` instead of `/** @test */` doc-comments
3. **Watch them FAIL** - Confirm tests fail for the right reason
4. **Write MINIMAL code** to pass tests
5. **Refactor** while keeping tests green
6. **Update API Resources** - Expose new data via API
7. **Update Models**
8. **Update Controllers**
9. **Update API Tests** - Verify API returns new fields
10. **Run FULL test suite** - Ensure no regressions
11. **Commit to git with clear message**
12. **Update todos, clean up documents**
13. **Update handover document**

### PHPUnit 11 Testing Standards:

**ALWAYS use PHP attributes instead of doc-comments:**

```php
// ✅ CORRECT - Use attributes
#[\PHPUnit\Framework\Attributes\Test]
public function it_creates_a_record()
{
    // test code
}

// ❌ WRONG - Doc-comments deprecated in PHPUnit 11+
/** @test */
public function it_creates_a_record()
{
    // test code
}
```

**Rationale:** Doc-comment metadata is deprecated and will be removed in PHPUnit 12. Using attributes ensures forward compatibility and eliminates warnings.

### What Must Be Tested:

**For Parser Changes:**
- ✅ Unit tests for new parser methods
- ✅ Test with real XML snippets
- ✅ Test edge cases (missing data, malformed XML)

**For Database Schema Changes:**
- ✅ Migration tests (schema validation)
- ✅ Model factory tests (can create instances)
- ✅ Model relationship tests

**For Importer Changes:**
- ✅ Feature tests for full import flow
- ✅ Test data integrity after import
- ✅ Test reimport behavior (updates vs creates)

**For API Changes:**
- ✅ API Resource includes new fields
- ✅ API endpoint tests return new data
- ✅ Test response structure matches documentation

### Example TDD Workflow:

```bash
# 1. Write failing test
docker compose exec php php artisan test --filter=RaceSpellcastingTest
# SHOULD FAIL - feature doesn't exist yet

# 2. Implement minimal code
# ... write parser/importer/resource code ...

# 3. Watch test pass
docker compose exec php php artisan test --filter=RaceSpellcastingTest
# SHOULD PASS

# 4. Run full suite
docker compose exec php php artisan test
# ALL TESTS SHOULD PASS

# 5. Format code
docker compose exec php ./vendor/bin/pint
```

### ❌ Anti-Patterns to Avoid:

- Writing implementation code before tests
- "I'll write tests after" (never happens)
- Skipping API resource updates
- Not testing edge cases
- Assuming existing tests cover new features

### ✅ Success Criteria:

Before marking ANY feature complete:
- [ ] New feature has dedicated tests
- [ ] All new tests pass
- [ ] API resources expose new data
- [ ] API endpoint tests verify new fields
- [ ] Full test suite passes (no regressions)
- [ ] Code formatted with Pint

**If tests aren't written, the feature ISN'T done.**

---

## 📋 Form Request Naming Convention

**ALWAYS follow this naming pattern for Form Request classes:**

### Pattern: `{Entity}{Action}Request`

```php
// ✅ CORRECT - Entity first, then controller action
SpellIndexRequest      // GET /api/v1/spells (list)
SpellShowRequest       // GET /api/v1/spells/{id} (single)
SpellStoreRequest      // POST /api/v1/spells (create)
SpellUpdateRequest     // PATCH /api/v1/spells/{id} (update)

FeatIndexRequest       // GET /api/v1/feats
FeatShowRequest        // GET /api/v1/feats/{id}

RaceIndexRequest       // GET /api/v1/races
RaceShowRequest        // GET /api/v1/races/{id}

// ❌ WRONG - Don't use Laravel's verb-first convention
IndexSpellRequest      // NO - verb first
StoreSpellRequest      // NO - verb first
```

### Purpose & Benefits

**Form Requests serve THREE critical functions:**

1. **Validation** - Validate incoming query parameters and request body
2. **Documentation** - Scramble reads Request classes to generate OpenAPI docs
3. **Type Safety** - IDE autocomplete and static analysis support

**Example Request Class:**
```php
class SpellIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Pagination
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            // Sorting (whitelist for security)
            'sort_by' => ['sometimes', Rule::in(['name', 'level', 'created_at'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],

            // Filters
            'level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'school' => ['sometimes', 'exists:spell_schools,id'],
            'concentration' => ['sometimes', Rule::in([true, false, 'true', 'false', 1, 0])],
        ];
    }
}
```

### ⚠️ CRITICAL Maintenance Rule

**WHENEVER you modify Models or Controllers, you MUST update corresponding Request classes:**

| Change Type | Required Updates |
|-------------|------------------|
| **Add model scope** | Add validation rule to `{Entity}IndexRequest` |
| **Add controller filter** | Add validation rule to Request class |
| **Add sortable column** | Add to `sort_by` Rule::in() whitelist |
| **Add relationship** | Add to `include` validation in `{Entity}ShowRequest` |
| **Add API field** | Add to `fields` validation in Show/Index requests |

**Why this matters:**
- ❌ Missing validation = unvalidated user input = security risk
- ❌ Missing validation = Scramble docs incomplete = poor DX
- ❌ Missing validation = no type hints = harder debugging

**Checklist before marking work complete:**
- [ ] Model scopes have corresponding Request validation
- [ ] Controller filters are validated in Request class
- [ ] Sortable columns are whitelisted
- [ ] Request class tests verify validation rules
- [ ] Scramble docs updated (run `php artisan scramble:docs`)

---

## Quick Start

### Database Initialization Protocol

**IMPORTANT:** Always start with a clean database and reimport all data:

```bash
# 1. Fresh database with seeded lookup data
docker compose exec php php artisan migrate:fresh --seed

# 2. Import all available entities
# Spells (9 files available, import subset for testing)
docker compose exec php bash -c 'for file in import-files/spells-phb.xml import-files/spells-tce.xml import-files/spells-xge.xml; do php artisan import:spells "$file" || true; done'

# Races (5 files)
docker compose exec php bash -c 'for file in import-files/races-*.xml; do php artisan import:races "$file"; done'

# Items (25 files)
docker compose exec php bash -c 'for file in import-files/items-*.xml; do php artisan import:items "$file"; done'

# Backgrounds (4 files)
docker compose exec php bash -c 'for file in import-files/backgrounds-*.xml; do php artisan import:backgrounds "$file"; done'

# Classes (35 files)
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file"; done'

# Feats (4 files)
docker compose exec php bash -c 'for file in import-files/feats-*.xml; do php artisan import:feats "$file"; done'
```

**Rationale:**
- Ensures consistent state across sessions
- Catches migration issues early
- Verifies importers still work with latest schema
- Rebuilds all slug/language/proficiency associations

### Running Tests
```bash
docker compose exec php php artisan test                    # All tests
docker compose exec php php artisan test --filter=Api       # API tests
docker compose exec php php artisan test --filter=Importer  # Importer tests
```

### Other Database Operations
```bash
docker compose exec php php artisan tinker                  # Interactive REPL
docker compose exec php php artisan db:seed                 # Re-seed lookup data only
```

## Development Workflow

### Todo-Based Development Protocol

**IMPORTANT:** Follow this workflow for all feature development and bug fixes:

#### Before Starting Each Todo Item:
```bash
# 1. Refresh database with latest schema
docker compose exec php php artisan migrate:fresh --seed

# 2. Import all entities to verify importers still work
# Spells (subset for speed)
docker compose exec php bash -c 'for file in import-files/spells-phb.xml import-files/spells-tce.xml import-files/spells-xge.xml; do php artisan import:spells "$file" || true; done'

# Races (all files)
docker compose exec php bash -c 'for file in import-files/races-*.xml; do php artisan import:races "$file"; done'

# Items (all files)
docker compose exec php bash -c 'for file in import-files/items-*.xml; do php artisan import:items "$file"; done'

# Backgrounds (all files)
docker compose exec php bash -c 'for file in import-files/backgrounds-*.xml; do php artisan import:backgrounds "$file"; done'

# Classes (all files)
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file"; done'

# Feats (all files)
docker compose exec php bash -c 'for file in import-files/feats-*.xml; do php artisan import:feats "$file"; done'

# 3. Run tests to verify starting point
docker compose exec php php artisan test
```

#### After Completing Each Todo Item:
```bash
# 1. Run tests to verify changes
docker compose exec php php artisan test

# 2. Format code
docker compose exec php ./vendor/bin/pint

# 3. Stage and commit changes
git add .
git commit -m "feat: [descriptive message]

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Rationale:**
- **Before:** Ensures starting with clean state, catches schema drift, verifies importers work
- **After:** Validates changes, maintains code quality, creates checkpoint for rollback
- **Commit per todo:** Keeps changes atomic, makes git history readable, enables easy rollback

**Commit Message Conventions:**
- `feat:` - New feature
- `fix:` - Bug fix
- `refactor:` - Code restructuring
- `test:` - Adding or updating tests
- `docs:` - Documentation changes
- `chore:` - Maintenance tasks

## Repository Structure

```
app/
  ├── Console/Commands/              # 6 import commands
  ├── Http/
  │   ├── Controllers/Api/           # 14 API controllers
  │   └── Resources/                 # 24 standardized API Resources
  ├── Models/                        # 23 Eloquent models
  └── Services/
      ├── Importers/                 # 6 XML importers (Spells, Races, Items, Backgrounds, Classes, Feats)
      └── Parsers/                   # XML parsing + table detection

database/
  ├── migrations/                    # 59 migrations (includes slug + language + prerequisites)
  └── seeders/                       # 12 seeders for lookup data

import-files/                        # XML source files
  ├── spells-*.xml                   # 9 spell files
  ├── races-*.xml                    # 5 race files
  ├── items-*.xml                    # 25 item files
  ├── backgrounds-*.xml              # 4 background files
  ├── class-*.xml                    # 35 class files (✅ DONE)
  ├── feats-*.xml                    # 4 feat files (✅ DONE)
  ├── bestiary-*.xml                 # 7 monster files (⚠️ PENDING)
  ├── optionalfeatures-*.xml         # 3 files (NOT IN SCHEMA)
  └── source-*.xml                   # 6 files (METADATA ONLY)

tests/
  ├── Feature/                       # API, importers, models, migrations
  └── Unit/                          # Parsers, factories, services
```

## Key Features

### Slug System (NEW 2025-11-19)
All entities support **dual ID/slug routing** for SEO-friendly URLs:
- Routes accept BOTH numeric IDs and string slugs
- Example: `/api/v1/spells/123` OR `/api/v1/spells/fireball`
- Hierarchical slugs for subraces/subclasses: `dwarf-hill`, `fighter-battle-master`
- Auto-generated during import via `Str::slug()`
- Backward compatible with existing code

### Search System (NEW 2025-11-20)
- **Laravel Scout + Meilisearch** integration for fast, typo-tolerant search
- **6 searchable entity types:** Spells, Items, Races, Classes, Backgrounds, Feats
- **Global unified search endpoint** - `/api/v1/search` searches across all entities
- **Entity-specific search** - Each entity endpoint supports `?q=` parameter
- **Performance:** <50ms average response time, <100ms p95
- **Typo-tolerance:** "firebll" finds "Fireball"
- **Graceful fallback** to MySQL FULLTEXT when Meilisearch unavailable
- **Faceted filtering** support (level, school, rarity, etc.)
- **3,002 documents indexed** across all entities (477 spells, 2,107 items, 115 races, 131 classes, 34 backgrounds, 138 feats)
- **Command:** `php artisan search:configure-indexes` - Sets up search indexes with optimal settings
- See `docs/SEARCH.md` for comprehensive documentation

### Multi-Source Entity System
All entities can cite multiple sourcebooks via `entity_sources` polymorphic table.
- Example: Spell appears in PHB p.151 and TCE p.108
- Enables accurate source attribution and page references

### Polymorphic Relationships
- **Traits** - Belong to races, classes, backgrounds
- **Modifiers** - Ability scores, skills, damage modifiers
- **Proficiencies** - Skills, weapons, armor, tools (with auto-matching to types)
- **Random Tables** - d6/d8/d100 tables for character features
- **Prerequisites** - Structured requirements for feats and items (ability scores, races, skills, proficiencies)

### Language System (NEW 2025-11-19)
- 30 D&D 5e languages seeded with metadata (script, type, rarity)
- Polymorphic `entity_languages` table (works with races, classes, backgrounds)
- Supports both fixed languages AND choice slots (e.g., "one extra language")
- `MatchesLanguages` trait auto-matches during import
- 119 language associations across races (59% coverage)

### Entity Prerequisites System (NEW 2025-11-19)
**Structured, queryable prerequisite data for feats and items.**

- **Double polymorphic design:** Links ANY entity to ANY prerequisite type
- **Supported prerequisite types:**
  - AbilityScore (e.g., "Dexterity 13 or higher")
  - Race (e.g., "Elf", "Dwarf")
  - Skill (e.g., "Proficiency in Acrobatics")
  - ProficiencyType (e.g., "Proficiency with medium armor")
  - Free-form (e.g., "The ability to cast at least one spell")

- **Complex AND/OR logic** via `group_id` field
  - Same group = OR logic (e.g., "Dwarf OR Gnome OR Halfling")
  - Different groups = AND logic (e.g., "(Dwarf OR Gnome) AND Proficiency in Acrobatics")

- **Parser patterns supported:**
  - Single ability score: "Strength 13 or higher"
  - Dual ability scores: "Intelligence or Wisdom 13 or higher"
  - Single race: "Elf"
  - Multiple races: "Dwarf, Gnome, Halfling"
  - Proficiency requirements: "Proficiency with medium armor"
  - Skill requirements: "Proficiency in Acrobatics"
  - Free-form features: "The ability to cast at least one spell"

- **Coverage:**
  - 28 feats with prerequisites (20% of 138 total)
  - 34 prerequisite records (16 AbilityScore, 5 ProficiencyType, 13 free-form)
  - Items with strength requirements auto-migrated

- **API support:** Fully exposed via EntityPrerequisiteResource with nested entity details

### Normalized Proficiency Types
- 82 proficiency types across 7 categories (weapons, armor, tools, etc.)
- `MatchesProficiencyTypes` trait auto-matches during import
- 100% match rate during import
- Enables queries like "Find races proficient with Longsword"

### Random Table System
- Extracts embedded tables from XML (76 tables, 381+ entries)
- Supports standard (d4-d100) and unusual dice (1d22, 1d33, 2d6)
- Handles roll ranges (1, 2-3, 01-02) and non-dice tables (Lever, Face)
- 97% have dice_type captured

### Hierarchical Entities
- **Races:** Base races (`parent_race_id IS NULL`) + subraces
- **Classes:** 13 core classes seeded + subclass support via `parent_class_id`

## API Endpoints

### Base URL: `/api/v1`

**Entity Endpoints:**
- `GET /api/v1/spells` - List/search spells (paginated, filterable)
- `GET /api/v1/races` - List/search races (paginated, filterable)
- `GET /api/v1/items` - List/search items (paginated, filterable)
- `GET /api/v1/backgrounds` - List/search backgrounds (paginated, filterable)
- `GET /api/v1/classes` - List/search classes (paginated, filterable, includes subclasses)
- `GET /api/v1/feats` - List/search feats (paginated, filterable)

**Advanced Filtering:**
All entity endpoints support Meilisearch's powerful filter syntax via the `filter` parameter.
See `docs/MEILISEARCH-FILTERS.md` for comprehensive examples.

```bash
# Range queries
GET /api/v1/spells?filter=level >= 1 AND level <= 3

# Logical operators
GET /api/v1/spells?filter=school_code = EV OR school_code = C

# Combined search + filter
GET /api/v1/spells?q=fire&filter=level <= 3
```

**Lookup Endpoints:**
- `GET /api/v1/sources` - D&D sourcebooks
- `GET /api/v1/spell-schools` - 8 schools of magic
- `GET /api/v1/damage-types` - 13 damage types
- `GET /api/v1/conditions` - 15 D&D conditions
- `GET /api/v1/proficiency-types?category=weapon` - Filterable proficiency types
- `GET /api/v1/languages` - 30 D&D languages
- Plus: sizes, ability-scores, skills, item-types, item-properties

**Features:**
- **Dual ID/Slug Routing:** `/spells/fireball` OR `/spells/123`
- Pagination: `?per_page=25` (default: 15)
- Search: `?search=term` (FULLTEXT)
- Filtering: By level, school, size, category, etc.
- Sorting: `?sort_by=name&sort_direction=asc`
- CORS enabled

## OpenAPI Documentation (Scramble)

**Automatic API Documentation via Scramble:**

The API is automatically documented using [Scramble](https://scramble.dedoc.co/), which generates OpenAPI 3.0 specifications by analyzing Laravel code.

### How It Works
- **Route Analysis:** Scans all `/api/*` routes
- **Form Request Validation:** Infers request parameters from validation rules
- **Resource Inference:** Analyzes API Resources to document response schemas
- **Type Detection:** Uses PHP types and docblocks for accurate schemas

### Accessing Documentation
- **Interactive UI:** `http://localhost:8080/docs/api` (Stoplight Elements)
- **OpenAPI JSON:** `http://localhost:8080/docs/api.json` or `api.json` file (306KB)
- **Export Command:** `php artisan scramble:export`

### Best Practices for Scramble

✅ **DO:**
- Use API Resources for all responses (`return XResource::collection($items)`)
- Use Form Requests for validation (rules auto-document parameters)
- Use PHP native types for properties and return values
- Let Scramble infer from code (it's smarter than manual annotations)

❌ **DON'T:**
- Use `@response` annotations (they block Scramble's inference!)
- Manually construct JSON with `response()->json(['data' => ...])`
- Return plain arrays from controllers
- Mix manual and automatic documentation

### Automated Testing
5 tests in `tests/Feature/ScrambleDocumentationTest.php` validate:
- Valid OpenAPI 3.0 specification structure
- All endpoints properly documented
- Response schemas correctly generated
- Component schemas properly referenced

**Run tests:** `php artisan test --filter=ScrambleDocumentationTest`

### Troubleshooting
If Scramble isn't generating correct docs:
1. Ensure controller returns an API Resource (not plain JSON)
2. Remove any `@response` annotations
3. Check Form Request validation rules are complete
4. Run tests to identify specific issues
5. Regenerate: `php artisan scramble:export`

## XML Import System

### Working Importers
1. **SpellImporter** - Imports spells with effects, class associations, multi-source citations
2. **RaceImporter** - Imports races/subraces with traits, modifiers, proficiencies, random tables, languages
3. **ItemImporter** - Imports items with magic flags, modifiers, abilities, embedded tables, prerequisites
4. **BackgroundImporter** - Imports backgrounds with proficiencies, traits, random tables, languages
5. **ClassImporter** - Imports classes/subclasses with features, spell progression, counters, proficiencies
6. **FeatImporter** - Imports feats with modifiers, proficiencies, conditions, prerequisites

### XML Format Structure
All XML files: `<compendium version="5" auto_indent="NO">`

**Common Elements:**
- `<name>` - Entity name
- `<text>` - Descriptive text (may contain embedded tables)
- `<trait>` - Features/abilities (with optional category)
- `<proficiency>` - Skills, weapons, armor, tools
- `<modifier category="">` - Ability scores, skills, damage
- `<roll description="">` - Dice formulas for abilities
- Random tables embedded in descriptions (pipe-separated, e.g., "1|Result|2|Result")

### Known Import Behaviors
These are **intentional** design decisions:

1. **Subclass information stripped** - "Fighter (Eldritch Knight)" → "Fighter"
   - Rationale: Spell associations are class-level

2. **Ability code normalization** - "Str +2" → "STR +2"
   - Rationale: Consistent with database lookup tables

3. **Random tables preserved in description** - Extracted to tables but NOT removed from text
   - Rationale: Original context preserved; frontend chooses rendering

4. **Roll descriptions from XML attribute** - 80.5% coverage (305/379 abilities)
   - Rationale: Not all rolls have description attribute in XML

## Testing

**Test Statistics:**
- **738 tests** (4,637 assertions) - 100% pass rate ⭐
- **1 incomplete test** (expected edge case documented)
- **Test Duration:** ~24 seconds
- Feature tests for API, importers, models, migrations, Scramble documentation
- Unit tests for parsers, factories, services
- **XML reconstruction tests** verify import completeness (~90-95% coverage)
- **Scramble documentation tests** (5 tests) validate OpenAPI spec generation

**Running Tests:**
```bash
docker compose exec php php artisan test                         # All tests
docker compose exec php php artisan test --filter=Api            # API tests
docker compose exec php php artisan test --filter=Reconstruction # XML tests
```

## Code Architecture (NEW 2025-11-19)

### Reusable Traits
All importers and parsers use shared traits to eliminate duplication:

**Parser Traits:**
- `ParsesSourceCitations` - Database-driven source mapping (no hardcoded arrays!)
- `MatchesProficiencyTypes` - Fuzzy matching for weapons, armor, tools
- `MatchesLanguages` - Language extraction and matching

**Importer Traits:**
- `ImportsSources` - Entity source citation handling
- `ImportsTraits` - Character trait import
- `ImportsProficiencies` - Proficiency import with skill FK linking

**Benefits:**
- 150+ lines of duplication eliminated
- Single source of truth for source mapping
- Consistent behavior across all importers

## Factories & Seeders

**12 Model Factories:**
All entities support factory-based creation. Polymorphic models use `forEntity()` pattern:
```php
CharacterTrait::factory()->forEntity(Race::class, $race->id)->create();
Proficiency::factory()->forEntity(Race::class, $race->id)->create();
EntitySource::factory()->forEntity(Spell::class, $spell->id)->fromSource('PHB')->create();
EntityLanguage::factory()->forEntity(Race::class, $race->id)->create();
```

**12 Database Seeders:**
- Sources, spell schools, damage types, conditions, proficiency types
- Sizes, ability scores, skills, item types/properties, character classes
- **Languages** - 30 D&D languages with script/type/rarity
- Run with: `docker compose exec php php artisan db:seed`

## What's Next

### Priority 1: Monster Importer ⭐ RECOMMENDED
**Why:** Last major entity type, schema is ready, completes the core D&D compendium

- 7 bestiary XML files available
- Traits, actions, legendary actions, spellcasting
- Schema complete and tested (monsters table + related tables)
- **Can reuse existing importer traits:** `ImportsSources`, `ImportsTraits`, `ImportsProficiencies`
- **Can reuse existing parser traits:** `ParsesSourceCitations`, `MatchesProficiencyTypes`
- **Estimated Effort:** 6-8 hours (with TDD)

### Priority 2: API Enhancements
- Filtering by proficiency types, conditions, rarity, attunement
- Aggregation endpoints (counts by type, rarity, school)
- Class spell list endpoints (GET /api/v1/classes/{id}/spells)
- OpenAPI/Swagger documentation

### Priority 3: Optional Features
- 3 optionalfeatures XML files (requires schema design)
- These are class variants like Fighting Styles, Eldritch Invocations, Metamagic
- Would need new table structure and relationships

## Documentation

**Essential Reading:**
- `docs/SESSION-HANDOVER.md` - Latest session details and recommendations
- `docs/PROJECT-STATUS.md` - Quick project status and stats
- `docs/plans/2025-11-17-dnd-compendium-database-design.md` - Database architecture
- `docs/plans/2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md` - Implementation strategy

## Recent Accomplishments (2025-11-19)

### Latest: Slug System Complete ✅
- 6 new migrations for slug columns on all entity tables
- Dual ID/slug route binding in `AppServiceProvider`
- Hierarchical slug generation for races/classes
- All API Resources include slug field
- 238 tests passing (100% pass rate)

### Code Refactoring + Trait System ✅
- **7 reusable traits** for parsers and importers
- Database-driven source mapping (no hardcoded arrays!)
- 150+ lines of duplication eliminated
- 100% clean source citations (no trailing commas)
- Schema consistency across all polymorphic tables

### Language System ✅
- 30 D&D languages seeded with metadata
- Polymorphic `entity_languages` table
- 119 language associations across races
- Supports choice slots (e.g., "one extra language")
- `MatchesLanguages` trait for parsing

### Conditions & Proficiency Types System ✅
- 15 D&D 5e conditions + 82 proficiency types
- `MatchesProficiencyTypes` trait for auto-matching
- 100% match rate during import
- New API endpoints for lookups

### Background Importer ✅
- 19 backgrounds imported (18 PHB + 1 ERLW)
- 71 traits, 38 proficiencies (100% matched)
- 76 random tables (personality, ideals, bonds, flaws)

### Item Enhancement Suite ✅
- Magic flag detection (1,447 magic items)
- Attunement parsing (631 items)
- Weapon range split (normal/long)
- Roll descriptions (80.5% coverage)
- Modifiers and abilities fully parsed

### Random Table Extraction System ✅
- 76 tables extracted with 381+ entries
- Supports standard and unusual dice types
- Handles roll ranges and non-numeric entries

### Entity Prerequisites System ✅ (NEW 2025-11-19)
- **Double polymorphic structure** for maximum flexibility
- **Parser with 6+ patterns** (ability scores, races, skills, proficiencies, free-form)
- **Complex AND/OR logic** via group_id system
- **Importer integration** for Feats and Items
- **API layer** with EntityPrerequisiteResource + nested entity details
- **Data migration** for items.strength_requirement → entity_prerequisites
- **Coverage:** 28 feats with 34 prerequisite records
- **27 new tests** (parser, importer, API, migration) - 100% passing
- **393 total tests** (2,268 assertions)

---

## Branch Status

**Current Branch:** `feature/entity-prerequisites`
**Status:** ✅ Complete and ready for merge
**Test Status:** 393 tests passing (100% pass rate)
**Key Changes:**
- Entity prerequisites system (database, parser, importer, API)
- Skill model support for skill-based prerequisites
- ItemController with full CRUD operations
- Route bindings for Items and Feats (dual ID/slug)
- Data migration for items.strength_requirement

---

**Project Status:** ✅ Prerequisites feature complete! Ready for review and merge. 🚀
