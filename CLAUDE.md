# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a Laravel 11.x application that imports D&D 5th Edition content from XML files and provides a RESTful API for accessing the data. The XML files follow the compendium format used by applications like Fight Club 5e and similar D&D companion apps.

**Current Status (2025-11-18):**
- ✅ 27 database migrations completed
- ✅ 18 Eloquent models defined (all with HasFactory trait)
- ✅ 10 Model factories for easy test data generation
- ✅ 9 Database seeders for lookup/reference data
- ✅ 16 API Resources (standardized and field-complete)
- ✅ 10 API Controllers with routes
- ✅ 228 tests passing (1309 assertions)
- ✅ 86 XML import files available
- ⚠️  2 importers implemented (Spells, Races) - others pending
- ⚠️  Database cleared and ready for fresh import

## Tech Stack

- **Framework:** Laravel 12.x
- **PHP Version:** 8.4
- **Database:** MySQL 8.0 (production), SQLite (testing)
- **Testing:** PHPUnit with Feature and Unit tests
- **Docker:** Multi-container setup (php, mysql, nginx)

## Repository Structure

```
app/
  ├── Console/Commands/
  │   └── ImportSpells.php              # Artisan command for spell import
  ├── Http/
  │   ├── Controllers/Api/              # 10 API controllers
  │   │   ├── RaceController.php
  │   │   ├── SpellController.php
  │   │   └── [8 lookup controllers]
  │   └── Resources/                    # 16 standardized API Resources
  │       ├── RaceResource.php
  │       ├── SpellResource.php
  │       ├── EntitySourceResource.php  # Multi-source support
  │       └── [13 more resources]
  ├── Models/                           # 18 Eloquent models
  │   ├── Race.php
  │   ├── Spell.php
  │   ├── CharacterClass.php
  │   └── [15 more models]
  └── Services/
      ├── Importers/                    # XML import services
      │   ├── SpellImporter.php
      │   └── RaceImporter.php
      └── Parsers/                      # XML parsing logic
          ├── SpellXmlParser.php
          └── RaceXmlParser.php

database/
  └── migrations/                       # 27 migration files
      ├── 2025_11_17_203923_create_sources_table.php
      ├── 2025_11_17_204217_create_lookup_tables.php  # Seeds 8 lookup tables
      ├── 2025_11_18_104911_create_entity_sources_table.php  # Multi-source
      └── [24 more migrations]

import-files/                           # 86 XML source files
  ├── spells-*.xml                      # 6 spell files
  ├── races-*.xml                       # 2 race files
  ├── class-*.xml                       # 35 class files
  ├── items-*.xml                       # 12 item files
  ├── bestiary-*.xml                    # 5 monster files
  ├── backgrounds-phb.xml
  ├── feats-*.xml
  └── [more source files]

tests/
  ├── Feature/
  │   ├── Api/                          # 32 API endpoint tests
  │   ├── Importers/                    # Import functionality tests
  │   ├── Migrations/                   # Migration tests
  │   └── Models/                       # Model relationship tests
  └── Unit/
      └── Parsers/                      # XML parser unit tests
```

## XML Format Structure

All XML files follow a common structure with `<compendium version="5" auto_indent="NO">` as the root element.

### Spell Format
- Each spell contains: name, level, school, casting time, range, components, duration, classes, text description
- May include `<roll>` elements for damage/scaling calculations
- May include `<ritual>YES</ritual>` for ritual spells
- School codes: A, C, D, EN, EV, I, N, T (Abjuration, Conjuration, Divination, Enchantment, Evocation, Illusion, Necromancy, Transmutation)
- Classes listed as comma-separated values

### Race Format
- Contains: name, size, speed, ability score increases
- Name format: "Base Race (Subrace)" or just "Race Name"
- Traits categorized as "description" for lore or specific features (Age, Alignment, Size)
- Proficiencies can be skills, weapons, armor, or tools
- May include `<roll>` elements within traits for random tables

### Background Format
- Contains: name, proficiency skills, traits (description, features, characteristics)
- Traits include narrative text with formatting for tables (d6/d8 rolls)

### Class Format
- Contains class-specific traits and options
- Organized by source book with flavor text and mechanical options
- May include roll tables for character customization

## Database Architecture

### Key Design Patterns

1. **Multi-Source Entity Architecture:**
   - All entities (spells, races, classes, etc.) can have multiple source citations
   - `entity_sources` polymorphic table with `reference_type`, `reference_id`, `source_id`, `pages`
   - Eager load with: `->with('sources.source')`
   - API serialization via `EntitySourceResource`

2. **Polymorphic Relationships:**
   - **Traits:** `reference_type`/`reference_id` - can belong to races, classes, backgrounds, etc.
   - **Modifiers:** `reference_type`/`reference_id` - ability scores, skills, damage types
   - **Proficiencies:** `reference_type`/`reference_id` - skills, weapons, armor, tools, saving throws

3. **Lookup Tables (Seeded in Migrations):**
   - `sources`: PHB, DMG, MM, XGE, TCE, VGTM
   - `spell_schools`: 8 schools with codes and descriptions
   - `damage_types`: 13 types (acid, bludgeoning, cold, etc.)
   - `ability_scores`: 6 scores (STR, DEX, CON, INT, WIS, CHA)
   - `skills`: 18 skills linked to ability scores
   - `sizes`: 6 sizes (Tiny, Small, Medium, Large, Huge, Gargantuan)
   - `item_types`, `item_properties`: For equipment categorization

4. **Race/Subrace Hierarchy:**
   - `races.parent_race_id` for subrace relationships
   - Base race: `parent_race_id IS NULL`
   - Subrace: `parent_race_id` points to base race
   - API includes both `parent_race` and `subraces` relationships

5. **Class Hierarchy:**
   - `character_classes.parent_class_id` for subclass relationships
   - 13 core classes seeded in migration
   - Supports spellcasting ability via `spellcasting_ability_id`

## API Structure

### Base URL: `/api/v1`

### Available Endpoints (21 routes):

**Spells:**
- `GET /api/v1/spells` - List spells (filterable, searchable, paginated)
- `GET /api/v1/spells/{spell}` - Get single spell with effects, classes, sources

**Races:**
- `GET /api/v1/races` - List races (filterable, searchable, paginated)
- `GET /api/v1/races/{race}` - Get single race with traits, proficiencies, modifiers, subraces

**Lookup Endpoints:**
- `GET /api/v1/sources` - List all sources
- `GET /api/v1/spell-schools` - List all spell schools
- `GET /api/v1/damage-types` - List all damage types
- `GET /api/v1/sizes` - List all sizes
- `GET /api/v1/ability-scores` - List all ability scores
- `GET /api/v1/skills` - List all skills
- `GET /api/v1/item-types` - List all item types
- `GET /api/v1/item-properties` - List all item properties

### API Features:
- **Pagination:** All collection endpoints support `per_page` parameter (default: 15)
- **Search:** FULLTEXT search on spells and races via `?search=term`
- **Filtering:** Spells by level, school, concentration, ritual; Races by size
- **Sorting:** Configurable via `sort_by` and `sort_direction` parameters
- **CORS Enabled:** All API endpoints include CORS headers

### API Resource Standards:
- All Resources match their Model's fillable fields exactly
- Relationships use `whenLoaded()` to prevent N+1 queries
- Conditional fields use `when()` for nullable relationships
- No inline arrays - dedicated Resource classes for all models
- Multi-source data serialized via `EntitySourceResource`

## Development Commands

### Docker Commands (ALWAYS use `docker compose exec php` prefix):

```bash
# Run tests
docker compose exec php php artisan test
docker compose exec php php artisan test --filter=Api  # API tests only
docker compose exec php php artisan test --filter=RaceImporterTest

# Database operations
docker compose exec php php artisan migrate              # Run migrations
docker compose exec php php artisan migrate:fresh        # Clear and rebuild
docker compose exec php php artisan db:show              # Show database info
docker compose exec php php artisan tinker               # Interactive REPL

# Import data
docker compose exec php php artisan import:spells import-files/spells-phb+dmg.xml

# List routes
docker compose exec php php artisan route:list --path=api
```

### Available Artisan Commands:
- `import:spells {file}` - Import spells from XML file

### Working with XML Files

When modifying XML files:
- Maintain the `<?xml version="1.0" encoding="UTF-8"?>` declaration
- Keep `auto_indent="NO"` attribute in the compendium tag
- Preserve proper XML escaping for special characters
- Maintain consistent indentation (tabs) within elements
- Include source citations in text fields (e.g., "Source: Player's Handbook (2014) p. XXX")

## Content Guidelines

When adding or modifying D&D content:
- Use official D&D 5e source abbreviations (PHB, XGE, DMG, TCE, MM, VGTM)
- Preserve exact wording from source material when possible
- Include page references in source citations
- Use standard school abbreviations for spells (A=Abjuration, C=Conjuration, D=Divination, EN=Enchantment, EV=Evocation, I=Illusion, N=Necromancy, T=Transmutation)
- Format class lists and tables using proper spacing and bullet points

## Testing

### Test Statistics:
- **Total Tests:** 228 tests
- **Total Assertions:** 1,309
- **Test Duration:** ~2.2 seconds
- **Coverage:** API endpoints, importers, models, migrations, parsers, factories

### Test Categories:
- `tests/Feature/Api/` - 32 API endpoint tests (RaceApiTest, SpellApiTest, LookupApiTest, ClassApiTest, CorsTest)
- `tests/Feature/Importers/` - Import functionality tests
- `tests/Feature/Models/` - Eloquent relationship tests
- `tests/Feature/Migrations/` - Migration and seeding tests
- `tests/Unit/Factories/` - Factory tests (20 tests)
- `tests/Unit/Parsers/` - XML parser unit tests

### Running Tests:
```bash
docker compose exec php php artisan test                    # All tests
docker compose exec php php artisan test --filter=Api       # API tests only
docker compose exec php php artisan test --filter=Importer  # Importer tests
docker compose exec php php artisan test --filter=Factories # Factory tests
```

## Factories

All entity models support factory-based creation for testing using Laravel's model factories.

### Available Factories (10):

1. **RaceFactory** - Basic race creation with size and speed
2. **SpellFactory** - States: `cantrip()`, `concentration()`, `ritual()`
3. **CharacterClassFactory** - States: `spellcaster($abilityCode)`, `subclass($parentClass)`
4. **SpellEffectFactory** - States: `damage($type)`, `scalingSpellSlot()`, `scalingCharacterLevel()`
5. **EntitySourceFactory** - States: `forEntity($type, $id)`, `fromSource($code)`
6. **CharacterTraitFactory** - States: `forEntity($type, $id)`
7. **ProficiencyFactory** - States: `skill($name)`, `forEntity($type, $id)`
8. **ModifierFactory** - States: `abilityScore($code, $value)`, `forEntity($type, $id)`
9. **RandomTableFactory** - States: `forEntity($type, $id)`
10. **RandomTableEntryFactory** - States: `forTable($table)`

### Polymorphic Factory Pattern:

All polymorphic models (Trait, Proficiency, Modifier, EntitySource, RandomTable) use a consistent `forEntity()` method:

```php
CharacterTrait::factory()->forEntity(Race::class, $race->id)->create();
Proficiency::factory()->forEntity(Race::class, $race->id)->create();
Modifier::factory()->forEntity(Race::class, $race->id)->create();
EntitySource::factory()->forEntity(Spell::class, $spell->id)->create();
```

### Usage Examples:

```php
// Create a spell with all defaults
$spell = Spell::factory()->create();

// Create a cantrip with specific name
$cantrip = Spell::factory()->cantrip()->create(['name' => 'Fire Bolt']);

// Create a concentration spell
$spell = Spell::factory()->concentration()->create();

// Create a race with traits and modifiers
$race = Race::factory()->create();
CharacterTrait::factory()->count(3)->forEntity(Race::class, $race->id)->create();
Modifier::factory()->abilityScore('DEX', 2)->forEntity(Race::class, $race->id)->create();

// Create a spell with damage effect
$spell = Spell::factory()->create();
SpellEffect::factory()->damage('Fire')->create(['spell_id' => $spell->id]);

// Create entity with multiple sources
$spell = Spell::factory()->create();
EntitySource::factory()->fromSource('PHB')->forEntity(Spell::class, $spell->id)->create();
EntitySource::factory()->fromSource('TCE')->forEntity(Spell::class, $spell->id)->create();

// Create a spellcasting class
$wizard = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);

// Create a subclass
$champion = CharacterClass::factory()->subclass($fighter)->create(['name' => 'Champion']);
```

## Database Seeders

Lookup and reference data is managed through dedicated database seeders (not in migrations).

### Available Seeders (9):

1. **SourceSeeder** - 6 D&D sourcebooks (PHB, DMG, MM, XGE, TCE, VGTM)
2. **SpellSchoolSeeder** - 8 schools of magic
3. **DamageTypeSeeder** - 13 damage types
4. **SizeSeeder** - 6 creature size categories
5. **AbilityScoreSeeder** - 6 core ability scores (STR, DEX, CON, INT, WIS, CHA)
6. **SkillSeeder** - 18 skills with FK dependencies to ability scores
7. **ItemTypeSeeder** - 10 item type categories
8. **ItemPropertySeeder** - 11 weapon properties
9. **CharacterClassSeeder** - 13 core D&D classes with entity_sources

### Running Seeders:

```bash
# Run all seeders
docker compose exec php php artisan db:seed

# Run specific seeder
docker compose exec php php artisan db:seed --class=SourceSeeder

# Fresh migration with seeding
docker compose exec php php artisan migrate:fresh --seed
```

### Test Environment:

All tests automatically run seeders via `protected $seed = true` in `tests/TestCase.php`. This ensures factories have valid foreign key references to lookup data (sizes, schools, abilities, etc.).

## Architecture Decisions

### API Resource Standardization (2025-11-18):
- **Decision:** Every model has a dedicated Resource class (no inline arrays)
- **Rationale:** Consistency, reusability, and easier maintenance
- **Impact:** Created 7 new Resources, updated 6 existing Resources
- **Status:** ✅ Completed - all Resources standardized

### Resource Field Completeness (2025-11-18):
- **Decision:** All Resources must include ALL model fields and relationships
- **Rationale:** API consumers need complete data; prevents silent omissions
- **Impact:** Fixed SkillResource (critical bug), completed ClassResource (7 missing fields), added 8 missing relationships
- **Status:** ✅ Completed - all Resources 100% field-complete

### Multi-Source Architecture (2025-11-17):
- **Decision:** Entities can cite multiple sources (e.g., spell in PHB and TCE)
- **Rationale:** D&D content often appears in multiple books with different page numbers
- **Implementation:** `entity_sources` polymorphic junction table
- **Migration:** Removed single-source foreign keys from all entity tables
- **Status:** ✅ Completed - 196 tests passing after migration

### Race/Subrace Hierarchy (2025-11-18):
- **Decision:** Use `parent_race_id` self-referencing foreign key
- **Rationale:** Subraces share base race data but have unique traits
- **Implementation:** Base races have `parent_race_id IS NULL`, subraces reference base race
- **Status:** ✅ Implemented in RaceImporter

## Known Issues

1. **Spell Import Error (2025-11-18):**
   - **Error:** "No query results for model [App\Models\SpellSchool]"
   - **Status:** Database verified to contain 8 spell schools with correct codes
   - **Next Steps:** Investigate SpellImporter/SpellXmlParser for lookup logic mismatch

2. **PHPUnit Deprecation Warnings:**
   - **Issue:** "Metadata found in doc-comment" - @test annotations deprecated
   - **Impact:** Tests still pass, but warnings clutter output
   - **Resolution:** Eventually migrate to PHP 8 attributes (#[Test])

## Pending Work

1. **Import Commands:** Only `import:spells` exists. Need importers for:
   - Races (parser exists, command needed)
   - Classes
   - Items
   - Monsters
   - Backgrounds
   - Feats

2. **API Controllers:** Need controllers/routes for:
   - Classes
   - Items
   - Monsters
   - Backgrounds
   - Feats

3. **Data Import:** Database is empty (freshly migrated). Need to run all importers to populate data.

## Project History

- **2025-11-17:** Initial database schema, migrations, models, basic API endpoints
- **2025-11-17:** Multi-source entity architecture implemented and tested
- **2025-11-17:** Spell effects parsing and SpellEffect model added
- **2025-11-17:** Class associations (class_spells junction table) implemented
- **2025-11-18:** Race/subrace hierarchy with parent_race_id
- **2025-11-18:** Proficiencies, traits, modifiers, random tables for races
- **2025-11-18:** API Resource standardization (7 tasks completed)
- **2025-11-18:** API Resource field completeness audit and fixes (7 tasks completed)
- **2025-11-18:** Database cleared and migrated fresh - ready for import

## Plan Documents

Detailed implementation plans available in `docs/plans/`:
- `2025-11-17-dnd-xml-importer-implementation-v3-with-api.md`
- `2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md`
- `2025-11-18-races-subraces-and-proficiencies.md`
- `2025-11-18-standardize-api-resources.md`
- `2025-11-18-fix-resource-field-completeness.md`
