# Entity Data Tables Refactor

**Date:** 2025-11-29
**Status:** Approved
**Breaking Change:** Yes (API key `random_tables` → `data_tables`)

## Overview

Rename `random_tables` to `entity_data_tables` and add a `table_type` discriminator column to accurately reflect the multi-purpose nature of this polymorphic table.

## Problem

The `random_tables` table stores 5 distinct types of data:
- **Random tables** (79%): Rollable with dice (Personality Trait d8, Wild Magic Surge d100)
- **Damage dice** (~10%): Feature damage expressions (Necrotic Damage d12)
- **Modifiers** (~7%): Size/weight calculations (Size Modifier 2d4)
- **Lookup tables** (~21%): Reference data without dice (Musical Instrument, Exhaustion)
- **Progressions** (~3%): Level-based data (Bard Spells Known)

The name "random_tables" is misleading for non-random data.

## Solution

1. Rename tables: `random_tables` → `entity_data_tables`
2. Add `table_type` enum column to categorize data
3. Rename all related classes, traits, and resources
4. Update API JSON keys: `random_tables` → `data_tables`

## Schema Changes

### New Migration

```php
// Rename tables
Schema::rename('random_tables', 'entity_data_tables');
Schema::rename('random_table_entries', 'entity_data_table_entries');

// Add table_type column
Schema::table('entity_data_tables', function (Blueprint $table) {
    $table->string('table_type', 20)->default('random')->after('dice_type');
    $table->index('table_type');
});

// Rename foreign keys
Schema::table('entity_data_table_entries', function (Blueprint $table) {
    $table->renameColumn('random_table_id', 'entity_data_table_id');
});

Schema::table('character_traits', function (Blueprint $table) {
    $table->renameColumn('random_table_id', 'entity_data_table_id');
});

// Populate table_type from existing data
DB::statement("UPDATE entity_data_tables SET table_type = 'damage'
    WHERE table_name LIKE '%Damage%'");
DB::statement("UPDATE entity_data_tables SET table_type = 'modifier'
    WHERE table_name LIKE '%Modifier%'");
DB::statement("UPDATE entity_data_tables SET table_type = 'progression'
    WHERE table_name LIKE '%Spells Known%' OR table_name LIKE '%Exhaustion%'");
DB::statement("UPDATE entity_data_tables SET table_type = 'lookup'
    WHERE (dice_type IS NULL OR dice_type = '') AND table_type = 'random'");
```

### Table Type Enum

| Value | Description | Has Dice | Example |
|-------|-------------|----------|---------|
| `random` | Rollable tables with discrete outcomes | Yes | Personality Trait (d8) |
| `damage` | Damage dice for features/spells | Yes | Necrotic Damage (d12) |
| `modifier` | Size/weight modifiers | Yes | Size Modifier (2d4) |
| `lookup` | Reference tables | No | Musical Instrument |
| `progression` | Level-based progressions | No | Bard Spells Known |

## File Changes

### Models (7 files)

| Action | Old | New |
|--------|-----|-----|
| Rename | `RandomTable.php` | `EntityDataTable.php` |
| Rename | `RandomTableEntry.php` | `EntityDataTableEntry.php` |
| Create | - | `app/Enums/DataTableType.php` |
| Update | `ClassFeature.php` | `randomTables()` → `dataTables()` |
| Update | `Item.php` | `randomTables()` → `dataTables()` |
| Update | `CharacterTrait.php` | `randomTables()` → `dataTables()`, `randomTable()` → `dataTable()` |
| Update | `Spell.php` | `randomTables()` → `dataTables()` |
| Update | `OptionalFeature.php` | Update class reference (keep `rolls()` alias) |

### Resources (6 files)

| Action | Old | New |
|--------|-----|-----|
| Rename | `RandomTableResource.php` | `EntityDataTableResource.php` |
| Rename | `RandomTableEntryResource.php` | `EntityDataTableEntryResource.php` |
| Update | `ClassFeatureResource.php` | Key: `random_tables` → `data_tables` |
| Update | `ItemResource.php` | Key: `random_tables` → `data_tables` |
| Update | `TraitResource.php` | Key: `random_tables` → `data_tables` |
| Update | `SpellResource.php` | Key: `random_tables` → `data_tables` |

### Importers (9 files)

| Action | Old | New |
|--------|-----|-----|
| Rename | `ImportsRandomTables.php` | `ImportsDataTables.php` |
| Rename | `ImportsRandomTablesFromText.php` | `ImportsDataTablesFromText.php` |
| Update | `BackgroundImporter.php` | Trait usage |
| Update | `ClassImporter.php` | Trait usage |
| Update | `RaceImporter.php` | Trait usage |
| Update | `SpellImporter.php` | Trait usage |
| Update | `ItemImporter.php` | Trait usage |
| Update | `BaseImporter.php` | Trait usage |
| Update | `ImportsClassFeatures.php` | References |

### Parsers (2 files)

| Action | Old | New |
|--------|-----|-----|
| Rename | `ParsesRandomTables.php` | `ParsesDataTables.php` |
| Update | `SpellXmlParser.php` | Trait usage |

### Factories (2 files)

| Action | Old | New |
|--------|-----|-----|
| Rename | `RandomTableFactory.php` | `EntityDataTableFactory.php` |
| Rename | `RandomTableEntryFactory.php` | `EntityDataTableEntryFactory.php` |

### Tests (13 files)

| Action | Old | New |
|--------|-----|-----|
| Rename | `RandomTableModelTest.php` | `EntityDataTableModelTest.php` |
| Rename | `RandomTableFactoriesTest.php` | `EntityDataTableFactoriesTest.php` |
| Rename | `SpellRandomTableImportTest.php` | `SpellDataTableImportTest.php` |
| Rename | `SpellRandomTableParserTest.php` | `SpellDataTableParserTest.php` |
| Rename | `ClassImporterRandomTablesTest.php` | `ClassImporterDataTablesTest.php` |
| Rename | `ImportsRandomTablesTest.php` | `ImportsDataTablesTest.php` |
| Update | `BackgroundApiTest.php` | JSON assertions |
| Update | `RaceApiTest.php` | JSON assertions |
| Update | `BackgroundXmlReconstructionTest.php` | References |
| Update | `RaceImporterTest.php` | References |
| Update | `RaceXmlReconstructionTest.php` | References |
| Update | `BackgroundXmlParserTest.php` | References |
| Update | `OptionalFeatureTest.php` | Model references |

### Documentation (4 files)

| Action | File | Change |
|--------|------|--------|
| Create | `docs/reference/DATA-TABLE-TYPES.md` | Document enum values |
| Update | `docs/TECH-DEBT.md` | Mark item completed |
| Update | `CHANGELOG.md` | Document breaking change |
| Regenerate | `api.json` | Run Scramble after code changes |

## Implementation Order

1. Create migration (schema changes + data population)
2. Create `DataTableType` enum
3. Rename model files and update internals
4. Update relationship methods in dependent models
5. Rename resource files and update JSON keys
6. Rename importer traits and update usage
7. Rename parser trait and update usage
8. Rename factories and update references
9. Rename test files and update all assertions
10. Create reference documentation
11. Update TECH-DEBT.md and CHANGELOG.md
12. Run all test suites
13. Regenerate api.json via Scramble
14. Commit and push

## Verification

```bash
# Run each test suite
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
docker compose exec php php artisan test --testsuite=Feature-Search

# Format code
docker compose exec php ./vendor/bin/pint

# Regenerate API docs
docker compose exec php php artisan scramble:export
```

## Rollback

If issues arise, the migration's `down()` method reverses all changes:
- Rename tables back
- Drop `table_type` column
- Rename foreign keys back
