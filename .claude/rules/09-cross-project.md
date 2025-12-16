# Cross-Project Coordination

Use GitHub Issues in `dfox288/ledger-of-heroes` for bugs, API issues, and cross-cutting concerns.

## Session Start Checklist

**Do these in order at the start of every session:**

```bash
just inbox
```

This shows:
1. Handoffs from frontend (`../wrapper/.claude/handoffs.md`)
2. GitHub issues labeled `backend`
3. GitHub issues labeled `both` (shared work)

If there's a handoff:
1. Read the full context in `../wrapper/.claude/handoffs.md`
2. The handoff contains decisions, API contracts, and reproduction steps
3. After absorbing the context, delete that handoff section
4. Start work on the related issue

## Create an Issue

```bash
gh issue create --repo dfox288/ledger-of-heroes --title "Brief description" --label "frontend,bug,from:backend" --body "Details here"
```

## Labels to Use

- **Assignee:** `frontend`, `backend`, `both`
- **Type:** `bug`, `feature`, `api-contract`, `data-issue`, `performance`
- **Source:** `from:frontend`, `from:backend`, `from:manual-testing`

## Write Handoffs (when creating frontend work)

**After creating an issue that requires frontend work, ALWAYS write a handoff.**

Append to `../wrapper/.claude/handoffs.md`:

```markdown
## For: frontend
**From:** backend | **Issue:** #NUMBER | **Created:** YYYY-MM-DD HH:MM

[Brief description of what was implemented]

**What I did:**
- [Key endpoints/models/services added]
- [Important implementation decisions]

**What frontend needs to do:**
- [Specific UI components needed]
- [Pages to create]
- [Filters to implement]

**API contract:**
- Endpoint: `GET /api/v1/endpoint`
- Filters: `field`, `other_field` (boolean), `array_field` (IN)
- Response shape:
```json
{
  "data": [{ "id": 1, "name": "Example", "slug": "example" }],
  "meta": { "total": 100, "per_page": 24 }
}
```

**Test with:**
```bash
curl "http://localhost:8080/api/v1/endpoint?filter=field=value"
```

**Related:**
- Follows from: #ORIGINAL_ISSUE
- See also: `app/Http/Controllers/Api/ExampleController.php`

---
```

**Key details to include:**
- Exact filterable fields and their types
- Response shape with actual field names
- Working curl command for testing
- Any gotchas or edge cases

## Close When Fixed

Issues close automatically when PR merges if the PR body contains `Closes #N`. For manual closure:

```bash
gh issue close 42 --repo dfox288/ledger-of-heroes --comment "Fixed in PR #123"
```
