# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 (prod) / SQLite (tests) | Pest 3.x | Docker Compose | Meilisearch | Redis

**Commands:** `docker compose exec php php artisan ...` | `docker compose exec php ./vendor/bin/pint`

**Essential Docs:**
- `docs/PROJECT-STATUS.md` - Metrics and current status

**Tasks & Issues:** [GitHub Issues](https://github.com/dfox288/ledger-of-heroes/issues) (shared with frontend)

---

## Documentation Locations

**All documentation (plans, handovers, proposals, reference) lives in the wrapper repo:**

```
../wrapper/docs/backend/
├── handovers/   # Session handovers
├── plans/       # Implementation plans
├── proposals/   # API enhancement proposals
├── reference/   # Stable reference docs (XML-SOURCE-PATHS.md, etc.)
├── archive/     # Old handovers
└── DND-FEATURES.md  # D&D feature roadmap
```

| Doc Type | Write To |
|----------|----------|
| **Plans** | `../wrapper/docs/backend/plans/YYYY-MM-DD-topic-design.md` |
| **Handovers** | `../wrapper/docs/backend/handovers/SESSION-HANDOVER-YYYY-MM-DD-topic.md` |
| **Proposals** | `../wrapper/docs/backend/proposals/` |
| **Reference** | `../wrapper/docs/backend/reference/` |

**Stays local:** `docs/PROJECT-STATUS.md`, `docs/README.md`

---

## Cross-Project Coordination

Use GitHub Issues in `dfox288/ledger-of-heroes` for bugs, API issues, and cross-cutting concerns.

### Session Start Checklist

**Do these in order at the start of every session:**

```bash
# 1. Check for handoffs from frontend
echo "=== Checking Handoffs ===" && grep -A 100 "## For: backend" ../wrapper/.claude/handoffs.md 2>/dev/null | head -50 || echo "No backend handoffs pending"

# 2. Check GitHub issues assigned to backend
echo "=== GitHub Issues ===" && gh issue list --repo dfox288/ledger-of-heroes --label "backend" --state open
```

If there's a handoff for you:
1. Read the full context in `../wrapper/.claude/handoffs.md`
2. The handoff contains decisions, API contracts, and reproduction steps you need
3. After absorbing the context, delete that handoff section from the file
4. Start work on the related issue

### Create an Issue

```bash
# IMPORTANT: Use single-line commands (backslash continuations break auto-approval)
gh issue create --repo dfox288/ledger-of-heroes --title "Brief description" --label "frontend,bug,from:backend" --body "Details here"
```

### Labels to Use

- **Assignee:** `frontend`, `backend`, `both`
- **Type:** `bug`, `feature`, `api-contract`, `data-issue`, `performance`
- **Source:** `from:frontend`, `from:backend`, `from:manual-testing`

### Write Handoffs (when creating frontend work)

**After creating an issue that requires frontend work, ALWAYS write a handoff.**

The handoff provides context that GitHub issues can't capture: the exact API response shape, filter syntax, test commands, and implementation decisions.

Append to `../wrapper/.claude/handoffs.md`:

```markdown
## For: frontend
**From:** backend | **Issue:** #NUMBER | **Created:** YYYY-MM-DD HH:MM

[Brief description of what was implemented]

**What I did:**
- [Key endpoints/models/services added]
- [Important implementation decisions]

**What frontend needs to do:**
- [Specific UI components needed]
- [Pages to create]
- [Filters to implement]

**API contract:**
- Endpoint: `GET /api/v1/endpoint`
- Filters: `field`, `other_field` (boolean), `array_field` (IN)
- Response shape:
```json
{
  "data": [{ "id": 1, "name": "Example", "slug": "example" }],
  "meta": { "total": 100, "per_page": 24 }
}
```

**Test with:**
```bash
curl "http://localhost:8080/api/v1/endpoint?filter=field=value"
```

**Related:**
- Follows from: #ORIGINAL_ISSUE
- See also: `app/Http/Controllers/Api/ExampleController.php`

---
```

**Key details to include:**
- Exact filterable fields and their types (string, boolean, IN array)
- Response shape with actual field names
- Working curl command for testing
- Any gotchas or edge cases discovered during implementation

### Close When Fixed

Issues close automatically when PR merges if the PR body contains `Closes #N`. For manual closure:

```bash
gh issue close 42 --repo dfox288/ledger-of-heroes --comment "Fixed in PR #123"
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

### Bug Fix Workflow (When Tests Already Exist)

When fixing bugs in code that has existing tests:

```
1. Reproduce the bug      → Understand what's broken
2. Check existing tests   → Do they test the buggy behavior?
3. UPDATE TESTS FIRST     → Write/modify tests for CORRECT behavior
4. Watch tests FAIL       → Confirms test catches the bug
5. Fix the code           → Make tests pass
6. Run test suite         → Verify no regressions
7. Commit + Push          → Include "fix:" prefix
```

**Critical:** When fixing bugs, do NOT just make existing tests pass. If tests pass with buggy code, the tests are wrong. Update tests to verify the correct behavior BEFORE fixing the code.

**Anti-pattern to avoid:**
- ❌ Code doesn't match tests → Rewrite code to match tests
- ❌ Tests pass but behavior is wrong → "Tests pass, ship it"
- ✅ Code doesn't match tests → Determine correct behavior → Update tests → Fix code

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
- Create session handover in `../wrapper/docs/backend/handovers/`

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

### Pest Syntax

```php
// Pest uses a functional syntax (not PHPUnit classes)
it('creates a record', function () {
    $record = Record::factory()->create();
    expect($record)->toBeInstanceOf(Record::class);
});

test('user can view spells', function () {
    $response = $this->getJson('/api/v1/spells');
    $response->assertOk();
});

// Group related tests
describe('spell filtering', function () {
    it('filters by level', function () { /* ... */ });
    it('filters by school', function () { /* ... */ });
});
```

### Pest Expectations

```php
expect($value)->toBe($expected);           // Strict equality
expect($value)->toEqual($expected);        // Loose equality
expect($collection)->toHaveCount(5);
expect($response)->toBeInstanceOf(Response::class);
expect($array)->toContain('item');
expect($string)->toMatch('/pattern/');
```

### Running with Coverage

**Prerequisites:** XDebug 3.0+ or PCOV must be installed.

```bash
# Basic coverage report
docker compose exec php ./vendor/bin/pest --coverage

# Enforce minimum coverage threshold
docker compose exec php ./vendor/bin/pest --coverage --min=80

# Generate HTML coverage report
docker compose exec php ./vendor/bin/pest --coverage-html=coverage/html

# Generate Clover XML (for CI tools)
docker compose exec php ./vendor/bin/pest --coverage-clover=coverage/clover.xml
```

**Exclude untestable code:**
```php
// @codeCoverageIgnoreStart
// untestable code here
// @codeCoverageIgnoreEnd
```

---

## Test Suites

**Tests use SQLite in-memory for speed.** Always run suites individually (not combined).

| Suite | Time | Tests | When to Use |
|-------|------|-------|-------------|
| `Unit-Pure` | ~12s | 741 | Parser changes, exceptions, pure logic |
| `Unit-DB` | ~26s | 1030 | Factories, models, strategies, caching |
| `Feature-DB` | ~19s | 516 | API endpoints (no search), requests |
| `Feature-Search` | ~38s | 510 | All search/filter tests with fixture data |
| `Importers` | ~12s | 269 | XML import command tests |

```bash
# Quick feedback during development
docker compose exec php ./vendor/bin/pest --testsuite=Unit-Pure

# Standard validation (run each suite)
docker compose exec php ./vendor/bin/pest --testsuite=Unit-DB
docker compose exec php ./vendor/bin/pest --testsuite=Feature-DB

# Search tests (requires Meilisearch with fixture data)
docker compose exec php ./vendor/bin/pest --testsuite=Feature-Search

# Run specific test file
docker compose exec php ./vendor/bin/pest tests/Feature/Api/SpellApiTest.php

# Run with coverage
docker compose exec php ./vendor/bin/pest --coverage --min=80
```

**Note:** Run suites individually, not combined. Cross-suite runs may have data isolation issues.

---

## Wizard Flow Chaos Testing

**Purpose:** Simulates the frontend character wizard to find bugs, especially in **switch/backtrack** scenarios where users change race/class/background mid-flow.

**Location:** `app/Services/WizardFlowTesting/` | `app/Console/Commands/TestWizardFlowCommand.php`

**GitHub Issue:** #443

### Quick Start

```bash
# Run 10 linear flows (no switches)
docker compose exec php php artisan test:wizard-flow --count=10

# Enable chaos mode (random switches)
docker compose exec php php artisan test:wizard-flow --count=10 --chaos

# Specific switch pattern (reproducible)
docker compose exec php php artisan test:wizard-flow --switches=race,background,race

# With seed for reproducibility
docker compose exec php php artisan test:wizard-flow --chaos --seed=12345

# View previous reports
docker compose exec php php artisan test:wizard-flow --list-reports
docker compose exec php php artisan test:wizard-flow --show-report=<run_id>
```

### What It Tests

| Mode | Description |
|------|-------------|
| Linear | Full wizard flow without switches (baseline) |
| Chaos | Random switches inserted at random points |
| Parameterized | Specific switch sequence for reproducing bugs |

### Switch Validation

After each switch, validates cascade behavior:
- **Race switch:** Racial spells/features/languages cleared, speed/size updated
- **Background switch:** Background features/proficiencies cleared
- **Class switch:** Class spells/features cleared, hit die updated

### Reports

JSON reports saved to `storage/wizard-flow-reports/` with:
- Full snapshots before/after switches
- Failure patterns identified
- Seed for reproducibility

### Known Limitations

- Equipment category choices (e.g., "any martial weapon") are skipped
- Some race/background combos have data issues (language count mismatches)

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
- See `../wrapper/docs/backend/reference/MEILISEARCH-FILTERS.md` for syntax

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

**See:** `../wrapper/docs/backend/reference/XML-SOURCE-PATHS.md` for complete documentation

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

# Create PR when ready
gh pr create --title "feat: Add feature" --body "Closes #42"
```

**NEVER use Claude or Anthropic bylines** in commits, PRs, or GitHub issues. No `Co-Authored-By: Claude`, no `Generated with Claude Code`, no AI attribution.

---

## Local Documentation

```
docs/
├── PROJECT-STATUS.md      # Metrics (single source of truth)
└── README.md              # Points to wrapper for all other docs

# All other docs in: ../wrapper/docs/backend/
```

**Tasks:** Use [GitHub Issues](https://github.com/dfox288/ledger-of-heroes/issues) for all task tracking.

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
- **gh commands:** Use single-line format (backslash `\` continuations break auto-approval)

---

**Default Branch:** `main` | **Workflow:** Feature branches → PR → Merge | **Status:** See `docs/PROJECT-STATUS.md`
