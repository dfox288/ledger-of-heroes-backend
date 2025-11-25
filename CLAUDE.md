# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 | PHPUnit 11+ | Docker Compose (not Sail) | Meilisearch | Redis

**⚠️ IMPORTANT: Docker Compose Setup**
- This project uses **Docker Compose directly**, NOT Laravel Sail
- Commands: `docker compose exec php php artisan ...`
- Database: `docker compose exec mysql mysql -uroot -ppassword dnd_importer`

**📖 Essential Reading:**
- `docs/PROJECT-STATUS.md` - **START HERE** - Project metrics and current status
- `docs/DND-FEATURES.md` - D&D 5e game mechanics (tags, AC, saving throws, etc.)
- `docs/SESSION-HANDOVER-2025-11-24-MEILISEARCH-PHASE-1.md` - **LATEST** handover

**Current Status:**
- ✅ 1,489 tests passing (7,704 assertions) - 99.7% pass rate
- ✅ All 7 entity APIs complete (Spells, Monsters, Classes, Races, Items, Backgrounds, Feats)
- ✅ Production-ready with Redis caching (93.7% faster)

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
7. **Update CHANGELOG.md** under `[Unreleased]`
8. Commit with clear message
9. **Push to remote** (`git push`)

**If tests aren't written, the feature ISN'T done.**

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

**Available Exceptions:**
- `InvalidFilterSyntaxException` (422) - Meilisearch filter validation
- `FileNotFoundException` (404) - Missing XML files
- `EntityNotFoundException` (404) - Missing lookup entities

**Laravel exception handler auto-renders** - no manual error handling in controllers needed.

---

## 🚀 Quick Start

### ⚠️ CRITICAL: Test vs Production Database Isolation

**Two Separate Databases:**
- **Production:** `dnd_compendium` (uses Meilisearch indexes: `spells`, `items`, etc.)
- **Test:** `dnd_compendium_test` (uses Meilisearch indexes: `test_spells`, `test_items`, etc.)

**BOTH databases must be imported separately to ensure Meilisearch indexes match database counts!**

### Database Initialization

**Production Database (One-Command):**
```bash
# Import EVERYTHING to production DB (takes ~2-5 minutes)
docker compose exec php php artisan import:all

# Options:
--skip-migrate  # Keep existing DB
--only=spells   # Import specific entities
--skip-search   # Skip search config
```

**Test Database (REQUIRED for tests to pass):**
```bash
# Import EVERYTHING to test DB with test_ Meilisearch prefix
# CRITICAL: Must use -e SCOUT_PREFIX=test_ for proper index isolation
docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:all --env=testing

# Verify test indexes populated:
# test_spells: 477, test_items: 2232, test_monsters: 598, test_races: 89
# test_classes: 131, test_backgrounds: 34, test_feats: 138
```

**Why Both?**
- Tests use `RefreshDatabase` which migrates/seeds `dnd_compendium_test`
- `.env.testing` configures MySQL test DB (NOT SQLite) for database compatibility
- `SCOUT_PREFIX=test_` ensures tests use isolated Meilisearch indexes
- Without proper test DB import, search/filter tests will fail

**Manual Import:**
```bash
# 1. Fresh database
docker compose exec php php artisan migrate:fresh --seed

# 2. Import items FIRST (required by classes/backgrounds for equipment matching)
docker compose exec php bash -c 'for file in import-files/item-*.xml; do php artisan import:items "$file" || true; done'

# 3. Import classes (required by spells)
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file" || true; done'

# 4. Import spells (main files)
docker compose exec php bash -c 'for file in import-files/spell-*.xml; do [[ ! "$file" =~ \+.*\.xml$ ]] && php artisan import:spells "$file" || true; done'

# 5. Import additive spell class mappings
docker compose exec php bash -c 'for file in import-files/spells-*+*.xml; do php artisan import:spell-class-mappings "$file" || true; done'

# 6. Import other entities
docker compose exec php bash -c 'for file in import-files/race-*.xml; do php artisan import:races "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/background-*.xml; do php artisan import:backgrounds "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/feat-*.xml; do php artisan import:feats "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/bestiary-*.xml; do php artisan import:monsters "$file" || true; done'

# 7. Configure search
docker compose exec php php artisan search:configure-indexes

# 8. Run tests
docker compose exec php php artisan test
```

**⚠️ CRITICAL ORDER:** Items → Classes → Spells → Spell Class Mappings → Other entities

### Development Workflow

```bash
# BEFORE starting:
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan test

# AFTER completing:
docker compose exec php php artisan test
docker compose exec php ./vendor/bin/pint
git add . && git commit -m "feat: clear message"
git push
```

---

## 📐 Repository Structure

```
app/
  ├── Http/
  │   ├── Controllers/Api/     # 18 controllers (7 entity + 11 lookup)
  │   ├── Resources/           # 29 API Resources
  │   └── Requests/            # 26 Form Requests
  ├── Models/                  # 32 models
  └── Services/
      ├── Importers/           # 9 importers + traits + strategies
      └── Parsers/             # XML parsing + traits

database/
  ├── migrations/              # 66 migrations
  └── seeders/                 # 12 seeders

import-files/                  # 60+ XML source files

tests/
  ├── Feature/                # API, importers, models
  └── Unit/                   # Parsers, services, strategies
```

---

## 🌐 API Endpoints

**Base:** `/api/v1`

**Entity Endpoints:**
- `GET /spells`, `GET /spells/{id|slug}` - 477 spells
- `GET /monsters`, `GET /monsters/{id|slug}`, `GET /monsters/{id}/spells` - 598 monsters
- `GET /classes`, `GET /classes/{id|slug}`, `GET /classes/{id}/spells` - 131 classes
- `GET /races`, `GET /races/{id|slug}` - 115 races
- `GET /items`, `GET /items/{id|slug}` - 516 items
- `GET /backgrounds`, `GET /backgrounds/{id|slug}` - 34 backgrounds
- `GET /feats`, `GET /feats/{id|slug}` - Character feats
- `GET /search?q=term&types=spells,monsters` - Global search

**Lookup Endpoints:**
- `GET /sources`, `/spell-schools`, `/damage-types`, `/conditions`, `/proficiency-types`, `/languages`

**Features:** Pagination, search, filtering, sorting, CORS, Redis caching (93.7% faster), OpenAPI docs

**📖 OpenAPI Docs:** `http://localhost:8080/docs/api` (Scramble)

---

## ⚡ Search & Filtering Architecture

**⚠️ CRITICAL: Use Meilisearch for ALL Filtering**

This API uses **Meilisearch** exclusively for search and filtering. Do NOT add Eloquent/Scout filtering logic to Service classes.

**✅ Correct Approach:**
- All filtering happens via the `?filter=` parameter using Meilisearch syntax
- Filterable fields are defined in each model's `searchableOptions()` method
- Data is indexed via `toSearchableArray()` method

**❌ Wrong Approach:**
- Adding custom query parameters like `?classes=bard`
- Writing Eloquent `whereHas()` logic in Service classes
- Creating Form Request validation for filter-specific parameters

**Examples:**
```bash
# ✅ CORRECT - Use Meilisearch filter syntax
GET /api/v1/spells?filter=class_slugs IN [bard]
GET /api/v1/spells?filter=class_slugs IN [bard] AND level <= 3
GET /api/v1/spells?filter=tag_slugs IN [fire] AND concentration = true

# ❌ WRONG - Don't add custom parameters
GET /api/v1/spells?classes=bard  # This does NOT work
```

**Adding New Filterable Fields:**
1. Add field to model's `toSearchableArray()` method
2. Add field to model's `searchableOptions()` → `filterableAttributes` array
3. Re-index with `php artisan scout:import "App\Models\ModelName"`
4. Document the field in the Controller PHPDoc

**No Service/DTO/Request changes needed!**

---

## 🧪 Testing

**1,489 tests** (7,704 assertions) - ~68s duration

```bash
docker compose exec php php artisan test                    # All tests
docker compose exec php php artisan test --filter=Api       # API tests
docker compose exec php php artisan test --filter=Importer  # Importers
```

### Test Output Logging

**Always capture test output:**

```bash
# Run with logging (recommended)
docker compose exec php php artisan test 2>&1 | tee tests/results/test-output.log

# Check failures
grep -E "(FAIL|FAILED)" tests/results/test-output.log
```

**Note:** `tests/results/` is gitignored. Create with `mkdir -p tests/results`

---

## 📥 XML Import System

### One-Command Import
```bash
docker compose exec php php artisan import:all                   # Everything
docker compose exec php php artisan import:all --skip-migrate    # Keep DB
docker compose exec php php artisan import:all --only=spells     # Specific type
```

### Individual Importers (9 Available)
```bash
php artisan import:all                         # Master command
php artisan import:classes <file>              # IMPORT FIRST!
php artisan import:spells <file>               # Main definitions
php artisan import:spell-class-mappings <file> # Additive mappings
php artisan import:races <file>
php artisan import:items <file>
php artisan import:backgrounds <file>
php artisan import:feats <file>
php artisan import:monsters <file>
```

**⚠️ Import Order:** Items → Classes → Spells → Spell Class Mappings → Others

**Why this order?**
- **Items first:** Classes and Backgrounds reference items for equipment. Items must exist before class/background import so `matchItemByDescription()` can link equipment to actual Item records.
- **Classes before Spells:** Spells reference classes for spell lists. Classes must exist before spell import.

**Additive Spell Files:** Files like `spells-phb+dmg.xml` contain ONLY `<name>` and `<classes>` - they add subclass associations to existing spells.

### Reusable Traits (23)

**Importer Traits (18):**
- **Core:** `CachesLookupTables`, `GeneratesSlugs`
- **Sources:** `ImportsSources`
- **Relationships:** `ImportsTraits`, `ImportsProficiencies`, `ImportsLanguages`, `ImportsConditions`, `ImportsModifiers`, `ImportsEntitySpells`, `ImportsEntityItems`
- **Classes:** `ImportsClassAssociations`
- **Prerequisites:** `ImportsPrerequisites`
- **Random Tables:** `ImportsRandomTables`, `ImportsRandomTablesFromText`
- **Combat:** `ImportsSavingThrows`, `ImportsArmorModifiers`

**Parser Traits (5):**
- `ParsesSourceCitations`, `ParsesTraits`, `ParsesRolls`, `MatchesProficiencyTypes`, `MatchesLanguages`, `MapsAbilityCodes`

**Benefits:** ~360 lines eliminated, consistent behavior, single source of truth

### Strategy Pattern (4 of 9 Importers)

**ItemImporter (5 strategies):** Charged, Scroll, Potion, Tattoo, Legendary

**MonsterImporter (12 strategies):** Beast, Elemental, Shapechanger, Aberration, Fiend, Celestial, Construct, Dragon, Spellcaster, Undead, Swarm, Default

**RaceImporter (3 strategies):** BaseRace, Subrace, RacialVariant

**ClassImporter (2 strategies):** BaseClass, Subclass

**Benefits:** 50-150 lines each, isolated testing, 85%+ coverage, structured logging

---

## 📚 Code Architecture

### Form Request Pattern
```php
// Every action has dedicated Request
public function index(SpellIndexRequest $request) { }
public function show(SpellShowRequest $request, Spell $spell) { }
```

### Service Layer Pattern
```php
// Controllers delegate to services
public function index(Request $request, SpellSearchService $service) {
    $dto = SpellSearchDTO::fromRequest($request);
    $spells = $service->searchWithMeilisearch($dto, $meilisearch);
    return SpellResource::collection($spells);
}
```

### Resource Pattern
```php
// Consistent API serialization
class SpellResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
```

---

## 🗂️ Factories & Seeders

**32 Model Factories:** All entities support factory-based test data

**Polymorphic Factory Pattern:**
```php
CharacterTrait::factory()->forEntity(Race::class, $race->id)->create();
EntitySource::factory()->forEntity(Spell::class, $spell->id)->fromSource('PHB')->create();
```

**12 Database Seeders:** Sources, spell schools, damage types, conditions, proficiencies (82), languages (30), sizes, ability scores, skills, item types, character classes

**Run:** `docker compose exec php php artisan db:seed`

---

## Git Workflow

**⚠️ IMPORTANT:** This project does NOT use git worktrees

### Commit Message Convention
```
feat: add universal tag support
fix: correct damage type parsing
refactor: extract ImportsSources trait
test: add tag integration tests
docs: update session handover
perf: add Redis caching

🤖 Generated with [Claude Code](https://claude.com/claude-code)
Co-Authored-By: Claude <noreply@anthropic.com>
```

### Creating Pull Requests
```bash
# Check diff
git log origin/main..HEAD --oneline
git diff origin/main...HEAD

# Push and create PR
git push -u origin feature/your-branch
gh pr create --title "Title" --body "$(cat <<'EOF'
## Summary
- Feature 1
- Feature 2

## Test Plan
- [ ] All tests passing

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## 🎯 Post-Feature Checklist

After completing ANY feature:

**Required:**
- [ ] All tests passing (1,489+ tests)
- [ ] Code formatted with Pint
- [ ] **CHANGELOG.md updated** under `[Unreleased]`
- [ ] **Push to remote** (`git push`)

**For new/modified data:**
- [ ] API Resources expose new data
- [ ] Form Requests validate new parameters
- [ ] Controllers eager-load new relationships

**For major features:**
- [ ] Session handover document created

**Why this matters:** CHANGELOG = release notes. Pushing = backup + team visibility.

---

**Branch:** `main` | **Status:** ✅ Production-Ready | **Tests:** 1,489 passing
- do not unnecessary run the test suite - if you see from the timestamp of our test log, that the test is relatively fresh - use that data to analyze. if you're not sure about it, ask the user