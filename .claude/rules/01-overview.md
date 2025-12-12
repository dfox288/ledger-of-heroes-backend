# Project Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

## Tech Stack

- **Framework:** Laravel 12.x
- **PHP:** 8.4
- **Database:** MySQL 8.0 (prod) / SQLite (tests)
- **Testing:** Pest 3.x
- **Container:** Docker Compose
- **Search:** Meilisearch
- **Cache:** Redis

## Essential Docs

- `docs/PROJECT-STATUS.md` - Metrics, test counts, current status
- [GitHub Issues](https://github.com/dfox288/ledger-of-heroes/issues) - Task tracking (shared with frontend)

## Documentation Locations

All documentation lives in the wrapper repo:

```
../wrapper/docs/backend/
├── handovers/   # Session handovers
├── plans/       # Implementation plans
├── proposals/   # API enhancement proposals
├── reference/   # Stable reference docs
├── archive/     # Old handovers
└── DND-FEATURES.md  # D&D feature roadmap
```

| Doc Type | Write To |
|----------|----------|
| Plans | `../wrapper/docs/backend/plans/YYYY-MM-DD-topic-design.md` |
| Handovers | `../wrapper/docs/backend/handovers/SESSION-HANDOVER-YYYY-MM-DD-topic.md` |
| Proposals | `../wrapper/docs/backend/proposals/` |
| Reference | `../wrapper/docs/backend/reference/` |

**Stays local:** `docs/PROJECT-STATUS.md`, `docs/README.md`

## AI Context (llms.txt)

Fetch before starting work:

- **Laravel 12:** `https://docfork.com/laravel/docs/llms.txt`
- **Meilisearch:** `https://meilisearch.com/llms.txt`
- **Scramble:** `https://scramble.dedoc.co/`
- **Spatie Tags:** `https://spatie.be/docs/laravel-tags/v4`

## Repository Structure

```
app/
  ├── Http/Controllers/Api/    # Entity + lookup controllers
  ├── Http/Resources/          # API Resources
  ├── Http/Requests/           # Form Requests
  ├── Models/
  └── Services/
      ├── Importers/           # Strategy pattern
      └── Parsers/             # XML parsing traits

tests/
  ├── Feature/                 # API, importers, models
  └── Unit/                    # Parsers, services
```

## API Endpoints

**Base:** `/api/v1`

| Entities | Lookups (under `/lookups/`) |
|----------|----------------------------|
| `/spells`, `/monsters`, `/classes` | `/sources`, `/spell-schools`, `/damage-types` |
| `/races`, `/items`, `/backgrounds` | `/conditions`, `/languages`, `/skills` |
| `/feats`, `/search` | `/tags`, `/monster-types`, `/rarities` |

**OpenAPI Docs:** `http://localhost:8080/docs/api`
