# Test Suites

**Tests use SQLite in-memory for speed.** Always run suites individually (not combined).

## Suite Overview

| Suite | When to Use |
|-------|-------------|
| `Unit-Pure` | Parser changes, exceptions, pure logic (fastest) |
| `Unit-DB` | Factories, models, strategies, caching |
| `Feature-DB` | API endpoints (no search), requests |
| `Feature-Search` | All search/filter tests with fixture data |
| `Importers` | XML import command tests |

**Current test counts:** See `docs/PROJECT-STATUS.md`

## Running Tests

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

## Suite Selection Guide

| Change Type | Suite to Run |
|-------------|--------------|
| XML parser logic | `Unit-Pure` |
| Model relationships | `Unit-DB` |
| Factory definitions | `Unit-DB` |
| API endpoint behavior | `Feature-DB` |
| Search/filter results | `Feature-Search` |
| Import commands | `Importers` |

## Specialized Testing

### Wizard Flow Chaos Testing

For testing character creation wizard with switch/backtrack scenarios, see:
`../wrapper/docs/backend/reference/WIZARD-FLOW-TESTING.md`

```bash
# Quick check
docker compose exec php php artisan test:wizard-flow --count=10 --chaos
```
