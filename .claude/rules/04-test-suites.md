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
just test-pure

# Standard validation (run each suite)
just test-unit
just test-feature

# Search tests (requires Meilisearch with fixture data)
just test-search

# Run specific test file
just test-file tests/Feature/Api/SpellApiTest.php

# Run with coverage
just test-coverage-min 80
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
just test-wizard --count=10 --chaos
```
