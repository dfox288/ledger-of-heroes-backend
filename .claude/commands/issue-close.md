# Close a Project Issue

Mark an issue as resolved in the coordination repo.

## Arguments

$ARGUMENTS - Issue number and optional comment

## Instructions

1. Parse $ARGUMENTS for issue number (required) and comment (optional)
2. If no comment provided, ask what was done to resolve it
3. Close the issue:

```bash
gh issue close [NUMBER] --repo dfox288/ledger-of-heroes \
  --comment "[RESOLUTION COMMENT]"
```

4. Confirm closure to the user

## Examples

- `/issue:close 42 Added components to SpellResource` → closes #42 with that comment
- `/issue:close 15` → prompts for resolution comment, then closes
