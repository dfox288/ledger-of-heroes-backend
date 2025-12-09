# Create a New Project Issue

Create a new issue in the coordination repo.

## Arguments

$ARGUMENTS - Description of the issue (optional, will prompt if not provided)

## Instructions

1. If $ARGUMENTS is provided, use it as the issue title/description
2. If not, ask the user:
   - What's the issue? (brief description)
   - Who should fix it? (frontend / backend / both)
   - What type? (bug / feature / api-contract / data-issue / performance)

3. Create the issue:

```bash
gh issue create --repo dfox288/ledger-of-heroes \
  --title "[TITLE]" \
  --label "[ASSIGNEE],[TYPE],from:backend" \
  --body "[DETAILS]"
```

4. Report the issue number and URL back to the user

## Quick Mode Examples

If the user provides structured input, parse it:
- `/issue:new frontend bug: SpellCard crashes on null components` → assignee=frontend, type=bug
- `/issue:new both api-contract: Need to discuss spell format` → assignee=both, type=api-contract
