# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Current Status (2025-11-21):**
- ✅ **719 tests passing** (4,700 assertions) - 100% pass rate
- ✅ **60 migrations** - Complete schema (slugs, languages, prerequisites, spell tags)
- ✅ **23 models + 25 API Resources + 17 controllers** - Full CRUD + Search
- ✅ **6 importers** - Spells, Races, Items, Backgrounds, Classes, Feats
- ✅ **Universal tag system** - All entities support Spatie Tags
- ✅ **Search complete** - Laravel Scout + Meilisearch (3,002 documents)
- ✅ **OpenAPI docs** - Auto-generated via Scramble (306KB spec)
- ⚠️  **1 importer pending** - Monsters (7 bestiary XML files ready)

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 | PHPUnit 11+ | Docker

**📖 Read handover:** `docs/SESSION-HANDOVER-2025-11-21.md` for latest session details

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

```bash
# 1. Fresh database with seeded lookup data
docker compose exec php php artisan migrate:fresh --seed

# 2. Import entities (example: spells subset)
docker compose exec php bash -c 'for file in import-files/spells-phb.xml import-files/spells-tce.xml; do php artisan import:spells "$file" || true; done'

# 3. Configure search indexes
docker compose exec php php artisan search:configure-indexes

# 4. Run tests
docker compose exec php php artisan test
```

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
  ├── Models/                  # 23 models (all have HasFactory)
  └── Services/
      ├── Importers/           # 6 XML importers + reusable traits
      └── Parsers/             # XML parsing + 15 reusable traits

database/
  ├── migrations/              # 60 migrations
  └── seeders/                 # 12 seeders (sources, schools, languages, etc.)

import-files/                  # XML source files
  ├── spells-*.xml            # 9 files (477 imported)
  ├── races-*.xml             # 5 files
  ├── items-*.xml             # 25 files
  ├── class-*.xml             # 35 files (131 imported)
  ├── feats-*.xml             # 4 files
  └── bestiary-*.xml          # 7 files (⚠️ PENDING)

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

**719 tests** (4,700 assertions) - 40s duration

```bash
docker compose exec php php artisan test                    # All tests
docker compose exec php php artisan test --filter=Api       # API tests
docker compose exec php php artisan test --filter=Importer  # Importer tests
```

**Test Categories:**
- Feature: API endpoints, importers, models, migrations, Scramble docs
- Unit: Parsers, factories, services, exceptions

---

## 📥 XML Import System

### Available Importers (6 Working)
```bash
php artisan import:spells <file>       # Spells (9 files available)
php artisan import:races <file>        # Races (5 files)
php artisan import:items <file>        # Items (25 files)
php artisan import:backgrounds <file>  # Backgrounds (4 files)
php artisan import:classes <file>      # Classes (35 files)
php artisan import:feats <file>        # Feats (4 files)
```

### Reusable Traits (15)
**Parser Traits:**
- `ParsesSourceCitations`, `ParsesTraits`, `ParsesRolls`
- `MatchesProficiencyTypes`, `MatchesLanguages`

**Importer Traits:**
- `ImportsSources`, `ImportsTraits`, `ImportsProficiencies`
- `ImportsModifiers`, `ImportsLanguages`, `ImportsConditions`
- `ImportsRandomTables`, `CachesLookupTables`, `GeneratesSlugs`

**Benefits:** DRY code, consistent behavior, easy to maintain

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

### Priority 1: Monster Importer ⭐ RECOMMENDED
- 7 bestiary XML files ready
- Schema complete and tested
- Can reuse all 15 importer/parser traits
- **Estimated:** 6-8 hours with TDD

### Priority 2: Import Remaining Data
- 6 more spell files (~300 spells)
- Races, Items, Backgrounds, Feats (importers ready, just need to run commands)

### Priority 3: API Enhancements
- Additional filtering/aggregation
- Rate limiting
- Caching strategy

---

## 📖 Documentation

**Essential Reading:**
- `docs/SESSION-HANDOVER-2025-11-21.md` - Latest session (spell enhancements + tags)
- `docs/SEARCH.md` - Search system documentation
- `docs/MEILISEARCH-FILTERS.md` - Advanced filter syntax
- `docs/recommendations/CUSTOM-EXCEPTIONS-ANALYSIS.md` - Exception patterns

**Plans:**
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
- [ ] All tests passing (719+ tests)
- [ ] Code formatted with Pint
- [ ] API Resources expose new data
- [ ] Form Requests validate new parameters
- [ ] Controllers eager-load new relationships
- [ ] Session handover document updated
- [ ] Commit messages are clear
- [ ] No uncommitted changes

**If tests aren't written, the feature ISN'T done.**

---

**Branch:** `main` | **Status:** ✅ Production-Ready | **Tests:** 719 passing
