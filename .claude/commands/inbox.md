---
description: Check backend inbox for handoffs and assigned GitHub issues
---

# Check Backend Inbox

Run `just inbox` to check for pending work (handoffs + GitHub issues).

```bash
just inbox
```

## After checking:

1. **If handoffs exist:** Read the full context in `../wrapper/.claude/handoffs.md`. Handoffs contain API contracts, reproduction steps, and decisions that GitHub issues don't capture.

2. **Process handoffs first:** They usually have richer context and are blocking frontend work.

3. **After absorbing a handoff:** Delete that section from the handoffs file so it doesn't show up again.

4. **Report findings:** Summarize what's pending and recommend what to tackle.

## Related commands

- `just issues` - List backend issues only
- `just issues both` - List issues assigned to both teams
- `just issue-view <number>` - View specific issue details
