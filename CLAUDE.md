# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a Laravel 12.x application that imports D&D 5th Edition content from XML files and provides a RESTful API for accessing the data. The XML files follow the compendium format used by applications like Fight Club 5e and similar D&D companion apps.

**Current Status (2025-11-18):**
- ✅ **44 migrations** - Complete database schema with enhancements
- ✅ **21 Eloquent models** - All with HasFactory trait
- ✅ **10 model factories** - Test data generation
- ✅ **11 database seeders** - Lookup/reference data
- ✅ **19 API Resources** - Standardized and 100% field-complete
- ✅ **12 API Controllers** - 4 entity + 8 lookup endpoints
- ✅ **313 tests passing** (1,766 assertions, 1 incomplete expected)
- ✅ **4 importers working** - Spells, Races, Items, Backgrounds
- ✅ **4 artisan commands** - `import:spells`, `import:races`, `import:items`, `import:backgrounds`
- ✅ **2,017 entities imported** - 19 backgrounds, 56 races, 1,942 items
- ⚠️  **2 importers pending** - Classes (RECOMMENDED NEXT), Monsters

## Tech Stack

- **Framework:** Laravel 12.x
- **PHP Version:** 8.4
- **Database:** MySQL 8.0 (production), SQLite (testing)
- **Testing:** PHPUnit 11+ with Feature and Unit tests
- **Docker:** Multi-container setup (php, mysql, nginx)

## Quick Start

### Running Tests
```bash
docker compose exec php php artisan test                    # All tests
docker compose exec php php artisan test --filter=Api       # API tests
docker compose exec php php artisan test --filter=Importer  # Importer tests
```

### Database Operations
```bash
docker compose exec php php artisan migrate:fresh --seed    # Fresh DB with lookup data
docker compose exec php php artisan tinker                  # Interactive REPL
```

### Importing Data
```bash
# Import all items (17 files)
docker compose exec php bash -c 'for file in import-files/items-*.xml; do php artisan import:items "$file"; done'

# Import all races (3 files)
docker compose exec php bash -c 'for file in import-files/races-*.xml; do php artisan import:races "$file"; done'

# Import all backgrounds (2 files)
docker compose exec php bash -c 'for file in import-files/backgrounds-*.xml; do php artisan import:backgrounds "$file"; done'
```

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
  ├── migrations/                    # 44 migrations
  └── seeders/                       # 11 seeders for lookup data

import-files/                        # 86 XML source files
  ├── spells-*.xml                   # 6 spell files
  ├── races-*.xml                    # 3 race files (56 imported ✅)
  ├── items-*.xml                    # 17 item files (1,942 imported ✅)
  ├── backgrounds-*.xml              # 2 background files (19 imported ✅)
  ├── class-*.xml                    # 35 class files (READY)
  └── bestiary-*.xml                 # 5 monster files

tests/
  ├── Feature/                       # API, importers, models, migrations
  └── Unit/                          # Parsers, factories, services
```

## Key Features

### Multi-Source Entity System
All entities can cite multiple sourcebooks via `entity_sources` polymorphic table.
- Example: Spell appears in PHB p.151 and TCE p.108
- Enables accurate source attribution and page references

### Polymorphic Relationships
- **Traits** - Belong to races, classes, backgrounds
- **Modifiers** - Ability scores, skills, damage modifiers
- **Proficiencies** - Skills, weapons, armor, tools (with auto-matching to types)
- **Random Tables** - d6/d8/d100 tables for character features

### Normalized Proficiency Types
- 80 proficiency types across 7 categories (weapons, armor, tools, etc.)
- `MatchesProficiencyTypes` trait auto-matches during import
- 100% match rate (25/25 non-skill proficiencies)
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
- Plus: sizes, ability-scores, skills, item-types, item-properties

**Features:**
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
- **313 tests** (1,766 assertions) - 99.7% pass rate
- **Test Duration:** ~3.2 seconds
- Feature tests for API, importers, models, migrations
- Unit tests for parsers, factories, services
- **XML reconstruction tests** verify import completeness (~90-95% coverage)

**Running Tests:**
```bash
docker compose exec php php artisan test                         # All tests
docker compose exec php php artisan test --filter=Api            # API tests
docker compose exec php php artisan test --filter=Reconstruction # XML tests
```

## Factories & Seeders

**10 Model Factories:**
All entities support factory-based creation. Polymorphic models use `forEntity()` pattern:
```php
CharacterTrait::factory()->forEntity(Race::class, $race->id)->create();
Proficiency::factory()->forEntity(Race::class, $race->id)->create();
EntitySource::factory()->forEntity(Spell::class, $spell->id)->fromSource('PHB')->create();
```

**11 Database Seeders:**
- Sources, spell schools, damage types, conditions, proficiency types
- Sizes, ability scores, skills, item types/properties, character classes
- Run with: `docker compose exec php php artisan db:seed`

## What's Next

### Priority 1: Class Importer ⭐ RECOMMENDED
**Why:** Most complex entity, builds on all established patterns, highest value

- 35 XML files ready to import
- 13 base classes seeded in database
- Subclass hierarchy using `parent_class_id`
- Class features, spell slots, counters (Ki, Rage)
- Can reuse `MatchesProficiencyTypes` trait
- **Estimated Effort:** 6-8 hours

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

## Recent Accomplishments (2025-11-18)

### Conditions & Proficiency Types System ✅
- 15 D&D 5e conditions + 80 proficiency types
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

**Project Status:** ✅ Healthy and ready for Class Importer! 🚀
