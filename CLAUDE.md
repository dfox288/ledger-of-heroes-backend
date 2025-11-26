# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 | PHPUnit 11+ | Docker Compose (not Sail) | Meilisearch | Redis

**Docker Compose Setup:**
- This project uses **Docker Compose directly**, NOT Laravel Sail
- Commands: `docker compose exec php php artisan ...`
- Database: `docker compose exec mysql mysql -uroot -ppassword dnd_compendium`

**Essential Reading:**
- `docs/PROJECT-STATUS.md` - Project metrics and current status
- `docs/DND-FEATURES.md` - D&D 5e game mechanics (tags, AC, saving throws, etc.)
- `docs/LATEST-HANDOVER.md` - Latest session handover

---

## Quick Reference

```bash
# Run tests (choose smallest relevant suite)
docker compose exec php php artisan test --testsuite=Unit-Pure      # ~5s - parsers, pure logic
docker compose exec php php artisan test --testsuite=Unit-DB        # ~20s - models, factories
docker compose exec php php artisan test --testsuite=Feature-DB     # ~30s - API endpoints
docker compose exec php php artisan test                            # Full suite

# Import data
docker compose exec php php artisan import:all

# Format code
docker compose exec php ./vendor/bin/pint

# Configure search indexes
docker compose exec php php artisan search:configure-indexes
```

---

## Development Standards

### 1. Test-Driven Development (Mandatory)

**EVERY feature MUST follow TDD:**
1. Write tests FIRST (watch them fail)
2. Write minimal code to pass
3. Refactor while green
4. Update API Resources/Controllers
5. Run relevant test suite
6. Format with Pint
7. **Update CHANGELOG.md** under `[Unreleased]`
8. Commit with clear message
9. **Push to remote** (`git push`)

**If tests aren't written, the feature ISN'T done.**

**PHPUnit 11 Requirement:**
```php
// Use attributes (doc-comments are deprecated)
#[\PHPUnit\Framework\Attributes\Test]
public function it_creates_a_record() { }
```

### 2. Form Request Naming: `{Entity}{Action}Request`

```php
// CORRECT
SpellIndexRequest      // GET /api/v1/spells
SpellShowRequest       // GET /api/v1/spells/{id}

// WRONG - verb first
IndexSpellRequest
```

**Purpose:** Validation + OpenAPI documentation + Type safety

**Maintenance:** WHENEVER you modify Models/Controllers, update corresponding Request validation rules (filters, sorts, relationships).

### 3. Backwards Compatibility

**NOT important** - Do not waste time on backwards compatibility

### 4. Use Superpower Laravel Skills

**ALWAYS** check for available Laravel skills before starting work

---

## Custom Exceptions

**Pattern: Service throws -> Controller returns Resource (single return)**

```php
// Service throws domain exception
public function search(DTO $dto): Collection {
    throw new InvalidFilterSyntaxException($dto->filter, $e->getMessage());
}

// Controller has single return (Scramble-friendly)
public function index(Request $request, Service $service) {
    $results = $service->search($dto);  // May throw
    return Resource::collection($results);  // Single return
}
```

**Available Exceptions:**
- `InvalidFilterSyntaxException` (422) - Meilisearch filter validation
- `FileNotFoundException` (404) - Missing XML files
- `EntityNotFoundException` (404) - Missing lookup entities

Laravel exception handler auto-renders - no manual error handling in controllers needed.

---

## Database Setup

### Test vs Production Database Isolation

**Two Separate Databases:**
- **Production:** `dnd_compendium` (Meilisearch indexes: `spells`, `items`, etc.)
- **Test:** `dnd_compendium_test` (Meilisearch indexes: `test_spells`, `test_items`, etc.)

**BOTH databases must be imported separately to ensure Meilisearch indexes match database counts!**

### Database Initialization

**Production Database (One-Command):**
```bash
docker compose exec php php artisan import:all

# Options:
--skip-migrate  # Keep existing DB
--only=spells   # Import specific entities
--skip-search   # Skip search config
```

**Test Database (REQUIRED for tests to pass):**
```bash
# CRITICAL: Must use -e SCOUT_PREFIX=test_ for proper index isolation
docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:all --env=testing
```

**Why Both?**
- Tests use `RefreshDatabase` which migrates/seeds `dnd_compendium_test`
- `.env.testing` configures MySQL test DB (NOT SQLite) for database compatibility
- `SCOUT_PREFIX=test_` ensures tests use isolated Meilisearch indexes
- Without proper test DB import, search/filter tests will fail

### Manual Import (Dependency Order)

**CRITICAL ORDER:** Sources -> Items -> Classes -> Spells -> Spell Class Mappings -> Others

```bash
# 1. Fresh database
docker compose exec php php artisan migrate:fresh --seed

# 2. Import sources FIRST (required by ALL other entities)
docker compose exec php bash -c 'for file in import-files/source-*.xml; do php artisan import:sources "$file" || true; done'

# 3. Import items (required by classes/backgrounds for equipment matching)
docker compose exec php bash -c 'for file in import-files/item-*.xml; do php artisan import:items "$file" || true; done'

# 4. Import classes (required by spells)
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file" || true; done'

# 5. Import spells (main files, exclude additive files)
docker compose exec php bash -c 'for file in import-files/spell-*.xml; do [[ ! "$file" =~ \+.*\.xml$ ]] && php artisan import:spells "$file" || true; done'

# 6. Import additive spell class mappings
docker compose exec php bash -c 'for file in import-files/spells-*+*.xml; do php artisan import:spell-class-mappings "$file" || true; done'

# 7. Import other entities
docker compose exec php bash -c 'for file in import-files/race-*.xml; do php artisan import:races "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/background-*.xml; do php artisan import:backgrounds "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/feat-*.xml; do php artisan import:feats "$file" || true; done'
docker compose exec php bash -c 'for file in import-files/bestiary-*.xml; do php artisan import:monsters "$file" || true; done'

# 8. Configure search
docker compose exec php php artisan search:configure-indexes
```

**Why this order?**
- **Sources first:** All entities reference sources via foreign keys
- **Items before Classes/Backgrounds:** Equipment matching requires items to exist
- **Classes before Spells:** Spell class lists reference classes

---

## Repository Structure

```
app/
  ├── Http/
  │   ├── Controllers/Api/     # Entity + lookup controllers
  │   ├── Resources/           # API Resources
  │   └── Requests/            # Form Requests
  ├── Models/
  └── Services/
      ├── Importers/           # Importers + traits + strategies
      └── Parsers/             # XML parsing + traits

database/
  ├── migrations/
  └── seeders/

import-files/                  # XML source files

tests/
  ├── Feature/                 # API, importers, models
  └── Unit/                    # Parsers, services, strategies
```

---

## API Endpoints

**Base:** `/api/v1`

**Entity Endpoints:**
- `GET /spells`, `GET /spells/{id|slug}`
- `GET /monsters`, `GET /monsters/{id|slug}`, `GET /monsters/{id}/spells`
- `GET /classes`, `GET /classes/{id|slug}`, `GET /classes/{id}/spells`
- `GET /races`, `GET /races/{id|slug}`
- `GET /items`, `GET /items/{id|slug}`
- `GET /backgrounds`, `GET /backgrounds/{id|slug}`
- `GET /feats`, `GET /feats/{id|slug}`
- `GET /search?q=term&types=spells,monsters` - Global search

**Lookup Endpoints:** (all under `/api/v1/lookups/`)
- Core: `/sources`, `/spell-schools`, `/damage-types`, `/conditions`, `/proficiency-types`, `/languages`
- Additional: `/sizes`, `/ability-scores`, `/skills`, `/item-types`, `/item-properties`
- Derived: `/tags`, `/monster-types`, `/alignments`, `/armor-types`, `/rarities`

**Features:** Pagination, search, filtering, sorting, CORS, Redis caching, OpenAPI docs

**OpenAPI Docs:** `http://localhost:8080/docs/api` (Scramble)

---

## Search & Filtering Architecture

**CRITICAL: Use Meilisearch for ALL Filtering**

This API uses **Meilisearch** exclusively for search and filtering. Do NOT add Eloquent/Scout filtering logic to Service classes.

**Correct Approach:**
- All filtering happens via the `?filter=` parameter using Meilisearch syntax
- Filterable fields are defined in each model's `searchableOptions()` method
- Data is indexed via `toSearchableArray()` method

**Wrong Approach:**
- Adding custom query parameters like `?classes=bard`
- Writing Eloquent `whereHas()` logic in Service classes
- Creating Form Request validation for filter-specific parameters

**Examples:**
```bash
# CORRECT - Use Meilisearch filter syntax
GET /api/v1/spells?filter=class_slugs IN [bard]
GET /api/v1/spells?filter=class_slugs IN [bard] AND level <= 3
GET /api/v1/spells?filter=tag_slugs IN [fire] AND concentration = true

# WRONG - Don't add custom parameters
GET /api/v1/spells?classes=bard  # This does NOT work
```

**Adding New Filterable Fields:**
1. Add field to model's `toSearchableArray()` method
2. Add field to model's `searchableOptions()` -> `filterableAttributes` array
3. Re-index with `php artisan scout:import "App\Models\ModelName"`
4. Document the field in the Controller PHPDoc

**No Service/DTO/Request changes needed!**

---

## Testing

### Test Suites - Run Only What You Need

The test suite is split into 6 independent suites. **Always run the smallest relevant suite** to save time.

| Suite | Time | Dependencies | When to Use |
|-------|------|--------------|-------------|
| `Unit-Pure` | ~5s | None | Parser changes, exceptions, pure logic |
| `Unit-DB` | ~20s | MySQL | Factories, models, strategies, caching |
| `Feature-DB` | ~30s | MySQL + Seeders | API endpoints (no search), requests, models |
| `Feature-Search-Isolated` | ~60s | MySQL + Meilisearch | Filter tests with factory data |
| `Feature-Search-Imported` | ~180s | MySQL + Meilisearch + Imports | Search tests needing real XML data |
| `Importers` | ~90s | MySQL + XML files | XML import command tests |

**Quick Reference:**

```bash
# Working on parsers, exceptions, or pure logic
docker compose exec php php artisan test --testsuite=Unit-Pure

# Working on models, factories, or services
docker compose exec php php artisan test --testsuite=Unit-DB

# Working on API endpoints without search
docker compose exec php php artisan test --testsuite=Feature-DB

# Working on filter operators with factory data
docker compose exec php php artisan test --testsuite=Feature-Search-Isolated

# Tests needing imported XML data (run import first!)
docker compose exec php php artisan test --testsuite=Feature-Search-Imported

# XML import command tests
docker compose exec php php artisan test --testsuite=Importers

# Full suite (pre-commit validation)
docker compose exec php php artisan test
```

### Which Suite Should Agents Run?

| Working On | Recommended Suite |
|------------|-------------------|
| XML Parser | `Unit-Pure` |
| Model changes | `Unit-DB` |
| New API endpoint | `Feature-DB` |
| Filter operators | `Feature-Search-Isolated` |
| Search behavior | `Feature-Search-Imported` |
| Import command | `Importers` |
| Pre-commit | `--exclude-group=search-imported` |
| Release validation | Full suite |

### Test Helpers for Meilisearch

```php
use Tests\Concerns\WaitsForMeilisearch;   // Intelligent polling (replaces sleep)
use Tests\Concerns\ClearsMeilisearchIndex; // Index cleanup for isolation

class MyTest extends TestCase
{
    use RefreshDatabase, WaitsForMeilisearch, ClearsMeilisearchIndex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearMeilisearchIndex(Spell::class);
    }

    public function test_search(): void
    {
        $spell = Spell::factory()->create();
        $spell->searchable();
        $this->waitForMeilisearch($spell);  // 50-200ms vs 1000ms sleep

        // Now test...
    }
}
```

### Test Output Logging

```bash
# Run with logging (recommended)
docker compose exec php php artisan test 2>&1 | tee tests/results/test-output.log

# Check failures
grep -E "(FAIL|FAILED)" tests/results/test-output.log
```

**Note:** `tests/results/` is gitignored. Create with `mkdir -p tests/results`

---

## XML Import System

### Import Commands

```bash
php artisan import:all                         # Master command (recommended)
php artisan import:sources <file>              # Sources (import FIRST)
php artisan import:items <file>                # Items (before classes/backgrounds)
php artisan import:classes <file>              # Classes (before spells)
php artisan import:spells <file>               # Main spell definitions
php artisan import:spell-class-mappings <file> # Additive class mappings
php artisan import:races <file>
php artisan import:backgrounds <file>
php artisan import:feats <file>
php artisan import:monsters <file>
php artisan import:optional-features <file>
```

**Additive Spell Files:** Files like `spells-phb+dmg.xml` contain ONLY `<name>` and `<classes>` - they add subclass associations to existing spells.

### Reusable Traits

**Importer Traits (19):**
- Core: `CachesLookupTables`, `GeneratesSlugs`
- Sources: `ImportsSources`
- Relationships: `ImportsTraits`, `ImportsProficiencies`, `ImportsLanguages`, `ImportsConditions`, `ImportsModifiers`, `ImportsEntitySpells`, `ImportsEntityItems`
- Classes: `ImportsClassAssociations`
- Prerequisites: `ImportsPrerequisites`
- Random Tables: `ImportsRandomTables`, `ImportsRandomTablesFromText`
- Combat: `ImportsSavingThrows`, `ImportsArmorModifiers`

**Parser Traits (16):**
- `ParsesSourceCitations`, `StripsSourceCitations`, `ParsesTraits`, `ParsesRolls`
- `ParsesModifiers`, `ParsesCharges`, `ParsesSavingThrows`, `ParsesItemSavingThrows`
- `ParsesItemSpells`, `ParsesItemProficiencies`, `ParsesRandomTables`
- `MatchesProficiencyTypes`, `MatchesLanguages`, `MapsAbilityCodes`
- `ConvertsWordNumbers`, `LookupsGameEntities`

### Strategy Pattern

**ItemImporter (5 strategies):** Charged, Scroll, Potion, Tattoo, Legendary

**MonsterImporter (12 strategies):** Beast, Elemental, Shapechanger, Aberration, Fiend, Celestial, Construct, Dragon, Spellcaster, Undead, Swarm, Default

**RaceImporter (3 strategies):** BaseRace, Subrace, RacialVariant

**ClassImporter (2 strategies):** BaseClass, Subclass

---

## Code Architecture

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

## Factories & Seeders

**Polymorphic Factory Pattern:**
```php
CharacterTrait::factory()->forEntity(Race::class, $race->id)->create();
EntitySource::factory()->forEntity(Spell::class, $spell->id)->fromSource('PHB')->create();
```

**Run seeders:** `docker compose exec php php artisan db:seed`

---

## Git Workflow

**This project does NOT use git worktrees**

### Commit Message Convention
```
feat: add universal tag support
fix: correct damage type parsing
refactor: extract ImportsSources trait
test: add tag integration tests
docs: update session handover
perf: add Redis caching

Generated with Claude Code
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

Generated with Claude Code
EOF
)"
```

---

## Post-Feature Checklist

After completing ANY feature:

**Required:**
- [ ] All tests passing
- [ ] Code formatted with Pint
- [ ] **CHANGELOG.md updated** under `[Unreleased]`
- [ ] **Push to remote** (`git push`)

**For new/modified data:**
- [ ] API Resources expose new data
- [ ] Form Requests validate new parameters
- [ ] Controllers eager-load new relationships

**For major features:**
- [ ] Session handover document created

---

## Code Quality

**Tools:**
- **Laravel Pint** - Code formatting (PSR-12 style)
- **PHPUnit 11** - Testing framework

**No static analysis tools configured** (no PHPStan/Larastan/Psalm). Code quality is enforced through:
1. Comprehensive test coverage (TDD mandatory)
2. Pint formatting
3. Code review

```bash
# Format code
docker compose exec php ./vendor/bin/pint

# Check formatting without changes
docker compose exec php ./vendor/bin/pint --test
```

---

## Documentation Structure

```
docs/
├── PROJECT-STATUS.md              # Metrics, milestones (single source of truth)
├── LATEST-HANDOVER.md             # Symlink to most recent handover
├── TODO.md                        # Active tasks and priorities
├── DND-FEATURES.md                # D&D 5e game mechanics reference
├── TECH-DEBT.md                   # Technical debt tracking
├── README.md                      # Simple index (no metrics)
│
├── reference/                     # Stable reference docs
│   ├── SEARCH.md
│   ├── MEILISEARCH-FILTERS.md
│   ├── API-EXAMPLES.md
│   └── PERFORMANCE-BENCHMARKS.md
│
├── plans/                         # Active implementation plans only
│   └── [active-plan].md
│
├── proposals/                     # Feature proposals (pre-plan)
│   └── [proposal].md
│
├── handovers/                     # Recent handovers (last 7 days)
│   └── SESSION-HANDOVER-YYYY-MM-DD-HHMM-topic.md
│
└── archive/                       # Historical docs (by type and month)
    ├── handovers/YYYY-MM/
    ├── plans/YYYY-MM/
    └── analysis/YYYY-MM/
```

**Use `/organize-docs`** to automatically organize the docs folder.

---

## Session Handovers

### When to Create Handovers

Create a handover when:
- Completing a major feature or milestone
- Ending a development session
- Before context would be lost

### Naming Convention

```
SESSION-HANDOVER-YYYY-MM-DD-HHMM-topic.md

Examples:
SESSION-HANDOVER-2025-11-26-1430-classes-detail-optimization.md
SESSION-HANDOVER-2025-11-26-1845-filter-testing-complete.md
```

**Include hour:minute** - multiple sessions may occur per day.

### Handover Workflow

1. **Create handover** in `docs/handovers/`:
   ```bash
   # Use current time in filename
   touch docs/handovers/SESSION-HANDOVER-$(date +%Y-%m-%d-%H%M)-topic.md
   ```

2. **Update symlink**:
   ```bash
   cd docs && rm -f LATEST-HANDOVER.md && ln -s handovers/SESSION-HANDOVER-[latest].md LATEST-HANDOVER.md
   ```

3. **Handover content** should include:
   - What was accomplished
   - Current state (tests passing, blockers)
   - Next steps / recommendations
   - Files changed

### Archiving

Handovers older than 7 days are moved to `docs/archive/handovers/YYYY-MM/` by `/organize-docs`.

---

## Agent Instructions

- **Test output reuse:** Do not unnecessarily run the full test suite. If `tests/results/test-output.log` has a recent timestamp, use that data for analysis. If unsure, ask the user.
- **Suite selection:** Always run the smallest relevant test suite for your changes.
- **Metrics:** See `docs/PROJECT-STATUS.md` for current test counts, model counts, and other metrics.
- **Handovers:** Create session handovers for major work. Use HHMM timestamps.
- **Tech debt:** Add items to `docs/TECH-DEBT.md`, don't create new files.
- **Tasks:** Track active work in `docs/TODO.md`. Update when starting/completing tasks.
- **Documentation:** Run `/organize-docs` periodically to keep docs tidy.

---

**Branch:** `main` | **Status:** See `docs/PROJECT-STATUS.md`
