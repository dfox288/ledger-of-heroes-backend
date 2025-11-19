# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a Laravel 12.x application that imports D&D 5th Edition content from XML files and provides a RESTful API for accessing the data. The XML files follow the compendium format used by applications like Fight Club 5e and similar D&D companion apps.

**Current Status (2025-11-19):**
- ✅ **50 migrations** - Complete database schema with slug system + languages
- ✅ **23 Eloquent models** - All with HasFactory trait
- ✅ **12 model factories** - Test data generation
- ✅ **12 database seeders** - Lookup/reference data (including 30 languages)
- ✅ **21 API Resources** - Standardized and 100% field-complete
- ✅ **13 API Controllers** - 4 entity + 9 lookup endpoints
- ✅ **238 tests passing** (1,463 assertions, 2 incomplete expected)
- ✅ **4 importers working** - Spells, Races, Items, Backgrounds
- ✅ **4 artisan commands** - `import:spells`, `import:races`, `import:items`, `import:backgrounds`
- ✅ **Slug system complete** - Dual ID/slug routing for all entities
- ✅ **7 reusable traits** - Parser + Importer traits for DRY code
- ⚠️  **2 importers pending** - Classes (RECOMMENDED NEXT), Monsters

## Tech Stack

- **Framework:** Laravel 12.x
- **PHP Version:** 8.4
- **Database:** MySQL 8.0 (production), SQLite (testing)
- **Testing:** PHPUnit 11+ with Feature and Unit tests
- **Docker:** Multi-container setup (php, mysql, nginx)

## ⚠️ CRITICAL: Test-Driven Development (TDD) Mandate

**EVERY feature implementation MUST follow TDD:**

### Required Steps (Non-Negotiable):

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

## Quick Start

### Database Initialization Protocol

**IMPORTANT:** Always start with a clean database and reimport all data:

```bash
# 1. Fresh database with seeded lookup data
docker compose exec php php artisan migrate:fresh --seed

# 2. Import all available entities
# Spells (9 files available, import subset for testing)
docker compose exec php bash -c 'for file in import-files/spells-phb.xml import-files/spells-tce.xml import-files/spells-xge.xml; do php artisan import:spells "$file" || true; done'

# Races (3 files)
docker compose exec php bash -c 'for file in import-files/races-*.xml; do php artisan import:races "$file"; done'

# Items (24 files)
docker compose exec php bash -c 'for file in import-files/items-*.xml; do php artisan import:items "$file"; done'

# Backgrounds (2 files)
docker compose exec php bash -c 'for file in import-files/backgrounds-*.xml; do php artisan import:backgrounds "$file"; done'
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
  ├── Console/Commands/              # 4 import commands
  ├── Http/
  │   ├── Controllers/Api/           # 12 API controllers
  │   └── Resources/                 # 19 standardized API Resources
  ├── Models/                        # 21 Eloquent models
  └── Services/
      ├── Importers/                 # 4 XML importers
      └── Parsers/                   # XML parsing + table detection

database/
  ├── migrations/                    # 50 migrations (includes slug + language system)
  └── seeders/                       # 12 seeders for lookup data

import-files/                        # XML source files
  ├── spells-*.xml                   # 9 spell files
  ├── races-*.xml                    # 3 race files
  ├── items-*.xml                    # 24 item files
  ├── backgrounds-*.xml              # 2 background files
  ├── class-*.xml                    # 35 class files (READY - Priority 1)
  └── bestiary-*.xml                 # 5 monster files

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

### Multi-Source Entity System
All entities can cite multiple sourcebooks via `entity_sources` polymorphic table.
- Example: Spell appears in PHB p.151 and TCE p.108
- Enables accurate source attribution and page references

### Polymorphic Relationships
- **Traits** - Belong to races, classes, backgrounds
- **Modifiers** - Ability scores, skills, damage modifiers
- **Proficiencies** - Skills, weapons, armor, tools (with auto-matching to types)
- **Random Tables** - d6/d8/d100 tables for character features

### Language System (NEW 2025-11-19)
- 30 D&D 5e languages seeded with metadata (script, type, rarity)
- Polymorphic `entity_languages` table (works with races, classes, backgrounds)
- Supports both fixed languages AND choice slots (e.g., "one extra language")
- `MatchesLanguages` trait auto-matches during import
- 119 language associations across races (59% coverage)

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

## XML Import System

### Working Importers
1. **SpellImporter** - Imports spells with effects, class associations, multi-source citations
2. **RaceImporter** - Imports races/subraces with traits, modifiers, proficiencies, random tables
3. **ItemImporter** - Imports items with magic flags, modifiers, abilities, embedded tables
4. **BackgroundImporter** - Imports backgrounds with proficiencies, traits, random tables

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
- **238 tests** (1,463 assertions) - 100% pass rate
- **2 incomplete tests** (expected edge cases documented)
- **Test Duration:** ~3.2-3.4 seconds
- Feature tests for API, importers, models, migrations
- Unit tests for parsers, factories, services
- **XML reconstruction tests** verify import completeness (~90-95% coverage)

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

### Priority 1: Class Importer ⭐ RECOMMENDED
**Why:** Most complex entity, builds on all established patterns, highest value

- 35 XML files ready to import
- 13 base classes seeded in database
- Subclass hierarchy using `parent_class_id`
- Class features, spell slots, counters (Ki, Rage)
- **Can reuse NEW importer traits:** `ImportsSources`, `ImportsTraits`, `ImportsProficiencies`
- **Can reuse NEW parser traits:** `ParsesSourceCitations`, `MatchesProficiencyTypes`, `MatchesLanguages`
- Hierarchical slugs ready: `fighter-battle-master`
- **Estimated Effort:** 6-8 hours (now faster with traits!)

### Priority 2: Monster Importer
- 5 bestiary XML files available
- Traits, actions, legendary actions, spellcasting
- Schema complete and tested
- **Estimated Effort:** 4-6 hours

### Priority 3: API Enhancements
- Filtering by proficiency types, conditions, rarity, attunement
- Aggregation endpoints (counts by type, rarity, school)
- OpenAPI/Swagger documentation

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

---

## Branch Status

**Current Branch:** `fix/parser-data-quality`
**Status:** ✅ Ready to merge
**Test Status:** 238 tests passing (100% pass rate)
**Key Changes:**
- Slug system with dual ID/slug routing
- Language system with 30 languages
- 7 reusable parser + importer traits
- 100% clean data quality (proficiencies, modifiers, sources)

---

**Project Status:** ✅ Healthy and ready for Class Importer! 🚀
