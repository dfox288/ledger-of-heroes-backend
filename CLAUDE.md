# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Overview

Laravel 12.x application importing D&D 5th Edition XML content and providing a RESTful API.

**Tech Stack:** Laravel 12.x | PHP 8.4 | MySQL 8.0 (prod) / SQLite (tests) | Pest 3.x | Docker Compose | Meilisearch | Redis

**Commands:** Run `just` or `just --list` to see all available commands.

**Essential Docs:**
- `docs/PROJECT-STATUS.md` - Metrics and current status
- [GitHub Issues](https://github.com/dfox288/ledger-of-heroes/issues) - Task tracking (shared with frontend)

## Session Memory (claude-mem)

**CRITICAL:** Use the **current directory name** as the `project` parameter for claude-mem searches.

| Directory | Project Name |
|-----------|--------------|
| `backend` | `backend` |
| `backend-agent-1` | `backend-agent-1` |
| `backend-agent-2` | `backend-agent-2` |

```
mcp__plugin_claude-mem_claude-mem-search__get_recent_context with project: "<directory-name>"
```

Or search for specific topics:
```
mcp__plugin_claude-mem_claude-mem-search__search with project: "<directory-name>" and query: "your topic"
```

This keeps session memory isolated between the main workspace and agent worktrees.

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
| `14-fixture-generation.md` | Class/subclass fixture campaign process |

## Critical Mandates

1. **TDD is non-negotiable** - Write tests FIRST, watch them fail, then implement
2. **Feature branches only** - Never commit directly to main
3. **Meilisearch for filtering** - No Eloquent `whereHas()` in services
4. **No AI attribution** - No Claude/Anthropic bylines in commits/PRs
5. **Single-line commands** - Backslash continuations break auto-approval

## Quick Reference

```bash
# Docker
just up                  # Start services
just down                # Stop services
just shell               # Open container shell

# Testing
just test                # Run all tests
just test-pure           # Unit tests (no DB, fastest)
just test-unit           # Unit tests with DB
just test-feature        # Feature tests (no search)
just test-search         # Search tests (needs fixtures)
just test <filter>       # Run tests matching filter

# Code Quality
just pint                # Format code
just pint-check          # Check formatting (dry run)

# Database
just migrate             # Run migrations
just migrate-fresh       # Fresh migration
just reset               # Fresh + import all data

# Imports
just import-all          # Import all XML data

# Development
just check               # Pre-commit: format + test
just validate            # Full validation: all suites
just artisan <command>   # Run any artisan command

# GitHub & Inbox
just inbox               # Check handoffs + backend + both issues
just issues              # List backend issues
just issue-view <num>    # View specific issue

# Git Workflow
just gs                  # Quick status + recent commits
just branches            # Show branches with tracking
just branch-feature <num> <desc>  # Create feature branch
just branch-fix <num> <desc>      # Create fix branch
just commit <type> <msg> # Quick conventional commit
just push                # Push with upstream
```

**Default Branch:** `main` | **Status:** See `docs/PROJECT-STATUS.md`
