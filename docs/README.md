# Documentation

**Most documentation has moved to the wrapper project.**

See: `../wrapper/docs/backend/`

## What's Here

| Document | Purpose |
|----------|---------|
| **[PROJECT-STATUS.md](PROJECT-STATUS.md)** | Project metrics and current status |
| **[LATEST-HANDOVER.md](LATEST-HANDOVER.md)** | Symlink to latest session handover |

## Writing New Docs

All new plans, handovers, proposals, and reference docs go to the wrapper repo:

```bash
# Plans
../wrapper/docs/backend/plans/YYYY-MM-DD-topic-design.md

# Handovers
../wrapper/docs/backend/handovers/SESSION-HANDOVER-YYYY-MM-DD-topic.md

# After writing a handover, update the symlink:
ln -sf ../../wrapper/docs/backend/handovers/SESSION-HANDOVER-YYYY-MM-DD-topic.md LATEST-HANDOVER.md
```

## Why Centralized?

Moving documentation to the wrapper project:
- Makes cross-cutting docs easier to find
- Reduces duplication between frontend/backend
- Keeps this repo focused on code
- Consolidates GitHub Issues + docs in same repo

See [../CLAUDE.md](../CLAUDE.md) for full documentation workflow.
