# Backend Project Status

**Last updated:** 2026-04-18
**Branch:** `main`
**Scope:** Numbers in this file reflect the working tree of `dfox288/ledger-of-heroes-backend` on the date above. Regenerate whenever counts drift.

---

## At a Glance

| Metric | Value |
|--------|-------|
| Eloquent models | 63 (+ 15 trait/concern files under `app/Models/Concerns/`) |
| API controllers | 59 (under `app/Http/Controllers/Api/`) |
| API Resources | 114 |
| Form Requests | 75 |
| Services (top-level) | 43 (under `app/Services/`, excludes `Importers/` and `Parsers/` subdirectories) |
| Migrations | 56 |
| Factories | 51 |
| Seeders | 26 |
| Test files (`*Test.php`) | 474 (484 total `.php` files in `tests/`) |
| PHPUnit test suites | 6 (Unit-Pure, Unit-DB, Feature-DB, Feature-Search, Importers, Health-Check) |
| Skipped / incomplete test markers | ~137 grep hits (combined `markTestSkipped`, `markTestIncomplete`, `->skip(`) — investigate before trusting suite-green as full green |

These counts were taken from the filesystem directly (`find`/`ls` on the relevant directories). Per-suite test and assertion counts are intentionally omitted here — run the suites locally (`just test-pure`, etc.) for authoritative numbers; prior snapshots in this file disagreed by hundreds of tests.

---

## Tech Stack Versions

Source of truth: `composer.json`.

| Component | Version |
|-----------|---------|
| PHP | `^8.2` required, 8.4 in dev/CI |
| Laravel Framework | `^12.0` |
| Laravel Sanctum | `^4.2` |
| Laravel Scout | `^10.22` |
| Laravel Tinker | `^2.10` |
| Pest | `^3.8` |
| PHPUnit | `^11.5.3` |
| Laravel Pint | `^1.24` |
| Scramble (OpenAPI) | `^0.13.4` |
| Meilisearch PHP client | `^1.16` |
| Spatie Laravel Tags | `^4.10` |
| Spatie Media Library | `*` (unpinned — review) |

Dev services (from docker compose): MySQL 8.0, Redis 7, Meilisearch (current 1.x line), Nginx on `:8080`.

---

## API Coverage

**Base URL:** `/api/v1`

Main entities with full REST surface: Spells, Classes, Monsters, Items, Feats, Backgrounds, Races, Characters (builder).

Character builder sub-resources include: spells, equipment, proficiencies, features (including optional features), conditions, notes, death saves, level-up, ASI/feat choice, export/import, stats.

Lookup endpoints exist for: sources, spell-schools, damage-types, conditions, languages, proficiency-types, skills, item-types, item-properties, sizes, alignments, rarities, ability-scores, monster-types, optional-feature-types, tags. See `/docs/api` (Scramble) for exact paths and schemas.

Global cross-entity search: `/api/v1/search?q=…&types[]=…`.

---

## Search Indexing

Meilisearch indexes are populated via Laravel Scout from the models' `toSearchableArray()` methods; filterable fields are declared in each model's `searchableOptions()`. Service layer extends `AbstractSearchService`; the gold-standard implementation is `SpellSearchService`.

Do not add bespoke query-string filters to controllers — filtering is done via the `filter` parameter using Meilisearch expressions. This is enforced by `.claude/rules/06-search-filtering.md`.

---

## Import System

XML is read directly from the sibling `fightclub_forked` repository (mounted into the php container). The importer globs across 9 source directories configured in `config/import.php` (PHB, DMG, MM, XGE, TCE, VGM, SCAG, ERLW, TWBTW).

Primary entry points:

- `just import-all` — full production import (runs `migrate:fresh` + seeds + every entity)
- `just import-test` — same against the SQLite test DB, required before `just test-search`
- `just import-<entity>` — individual importer recipes exist for sources, spells, classes, races, items, backgrounds, feats, monsters, optional-features, and cantrip linking

Import order is load-bearing: Sources → Items → Classes → Spells → others. See `.claude/rules/10-import-system.md` and `../wrapper/docs/backend/reference/XML-SOURCE-PATHS.md`.

---

## Test Suites

Defined in `phpunit.xml`:

| Suite | Purpose | Needs |
|-------|---------|-------|
| **Unit-Pure** | Parsers, enums, exceptions, resources, pure services | None (no DB, no search) |
| **Unit-DB** | Factories, models, importers, strategies, cache, DB-backed services | SQLite in-memory |
| **Feature-DB** | API endpoints and model feature tests that don't hit Meilisearch | SQLite in-memory |
| **Feature-Search** | Filter/search endpoints | SQLite + Meilisearch + fixture data (`just import-test`) |
| **Importers** | XML import command tests | SQLite in-memory |
| **Health-Check** | Smoke tests | Minimal |

Run individually (`just test-pure`, etc.) — combined runs are not guaranteed to pass due to data-isolation differences between suites.

---

## Fixture Generation Progress

Class/subclass fixture coverage (for wizard flow and level-up testing) is tracked in `.claude/rules/14-fixture-generation.md`. As of that rule file: 4/13 base classes complete. Do not duplicate the table here — update the rule file.

---

## Open Concerns

Carried forward from the audit on 2026-04-18. None of these block day-to-day work, but they're worth noting.

- **Skipped tests.** ~137 grep hits for skip markers in `tests/`. A green suite run is not the same as full coverage; audit periodically.
- **Doc drift.** Prior versions of this file and `README.md` carried three different test counts and three different model counts. If you update counts here, regenerate both in the same change — see the ground-truth commands in the README contribution notes.
- **Unpinned dependency.** `spatie/laravel-medialibrary` is pinned to `*` in `composer.json`. Worth replacing with a constrained version the next time dependencies are touched.
- **Legacy doc references.** Several older handover docs referenced `docs/SEARCH.md`, `docs/MEILISEARCH-FILTERS.md`, `docs/PERFORMANCE-BENCHMARKS.md`, and `docs/recommendations/` — none of those exist here anymore. Canonical versions live under `../wrapper/docs/backend/reference/`.

---

## Further Reading

- Working agreements: `CLAUDE.md` + `.claude/rules/*.md`
- Stable reference docs: `../wrapper/docs/backend/reference/`
- Session handovers: `../wrapper/docs/backend/handovers/`
- In-flight plans: `docs/plans/` (backend-local) and `../wrapper/docs/backend/plans/`
- Auto-generated OpenAPI: `http://localhost:8080/docs/api`
