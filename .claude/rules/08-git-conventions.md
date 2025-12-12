# Git Conventions

**Feature branches required** - never commit directly to main.

## Branch Naming

```bash
git checkout -b feature/issue-42-entity-spells
git checkout -b fix/issue-99-filter-bug
git checkout -b chore/issue-13-docs-update
```

## Commit Convention

```
feat|fix|refactor|test|docs|perf: description
```

## Creating PRs

```bash
gh pr create --title "feat: Add feature" --body "Closes #42"
```

## AI Attribution Policy

**NEVER use Claude or Anthropic bylines** in commits, PRs, or GitHub issues:
- No `Co-Authored-By: Claude`
- No `Generated with Claude Code`
- No AI attribution of any kind

## Command Format

**Use single-line commands** - backslash continuations (`\` at end of line) break auto-approval.

```bash
# CORRECT - single line
gh issue create --repo dfox288/ledger-of-heroes --title "Brief description" --label "frontend,bug" --body "Details"

# WRONG - breaks auto-approval
gh issue create \
  --repo dfox288/ledger-of-heroes \
  --title "Brief description"
```

## Default Branch

`main` - Feature branches merge via PR
