# Design: Fix Progression Table Columns

**Date:** 2025-11-29
**Status:** Ready for Implementation
**Priority:** Medium
**Related:** `frontend/docs/proposals/CLASSES-DETAIL-PAGE-BACKEND-FIXES.md` Section 3.3

---

## Problem Statement

The `ClassProgressionTableGenerator` produces incorrect columns for several classes:

| Class | Current Columns | PHB Canonical Columns |
|-------|-----------------|----------------------|
| **Barbarian** | `rage` | `rage`, `rage_damage` |
| **Monk** | `ki`, `wholeness_of_body` | `martial_arts`, `ki_points`, `unarmored_movement` |
| **Rogue** | `stroke_of_luck` | `sneak_attack` |

### Root Causes

1. **Wrong counters showing**: `Wholeness of Body` and `Stroke of Luck` are one-time features with uses/rest, not PHB progression columns. They're correctly stored as `<counter>` in XML but shouldn't appear in progression tables.

2. **Missing data - Sneak Attack**: Data EXISTS in `RandomTable` (parsed from `<roll>` XML elements) but `ClassProgressionTableGenerator` only queries `class_counters`.

3. **Missing data - Martial Arts, Unarmored Movement, Rage Damage**: Data exists only as **plain text tables** in feature descriptions. Not currently parsed or stored.

---

## Solution Overview

Three-part fix:

1. **Exclude non-progression counters** from generator (quick fix)
2. **Use RandomTable data** for features with `<roll>` elements (Sneak Attack)
3. **Parse text tables** from feature descriptions (Martial Arts, Unarmored Movement, Rage Damage)

---

## Technical Design

### Part 1: Exclude Non-Progression Counters

**File:** `app/Services/ClassProgressionTableGenerator.php`

Add to `EXCLUDED_COUNTERS` constant:

```php
private const EXCLUDED_COUNTERS = [
    // Existing...
    'Arcane Recovery',
    'Action Surge',
    'Indomitable',
    'Second Wind',
    'Lay on Hands',
    'Channel Divinity',

    // NEW: One-time features (not PHB progression columns)
    'Wholeness of Body',    // Monk L6 - 1 use/long rest
    'Stroke of Luck',       // Rogue L20 - 1 use/short rest
];
```

**Rationale:** These have `<counter>` elements because they track uses/rest, but they're single-level features, not scaling progressions shown in PHB tables.

---

### Part 2: Use RandomTable Data for Progression Columns

**Current state:** Sneak Attack's `<roll>` elements are parsed and stored in `EntityDataTable` + `EntityDataTableEntry`, linked to the feature via polymorphic relationship.

**Change:** Update `ClassProgressionTableGenerator::buildColumns()` to also check for `EntityDataTable` entries linked to class features that represent progression data.

**Detection criteria for "progression tables":**
- Linked to a `ClassFeature` (not a spell or item)
- Has entries with `level` values (level-based progression)
- Table name suggests progression (e.g., "Extra Damage" for Sneak Attack)

**New method in generator:**

```php
private function getProgressionTablesFromFeatures(CharacterClass $class): Collection
{
    // Get features with data tables that have level-based entries
    return $class->features
        ->filter(fn ($f) => $f->dataTables->isNotEmpty())
        ->flatMap(fn ($f) => $f->dataTables)
        ->filter(fn ($t) => $t->entries->contains(fn ($e) => $e->level !== null));
}
```

**Column generation:**
- Extract feature name as column label (e.g., "Sneak Attack")
- Slugify for key (e.g., `sneak_attack`)
- Determine type from dice notation in entries

---

### Part 3: Parse Level-Ordinal Text Tables

**Current format in XML feature descriptions:**

```
The Monk Table:
Level | Martial Arts
1st | 1d4
5th | 1d6
11th | 1d8
17th | 1d10
```

**Problem:** `ItemTableDetector` patterns expect numeric rows (`1 |`) not ordinals (`1st |`).

**Solution:** Add Pattern 4 to `ItemTableDetector`:

```php
// Pattern 4: Level-ordinal tables (class progression)
// Matches:
//   Table Name:
//   Level | Column
//   1st | value
//   5th | value
$pattern4 = '/^(.+?):\s*\n(Level\s*\|[^\n]+)\s*\n((?:^\d+(?:st|nd|rd|th)\s*\|[^\n]+\s*\n?)+)/mi';
```

**Storage:** Parse into `EntityDataTable` + `EntityDataTableEntry` linked to the feature, with `level` field populated from ordinal (e.g., "5th" → 5). Use `table_type = DataTableType::Progression`.

**Import integration:**
- In `ImportsClassFeatures::importFeature()`, after creating feature
- Call new method `parseProgressionTablesFromDescription()`
- Store as `EntityDataTable` with entries (table_type = progression)

---

## Data Flow

```
XML Import
    │
    ├─► <counter> elements ──► class_counters table
    │                              │
    ├─► <roll> elements ─────► EntityDataTable (linked to feature)
    │                              │
    └─► Text tables in ──────► EntityDataTable (linked to feature, type=progression)
        description                │
                                   ▼
                    ClassProgressionTableGenerator
                                   │
                    ┌──────────────┼──────────────┐
                    ▼              ▼              ▼
              class_counters  EntityDataTable  EntityDataTable
              (usage-based)   (from rolls)     (from text)
                    │              │              │
                    └──────────────┴──────────────┘
                                   │
                                   ▼
                         Progression Table API
```

---

## Affected Files

| File | Change |
|------|--------|
| `app/Services/ClassProgressionTableGenerator.php` | Add exclusions, add EntityDataTable column source |
| `app/Services/Parsers/ItemTableDetector.php` | Add Pattern 4 for level-ordinal tables |
| `app/Services/Importers/Concerns/ImportsClassFeatures.php` | Parse text tables from descriptions |
| `tests/Unit/Services/ClassProgressionTableGeneratorTest.php` | Test new column sources |
| `tests/Unit/Parsers/ItemTableDetectorTest.php` | Test Pattern 4 |

---

## Test Cases

### ItemTableDetector Pattern 4

```php
public function it_detects_level_ordinal_tables(): void
{
    $text = "The Monk Table:\nLevel | Martial Arts\n1st | 1d4\n5th | 1d6\n11th | 1d8\n17th | 1d10";

    $detector = new ItemTableDetector();
    $tables = $detector->detectTables($text);

    $this->assertCount(1, $tables);
    $this->assertEquals('The Monk Table', $tables[0]['name']);
}
```

### ClassProgressionTableGenerator

```php
public function it_includes_sneak_attack_from_random_table(): void
{
    $rogue = CharacterClass::where('slug', 'rogue')->first();
    $generator = new ClassProgressionTableGenerator();
    $result = $generator->generate($rogue);

    $columnKeys = collect($result['columns'])->pluck('key');
    $this->assertContains('sneak_attack', $columnKeys);
    $this->assertNotContains('stroke_of_luck', $columnKeys);
}

public function it_excludes_wholeness_of_body(): void
{
    $monk = CharacterClass::where('slug', 'monk')->first();
    $generator = new ClassProgressionTableGenerator();
    $result = $generator->generate($monk);

    $columnKeys = collect($result['columns'])->pluck('key');
    $this->assertNotContains('wholeness_of_body', $columnKeys);
}
```

---

## Migration/Re-import Required

After implementation:
1. Run `php artisan import:classes` to re-parse text tables
2. Verify with: `curl http://localhost:8080/api/v1/classes/rogue | jq '.data.computed.progression_table.columns'`

---

## Expected Outcome

### Barbarian Progression Table Columns
- `level`, `proficiency_bonus`, `features`, `rage`, `rage_damage`

### Monk Progression Table Columns
- `level`, `proficiency_bonus`, `martial_arts`, `ki_points`, `unarmored_movement`, `features`

### Rogue Progression Table Columns
- `level`, `proficiency_bonus`, `sneak_attack`, `features`

---

## Open Questions

1. **Column ordering**: PHB has specific column order. Should we enforce canonical ordering per class, or let it be dynamic?

2. **Unarmored Movement format**: Values are `+10`, `+15`, etc. Should these be stored as integers (10, 15) or strings ("+10")?

3. **Rage Damage**: Not in XML as structured data at all - only in prose: "At 1st level, you have a +2 bonus to damage. Your bonus increases to +3 at 9th level and to +4 at 16th." May need special handling or hardcoding.

---

## References

- PHB Appendix B: Canonical progression table columns
- `frontend/docs/proposals/CLASSES-DETAIL-PAGE-BACKEND-FIXES.md` Section 3.3 and Appendix B
- Existing patterns: `ItemTableDetector`, `ParsesRandomTables` trait
