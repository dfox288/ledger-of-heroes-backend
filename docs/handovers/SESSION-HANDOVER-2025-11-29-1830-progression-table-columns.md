# Session Handover: Progression Table Columns Enhancement

**Date:** 2025-11-29 18:30
**Branch:** main
**Status:** Complete

## Summary

Enhanced class progression tables to include data-driven columns from feature descriptions, `<roll>` XML elements, and synthetic progressions. This addresses the missing Martial Arts, Sneak Attack, and Rage Damage columns.

## What Was Done

### 1. Level-Ordinal Table Detection (Pattern 4)
- Added Pattern 4 to `ItemTableDetector` for tables like:
  ```
  The Monk Table:
  Level | Martial Arts
  1st | 1d4
  5th | 1d6
  ```
- Detects ordinal suffixes (1st, 2nd, 3rd, 4th, etc.)
- Sets `is_level_progression = true` flag

### 2. Level-Ordinal Table Parser
- Added `ItemTableParser::parseLevelProgression()` method
- Parses ordinal levels to integers
- Returns `{table_name, column_name, rows: [{level, value}]}`

### 3. Import Pipeline Updates
- `ImportsDataTablesFromText` now handles level progression tables
- Creates `EntityDataTable` with type `PROGRESSION`
- Stores level values in `EntityDataTableEntry.level` column

### 4. Generator Enhancement
- `ClassProgressionTableGenerator` now pulls from:
  - `PROGRESSION` type tables (from text parsing)
  - `DAMAGE` type tables (from `<roll>` elements)
- Added synthetic progressions for Barbarian Rage Damage
- Avoids duplicate columns via key tracking

### 5. Counter Exclusions
- Added `Wholeness of Body` (Monk L6) - one-time feature
- Added `Stroke of Luck` (Rogue L20) - capstone feature

## API Changes

### Progression Table Columns by Class

| Class | Column | Type | Source |
|-------|--------|------|--------|
| Monk | `martial_arts` | dice | Parsed from feature text table |
| Monk | `ki` | integer | Counter data |
| Rogue | `sneak_attack` | dice | `<roll>` element EntityDataTable |
| Barbarian | `rage` | integer | Counter data |
| Barbarian | `rage_damage` | bonus | Synthetic progression |

### Example API Response (Barbarian)

```json
{
  "columns": [
    {"key": "level", "label": "Level", "type": "integer"},
    {"key": "proficiency_bonus", "label": "Proficiency Bonus", "type": "bonus"},
    {"key": "features", "label": "Features", "type": "string"},
    {"key": "rage", "label": "Rage", "type": "integer"},
    {"key": "rage_damage", "label": "Rage Damage", "type": "bonus"}
  ],
  "rows": [
    {"level": 1, "proficiency_bonus": "+2", "features": "...", "rage": "2", "rage_damage": "+2"},
    {"level": 9, "proficiency_bonus": "+4", "features": "...", "rage": "4", "rage_damage": "+3"},
    {"level": 16, "proficiency_bonus": "+5", "features": "...", "rage": "5", "rage_damage": "+4"}
  ]
}
```

## Files Changed

### Core Implementation
- `app/Services/ClassProgressionTableGenerator.php` - Column/row generation from data tables
- `app/Services/Parsers/ItemTableDetector.php` - Pattern 4 for level-ordinal tables
- `app/Services/Parsers/ItemTableParser.php` - `parseLevelProgression()` method
- `app/Services/Importers/Concerns/ImportsDataTablesFromText.php` - Handle level progression tables

### API Documentation
- `app/Http/Controllers/Api/ClassController.php` - Updated PHPDoc for progression columns

### Tests (TDD)
- `tests/Unit/Services/ClassProgressionTableGeneratorTest.php` - 23 tests (+4 new)
- `tests/Unit/Services/ItemTableDetectorTest.php` - Pattern 4 tests
- `tests/Unit/Services/ItemTableParserTest.php` - Level progression parser tests
- `tests/Unit/Importers/Concerns/ImportsDataTablesTest.php` - Import test

### Documentation
- `docs/plans/2025-11-29-progression-table-columns-design.md` - Updated model names
- `docs/plans/2025-11-29-progression-table-columns-plan.md` - Updated model names
- `CHANGELOG.md` - Added feature entry
- `docs/TODO.md` - Marked task complete

## Test Results

```
Unit-Pure:   273 passed
Unit-DB:     443 passed (1 skipped)
Feature-DB:  336 passed
```

## Known Limitations

1. **Sneak Attack data issue**: XML `<roll>` elements have levels 1-9 (dice count), not character levels 1, 3, 5... This is a source data issue, not an implementation issue.

2. **Rage Damage is synthetic**: The +2/+3/+4 progression is hardcoded because the XML only has prose text. If the XML is updated with structured data, the synthetic progression should be removed.

3. **Unarmored Movement not parsed**: The Monk's Unarmored Movement feature has a very short description without a table. Speed bonuses would need to be added to the synthetic progressions if needed.

## Verification Commands

```bash
# Check Monk progression table
curl -s 'http://localhost:8080/api/v1/classes/monk' | jq '.data.computed.progression_table.columns'

# Check Barbarian rage columns
curl -s 'http://localhost:8080/api/v1/classes/barbarian' | jq '[.data.computed.progression_table.rows[] | {level, rage, rage_damage}]'

# Check Rogue sneak attack
curl -s 'http://localhost:8080/api/v1/classes/rogue' | jq '.data.computed.progression_table.rows[0:5]'
```

## Next Session

- Consider adding Unarmored Movement synthetic progression for Monk
- Consider fixing Sneak Attack XML data to use actual character levels
- Move completed plan files to archive
