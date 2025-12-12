# Development Workflow

## Every Feature/Fix

```
1. Check Laravel skills     → Use Superpower skills if applicable
2. Check GitHub Issues      → gh issue list for assigned tasks
3. Create feature branch    → See 08-git-conventions.md for naming
4. Write tests FIRST        → Watch them fail (TDD mandatory)
5. Write minimal code       → Make tests pass
6. Refactor while green     → Clean up
7. Run test suite           → Smallest relevant suite
8. Format with Pint         → docker compose exec php ./vendor/bin/pint
9. Update CHANGELOG.md      → Under [Unreleased]
10. Commit + Push           → Clear message, push to feature branch
11. Create PR               → gh pr create with issue reference
12. Close GitHub Issue      → Closes automatically via PR merge
```

## Bug Fix Workflow (When Tests Already Exist)

```
1. Reproduce the bug      → Understand what's broken
2. Check existing tests   → Do they test the buggy behavior?
3. UPDATE TESTS FIRST     → Write/modify tests for CORRECT behavior
4. Watch tests FAIL       → Confirms test catches the bug
5. Fix the code           → Make tests pass
6. Run test suite         → Verify no regressions
7. Commit + Push          → Include "fix:" prefix
```

**Critical:** When fixing bugs, do NOT just make existing tests pass. If tests pass with buggy code, the tests are wrong. Update tests to verify the correct behavior BEFORE fixing the code.

**Anti-patterns to avoid:**
- Code doesn't match tests → Rewrite code to match tests
- Tests pass but behavior is wrong → "Tests pass, ship it"

**Correct approach:**
- Code doesn't match tests → Determine correct behavior → Update tests → Fix code

## Additional Checklists by Change Type

### For API Changes
- Update Form Requests validation
- Update API Resources
- Eager-load new relationships in Controllers
- Update Controller PHPDoc for filters

### For Search/Filter Changes
See `06-search-filtering.md` for the complete checklist.

### For Major Features
- Create session handover in `../wrapper/docs/backend/handovers/`

## Success Checklist

Before creating a PR:

- [ ] Working on feature branch (see `08-git-conventions.md`)
- [ ] Tests written FIRST (TDD mandate)
- [ ] All tests pass (relevant suite)
- [ ] Full suite passes (pre-commit)
- [ ] Code formatted with Pint
- [ ] CHANGELOG.md updated
- [ ] Commits pushed to feature branch
- [ ] PR created with issue reference (`Closes #N`)

**For API changes:**
- [ ] Form Requests validate new parameters
- [ ] API Resources expose new data
- [ ] Controller PHPDoc documents filters

**If ANY checkbox is unchecked, work is NOT done.**
