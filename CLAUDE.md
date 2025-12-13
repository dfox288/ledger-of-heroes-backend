# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 (prod) / SQLite (tests) | Pest 3.x | Docker Compose | Meilisearch | Redis

**Commands:** `docker compose exec php php artisan ...` | `docker compose exec php ./vendor/bin/pint`

**Essential Docs:**
- `docs/PROJECT-STATUS.md` - Metrics and current status
- [GitHub Issues](https://github.com/dfox288/ledger-of-heroes/issues) - Task tracking (shared with frontend)

## Session Memory

**IMPORTANT:** At session start, check claude-mem for recent context:

```
mcp__plugin_claude-mem_claude-mem-search__get_recent_context with project: "ledger-of-heroes"
```

Or search for specific topics:
```
mcp__plugin_claude-mem_claude-mem-search__search with project: "ledger-of-heroes" and query: "your topic"
```

This provides continuity between sessions - decisions made, work completed, and context from previous sessions.

## Rules

Detailed instructions are split into `.claude/rules/`:

| Rule | Purpose |
|------|---------|
| `01-overview.md` | Tech stack, docs, API endpoints, structure |
| `02-development-workflow.md` | Feature/fix workflow, PR checklist |
| `03-tdd-mandate.md` | TDD requirements, violation examples, Pest syntax |
| `04-test-suites.md` | Suite descriptions, selection guide |
| `05-code-patterns.md` | Gold standards, anti-patterns |
| `06-search-filtering.md` | Meilisearch architecture |
| `07-database-conventions.md` | Table naming, polymorphic patterns |
| `08-git-conventions.md` | Branch naming, commits, no AI attribution |
| `09-cross-project.md` | Handoffs, GitHub issues, coordination |
| `10-import-system.md` | XML import setup and sources |
| `11-commands-reference.md` | Docker, Artisan, slash commands |
| `12-factories-testing.md` | Factory patterns, make vs create |
| `13-api-responses.md` | Response envelope, pagination, errors |

## Critical Mandates

1. **TDD is non-negotiable** - Write tests FIRST, watch them fail, then implement
2. **Feature branches only** - Never commit directly to main
3. **Meilisearch for filtering** - No Eloquent `whereHas()` in services
4. **No AI attribution** - No Claude/Anthropic bylines in commits/PRs
5. **Single-line commands** - Backslash continuations break auto-approval

## Quick Reference

```bash
# Run tests
docker compose exec php ./vendor/bin/pest --testsuite=Unit-Pure

# Format code
docker compose exec php ./vendor/bin/pint

# Import data
docker compose exec php php artisan import:all

# Check issues
gh issue list --repo dfox288/ledger-of-heroes --label "backend" --state open
```

**Default Branch:** `main` | **Status:** See `docs/PROJECT-STATUS.md`
