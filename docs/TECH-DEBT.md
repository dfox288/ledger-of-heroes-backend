# Technical Debt & Future Refactoring

This document tracks technical debt items and future refactoring opportunities.

---

## Database Schema Improvements

### 1. Rename `random_tables` to `entity_data_tables`

**Status:** 📋 PLANNED - Ready to Execute
**Priority:** Medium
**Added:** 2025-11-26
**Updated:** 2025-11-29
**Context:** Optional Features implementation, API clarity

**Current State:**
The `random_tables` table stores 563 records across 5 distinct use cases:
- **Random tables** (79%): Rollable with dice (Personality Trait d8, Wild Magic Surge d100)
- **Damage dice** (~10%): Feature damage expressions (Necrotic Damage d12)
- **Modifiers** (~7%): Size/weight calculations (Size Modifier 2d4)
- **Lookup tables** (~21%): Reference data without dice (Musical Instrument, Exhaustion)
- **Progressions** (~3%): Level-based data (Bard Spells Known)

**Problem:**
The name "random_tables" is misleading for non-random data (21% have no dice at all).

**Approved Solution:**
- Rename tables: `random_tables` → `entity_data_tables`
- Add `table_type` enum column: `random`, `damage`, `modifier`, `lookup`, `progression`
- Rename all related classes, traits, resources
- **BREAKING API CHANGE:** JSON key `random_tables` → `data_tables`

**Implementation Plans:**
- **Design:** `docs/plans/2025-11-29-entity-data-tables-refactor.md`
- **Step-by-step:** `docs/plans/2025-11-29-entity-data-tables-implementation.md`

**Scope:** ~45 files, ~15 commits, 16 tasks

**Estimated Effort:** 4-6 hours
**Risk:** Medium (many files, breaking API change, but comprehensive test coverage)

---

## Future Considerations

### 2. Consolidate Lookup Endpoints

**Status:** Not Started
**Priority:** Medium
**Context:** API organization

Consider consolidating `/lookups/*` endpoints into a single response or GraphQL-style query.

### 3. Add Soft Deletes

**Status:** Not Started
**Priority:** Low

Consider adding soft deletes to main entities for data recovery.

---

## Completed Items

_(Move items here when resolved)_

---

**Last Updated:** 2025-11-29
