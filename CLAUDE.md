# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Current Status (2025-11-23):**
- ✅ **1,273 tests passing** (7,200+ assertions) - 100% pass rate, comprehensive coverage
- ✅ **64 migrations** - Complete schema (slugs, languages, prerequisites, spell tags, saving throws with DC, monsters)
- ✅ **32 models + 29 API Resources + 18 controllers** - Full CRUD + Search for 7 entities
- ✅ **9 importers** - Spells, Classes, Races, Items, Backgrounds, Feats, Monsters, Spell Class Mappings, Master Import
- ✅ **22 reusable traits** - 7 NEW from refactoring (2025-11-22), ~360 lines eliminated
- ✅ **Monster API Complete** - 598 monsters imported, full REST API with filtering by CR/type/size/alignment/spells
- ✅ **Monster Spell Filtering API** - Query monsters by spells (`?spells=fireball`), list monster spells (`GET /monsters/{id}/spells`)
- ✅ **Monster Importer with Strategy Pattern** - 5 type-specific strategies (Dragon, Spellcaster, Undead, Swarm, Default)
- ✅ **Monster Spell Syncing** - SpellcasterStrategy syncs 1,098 spell relationships for 129 spellcasting monsters
- ✅ **Item Parser Strategy Pattern** - 5 type-specific strategies (Charged, Scroll, Potion, Tattoo, Legendary)
- ✅ **Performance Optimizations Complete** - Redis caching for lookup + entity endpoints (93.7% improvement, 16.6x faster)
- ✅ **One-command import** - `import:all` handles all 60+ XML files in correct order
- ✅ **Universal tag system** - All 7 entities support Spatie Tags
- ✅ **Saving throw modifiers** - Detects advantage/disadvantage + DC values
- ✅ **AC modifier categories** - Base AC, bonuses, and magic (with DEX rules)
- ✅ **Additive spell imports** - Handles supplemental class association files
- ✅ **Search complete** - Laravel Scout + Meilisearch (3,600+ documents indexed)
- ✅ **OpenAPI docs** - Auto-generated via Scramble (306KB spec)
- ✅ **Item enhancements** - Usage limits ("at will"), set scores (`set:19`), potion resistance (23 items)
- ✅ **Test suite optimized** - Removed 36 redundant tests, 10 files deleted, 48.58s duration

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 | PHPUnit 11+ | Docker Compose (not Sail) | Meilisearch | Redis

**⚠️ IMPORTANT: Docker Compose Setup**
- This project uses **Docker Compose directly**, NOT Laravel Sail
- Commands use `docker compose exec php` instead of `sail`
- All PHP commands: `docker compose exec php php artisan ...`
- All Composer commands: `docker compose exec php composer ...`
- Database access: `docker compose exec mysql mysql -uroot -ppassword dnd_importer`

**📖 Read handovers:**
- `docs/PROJECT-STATUS.md` - **START HERE** Comprehensive project overview with metrics
- `docs/README.md` - Documentation index and navigation
- `docs/SESSION-HANDOVER-2025-11-22-PERFORMANCE-PHASE-3-ENTITY-CACHING.md` - **LATEST** Entity caching (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-PERFORMANCE-PHASE-2-CACHING.md` - Lookup caching (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-SPELL-API-COMPLETE.md` - Monster spell filtering API (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-SPELLCASTER-STRATEGY-ENHANCEMENT.md` - Monster spell syncing (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-API-AND-SEARCH-COMPLETE.md` - Monster API implementation (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-IMPORTER-COMPLETE.md` - Monster importer with strategies (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-ITEM-PARSER-STRATEGIES-COMPLETE.md` - Item parser refactoring (COMPLETE)
- `docs/SESSION-HANDOVER-2025-11-22-TEST-REDUCTION-PHASE-1.md` - Test suite optimization (COMPLETE)

**🚀 Next tasks (all optional - core features complete):**
1. Search result caching (Phase 4) - Cache Meilisearch queries - 2-3 hours
2. Character Builder API (character creation, leveling, spell selection) - 8-12 hours
3. Additional Monster Strategies (FiendStrategy, CelestialStrategy, ConstructStrategy) - 2-3h each
4. Frontend Application (Inertia.js/Vue or Next.js/React) - 20-40 hours

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

## 🏷️ Universal Tag System

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

# 5. Import other entities (races, items, backgrounds, feats, monsters)
docker compose exec php bash -c 'for file in import-files/race-*.xml; do php artisan import:races "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/item-*.xml; do php artisan import:items "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/background-*.xml; do php artisan import:backgrounds "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/feat-*.xml; do php artisan import:feats "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/bestiary-*.xml; do php artisan import:monsters "$file" || true; done'

# 6. Configure search indexes
docker compose exec php php artisan search:configure-indexes

# 7. Run tests
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
  │   ├── Controllers/Api/     # 18 controllers (7 entity + 11 lookup)
  │   ├── Resources/           # 29 API Resources (+ TagResource)
  │   └── Requests/            # 26 Form Requests
  ├── Models/                  # 32 models (all have HasFactory)
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
  └── bestiary-*.xml          # 9 files (598 monsters imported)

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
- **Tags** - Universal categorization system
- **Prerequisites** - Double polymorphic (entity → prerequisite type)
- **Random Tables** - d6/d8/d100 embedded in descriptions

### 6. Language System
30 D&D languages + choice slots ("choose one extra language")

### 7. Saving Throw Modifiers

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

### 8. AC Modifier Category System

**AC modifiers categorized by type for accurate D&D 5e calculations**

```json
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

**Why Categories?**
1. **Semantic Clarity** - Intent is explicit (base vs bonus vs magic)
2. **Fixes Shield +2 Bug** - No longer confused with base bonus
3. **Query Flexibility** - Filter by type: magic-only, equipment-only, or total
4. **D&D 5e Compliance** - Ready for complex AC calculations (Mage Armor, Barbarian Unarmored Defense)

**Implementation:** Light/Medium/Heavy armor auto-creates `ac_base` modifiers with DEX rules (`dex_modifier: full|max_2|none`). Shields use `ac_bonus` for base and `ac_magic` for enchantments.

---

## 🌐 API Endpoints

**Base:** `/api/v1`

**Entity Endpoints (All 7 Complete):**
- `GET /spells`, `GET /spells/{id|slug}` - 477 spells
- `GET /items`, `GET /items/{id|slug}` - 516 items/equipment
- `GET /monsters`, `GET /monsters/{id|slug}`, `GET /monsters/{id}/spells` - 598 monsters
- `GET /classes`, `GET /classes/{id|slug}`, `GET /classes/{id}/spells` - 131 classes/subclasses
- `GET /races`, `GET /races/{id|slug}`, `GET /races/{id}/spells` - Races/subraces with innate spells
- `GET /backgrounds`, `GET /backgrounds/{id|slug}` - Character backgrounds
- `GET /feats`, `GET /feats/{id|slug}` - Character feats
- `GET /search?q=term&types=spells,items,monsters,classes,races,backgrounds,feats` - Global search

**Lookup Endpoints:**
- `GET /sources` - D&D sourcebooks
- `GET /spell-schools` - 8 schools of magic
- `GET /damage-types` - 13 damage types
- `GET /conditions` - 15 D&D conditions
- `GET /proficiency-types` - 82 weapon/armor/tool types
- `GET /languages` - 30 languages

**Features:** Pagination, search, filtering, sorting, CORS enabled, Redis caching (93.7% faster)

**📖 OpenAPI Docs:** `http://localhost:8080/docs/api` (auto-generated via Scramble)

---

## 🧪 Testing

**1,273 tests** (7,200+ assertions) - ~45s duration

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
php artisan import:monsters <file>             # Monsters (9 bestiary files - 598 monsters)
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

### Reusable Traits (22)

**Importer Traits (17):**
- **Core:** `CachesLookupTables`, `GeneratesSlugs`
- **Sources:** `ImportsSources` (with optional deduplication)
- **Relationships:** `ImportsTraits`, `ImportsProficiencies`, `ImportsLanguages`, `ImportsConditions`, `ImportsModifiers`
- **Spells:** `ImportsEntitySpells` - Case-insensitive spell lookup with flexible pivot data
- **Classes:** `ImportsClassAssociations` - Resolve class names (base/subclass) with fuzzy matching, alias mapping, and sync strategies
- **Prerequisites:** `ImportsPrerequisites` - Standardized prerequisite creation
- **Random Tables:** `ImportsRandomTables`, `ImportsRandomTablesFromText` - Polymorphic table import
- **Saving Throws:** `ImportsSavingThrows`
- **Armor Modifiers:** `ImportsArmorModifiers` - Consolidated AC modifier logic

**Parser Traits (5):**
- `ParsesSourceCitations`, `ParsesTraits`, `ParsesRolls`
- `MatchesProficiencyTypes`, `MatchesLanguages`
- `MapsAbilityCodes` - Added ID resolution with caching

**Benefits:**
- DRY code with single source of truth
- Consistent behavior across all importers
- ~360 lines eliminated from existing importers
- SpellImporter -24%, SpellClassMappingImporter -28%

### Strategy Pattern Architecture (4 of 9 Importers)

**ItemImporter (5 strategies):**
1. **ChargedItemStrategy** - Staves/wands with spell casting, syncs entity_spells
2. **ScrollStrategy** - Spell scrolls with level extraction
3. **PotionStrategy** - Effect categorization (healing, resistance, buff, debuff, utility)
4. **TattooStrategy** - Magic tattoos with activation methods
5. **LegendaryStrategy** - Sentient items, artifacts, alignment/personality

**MonsterImporter (5 strategies):**
1. **DefaultStrategy** - Baseline for all monsters (traits/actions/legendary actions)
2. **DragonStrategy** - Breath weapon damage/save, frightful presence DC
3. **SpellcasterStrategy** - Spell lists, syncs 1,098 entity_spells for 129 monsters
4. **UndeadStrategy** - Undead fortitude, turn resistance
5. **SwarmStrategy** - Swarm type and creature count

**RaceImporter (3 strategies):**
1. **BaseRaceStrategy** - Base races (Elf, Dwarf, Human)
2. **SubraceStrategy** - Subraces with parent resolution (High Elf → Elf)
3. **RacialVariantStrategy** - Racial variants (Dragonborn Gold)

**ClassImporter (2 strategies):**
1. **BaseClassStrategy** - Base classes with spellcasting detection
2. **SubclassStrategy** - Subclasses with parent resolution (School of Evocation → Wizard)

**Benefits:**
- Each strategy 50-150 lines (vs 400+ line monoliths)
- Isolated testing with real XML fixtures
- Composition: entities can use multiple strategies
- 85%+ test coverage per strategy
- Structured logging to `storage/logs/import-strategy-{date}.log`

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

## 📖 Documentation

**Essential Reading:**
- `docs/SESSION-HANDOVER-2025-11-22-PERFORMANCE-PHASE-3-ENTITY-CACHING.md` - **LATEST** Entity caching
- `docs/SESSION-HANDOVER-2025-11-22-PERFORMANCE-PHASE-2-CACHING.md` - Lookup caching
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-IMPORTER-COMPLETE.md` - Monster Importer complete
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
perf: add Redis caching for entity endpoints

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
- [ ] All tests passing (1,273+ tests)
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

**Branch:** `main` | **Status:** ✅ Performance Optimized (Phase 3 Complete) | **Tests:** 1,273 passing | **Models:** 32 | **Importers:** 9 (Strategy Pattern)
