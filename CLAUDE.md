# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 | PHPUnit 11+ | Docker Compose (not Sail) | Meilisearch | Redis

**Commands:** `docker compose exec php php artisan ...` | `docker compose exec php ./vendor/bin/pint`

**Essential Docs:**
- `docs/PROJECT-STATUS.md` - Metrics and current status
- `docs/TODO.md` - Active tasks
- `docs/LATEST-HANDOVER.md` - Latest session handover

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
2. Update docs/TODO.md      → Mark task "in progress"
3. Write tests FIRST        → Watch them fail (TDD mandatory)
4. Write minimal code       → Make tests pass
5. Refactor while green     → Clean up
6. Run test suite           → Smallest relevant suite
7. Format with Pint         → docker compose exec php ./vendor/bin/pint
8. Update CHANGELOG.md      → Under [Unreleased]
9. Commit + Push            → Clear message, push to remote
10. Update docs/TODO.md     → Mark complete
```

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

**Always run the smallest relevant suite.**

| Suite | Time | Tests | When to Use |
|-------|------|-------|-------------|
| `Unit-Pure` | ~5s | Parsers | Parser changes, exceptions, pure logic |
| `Unit-DB` | ~20s | Models | Factories, models, strategies, caching |
| `Feature-DB` | ~30s | API | API endpoints (no search), requests |
| `Feature-Search` | ~120s | Search | All search/filter tests with fixture data |
| `Importers` | ~90s | Import | XML import command tests |
| **Full** | ~400s | All | Pre-commit, release validation |

```bash
# Quick feedback during development
docker compose exec php php artisan test --testsuite=Unit-Pure

# Before commit (run most suites)
docker compose exec php php artisan test --testsuite=Unit-Pure,Unit-DB,Feature-DB

# Full validation
docker compose exec php php artisan test
```

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

## Database Setup

```bash
# Production - one command
docker compose exec php php artisan import:all

# Test DB (required for search tests)
docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:all --env=testing
```

**Import order matters:** Sources → Items → Classes → Spells → Others

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

## Git Workflow

**No worktrees** - work directly on main or feature branches.

```bash
# Commit convention
feat|fix|refactor|test|docs|perf: description

# Always include
🤖 Generated with [Claude Code](https://claude.com/claude-code)
Co-Authored-By: Claude <noreply@anthropic.com>
```

---

## Documentation

```
docs/
├── PROJECT-STATUS.md      # Metrics (single source of truth)
├── TODO.md                # Active tasks
├── LATEST-HANDOVER.md     # Symlink to recent handover
├── TECH-DEBT.md           # Technical debt
├── reference/             # Stable docs (search, filters, API)
├── plans/                 # Active plans only
├── handovers/             # Recent (last 7 days)
└── archive/               # Historical by month
```

**Naming:** `SESSION-HANDOVER-YYYY-MM-DD-HHMM-topic.md`

Run `/organize-docs` to archive old handovers.

---

## Success Checklist

Before marking ANY work complete:

- [ ] Tests written FIRST (TDD mandate)
- [ ] All tests pass (relevant suite)
- [ ] Full suite passes (pre-commit)
- [ ] Code formatted with Pint
- [ ] CHANGELOG.md updated
- [ ] Work committed and pushed
- [ ] TODO.md updated

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
- **Tasks:** Track in `docs/TODO.md`
- **Tech debt:** Add to `docs/TECH-DEBT.md`
- **Docs cleanup:** Run `/organize-docs` periodically
- **Gold standards:** Reference `Spell*` classes for patterns

---

**Branch:** `main` | **Status:** See `docs/PROJECT-STATUS.md`
