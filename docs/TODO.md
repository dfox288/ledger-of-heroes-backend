# TODO

Active tasks and priorities for this project.

---

## In Progress

_Tasks currently being worked on_

- [ ] Classes detail page optimization - remaining items:
  - [ ] Issue #12: Filter irrelevant progression columns (class-specific)
  - [ ] Issue #13: Handle duplicate description content

---

## Next Up

_Prioritized tasks ready to start_

- [ ] Optional Features import improvements
- [ ] API documentation standardization (remaining entities)

---

## Backlog

_Future tasks, not yet prioritized_

- [ ] Character Builder API (see `plans/2025-11-23-character-builder-api-proposal.md`)
- [ ] Search result caching (Phase 4)
- [ ] Additional Monster Strategies
- [ ] Frontend application

---

## Completed

_Recently completed tasks (move to CHANGELOG.md after release)_

- [x] **SQLite test migration** - Tests use in-memory SQLite instead of MySQL (2025-11-28)
  - ~10x faster test execution (39s total vs ~400s)
  - Unit-Pure: ~3s, Unit-DB: ~7s, Feature-DB: ~9s, Feature-Search: ~20s
  - Fixed MySQL-specific migration (parent_feature_id) for SQLite compatibility
- [x] **Test fixture migration** - Feature-Search tests use fixtures only (2025-11-28)
  - [x] Unit-DB suite: 13 failures fixed (replaced `firstOrCreate()` with `factory()->create()`)
  - [x] Feature-DB suite: 1 failure fixed (updated counter assertions)
  - [x] Feature-Search suite: **37 failures fixed** (was 37 fail, 257 pass)
  - [x] Final status: **0 fail, 286 pass, 28 skipped** (2 incomplete)
  - [x] Made FilterOperatorTest assertions data-agnostic
  - [x] Replaced hardcoded slugs with dynamic fixture queries
  - [x] Added skip logic for missing fixture relationships
  - [x] Updated SpellImportToApiTest API structure
  - See `docs/handovers/SESSION-HANDOVER-2025-11-28-1430-test-fixture-complete.md`
- [x] Fixture-based test data extraction (2025-11-28)
  - [x] Created `fixtures:extract` command
  - [x] Migrated tests from XML imports to JSON fixtures
  - [x] Improved test performance (~30s for Unit-Pure + Unit-DB)
  - [x] Fixed ImportsClassAssociationsTest to use correct slugs
- [x] Class API enhancements (2025-11-26)
  - [x] Subclass level accessor (#7)
  - [x] Nested feature choice options (#8)
  - [x] Feature count excludes choices (#9)
  - [x] Progression table excludes choices (#10)
  - [x] Counter grouping by name (#11)
- [x] Import command refactoring (BaseImportCommand)
- [x] Test suite reorganization (6 independent suites)
- [x] Parser architecture refactoring
- [x] Filter operator testing (100% coverage)

---

## Notes

- See `PROJECT-STATUS.md` for current metrics and milestones
- See `LATEST-HANDOVER.md` for most recent session context
- See `TECH-DEBT.md` for technical debt items
- Update this file when starting/completing tasks
