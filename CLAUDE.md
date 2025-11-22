# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Current Status (2025-11-22):**
- ✅ **1,018 tests passing** (5,915 assertions) - 99.9% pass rate, +5 new monster spell API tests
- ✅ **64 migrations** - Complete schema (slugs, languages, prerequisites, spell tags, saving throws with DC, monsters)
- ✅ **32 models + 29 API Resources + 18 controllers** - Full CRUD + Search for 7 entities
- ✅ **9 importers** - Spells, Classes, Races, Items, Backgrounds, Feats, Monsters, Spell Class Mappings, Master Import
- ✅ **21 reusable traits** - 6 NEW from refactoring (2025-11-22), ~260 lines eliminated
- ✅ **Monster API Complete** - 598 monsters imported, full REST API with filtering by CR/type/size/alignment/spells
- ✅ **Monster Spell Filtering API** - Query monsters by spells (`?spells=fireball`), list monster spells (`GET /monsters/{id}/spells`)
- ✅ **Monster Importer with Strategy Pattern** - 5 type-specific strategies (Dragon, Spellcaster, Undead, Swarm, Default)
- ✅ **Monster Spell Syncing** - SpellcasterStrategy syncs 1,098 spell relationships for 129 spellcasting monsters
- ✅ **Item Parser Strategy Pattern** - 5 type-specific strategies (Charged, Scroll, Potion, Tattoo, Legendary)
- ✅ **One-command import** - `import:all` handles all 60+ XML files in correct order
- ✅ **Universal tag system** - All 7 entities support Spatie Tags
- ✅ **Saving throw modifiers** - Detects advantage/disadvantage + DC values
- ✅ **AC modifier categories** - Base AC, bonuses, and magic (with DEX rules)
- ✅ **Additive spell imports** - Handles supplemental class association files
- ✅ **Search complete** - Laravel Scout + Meilisearch (3,600+ documents indexed)
- ✅ **OpenAPI docs** - Auto-generated via Scramble (306KB spec)
- ✅ **Item enhancements** - Usage limits ("at will"), set scores (`set:19`), potion resistance (23 items)
- ✅ **Test suite optimized** - Removed 36 redundant tests, 10 files deleted, 48.58s duration

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 | PHPUnit 11+ | Docker | Meilisearch

**📖 Read handovers:**
- `docs/PROJECT-STATUS.md` - **START HERE** Comprehensive project overview with metrics
- `docs/README.md` - Documentation index and navigation
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-SPELL-API-COMPLETE.md` - Monster spell filtering API (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-SPELLCASTER-STRATEGY-ENHANCEMENT.md` - Monster spell syncing (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-API-AND-SEARCH-COMPLETE.md` - Monster API implementation (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-IMPORTER-COMPLETE.md` - Monster importer with strategies (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-ITEM-PARSER-STRATEGIES-COMPLETE.md` - Item parser refactoring (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-TEST-REDUCTION-PHASE-1.md` - Test suite optimization (COMPLETE)

**🚀 Next tasks (all optional - core features complete):**
1. Performance optimizations (caching, indexing, Meilisearch spell filtering) - 2-4 hours
2. Enhanced spell filtering (OR logic, level filters, spellcasting ability) - 1-2 hours
3. Character Builder API (character creation, leveling, spell selection) - 8-12 hours
4. Additional Monster Strategies (FiendStrategy, CelestialStrategy, ConstructStrategy) - 2-3h each
5. Frontend Application (Inertia.js/Vue or Next.js/React) - 20-40 hours

---

## ⚠️ CRITICAL: Development Standards

### 1. Test-Driven Development (Mandatory)

**EVERY feature MUST follow TDD:**
1. Write tests FIRST (watch them fail)
2. Write minimal code to pass
3. Refactor while green
4. Update API Resources/Controllers
5. Run full test suite
6. Format with Pint
7. Commit with clear message

**PHPUnit 11 Requirement:**
```php
// ✅ CORRECT - Use attributes
#[\PHPUnit\Framework\Attributes\Test]
public function it_creates_a_record() { }

// ❌ WRONG - Doc-comments deprecated
/** @test */
public function it_creates_a_record() { }
```

### 2. Form Request Naming: `{Entity}{Action}Request`

```php
// ✅ CORRECT
SpellIndexRequest      // GET /api/v1/spells
SpellShowRequest       // GET /api/v1/spells/{id}

// ❌ WRONG
IndexSpellRequest      // No - verb first
```

**Purpose:** Validation + OpenAPI documentation + Type safety

**⚠️ CRITICAL Maintenance:** WHENEVER you modify Models/Controllers, update corresponding Request validation rules (filters, sorts, relationships).

### 3. Backwards Compatibility

**NOT important** - Do not waste time on backwards compatibility

### 4. Use Superpower Laravel Skills

**ALWAYS** check for available Laravel skills before starting work

---

## 🔥 Custom Exceptions

**Pattern: Service throws → Controller returns Resource (single return)**

```php
// ✅ Service throws domain exception
public function search(DTO $dto): Collection {
    throw new InvalidFilterSyntaxException($dto->filter, $e->getMessage());
}

// ✅ Controller has single return (Scramble-friendly)
public function index(Request $request, Service $service) {
    $results = $service->search($dto);  // May throw
    return Resource::collection($results);  // Single return
}
```

**Available Exceptions (Phase 1):**
- `InvalidFilterSyntaxException` (422) - Meilisearch filter validation
- `FileNotFoundException` (404) - Missing XML files
- `EntityNotFoundException` (404) - Missing lookup entities

**Laravel exception handler auto-renders** - no manual error handling in controllers needed.

---

## 🏷️ Universal Tag System (NEW 2025-11-21)

**All 6 main entities support tags:** Spell, Race, Item, Background, Class, Feat

```php
// Model
use Spatie\Tags\HasTags;
class Spell extends Model { use HasTags; }

// Resource (always included, no ?include= needed)
'tags' => TagResource::collection($this->whenLoaded('tags')),

// Controller (eager-load by default)
$spell->load(['spellSchool', 'sources', 'effects', 'classes', 'tags']);

// API Response
{
  "tags": [
    {"id": 2, "name": "Touch Spells", "slug": "touch-spells", "type": null}
  ]
}
```

**Benefits:** Categorization, filtering, consistent structure, type support

---

## 🚀 Quick Start

### Database Initialization (Always Start Here)

**Option 1: One-Command Import (Recommended)**
```bash
# Import EVERYTHING with one command (takes ~2-5 minutes)
docker compose exec php php artisan import:all

# Options:
docker compose exec php php artisan import:all --skip-migrate  # Keep existing DB
docker compose exec php php artisan import:all --only=spells   # Import only spells
docker compose exec php php artisan import:all --only=classes,spells  # Multiple types
docker compose exec php php artisan import:all --skip-search   # Skip search config
```

**Option 2: Manual Step-by-Step Import**
```bash
# 1. Fresh database with seeded lookup data
docker compose exec php php artisan migrate:fresh --seed

# 2. Import classes FIRST (spells reference classes via class_spells table)
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file" || true; done'

# 3. Import spells (main files with full definitions)
docker compose exec php bash -c 'for file in import-files/spell-*.xml; do [[ ! "$file" =~ \+.*\.xml$ ]] && php artisan import:spells "$file" || true; done'

# 4. Import additive spell class mappings (supplemental class associations)
docker compose exec php bash -c 'for file in import-files/spells-*+*.xml; do php artisan import:spell-class-mappings "$file" || true; done'

# 5. Import races
docker compose exec php bash -c 'for file in import-files/race-*.xml; do php artisan import:races "$file" || true; done'

# 6. Import items
docker compose exec php bash -c 'for file in import-files/item-*.xml; do php artisan import:items "$file" || true; done'

# 7. Import backgrounds
docker compose exec php bash -c 'for file in import-files/background-*.xml; do php artisan import:backgrounds "$file" || true; done'

# 8. Import feats
docker compose exec php bash -c 'for file in import-files/feat-*.xml; do php artisan import:feats "$file" || true; done'

# 9. Configure search indexes
docker compose exec php php artisan search:configure-indexes

# 10. Run tests
docker compose exec php php artisan test
```

**⚠️ CRITICAL ORDER:** Classes → Spells → Spell Class Mappings → Other entities. Spells require classes to exist for `class_spells` pivot table.

**Rationale:** Ensures consistent state, catches schema issues, verifies importers

### Development Workflow (Per Todo Item)

```bash
# BEFORE starting:
docker compose exec php php artisan migrate:fresh --seed  # Fresh state
docker compose exec php php artisan test                   # Verify starting point

# AFTER completing:
docker compose exec php php artisan test                   # Verify changes
docker compose exec php ./vendor/bin/pint                  # Format code
git add . && git commit -m "feat: clear message"           # Commit
```

---

## 📐 Repository Structure

```
app/
  ├── Http/
  │   ├── Controllers/Api/     # 17 controllers (6 entity + 11 lookup)
  │   ├── Resources/           # 25 API Resources (+ TagResource)
  │   └── Requests/            # 26 Form Requests
  ├── Models/                  # 28 models (all have HasFactory)
  └── Services/
      ├── Importers/           # 9 XML importers + reusable traits + strategy pattern
      └── Parsers/             # XML parsing + 15 reusable traits

database/
  ├── migrations/              # 64 migrations
  └── seeders/                 # 12 seeders (sources, schools, languages, etc.)

import-files/                  # XML source files
  ├── spells-*.xml            # 9 files (477 imported)
  ├── races-*.xml             # 5 files
  ├── items-*.xml             # 25 files
  ├── class-*.xml             # 35 files (131 imported)
  ├── feats-*.xml             # 4 files
  └── bestiary-*.xml          # 9 files (ready to import ~500-600 monsters)

tests/
  ├── Feature/                # API, importers, models, migrations
  └── Unit/                   # Parsers, factories, services
```

---

## 🔍 Key Features

### 1. Dual ID/Slug Routing (All Entities)
```
/api/v1/spells/123       ← Numeric ID
/api/v1/spells/fireball  ← SEO-friendly slug
```

### 2. Laravel Scout + Meilisearch
- **6 searchable types:** Spells, Items, Races, Classes, Backgrounds, Feats
- **Global search:** `/api/v1/search?q=fire&types=spells,items`
- **Typo-tolerant:** "firebll" finds "Fireball"
- **Performance:** <50ms average, <100ms p95
- **Graceful fallback** to MySQL FULLTEXT

### 3. Advanced Meilisearch Filtering
```bash
# Range queries
GET /api/v1/spells?filter=level >= 1 AND level <= 3

# Logical operators
GET /api/v1/spells?filter=school_code = EV OR school_code = C

# Combined search + filter
GET /api/v1/spells?q=fire&filter=level <= 3
```
See `docs/MEILISEARCH-FILTERS.md` for full syntax

### 4. Multi-Source Citations
Entities cite multiple sourcebooks via `entity_sources` polymorphic table.

### 5. Polymorphic Relationships
- **Traits, Modifiers, Proficiencies** - Shared across races/classes/backgrounds
- **Tags** - Universal categorization system (NEW)
- **Prerequisites** - Double polymorphic (entity → prerequisite type)
- **Random Tables** - d6/d8/d100 embedded in descriptions

### 6. Language System
30 D&D languages + choice slots ("choose one extra language")

### 7. Saving Throw Modifiers (NEW 2025-11-21)

**Tracks advantage/disadvantage on saving throws**

```json
{
  "saving_throws": [{
    "ability_score": {"code": "WIS", "name": "Wisdom"},
    "save_effect": "half_damage",
    "save_modifier": "none"  // 'none', 'advantage', or 'disadvantage'
  }]
}
```

**Semantic Meaning:**
- `'none'` = Standard save (Fireball: "make a DEX save")
- `'advantage'` = Grants advantage on saves (Heroes' Feast: "makes WIS saves with advantage")
- `'disadvantage'` = Imposes disadvantage (Charm Monster: "does so with advantage if fighting")
- `NULL` = Parser couldn't determine (data quality indicator)

**Use Cases:**
- Filter buff spells that grant advantage on saves
- Identify spells with conditional saves
- Character builders can optimize spell selection

### 8. AC Modifier Category System (NEW 2025-11-22)

**Shields use BOTH `armor_class` column AND `modifiers` table with distinct categories**

```json
{
  "name": "Shield",
  "armor_class": 2,
  "modifiers": [
    {"modifier_category": "ac_bonus", "value": "2"}
  ]
}

{
  "name": "Shield +1",
  "armor_class": 2,
  "modifiers": [
    {"modifier_category": "ac_bonus", "value": "2"},  // Base shield bonus
    {"modifier_category": "ac_magic", "value": "1"}   // Magic enchantment
  ]
}

{
  "name": "Shield +2",
  "armor_class": 2,
  "modifiers": [
    {"modifier_category": "ac_bonus", "value": "2"},  // Base shield bonus
    {"modifier_category": "ac_magic", "value": "2"}   // Magic enchantment (+2)
  ]
}
```

**AC Modifier Categories:**
- `ac_base` - Base armor AC (replaces natural AC, stores DEX modifier rules)
- `ac_bonus` - Equipment AC bonuses (shields, always additive)
- `ac_magic` - Magic enchantment bonuses (always additive)
- `ac` - Generic AC (legacy, may be deprecated)

**Why Distinct Categories?**
1. **Semantic Clarity** - `ac_bonus` vs `ac_magic` makes intent explicit
2. **Fixes Shield +2 Bug** - No longer confused with base bonus (both were +2)
3. **Query Flexibility** - Can filter by type: magic-only, equipment-only, or total
4. **Future-Proof** - Ready for complex armor calculations (Mage Armor, Barbarian AC)

**D&D 5e AC Calculation:**
```php
// Complete AC calculation model
$baseAC = $modifiers->where('category', 'ac_base')->max('value') ?? 10; // Armor or natural AC

// Apply DEX modifier based on armor type
$dexMod = $character->dexModifier;
$armorMod = $modifiers->where('category', 'ac_base')->first();
if ($armorMod) {
    $dexRule = $armorMod->condition; // 'dex_modifier: full' | 'max_2' | 'none'
    if (str_contains($dexRule, 'max_2')) $dexMod = min($dexMod, 2);
    if (str_contains($dexRule, 'none')) $dexMod = 0;
}

$totalAC = $baseAC + $dexMod
    + $modifiers->where('category', 'ac_bonus')->sum('value')  // Shields
    + $modifiers->where('category', 'ac_magic')->sum('value'); // Enchantments
```

**Implementation Details:**
- **Light Armor (LA):** `armor_class=11` + auto-created modifier(ac_base, 11) with `condition: "dex_modifier: full"`
- **Medium Armor (MA):** `armor_class=14` + auto-created modifier(ac_base, 14) with `condition: "dex_modifier: max_2"`
- **Heavy Armor (HA):** `armor_class=18` + auto-created modifier(ac_base, 18) with `condition: "dex_modifier: none"`
- **Regular shields:** `armor_class=2` + auto-created modifier(ac_bonus, 2)
- **Magic shields:** `armor_class=2` + base modifier(ac_bonus, 2) + magic modifier(ac_magic, +N)
- **Total AC from Shield +2:** `ac_bonus(2) + ac_magic(2) = +4`
- **Total AC from Plate + Shield +1:** `ac_base(18) + ac_bonus(2) + ac_magic(1) = 21`

**Migration:** Existing shields were backfilled with base AC modifiers via `2025_11_21_191858_add_ac_modifiers_for_shields.php`

---

## 🌐 API Endpoints

**Base:** `/api/v1`

**Entity Endpoints:**
- `GET /spells`, `GET /spells/{id|slug}` - 477 spells
- `GET /races`, `GET /races/{id|slug}` - Races/subraces
- `GET /items`, `GET /items/{id|slug}` - Items/equipment
- `GET /backgrounds`, `GET /backgrounds/{id|slug}` - Character backgrounds
- `GET /classes`, `GET /classes/{id|slug}` - 131 classes/subclasses
- `GET /classes/{id}/spells` - Class spell lists
- `GET /feats`, `GET /feats/{id|slug}` - Character feats
- `GET /search?q=term&types=spells,items` - Global search

**Lookup Endpoints:**
- `GET /sources` - D&D sourcebooks
- `GET /spell-schools` - 8 schools of magic
- `GET /damage-types` - 13 damage types
- `GET /conditions` - 15 D&D conditions
- `GET /proficiency-types` - 82 weapon/armor/tool types
- `GET /languages` - 30 languages

**Features:** Pagination, search, filtering, sorting, CORS enabled

**📖 OpenAPI Docs:** `http://localhost:8080/docs/api` (auto-generated via Scramble)

---

## 🧪 Testing

**826 tests** (5,500+ assertions) - ~40s duration

```bash
docker compose exec php php artisan test                    # All tests
docker compose exec php php artisan test --filter=Api       # API tests
docker compose exec php php artisan test --filter=Importer  # Importer tests
```

### Test Output Logging

**Always capture test output to a file for easier review:**

```bash
# Run tests with output logging (recommended)
docker compose exec php php artisan test 2>&1 | tee tests/results/test-output.log

# Check for failures in the log file
grep -E "(FAIL|FAILED)" tests/results/test-output.log

# Extract failed test details
grep -A 20 "FAILED" tests/results/test-output.log
```

**Benefits:**
- No need to re-run tests to see failure details
- Easier to share test output with team
- Can be committed to repo for debugging sessions
- Faster debugging workflow

**Note:** The `tests/results/` directory is gitignored. Create it if needed: `mkdir -p tests/results`

### Test Categories
- Feature: API endpoints, importers, models, migrations, Scramble docs
- Unit: Parsers, factories, services, exceptions

---

## 📥 XML Import System

### One-Command Import (Recommended)
```bash
php artisan import:all                   # Import EVERYTHING (fresh DB + all entities)
php artisan import:all --skip-migrate    # Keep existing DB, just import data
php artisan import:all --only=spells     # Import only specific entity type(s)
php artisan import:all --skip-search     # Skip search index configuration
```

**Features:**
- ✅ Automatically maintains correct import order
- ✅ Per-entity progress tracking
- ✅ Detailed summary table with success/fail counts
- ✅ Excludes additive files from main spell import
- ✅ Handles all 51+ XML files in one command

### Individual Importers (9 Available)
```bash
php artisan import:all                         # ⭐ MASTER COMMAND - imports everything
php artisan import:classes <file>              # Classes (35 files) - IMPORT FIRST!
php artisan import:spells <file>               # Spells (9 files - main definitions)
php artisan import:spell-class-mappings <file> # Additive class mappings (6 files)
php artisan import:races <file>                # Races (5 files)
php artisan import:items <file>                # Items (25 files)
php artisan import:backgrounds <file>          # Backgrounds (4 files)
php artisan import:feats <file>                # Feats (4 files)
php artisan import:monsters <file>             # Monsters (9 bestiary files ~500-600 monsters)
```

**⚠️ Import Order Matters:**
1. **Classes first** - Required by spells for `class_spells` pivot table
2. **Main spell files** - Full spell definitions (spells-phb.xml, spells-xge.xml, etc.)
3. **Additive spell files** - Only class mappings (spells-phb+dmg.xml, spells-*+*.xml)

**Additive Spell Files:**
These files contain ONLY `<name>` and `<classes>` elements. They add subclass associations to spells already imported from main files:
- `spells-phb+dmg.xml` - Death Domain, Oathbreaker additions
- `spells-phb+scag.xml` - Arcana Domain, Crown Paladin additions
- `spells-phb+tce.xml` - Tasha's subclass spell lists
- `spells-phb+xge.xml` - Xanathar's subclass spell lists
- `spells-xge+erlw.xml` - Eberron additions
- `spells-phb+erlw.xml` - Eberron additions

### Reusable Traits (21)

**NEW (2025-11-22):** Major refactoring completed - extracted 6 new traits to eliminate ~260 lines of duplicate code.

**Importer Traits (16):**
- **Core:** `CachesLookupTables`, `GeneratesSlugs`
- **Sources:** `ImportsSources` (with optional deduplication)
- **Relationships:** `ImportsTraits`, `ImportsProficiencies`, `ImportsLanguages`, `ImportsConditions`, `ImportsModifiers`
- **Spells:** `ImportsEntitySpells` ✨ NEW - Case-insensitive spell lookup with flexible pivot data
- **Prerequisites:** `ImportsPrerequisites` ✨ NEW - Standardized prerequisite creation
- **Random Tables:** `ImportsRandomTables`, `ImportsRandomTablesFromText` ✨ NEW - Polymorphic table import
- **Saving Throws:** `ImportsSavingThrows`
- **Armor Modifiers:** `ImportsArmorModifiers` ✨ NEW - Consolidated AC modifier logic

**Parser Traits (5):**
- `ParsesSourceCitations`, `ParsesTraits`, `ParsesRolls`
- `MatchesProficiencyTypes`, `MatchesLanguages`
- `MapsAbilityCodes` ✨ ENHANCED - Added ID resolution with caching

**Benefits:**
- DRY code with single source of truth
- Consistent behavior across all importers
- ~260 lines eliminated from existing importers
- Monster importer will be ~43% smaller

### Item Parser Strategy Pattern (NEW 2025-11-22)

**Architecture:** Type-specific parsing strategies for improved accuracy and maintainability.

**Problem Solved:** ItemXmlParser was a 481-line monolith with type-specific logic scattered throughout, making it difficult to maintain and test.

**Solution:** Strategy Pattern with 5 composable type-specific strategies:

1. **ChargedItemStrategy** - Staves, wands, rods with spell casting
   - Extracts spell names and charge costs from descriptions
   - Case-insensitive spell matching with database lookup
   - Supports variable costs: "1 charge per spell level, up to 4th"
   - Creates entity_spells relationships automatically
   - Tracks: `spell_references_found`, `spells_matched`, `spells_not_found`

2. **ScrollStrategy** - Spell scrolls and protection scrolls
   - Extracts spell level from names: "Spell Scroll (3rd Level)" → level 3
   - Handles cantrips: "Spell Scroll (Cantrip)" → level 0
   - Distinguishes protection scrolls (no spell level)
   - Tracks: `spell_scrolls`, `protection_scrolls`, `spell_level`, `protection_duration`

3. **PotionStrategy** - Potion effect categorization
   - Extracts duration: "for 1 hour", "for 10 minutes"
   - Categorizes effects: healing, resistance, buff, debuff, utility
   - Detects resistance from modifiers or description
   - Tracks per-category metrics: `effect_healing`, `effect_resistance`, etc.

4. **TattooStrategy** - Magic tattoos (wondrous items)
   - Extracts tattoo type: "Absorbing Tattoo" → "absorbing"
   - Detects activation methods: action, bonus action, reaction, passive
   - Attempts body location extraction (arm, chest, back, etc.)
   - Tracks: `tattoo_type`, `activation_methods`, `body_location`

5. **LegendaryStrategy** - Legendary and artifact items
   - Detects sentient items (intelligence/wisdom/charisma scores, telepathy)
   - Extracts alignment: lawful good, chaotic evil, etc.
   - Extracts personality traits: arrogant, cruel, kind, etc.
   - Detects artifact destruction methods
   - Tracks: `sentient_items`, `artifacts`, `legendary_items`, `alignment`

**Strategy Logging:** `storage/logs/import-strategy-{date}.log` (structured JSON, cleared per import)

**Import Statistics Display:**
```
✓ Successfully imported 516 items

Strategy Statistics:
+---------------------+----------------+----------+
| Strategy            | Items Enhanced | Warnings |
+---------------------+----------------+----------+
| ChargedItemStrategy | 62             | 29       |
| LegendaryStrategy   | 65             | 0        |
| PotionStrategy      | 45             | 0        |
| ScrollStrategy      | 20             | 1        |
+---------------------+----------------+----------+
⚠ Detailed logs: storage/logs/import-strategy-2025-11-22.log
```

**Architecture Benefits:**
- Each strategy ~100-150 lines (vs 481-line monolith)
- Isolated testing with real XML fixtures
- Composition: items can use multiple strategies (e.g., Legendary + Charged)
- Single point of change for XML format updates
- 85%+ test coverage per strategy (44 strategy-specific tests)

**Adding New Strategies:**
1. Extend `AbstractItemStrategy`
2. Implement `appliesTo()` and enhancement methods
3. Add to `ItemXmlParser::initializeStrategies()`
4. Write tests with real XML fixtures (85%+ coverage)
5. Verify with `php artisan test --filter=YourStrategyTest`

### Monster Parser Strategy Pattern (NEW 2025-11-22)

**Architecture:** Type-specific parsing strategies for improved accuracy and maintainability.

**Solution:** Strategy Pattern with 5 composable type-specific strategies:

1. **DefaultStrategy** - Baseline for all monsters
   - Standard trait/action/legendary action parsing
   - Applied to 100% of monsters as foundation

2. **DragonStrategy** - Dragons (by type)
   - Detects dragon breath weapon damage/save
   - Extracts frightful presence DC
   - Tags: breath_weapon, frightful_presence

3. **SpellcasterStrategy** - Creatures with spellcasting
   - Extracts spellcasting ability (INT/WIS/CHA)
   - Parses spell slots and spell lists
   - Creates MonsterSpellcasting record
   - **Note:** Does NOT sync entity_spells table yet (enhancement opportunity)

4. **UndeadStrategy** - Undead creatures (by type)
   - Detects undead fortitude trait
   - Tags: undead_fortitude, turn_resistance

5. **SwarmStrategy** - Swarm creatures (by size prefix)
   - Detects swarm type and creature count
   - Tags: swarm, swarm_type

**Strategy Logging:** `storage/logs/import-strategy-{date}.log` (structured JSON, cleared per import)

**Import Statistics Display:**
```
✓ Successfully imported 354 monsters

Strategy Statistics:
+---------------------+----------+----------+
| Strategy            | Monsters | Warnings |
+---------------------+----------+----------+
| DragonStrategy      | 12       | 0        |
| SpellcasterStrategy | 45       | 3        |
| DefaultStrategy     | 354      | 0        |
+---------------------+----------+----------+
⚠ Detailed logs: storage/logs/import-strategy-2025-11-22.log
```

**Architecture Benefits:**
- Each strategy ~50-60 lines (vs 400+ line monolith)
- Isolated testing with real XML fixtures
- Composition: monsters can use multiple strategies (e.g., Dragon + Spellcaster)
- Single point of change for XML format updates
- 85%+ test coverage per strategy (75 strategy-specific tests)

**Models:**
- Monster (main stat block)
- MonsterTrait (passive abilities)
- MonsterAction (combat actions)
- MonsterLegendaryAction (legendary actions)
- MonsterSpellcasting (spell lists)

**Enhancement Opportunities:**
- SpellcasterStrategy could sync entity_spells (like ChargedItemStrategy)
- Additional strategies: FiendStrategy, CelestialStrategy, ConstructStrategy
- Lair actions & regional effects (requires schema changes)

---

## 📚 Code Architecture

### Form Request Pattern
Every controller action has dedicated Request class:
```php
// SpellIndexRequest validates: per_page, sort_by, level, school, etc.
public function index(SpellIndexRequest $request) { }

// SpellShowRequest validates: include relationships
public function show(SpellShowRequest $request, Spell $spell) { }
```

### Service Layer Pattern
Controllers delegate to services for business logic:
```php
// SpellSearchService handles Scout/Meilisearch/database queries
public function index(Request $request, SpellSearchService $service) {
    $dto = SpellSearchDTO::fromRequest($request);
    $spells = $service->searchWithMeilisearch($dto, $meilisearch);
    return SpellResource::collection($spells);  // Single return
}
```

### Resource Pattern
Consistent API serialization via JsonResource classes:
```php
class SpellResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            // ... all fields explicitly defined
        ];
    }
}
```

---

## 🗂️ Factories & Seeders

**12 Model Factories:** All entities support factory-based test data creation

**Polymorphic Factory Pattern:**
```php
CharacterTrait::factory()->forEntity(Race::class, $race->id)->create();
EntitySource::factory()->forEntity(Spell::class, $spell->id)->fromSource('PHB')->create();
```

**12 Database Seeders:**
- Sources (D&D sourcebooks)
- Spell schools, damage types, conditions
- Proficiency types (82 entries)
- Languages (30 entries)
- Sizes, ability scores, skills
- Item types/properties, character classes

**Run:** `docker compose exec php php artisan db:seed`

---

## 🚦 What's Next

### Priority 1: Enhance SpellcasterStrategy ⭐ RECOMMENDED (3-4 hours)
**Status:** Ready to implement
**Goal:** Sync entity_spells table for monsters with spellcasting

**Current Behavior:**
- SpellcasterStrategy creates `MonsterSpellcasting` records with spell names
- Spells are NOT synced to `entity_spells` table (no queryable relationships)

**Enhancement:**
- Add spell name → Spell lookup with caching (following `ChargedItemStrategy` pattern)
- Sync spells to `entity_spells` polymorphic table
- Track metrics: `spells_matched`, `spells_not_found`
- Enable filtering: `GET /api/v1/monsters?spells=fireball`
- Enable new endpoint: `GET /api/v1/monsters/{id}/spells`

**Files to Modify:**
- `app/Services/Importers/Strategies/Monster/SpellcasterStrategy.php`
- `tests/Unit/Strategies/Monster/SpellcasterStrategyTest.php`

**After Implementation:**
Re-import monsters to populate entity_spells: `php artisan import:all --only=monsters --skip-migrate`

### Priority 2: Race API Endpoints (2-3 hours)
**Create RESTful API for Races**

**Files to Create:**
- `app/Http/Controllers/Api/RaceController.php`
- `app/Http/Resources/RaceResource.php`
- `app/Http/Requests/RaceIndexRequest.php`
- `app/Http/Requests/RaceShowRequest.php`
- `tests/Feature/Api/RaceApiTest.php`

**Endpoints:**
```
GET /api/v1/races - List with pagination, filtering
GET /api/v1/races/{id|slug} - Show with relationships
```

**Filters:** Size, speed, ability score bonuses, subraces

### Priority 3: Background API Endpoints (2-3 hours)
**Create RESTful API for Backgrounds**

**Files to Create:**
- `app/Http/Controllers/Api/BackgroundController.php`
- `app/Http/Resources/BackgroundResource.php`
- `app/Http/Requests/BackgroundIndexRequest.php`
- `app/Http/Requests/BackgroundShowRequest.php`
- `tests/Feature/Api/BackgroundApiTest.php`

**Endpoints:**
```
GET /api/v1/backgrounds - List with pagination
GET /api/v1/backgrounds/{id|slug} - Show with relationships
```

### Priority 4: Performance & Polish (5-8 hours)
**Optional enhancements**

- **API Caching:** Cache lookup tables (1h expiry), search results (5min), entity endpoints (15min)
- **Database Indexing:** Add composite indexes for common filter combinations
- **Rate Limiting:** Per-IP throttling (60 req/min) to prevent abuse
- **Add CR Numeric Column:** Fix challenge rating filtering edge cases (VARCHAR → DECIMAL)
- **Postman Collection:** Example requests for all 7 entity endpoints

### Additional Opportunities
- **Character Builder API:** API endpoints for character creation (8-12 hours)
- **Encounter Builder API:** Balanced encounter creation for DMs (6-10 hours)
- **Frontend Application:** Web UI using Inertia.js/Vue or Next.js/React (20-40 hours)
- **Additional Monster Strategies:** FiendStrategy, CelestialStrategy, ConstructStrategy (2-3h each)

---

## 📖 Documentation

**Essential Reading:**
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-IMPORTER-COMPLETE.md` - **LATEST** Monster Importer complete
- `docs/SESSION-HANDOVER-2025-11-22-ITEM-PARSER-STRATEGIES-COMPLETE.md` - Item Parser strategies
- `docs/SEARCH.md` - Search system documentation
- `docs/MEILISEARCH-FILTERS.md` - Advanced filter syntax
- `docs/recommendations/CUSTOM-EXCEPTIONS-ANALYSIS.md` - Exception patterns

**Plans:**
- `docs/plans/2025-11-22-monster-importer-implementation.md` - Monster importer implementation
- `docs/plans/2025-11-22-monster-importer-strategy-pattern.md` - Monster strategy design
- `docs/plans/2025-11-17-dnd-compendium-database-design.md` - Database architecture
- `docs/plans/2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md` - Implementation strategy

---

## Git Workflow

### Commit Message Convention
```
feat: add universal tag support
fix: correct damage type parsing
refactor: extract ImportsSources trait
test: add tag integration tests
docs: update session handover

🤖 Generated with [Claude Code](https://claude.com/claude-code)
Co-Authored-By: Claude <noreply@anthropic.com>
```

### Creating Pull Requests
```bash
# 1. Check diff and commit history
git log origin/main..HEAD --oneline
git diff origin/main...HEAD

# 2. Push and create PR
git push -u origin feature/your-branch
gh pr create --title "Title" --body "$(cat <<'EOF'
## Summary
- Feature 1
- Feature 2

## Test Plan
- [ ] All tests passing
- [ ] Manual testing complete

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## 🎯 Success Checklist

Before marking work complete:
- [ ] All tests passing (1,012+ tests)
- [ ] Code formatted with Pint
- [ ] API Resources expose new data
- [ ] Form Requests validate new parameters
- [ ] Controllers eager-load new relationships
- [ ] **CHANGELOG.md updated** with new features/changes
- [ ] Session handover document updated
- [ ] Commit messages are clear
- [ ] No uncommitted changes

**If tests aren't written, the feature ISN'T done.**

**⚠️ IMPORTANT:** After completing ANY feature, always update `CHANGELOG.md` under the `[Unreleased]` section. Before each release, move unreleased items to a dated version section.

---

**Branch:** `main` | **Status:** ✅ All Importers Complete | **Tests:** 1,012 passing | **Models:** 28 | **Importers:** 9 (Strategy Pattern)
