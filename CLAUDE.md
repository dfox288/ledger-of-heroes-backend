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
- ✅ **240 tests passing (1412 assertions, 0 warnings)**
- ✅ **12 XML reconstruction tests (verifies import completeness)**
- ✅ 2 importers working (Spells: 361 imported, Races: 19 imported)
- ✅ **Import coverage: Spells ~95%, Races ~90%**
- ✅ 2 artisan commands (`import:spells`, `import:races`)
- ✅ 86 XML import files available
- ⚠️  5 importers pending (Items, Classes, Monsters, Backgrounds, Feats)

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
  │   ├── ImportSpells.php              # Artisan command for spell import
  │   └── ImportRaces.php               # Artisan command for race import
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
docker compose exec php php artisan import:spells import-files/spells-phb.xml
docker compose exec php php artisan import:races import-files/races-phb.xml

# List routes
docker compose exec php php artisan route:list --path=api
```

### Available Artisan Commands:
- `import:spells {file}` - Import spells from XML file
- `import:races {file}` - Import races from XML file

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
- **Total Tests:** 240 tests (1 incomplete)
- **Total Assertions:** 1,412
- **Test Duration:** ~2.25 seconds
- **Coverage:** API endpoints, importers, models, migrations, parsers, factories, XML reconstruction

### Test Categories:
- `tests/Feature/Api/` - 32 API endpoint tests (RaceApiTest, SpellApiTest, LookupApiTest, ClassApiTest, CorsTest)
- `tests/Feature/Importers/` - Import functionality tests + **12 XML reconstruction tests**
- `tests/Feature/Models/` - Eloquent relationship tests
- `tests/Feature/Migrations/` - Migration and seeding tests
- `tests/Unit/Factories/` - Factory tests (20 tests)
- `tests/Unit/Parsers/` - XML parser unit tests

### XML Reconstruction Tests:
These tests verify import completeness by reconstructing the original XML from database records:

**Spell Reconstruction (7 tests):**
- Simple cantrip with character-level scaling
- Concentration spell with material components
- Ritual spell
- Multiple sources (PHB + TCE)
- Spell effects with damage
- Class associations (subclass stripping)
- "At Higher Levels" text preservation

**Race Reconstruction (5 tests + 1 incomplete):**
- Simple race (Dragonborn)
- Subrace with parent (Hill Dwarf)
- Ability bonuses (multiple modifiers)
- Proficiencies (armor, weapons, skills)
- Traits with categories
- Random table references (incomplete - enhancement needed)

**Coverage Results:**
- **Spells:** ~95% attribute coverage (65 assertions)
- **Races:** ~90% attribute coverage (38 assertions)

### Running Tests:
```bash
docker compose exec php php artisan test                         # All tests
docker compose exec php php artisan test --filter=Api            # API tests only
docker compose exec php php artisan test --filter=Importer       # Importer tests
docker compose exec php php artisan test --filter=Reconstruction # XML reconstruction tests
docker compose exec php php artisan test --filter=Factories      # Factory tests
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

None currently - all systems operational! ✅

### Recently Fixed:
- ~~Spell Import Error~~ - Issue was using supplemental XML file (`spells-phb+dmg.xml`) instead of core file
- ~~PHPUnit Deprecation Warnings~~ - Migrated all tests to PHP 8 attributes (#[Test])
- ~~Missing import:races command~~ - Created and tested
- ~~Multi-source page commas~~ - Parser was capturing trailing commas in page numbers (found via reconstruction tests)
- ~~Subrace detection~~ - Parser only checked comma format, not parentheses format "Race (Subrace)" (found via reconstruction tests)
- ~~Multiple proficiencies~~ - Parser combined multiple `<proficiency>` elements instead of parsing separately (found via reconstruction tests)

## Known Limitations & Design Decisions

These are **intentional** behaviors, not bugs. Documented via XML reconstruction tests.

### Spell Import Limitations:

1. **Subclass Information Stripped**
   - **XML:** `Fighter (Eldritch Knight), Rogue (Arcane Trickster)`
   - **Stored:** Base classes only: `Fighter, Rogue`
   - **Rationale:** Subclass data stored in separate table; spell associations are class-level
   - **Impact:** Subclass notation lost in reconstruction, but accurate for game rules

2. **"At Higher Levels" Not Separated**
   - **XML:** May have dedicated section for spell slot scaling text
   - **Stored:** Combined with main description
   - **Rationale:** No semantic benefit to separation; both are descriptive text
   - **Impact:** Functional equivalence; text preserved completely

3. **School Prefix in Classes Stripped**
   - **XML:** `School: Evocation, Sorcerer, Wizard`
   - **Stored:** `Sorcerer, Wizard`
   - **Rationale:** School already in `spell_school_id`; redundant prefix removed
   - **Impact:** Cleaner data; no information loss

4. **Material Component Extraction**
   - **XML:** `V, S, M (a tiny ball of bat guano)`
   - **Stored:** `components="V, S, M"` + `material_components="a tiny ball of bat guano"`
   - **Rationale:** Enables filtering by component type; preserves full text
   - **Impact:** More queryable; reconstruction adds back parentheses

### Race Import Limitations:

1. **Ability Code Normalization**
   - **XML:** Mixed case: `Str +2, Cha +1`
   - **Stored:** Uppercase: `STR +2, CHA +1`
   - **Rationale:** Database lookup requires consistent case; ability_scores table uses uppercase
   - **Impact:** Visual difference only; functionally identical

2. **Source Attribution at Race Level**
   - **XML:** Source may appear in first trait's text
   - **Stored:** Source in `entity_sources` table, linked to race (not individual traits)
   - **Rationale:** Source applies to entire race, not per-trait
   - **Impact:** Reconstruction adds source to first trait for XML format compliance

3. **Random Table Entries Not Parsed**
   - **XML:** d8 tables embedded in trait text as formatted lists
   - **Stored:** `random_tables.dice_type` captured, but individual entries remain in trait text
   - **Rationale:** No structured `<entry>` elements in XML; entries are narrative text
   - **Impact:** Roll formula preserved; table entries queryable via full-text search on trait description
   - **Status:** ⚠️ Enhancement opportunity - could parse formatted lists in future

4. **Proficiency Type Inference**
   - **XML:** Single `<proficiency>` tag with name only
   - **Stored:** `proficiency_type` inferred from name patterns (armor/weapon/tool/skill)
   - **Rationale:** No explicit type in XML; heuristics work for 95% of cases
   - **Impact:** Enables filtering by type; occasional misclassification possible

### Whitespace & Formatting:

- **Normalization Applied:** Leading/trailing whitespace trimmed, newlines standardized
- **Rationale:** Database storage optimization; no semantic meaning to exact whitespace
- **Impact:** Reconstructed XML may differ in spacing but is semantically identical

## Pending Work

1. **Import Commands & Parsers:** Need to implement for:
   - Items (12 XML files available)
   - Classes (35 XML files available)
   - Monsters (5 bestiary files)
   - Backgrounds (1 file)
   - Feats (multiple files)

2. **API Controllers:** Need controllers/routes for:
   - Items
   - Monsters
   - Backgrounds
   - Feats

3. **XML Reconstruction Tests:** Verify import completeness by reconstructing XML from database

## Project History

- **2025-11-17:** Initial database schema, migrations, models, basic API endpoints
- **2025-11-17:** Multi-source entity architecture implemented
- **2025-11-18:** Spell & Race importers with parsers, factories, seeders
- **2025-11-18:** API Resource standardization and field completeness
- **2025-11-18:** Factory implementation (10 factories, 9 seeders)
- **2025-11-18:** PHPUnit migration to PHP 8 attributes
- **2025-11-18:** Successful import verification (361 spells, 19 races)

## Documentation

- **`docs/PROJECT-STATUS.md`** - Quick project overview and current status
- **`docs/plans/2025-11-17-dnd-compendium-database-design.md`** - Database architecture (ESSENTIAL)
- **`docs/plans/2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md`** - Implementation strategy
