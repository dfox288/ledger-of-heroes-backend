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

## Quick Reference

```bash
# Tests (choose smallest relevant suite)
docker compose exec php php artisan test --testsuite=Unit-Pure      # ~5s
docker compose exec php php artisan test --testsuite=Unit-DB        # ~20s
docker compose exec php php artisan test --testsuite=Feature-DB     # ~30s
docker compose exec php php artisan test                            # Full

# Import & Format
docker compose exec php php artisan import:all
docker compose exec php ./vendor/bin/pint
```

| Working On | Test Suite |
|------------|------------|
| Parsers, pure logic | `Unit-Pure` |
| Models, factories | `Unit-DB` |
| API endpoints | `Feature-DB` |
| Filter operators | `Feature-Search-Isolated` |
| Search with real data | `Feature-Search-Imported` |
| Import commands | `Importers` |

---

## Development Standards

### TDD is Mandatory

**If tests aren't written, the feature ISN'T done.**

```php
// PHPUnit 11 - Use attributes (doc-comments deprecated)
#[\PHPUnit\Framework\Attributes\Test]
public function it_creates_a_record() { }
```

### Form Request Naming: `{Entity}{Action}Request`

```php
SpellIndexRequest      // GET /api/v1/spells
SpellShowRequest       // GET /api/v1/spells/{id}
```

Update Request validation whenever Models/Controllers change.

### Other Standards
- **Backwards compatibility:** NOT important - don't waste time on it
- **Laravel skills:** ALWAYS check for available Superpower skills before starting

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

See `docs/reference/` for manual import steps if needed.

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

## API Endpoints

**Base:** `/api/v1`

| Entities | Lookups (under `/lookups/`) |
|----------|----------------------------|
| `/spells`, `/monsters`, `/classes` | `/sources`, `/spell-schools`, `/damage-types` |
| `/races`, `/items`, `/backgrounds` | `/conditions`, `/languages`, `/skills` |
| `/feats`, `/search` | `/tags`, `/monster-types`, `/rarities` |

**OpenAPI Docs:** `http://localhost:8080/docs/api`

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

### Session Handovers

**Naming:** `SESSION-HANDOVER-YYYY-MM-DD-HHMM-topic.md`

Create when completing major features. Run `/organize-docs` to archive old ones.

---

## Agent Instructions

- **Test output:** Reuse `tests/results/test-output.log` if recent
- **Suite selection:** Always run smallest relevant suite
- **Metrics:** See `docs/PROJECT-STATUS.md` for counts
- **Tasks:** Track in `docs/TODO.md`
- **Tech debt:** Add to `docs/TECH-DEBT.md`
- **Docs cleanup:** Run `/organize-docs` periodically

---

**Branch:** `main` | **Status:** See `docs/PROJECT-STATUS.md`
