# Ledger of Heroes — Backend API

Laravel 12 REST API for D&D 5th Edition content and character building. Imports D&D XML sources into a queryable API with Meilisearch-backed filtering and a full character builder.

For current counts, test totals, and import status see [`docs/PROJECT-STATUS.md`](docs/PROJECT-STATUS.md).

## Tech Stack

- **Framework:** Laravel 12.x
- **Language:** PHP 8.4 (composer requires `^8.2`)
- **Database:** MySQL 8.0 (dev/prod), SQLite in-memory (tests)
- **Cache / Queue:** Redis 7
- **Search:** Meilisearch 1.x via Laravel Scout
- **Auth:** Laravel Sanctum (token-based)
- **Testing:** Pest 3.x on PHPUnit 11.x
- **API Docs:** Scramble (OpenAPI 3)
- **Code Quality:** Laravel Pint
- **Dev env:** Docker Compose; all commands go through `just` recipes

## Quick Start

Prerequisites: Docker Desktop (or Docker Engine + Compose v2), Git, and [`just`](https://github.com/casey/just).

```bash
git clone <repo-url> backend
cd backend
cp .env.example .env
just up                # start docker services (php, nginx, mysql, redis, meilisearch)
just artisan key:generate
just reset             # migrate:fresh + import all XML data
```

`just reset` runs `migrate:fresh` and imports every XML source in the correct order (Sources → Items → Classes → Spells → others). Expect 2–5 minutes on a warm host.

Once up:

- API: `http://localhost:8080/api/v1`
- OpenAPI docs: `http://localhost:8080/docs/api`
- Meilisearch: `http://localhost:7700`

## Command Policy

This project uses `just` as the primary command interface. Always check `just --list` before reaching for raw `docker compose`, `artisan`, `composer`, `git`, or `gh` commands. If a command will be reused, add a recipe.

Frequently used recipes:

```bash
just --list              # See every available recipe
just up / just down      # Start / stop services
just shell               # Open a shell in the php container
just artisan <cmd>       # Any artisan command
just tinker              # Tinker REPL
just migrate             # Run migrations
just migrate-fresh       # Drop + re-migrate (empty DB)
just reset               # Fresh DB + full XML import
just pint                # Format with Pint
just pint-check          # Dry-run formatting
```

## Testing

Tests run against SQLite in-memory. Run suites individually — cross-suite runs have data-isolation edges.

```bash
just test-pure           # Unit-Pure (no DB, fastest)
just test-unit           # Unit-DB (database only)
just test-feature        # Feature-DB (API without search)
just test-search         # Feature-Search (needs Meilisearch + fixtures)
just test-importers      # XML import command tests
just test-health         # Smoke tests

just test                # Full suite
just test-file <path>    # Specific file
just test <filter>       # Name filter
just test-coverage       # Coverage report (requires Xdebug/PCOV)
just check               # pint + pure + unit + feature (pre-commit gate)
just validate            # pure + unit + feature + search (full gate)
```

See `.claude/rules/04-test-suites.md` for which suite to run for each change type.

## Project Structure

```
app/
  Http/
    Controllers/Api/     # Entity + lookup controllers
    Requests/            # Form Requests (one per action)
    Resources/           # API Resources (response shape)
  Models/                # Eloquent models (Concerns/ for traits)
  Services/
    Importers/           # Strategy-based XML importers
    Parsers/             # XML parsers + parser traits
  DTOs/ Events/ Exceptions/

database/
  factories/ migrations/ seeders/

tests/
  Unit/                  # Pure + DB-backed unit tests
  Feature/               # API, model, importer, search tests

config/import.php        # XML source directory map
```

Custom table names and polymorphic conventions are documented in `.claude/rules/07-database-conventions.md`.

## API Overview

**Base URL:** `/api/v1`

Main entity endpoints (all support `?q=`, `?filter=`, `?sort=`, `?per_page=`, `?page=`):

| Entity | Endpoints |
|--------|-----------|
| Spells | `/spells`, `/spells/{id\|slug}` |
| Classes | `/classes`, `/classes/{id\|slug}`, `/classes/{id}/spells` |
| Monsters | `/monsters`, `/monsters/{id\|slug}` |
| Items | `/items`, `/items/{id\|slug}`, `/items/{id}/spells` |
| Feats | `/feats`, `/feats/{id\|slug}` |
| Backgrounds | `/backgrounds`, `/backgrounds/{id\|slug}` |
| Races | `/races`, `/races/{id\|slug}` |
| Characters | `/characters`, `/characters/{id}/…` (spells, equipment, level-up, ASI, notes, …) |
| Global search | `/search?q=…&types[]=spell` |

Lookup tables live under `/api/v1/lookups/` — see the OpenAPI docs at `/docs/api` for the complete list (sources, spell-schools, damage-types, conditions, languages, proficiency-types, skills, item-types, item-properties, sizes, alignments, rarities, ability-scores, monster-types, optional-feature-types, tags).

### Filtering

All filtering happens server-side via Meilisearch expressions on the `filter` query param — **not** via bespoke query parameters in the controller. Example:

```
GET /api/v1/spells?filter=class_slugs IN [bard] AND level <= 3
GET /api/v1/monsters?filter=challenge_rating > 10 AND has_truesight = true
```

Filterable fields are declared in each model's `searchableOptions()`. Full syntax reference and the list of filterable fields per entity live in `../wrapper/docs/backend/reference/MEILISEARCH-FILTERS.md`. See `.claude/rules/06-search-filtering.md` for the policy.

### Response Envelope

Resources use Laravel's JSON envelope (`data`, `meta`, `links`). Conventions — required fields first, relationships via `whenLoaded`, errors as 404/422 with `message` + `errors` — are documented in `.claude/rules/13-api-responses.md`.

## Imports

XML lives in a sibling repo (`fightclub_forked`) mounted into the php container at `/var/www/fightclub_forked`. The importer reads it directly — no file copying.

```bash
just import-all          # Full fresh import (prod DB)
just import-test         # Same, against the test DB (prep for test-search)
just import-spells       # One entity at a time
just import-classes
just import-monsters
# …see `just --list` for the rest
```

Source directory map: `config/import.php`. Reference: `.claude/rules/10-import-system.md` and `../wrapper/docs/backend/reference/XML-SOURCE-PATHS.md`.

## Documentation

| Where | What |
|-------|------|
| `CLAUDE.md` + `.claude/rules/*.md` | Working agreements, code patterns, TDD mandate, conventions |
| `docs/PROJECT-STATUS.md` | Current metrics, test counts, open concerns (local-only) |
| `docs/plans/` | Per-feature design docs still in flight |
| `../wrapper/docs/backend/reference/` | Stable reference: search service architecture, Meilisearch filters, XML source paths, wizard flow testing |
| `../wrapper/docs/backend/handovers/` | Session handovers between agents / contributors |
| `../wrapper/docs/backend/plans/`, `../wrapper/docs/backend/proposals/` | Larger design docs and API proposals |
| `http://localhost:8080/docs/api` | Auto-generated OpenAPI (Scramble) |

## Contributing Workflow

1. Work on a feature branch — never commit to `main`. Naming: `feature/issue-N-slug`, `fix/issue-N-slug`, `chore/issue-N-slug`. See `.claude/rules/08-git-conventions.md`.
2. TDD is non-negotiable: write the failing test first, then implement. See `.claude/rules/03-tdd-mandate.md`.
3. Run the relevant suite (`just test-pure` / `test-unit` / `test-feature` / `test-search`) while iterating, then `just check` before committing.
4. Format with `just pint`.
5. Update `CHANGELOG.md` under `[Unreleased]`.
6. Open a PR against `dfox288/ledger-of-heroes-backend`; issues live in the shared tracker at `dfox288/ledger-of-heroes`.

## License

Educational / personal project. D&D 5e content belongs to Wizards of the Coast; this codebase does not redistribute it — XML sources are consumed from the sibling `fightclub_forked` repository at import time.
