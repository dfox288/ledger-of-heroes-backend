# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 (prod) / SQLite (tests) | PHPUnit 11+ | Docker Compose | Meilisearch | Redis

**Commands:** `docker compose exec php php artisan ...` | `docker compose exec php ./vendor/bin/pint`

**Essential Docs:**
- `docs/PROJECT-STATUS.md` - Metrics and current status
- `docs/LATEST-HANDOVER.md` - Latest session handover
- `docs/reference/XML-SOURCE-PATHS.md` - XML source path mappings

**Tasks & Issues:** [GitHub Issues](https://github.com/dfox288/dnd-rulebook-project/issues) (shared with frontend)

---

## Cross-Project Coordination

Use GitHub Issues in `dfox288/dnd-rulebook-project` for bugs, API issues, and cross-cutting concerns.

### Check Your Inbox (do this at session start)

```bash
gh issue list --repo dfox288/dnd-rulebook-project --label "backend" --state open
```

### Create an Issue

```bash
# When you discover a frontend issue or cross-cutting bug
gh issue create --repo dfox288/dnd-rulebook-project \
  --title "Brief description" \
  --label "frontend,bug,from:backend" \
  --body "Details here"
```

### Labels to Use

- **Assignee:** `frontend`, `backend`, `both`
- **Type:** `bug`, `feature`, `api-contract`, `data-issue`, `performance`
- **Source:** `from:frontend`, `from:backend`, `from:manual-testing`

### Close When Fixed

Issues close automatically when PR merges if the PR body contains `Closes #N`. For manual closure:

```bash
gh issue close 42 --repo dfox288/dnd-rulebook-project --comment "Fixed in PR #123"
```

---

## AI Context (llms.txt)

**Fetch these before starting work:**

- **Laravel 12:** `https://docfork.com/laravel/docs/llms.txt`
- **Meilisearch:** `https://meilisearch.com/llms.txt` (or `llms-full.txt` for complete docs)
- **Scramble:** No llms.txt — use `https://scramble.dedoc.co/`
- **Spatie Tags:** No llms.txt — use `https://spatie.be/docs/laravel-tags/v4`

---

## Development Cycle

### Every Feature/Fix

```
1. Check Laravel skills     → Use Superpower skills if applicable
2. Check GitHub Issues      → gh issue list for assigned tasks
3. Create feature branch    → git checkout -b feature/issue-N-short-description
4. Write tests FIRST        → Watch them fail (TDD mandatory)
5. Write minimal code       → Make tests pass
6. Refactor while green     → Clean up
7. Run test suite           → Smallest relevant suite
8. Format with Pint         → docker compose exec php ./vendor/bin/pint
9. Update CHANGELOG.md      → Under [Unreleased]
10. Commit + Push           → Clear message, push to feature branch
11. Create PR               → gh pr create with issue reference
12. Close GitHub Issue      → Closes automatically via PR merge (or manual close)
```

### Branch Naming Convention

```bash
# Format: feature/issue-{number}-{short-description}
git checkout -b feature/issue-42-entity-spells-relationship
git checkout -b fix/issue-99-filter-syntax-error
git checkout -b chore/issue-13-api-documentation
```

**Prefixes:**
- `feature/` - New functionality
- `fix/` - Bug fixes
- `chore/` - Maintenance, docs, refactoring

### For API Changes (Additional)
- Update Form Requests validation
- Update API Resources
- Eager-load new relationships in Controllers

### For Search/Filter Changes (Additional)
- Add field to model's `toSearchableArray()`
- Add to `searchableOptions()` → `filterableAttributes`
- Re-index: `php artisan scout:import "App\Models\ModelName"`
- Document in Controller PHPDoc

### For Major Features (Additional)
- Create session handover in `docs/handovers/`

---

## TDD Mandate

**THIS IS NON-NEGOTIABLE.**

### Rejection Criteria

Your work will be **REJECTED** if:
- Implementation code written before tests
- Tests skipped ("it's simple")
- Tests promised "later"
- Tests written after implementation
- "Manual testing is enough"

### PHPUnit 11 Syntax

```php
// Use attributes (doc-comments deprecated)
#[\PHPUnit\Framework\Attributes\Test]
public function it_creates_a_record() { }
```

### PHPUnit 11 "Risky" Warnings

PHPUnit 11 tracks error/exception handler changes and marks tests "risky" if handlers aren't properly restored. The Guzzle HTTP client (used by Meilisearch) temporarily modifies these handlers during requests.

**Solution:** `tests/TestCase.php` captures handlers in `setUp()` and restores them in `tearDown()`. This handles ~99% of cases. One or two tests may still show risky warnings due to timing edge cases with Meilisearch - this is acceptable.

**If you see many risky warnings:** Ensure your test extends `Tests\TestCase`, not `PHPUnit\Framework\TestCase`.

---

## Test Suites

**Tests use SQLite in-memory for speed.** Always run suites individually (not combined).

| Suite | Time | Tests | When to Use |
|-------|------|-------|-------------|
| `Unit-Pure` | ~3s | 273 | Parser changes, exceptions, pure logic |
| `Unit-DB` | ~7s | 436 | Factories, models, strategies, caching |
| `Feature-DB` | ~9s | 367 | API endpoints (no search), requests |
| `Feature-Search` | ~20s | 286 | All search/filter tests with fixture data |
| `Importers` | ~30s | ~135 | XML import command tests |

```bash
# Quick feedback during development
docker compose exec php php artisan test --testsuite=Unit-Pure

# Standard validation (run each suite)
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB

# Search tests (requires Meilisearch with fixture data)
docker compose exec php php artisan test --testsuite=Feature-Search
```

**Note:** Run suites individually, not combined. Cross-suite runs may have data isolation issues.

---

## Gold Standards

**Use these as reference implementations:**

| Pattern | Reference |
|---------|-----------|
| Controller + PHPDoc | `SpellController` |
| API Resource | `SpellResource` |
| Form Request | `SpellIndexRequest`, `SpellShowRequest` |
| Search Service | `SpellSearchService` |
| Model searchable | `Spell::searchableOptions()` |
| Importer | `SpellImporter` |
| Parser | `SpellXmlParser` |

---

## Search & Filtering Architecture

**Use Meilisearch for ALL filtering** - no Eloquent `whereHas()` in Services.

```bash
# CORRECT - Meilisearch filter syntax
GET /api/v1/spells?filter=class_slugs IN [bard] AND level <= 3

# WRONG - Don't add custom parameters
GET /api/v1/spells?classes=bard
```

- Filterable fields defined in model's `searchableOptions()`
- Data indexed via `toSearchableArray()`
- See `docs/reference/MEILISEARCH-FILTERS.md` for syntax

---

## XML Import Setup

**XML files are read directly from the fightclub_forked repository** (mounted at `/var/www/fightclub_forked`).

```bash
# Production - one command (reads from fightclub_forked)
docker compose exec php php artisan import:all

# Test DB (required for search tests)
docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:all --env=testing
```

**Import order matters:** Sources → Items → Classes → Spells → Others

### Source Configuration

The importer reads from 9 source directories configured in `config/import.php`:

| Source | Path |
|--------|------|
| PHB | `01_Core/01_Players_Handbook/` |
| DMG | `01_Core/02_Dungeon_Masters_Guide/` |
| MM | `01_Core/03_Monster_Manual/` |
| XGE | `02_Supplements/Xanathars_Guide_to_Everything/` |
| TCE | `02_Supplements/Tashas_Cauldron_of_Everything/` |
| VGM | `02_Supplements/Volos_Guide_to_Monsters/` |
| SCAG | `03_Campaign_Settings/Sword_Coast_Adventurers_Guide/` |
| ERLW | `03_Campaign_Settings/Eberron_Rising_From_the_Last_War/` |
| TWBTW | `05_Adventures/The_Wild_Beyond_the_Witchlight/` |

**Env variable:** `XML_SOURCE_PATH=/var/www/fightclub_forked/Sources/PHB2014/WizardsOfTheCoast`

**See:** `docs/reference/XML-SOURCE-PATHS.md` for complete documentation

---

## Code Patterns

```php
// Form Request - every action has dedicated Request
public function index(SpellIndexRequest $request) { }

// Service Layer - controllers delegate to services
$results = $service->searchWithMeilisearch($dto);
return SpellResource::collection($results);

// Custom Exceptions - service throws, controller returns single Resource
throw new InvalidFilterSyntaxException($filter, $message);  // 422
throw new EntityNotFoundException($type, $id);              // 404
```

### Form Request Naming: `{Entity}{Action}Request`

```php
SpellIndexRequest      // GET /api/v1/spells
SpellShowRequest       // GET /api/v1/spells/{id}
```

---

## API Endpoints

**Base:** `/api/v1`

| Entities | Lookups (under `/lookups/`) |
|----------|----------------------------|
| `/spells`, `/monsters`, `/classes` | `/sources`, `/spell-schools`, `/damage-types` |
| `/races`, `/items`, `/backgrounds` | `/conditions`, `/languages`, `/skills` |
| `/feats`, `/search` | `/tags`, `/monster-types`, `/rarities` |

**OpenAPI Docs:** `http://localhost:8080/docs/api`

---

## Repository Structure

```
app/
  ├── Http/Controllers/Api/    # Entity + lookup controllers
  ├── Http/Resources/          # API Resources
  ├── Http/Requests/           # Form Requests
  ├── Models/
  └── Services/
      ├── Importers/           # 19 traits, strategy pattern
      └── Parsers/             # 16 traits

tests/
  ├── Feature/                 # API, importers, models
  └── Unit/                    # Parsers, services
```

---

## Table Naming Conventions

Three patterns are used for database tables:

| Pattern | Example Tables | Convention |
|---------|----------------|------------|
| **Standard Laravel** | `class_spells`, `item_property` | Alphabetical pivot naming |
| **Polymorphic** | `entity_sources`, `entity_spells` | `entity_*` prefix for MorphMany/MorphToMany |
| **HasMany Children** | `monster_actions`, `class_features` | `parent_children` naming |

**Models with Custom Table Names:**

| Model | Table | Reason |
|-------|-------|--------|
| `CharacterClass` | `classes` | "Class" is PHP reserved word |
| `Proficiency` | `entity_proficiencies` | Polymorphic consistency |
| `Modifier` | `entity_modifiers` | Polymorphic consistency |
| `CharacterTrait` | `entity_traits` | Polymorphic consistency |

**Polymorphic Tables and Their Entity Types:**

| Table | Used By |
|-------|---------|
| `entity_sources` | Background, CharacterClass, Feat, Item, OptionalFeature, Race, Spell |
| `entity_spells` | Feat, Monster, Race |
| `entity_conditions` | Feat, Race |
| `entity_languages` | Background, Race |
| `entity_senses` | Monster, Race |
| `entity_proficiencies` | Background, CharacterClass, Feat, Item, Race |
| `entity_modifiers` | CharacterClass, Feat, Item, Monster, Race |
| `entity_prerequisites` | Feat, Item, OptionalFeature |
| `entity_items` | Background, CharacterClass |
| `entity_traits` | Background, CharacterClass, Race |
| `entity_data_tables` | CharacterTrait, ClassFeature, Item, Spell |

---

## Git Workflow

**Feature branches required** - never commit directly to main.

```bash
# Branch naming
git checkout -b feature/issue-42-entity-spells
git checkout -b fix/issue-99-filter-bug
git checkout -b chore/issue-13-docs-update

# Commit convention
feat|fix|refactor|test|docs|perf: description

# Always include in commit message
🤖 Generated with [Claude Code](https://claude.com/claude-code)
Co-Authored-By: Claude <noreply@anthropic.com>

# Create PR when ready
gh pr create --title "feat: Add feature" --body "Closes #42"
```

---

## Documentation

```
docs/
├── PROJECT-STATUS.md      # Metrics (single source of truth)
├── LATEST-HANDOVER.md     # Symlink to recent handover
├── reference/             # Stable docs (search, filters, API)
├── plans/                 # Active plans only
├── handovers/             # Recent (last 7 days)
└── archive/               # Historical by month
```

**Tasks:** Use [GitHub Issues](https://github.com/dfox288/dnd-rulebook-project/issues) for all task tracking.

**Naming:** `SESSION-HANDOVER-YYYY-MM-DD-HHMM-topic.md`

Run `/organize-docs` to archive old handovers.

---

## Success Checklist

Before creating a PR:

- [ ] Working on feature branch (`feature/issue-N-*`)
- [ ] Tests written FIRST (TDD mandate)
- [ ] All tests pass (relevant suite)
- [ ] Full suite passes (pre-commit)
- [ ] Code formatted with Pint
- [ ] CHANGELOG.md updated
- [ ] Commits pushed to feature branch
- [ ] **PR created with issue reference** (`Closes #N`)

**For API changes:**
- [ ] Form Requests validate new parameters
- [ ] API Resources expose new data
- [ ] Controller PHPDoc documents filters

**If ANY checkbox is unchecked, work is NOT done.**

---

## Agent Instructions

- **Test output:** Reuse `tests/results/test-output.log` if recent
- **Suite selection:** Always run smallest relevant suite
- **Metrics:** See `docs/PROJECT-STATUS.md` for counts
- **Tasks:** Use `/issue:inbox` to check assigned issues
- **New issues:** Use `/issue:new` to create issues for bugs/features
- **Docs cleanup:** Run `/organize-docs` periodically
- **Gold standards:** Reference `Spell*` classes for patterns

---

**Default Branch:** `main` | **Workflow:** Feature branches → PR → Merge | **Status:** See `docs/PROJECT-STATUS.md`
