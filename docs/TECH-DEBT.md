# Technical Debt & Future Refactoring

This document tracks technical debt items and future refactoring opportunities.

---

## Database Schema Improvements

### 1. Rename `random_tables` to `dice_progressions` (or `scaling_tables`)

**Status:** Deferred
**Priority:** Low
**Added:** 2025-11-26
**Context:** Optional Features implementation

**Current State:**
The `random_tables` and `random_table_entries` tables serve dual purposes:
1. **Random roll tables** (d6, d8, d100 with discrete outcomes) - e.g., Background personality traits
2. **Level/resource scaling data** - e.g., Sneak Attack damage progression, Elemental Discipline ki cost scaling

**Problem:**
The name "random_tables" is misleading for non-random data like damage scaling.

**Proposed Solution:**
```sql
-- Rename tables
ALTER TABLE random_tables RENAME TO dice_progressions;
ALTER TABLE random_table_entries RENAME TO dice_progression_entries;

-- Add progression_type enum
ALTER TABLE dice_progressions ADD COLUMN progression_type ENUM('random', 'level_scaling', 'resource_scaling');
```

**Files Affected:**
- `app/Models/RandomTable.php` → `DiceProgression.php`
- `app/Models/RandomTableEntry.php` → `DiceProgressionEntry.php`
- `app/Services/Importers/Concerns/ImportsRandomTables.php`
- `app/Services/Importers/Concerns/ImportsRandomTablesFromText.php`
- `app/Services/Importers/ClassImporter.php`
- `app/Services/Importers/BackgroundImporter.php`
- `app/Services/Importers/RaceImporter.php`
- `app/Services/Importers/SpellImporter.php`
- `app/Http/Resources/*Resource.php` (any that include random tables)
- All related tests

**Estimated Effort:** 4-6 hours
**Risk:** Medium (touches many files, needs careful testing)

**Why Deferred:**
- Current naming works and is well-understood
- No functional issues
- Refactoring would delay OptionalFeatures implementation
- Can be done as a standalone cleanup task

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

**Last Updated:** 2025-11-26
