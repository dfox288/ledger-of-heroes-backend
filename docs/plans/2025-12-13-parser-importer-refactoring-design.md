# Parser & Importer Refactoring Design

**Issue:** #562
**Date:** 2025-12-13
**Status:** Approved

## Overview

Address structural debt in the import system through two phases:
- Phase 1: Create enums for magic strings (quick wins)
- Phase 2: Break down ClassXmlParser into focused traits

## Phase 1: Enums

### RequirementLogic Enum

For multiclass requirement grouping (currently stored in `proficiency_subcategory`):

```php
enum RequirementLogic: string
{
    case OR = 'OR';   // Any one requirement satisfies
    case AND = 'AND'; // All requirements must be met

    public function label(): string
    {
        return match ($this) {
            self::OR => 'Any One',
            self::AND => 'All Required',
        };
    }
}
```

**Files to update:**
- `ClassImporter.php:764` - Use `RequirementLogic::OR->value` / `RequirementLogic::AND->value`

### ToolProficiencyCategory Enum

For tool choice categories:

```php
enum ToolProficiencyCategory: string
{
    case ARTISAN = 'artisan';
    case MUSICAL_INSTRUMENT = 'musical_instrument';
    case GAMING = 'gaming';

    public function label(): string
    {
        return match ($this) {
            self::ARTISAN => "Artisan's Tools",
            self::MUSICAL_INSTRUMENT => 'Musical Instrument',
            self::GAMING => 'Gaming Set',
        };
    }
}
```

**Files to update:**
- `ClassXmlParser.php:220,234` - Use enum values
- `BackgroundXmlParser.php:509,513` - Return enum values from `detectProficiencySubcategory()`

### Backward Compatibility

- No migration needed - values remain strings in DB
- Existing data stays valid
- Type safety enforced at code level only

## Phase 2: ClassXmlParser Breakdown

Current: **1266 lines**, 17 methods, 11 traits.

### Trait Extraction Plan

| Trait | Methods | ~Lines |
|-------|---------|--------|
| `ParsesSpellProgression` | `parseSpellSlots()`, `parseOptionalSpellSlots()`, `hasNonOptionalSpellSlots()` | 170 |
| `ParsesClassCounters` | `parseCounters()`, `addSpecialCaseCounters()` | 120 |
| `ParsesClassEquipment` | `parseEquipment()`, `parseEquipmentChoices()`, `parseCompoundItem()`, `parseSingleItem()` | 220 |
| `ParsesSubclassDetection` | `detectSubclasses()`, `featureBelongsToSubclass()` | 170 |

### Result

- `ClassXmlParser.php`: 1266 → ~600 lines (53% reduction)
- Each trait has single responsibility
- Traits go in `app/Services/Parsers/Concerns/`

## Implementation Order

### Phase 1: Enums

1. Create `RequirementLogic` enum
2. Create `ToolProficiencyCategory` enum
3. Update `ClassImporter.php`
4. Update `ClassXmlParser.php`
5. Update `BackgroundXmlParser.php`
6. Run tests, commit

### Phase 2: Trait Extraction

1. Extract `ParsesSpellProgression` trait
2. Extract `ParsesClassCounters` trait
3. Extract `ParsesClassEquipment` trait
4. Extract `ParsesSubclassDetection` trait
5. Run full importer test suite, commit

## Verification

```bash
docker compose exec php ./vendor/bin/pest --testsuite=Unit-Pure
docker compose exec php ./vendor/bin/pest --testsuite=Importers
```

## Out of Scope

- Relationship clearing patterns (separate issue)
- Error handling standardization (Phase 3)
- Long method extraction beyond ClassXmlParser
