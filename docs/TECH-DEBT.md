# Technical Debt & Future Refactoring

This document tracks technical debt items and future refactoring opportunities.

---

## Database Schema Improvements

### ~~1. Rename `random_tables` to `entity_data_tables`~~ ✅ COMPLETED

**Status:** ✅ COMPLETED (2025-11-29)
**Completed By:** Commit `09551bd`

**What was done:**
- Renamed tables: `random_tables` → `entity_data_tables`, `random_table_entries` → `entity_data_table_entries`
- Added `DataTableType` enum with values: `random`, `damage`, `modifier`, `lookup`, `progression`
- Updated all models, resources, importers, parsers, factories, tests (62 files)
- **BREAKING API CHANGE:** JSON key `random_tables` → `data_tables`

**Handover:** `docs/handovers/SESSION-HANDOVER-2025-11-29-1700-entity-data-tables-refactor-complete.md`

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
