# Rest Mechanics Design

**Issue:** #110 - Character Builder: Rest Mechanics (Short/Long Rest)
**Date:** 2025-12-04
**Status:** Approved for implementation

## Overview

Implement short rest and long rest mechanics for characters. Resting is fundamental to D&D 5e gameplay, affecting spell slots, hit points, hit dice, and feature uses.

## Dependencies

- ✅ Hit Dice Tracking (#111) - Completed
- ✅ Death Saves Tracking (#112) - Completed

## Design Decisions

### 1. Spell Slot Tracking

**Decision:** Track spell slots in backend (not frontend-only)

**Schema:** `character_spell_slots`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| character_id | FK | Reference to character |
| spell_level | tinyint | 1-9 |
| max_slots | tinyint | Calculated from class/multiclass |
| used_slots | tinyint | Default 0 |
| slot_type | enum | `standard` or `pact_magic` |
| timestamps | | |

**Why `slot_type`:**
- Standard slots reset on **long rest** (most casters)
- Pact magic slots reset on **short rest** (Warlock only)
- Multiclass characters pool standard slots but keep pact magic separate

### 2. Feature Reset Timing

**Decision:** Add `resets_on` enum to source models, parse from XML descriptions

**Affected Models:**
- `ClassFeature` - Add `resets_on` column
- `Feat` - Add `resets_on` column

**Enum: `ResetTiming`**
```php
enum ResetTiming: string
{
    case SHORT_REST = 'short_rest';
    case LONG_REST = 'long_rest';
    case DAWN = 'dawn';
}
```

**Why on source models (not CharacterFeature):**
- Reset timing is intrinsic to the feature definition
- `CharacterFeature` uses polymorphic `feature()` relationship to access it
- Avoids data duplication

### 3. XML Parser Changes

**New Trait:** `ParsesRestTiming`

**Patterns to detect:**

| Text Pattern | `resets_on` Value |
|--------------|-------------------|
| `short or long rest` | `short_rest` |
| `short rest` (without "long") | `short_rest` |
| `finish a long rest` | `long_rest` |
| `between long rests` | `long_rest` |
| `once per long rest` | `long_rest` |
| `at dawn` / `next dawn` | `dawn` |

**Used by:**
- `ClassXmlParser` (class features)
- `FeatXmlParser` (feats)
- `RaceXmlParser` (racial traits with limited uses)

## Database Schema

### New Table: `character_spell_slots`

```sql
CREATE TABLE character_spell_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_level TINYINT UNSIGNED NOT NULL,
    max_slots TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used_slots TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slot_type VARCHAR(255) NOT NULL DEFAULT 'standard',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    UNIQUE KEY (character_id, spell_level, slot_type)
);
```

### Migration: Add `resets_on` to `class_features`

```sql
ALTER TABLE class_features
ADD COLUMN resets_on VARCHAR(255) NULL AFTER sort_order;
```

### Migration: Add `resets_on` to `feats`

```sql
ALTER TABLE feats
ADD COLUMN resets_on VARCHAR(255) NULL;
```

## Service Layer

### SpellSlotService

```php
class SpellSlotService
{
    public function getSlots(Character $character): array;
    public function useSlot(Character $character, int $level, string $type = 'standard'): void;
    public function resetSlots(Character $character, string $type): void;
    public function resetAllSlots(Character $character): void;
    public function recalculateMaxSlots(Character $character): void;
}
```

**Slot calculation:** Uses existing `MulticlassSpellSlotCalculator` for standard slots, `CharacterStatCalculator::WARLOCK_PACT_SLOTS` for pact magic.

### RestService

```php
class RestService
{
    public function __construct(
        private HitDiceService $hitDiceService,
        private SpellSlotService $spellSlotService,
    ) {}

    public function shortRest(Character $character, array $hitDiceToSpend = []): ShortRestResultDTO;
    public function longRest(Character $character): LongRestResultDTO;
}
```

**Short Rest:**
1. Spend hit dice (if provided) and heal
2. Reset pact magic slots
3. Reset features where `resets_on = short_rest`
4. Return summary

**Long Rest:**
1. Restore HP to max
2. Recover hit dice (half total, min 1)
3. Reset ALL spell slots (standard + pact magic)
4. Reset ALL features with `uses_remaining`
5. Reset death saves to 0/0
6. Return summary

## API Endpoints

### POST /api/v1/characters/{character}/short-rest

**Request:**
```json
{
    "hit_dice": [
        { "die_type": "d10", "quantity": 2 }
    ]
}
```

**Response:**
```json
{
    "data": {
        "hp_before": 15,
        "hp_after": 23,
        "hp_healed": 8,
        "hit_dice_spent": {
            "d10": 2
        },
        "hit_dice_remaining": {
            "d10": { "available": 3, "max": 5, "spent": 2 }
        },
        "features_reset": ["Second Wind", "Action Surge"],
        "pact_slots_reset": true
    }
}
```

### POST /api/v1/characters/{character}/long-rest

**Request:** (empty body)

**Response:**
```json
{
    "data": {
        "hp_before": 23,
        "hp_after": 45,
        "hp_restored": 22,
        "hit_dice_recovered": 2,
        "hit_dice_status": {
            "d10": { "available": 5, "max": 5, "spent": 0 }
        },
        "spell_slots_reset": true,
        "features_reset": ["Indomitable", "Lucky", "Second Wind", "Action Surge"],
        "death_saves_reset": true
    }
}
```

## DTOs

### ShortRestResultDTO

```php
class ShortRestResultDTO
{
    public function __construct(
        public int $hpBefore,
        public int $hpAfter,
        public int $hpHealed,
        public array $hitDiceSpent,
        public array $hitDiceRemaining,
        public array $featuresReset,
        public bool $pactSlotsReset,
    ) {}
}
```

### LongRestResultDTO

```php
class LongRestResultDTO
{
    public function __construct(
        public int $hpBefore,
        public int $hpAfter,
        public int $hpRestored,
        public int $hitDiceRecovered,
        public array $hitDiceStatus,
        public bool $spellSlotsReset,
        public array $featuresReset,
        public bool $deathSavesReset,
    ) {}
}
```

## File Changes Summary

### New Files
- `app/Enums/ResetTiming.php`
- `app/Enums/SpellSlotType.php`
- `app/Models/CharacterSpellSlot.php`
- `app/Services/SpellSlotService.php`
- `app/Services/RestService.php`
- `app/DTOs/ShortRestResultDTO.php`
- `app/DTOs/LongRestResultDTO.php`
- `app/Http/Controllers/Api/RestController.php`
- `app/Http/Requests/ShortRestRequest.php`
- `app/Http/Resources/ShortRestResource.php`
- `app/Http/Resources/LongRestResource.php`
- `app/Services/Parsers/Concerns/ParsesRestTiming.php`
- `database/migrations/xxxx_create_character_spell_slots_table.php`
- `database/migrations/xxxx_add_resets_on_to_class_features_table.php`
- `database/migrations/xxxx_add_resets_on_to_feats_table.php`
- `database/factories/CharacterSpellSlotFactory.php`
- `tests/Unit/Services/SpellSlotServiceTest.php`
- `tests/Unit/Services/RestServiceTest.php`
- `tests/Unit/Parsers/ParsesRestTimingTest.php`
- `tests/Feature/Api/RestControllerTest.php`

### Modified Files
- `app/Services/Parsers/ClassXmlParser.php` - Use ParsesRestTiming trait
- `app/Services/Parsers/FeatXmlParser.php` - Use ParsesRestTiming trait
- `app/Services/Parsers/RaceXmlParser.php` - Use ParsesRestTiming trait (if applicable)
- `app/Services/Importers/ClassImporter.php` - Save resets_on field
- `app/Services/Importers/FeatImporter.php` - Save resets_on field
- `app/Models/ClassFeature.php` - Add resets_on cast
- `app/Models/Feat.php` - Add resets_on cast
- `app/Services/AddClassService.php` - Initialize spell slots when class added
- `app/Services/LevelUpService.php` - Recalculate spell slots on level up
- `routes/api.php` - Add rest endpoints

## Implementation Order

1. **Database & Models** - Migrations, enums, CharacterSpellSlot model
2. **Parser Trait** - ParsesRestTiming with regex patterns
3. **Parser Integration** - Add to ClassXmlParser, FeatXmlParser
4. **Importer Updates** - Save resets_on during import
5. **SpellSlotService** - CRUD and reset logic
6. **RestService** - Orchestrate short/long rest
7. **API Layer** - Controller, requests, resources, routes
8. **Integration** - Update AddClassService, LevelUpService
9. **Tests** - Unit and feature tests throughout

## Testing Strategy

- **Unit tests** for ParsesRestTiming regex patterns
- **Unit tests** for SpellSlotService calculations
- **Unit tests** for RestService orchestration
- **Feature tests** for REST endpoints
- **Re-run importer tests** after parser changes

---

## Implementation Plan

**Runner:** Sail (`docker compose exec php ...`)
**Branch:** `feature/issue-110-rest-mechanics`

### Batch 1: Database Foundation & Enums

#### Task 1.1: Create ResetTiming enum
- **File:** `app/Enums/ResetTiming.php`
- **Test:** N/A (enum only)
```php
enum ResetTiming: string
{
    case SHORT_REST = 'short_rest';
    case LONG_REST = 'long_rest';
    case DAWN = 'dawn';
}
```

#### Task 1.2: Create SpellSlotType enum
- **File:** `app/Enums/SpellSlotType.php`
- **Test:** N/A (enum only)
```php
enum SpellSlotType: string
{
    case STANDARD = 'standard';
    case PACT_MAGIC = 'pact_magic';
}
```

#### Task 1.3: Migration - Add resets_on to class_features
- **File:** `database/migrations/2025_12_04_000003_add_resets_on_to_class_features_table.php`
- **Verify:** `sail artisan migrate` runs clean
```php
Schema::table('class_features', function (Blueprint $table) {
    $table->string('resets_on')->nullable()->after('sort_order');
});
```

#### Task 1.4: Migration - Add resets_on to feats
- **File:** `database/migrations/2025_12_04_000004_add_resets_on_to_feats_table.php`
- **Verify:** `sail artisan migrate` runs clean
```php
Schema::table('feats', function (Blueprint $table) {
    $table->string('resets_on')->nullable();
});
```

#### Task 1.5: Migration - Create character_spell_slots table
- **File:** `database/migrations/2025_12_04_000005_create_character_spell_slots_table.php`
- **Verify:** `sail artisan migrate` runs clean
```php
Schema::create('character_spell_slots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('character_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('spell_level');
    $table->unsignedTinyInteger('max_slots')->default(0);
    $table->unsignedTinyInteger('used_slots')->default(0);
    $table->string('slot_type')->default('standard');
    $table->timestamps();
    $table->unique(['character_id', 'spell_level', 'slot_type']);
});
```

#### Task 1.6: Update ClassFeature model
- **File:** `app/Models/ClassFeature.php`
- **Change:** Add `resets_on` to fillable and casts
```php
protected $fillable = [
    // ... existing
    'resets_on',
];

protected $casts = [
    // ... existing
    'resets_on' => ResetTiming::class,
];
```

#### Task 1.7: Update Feat model
- **File:** `app/Models/Feat.php`
- **Change:** Add `resets_on` to fillable and casts

#### Task 1.8: Create CharacterSpellSlot model + factory
- **File:** `app/Models/CharacterSpellSlot.php`
- **File:** `database/factories/CharacterSpellSlotFactory.php`
- **Test:** `tests/Unit/Models/CharacterSpellSlotTest.php`

**Commit after Batch 1:** `feat(#110): Add database schema for rest mechanics`

---

### Batch 2: Parser - ParsesRestTiming Trait

#### Task 2.1: Write failing tests for ParsesRestTiming
- **File:** `tests/Unit/Parsers/ParsesRestTimingTest.php`
- **Test cases:**
  - `short or long rest` → `short_rest`
  - `finish a short rest` → `short_rest`
  - `finish a long rest` → `long_rest`
  - `between long rests` → `long_rest`
  - `once per long rest` → `long_rest`
  - `regain at dawn` → `dawn`
  - `next dawn` → `dawn`
  - No match → `null`

#### Task 2.2: Implement ParsesRestTiming trait
- **File:** `app/Services/Parsers/Concerns/ParsesRestTiming.php`
- **Method:** `parseResetTiming(string $description): ?ResetTiming`
- **Verify:** All tests pass

**Commit after Batch 2:** `feat(#110): Add ParsesRestTiming trait for XML parsing`

---

### Batch 3: Parser Integration

#### Task 3.1: Integrate ParsesRestTiming into ClassXmlParser
- **File:** `app/Services/Parsers/ClassXmlParser.php`
- **Change:**
  - Add `use ParsesRestTiming` trait
  - In `parseFeatures()`, call `$this->parseResetTiming($text)` and add to feature array
- **Test:** Existing parser tests should still pass + add test for resets_on parsing

#### Task 3.2: Integrate ParsesRestTiming into FeatXmlParser
- **File:** `app/Services/Parsers/FeatXmlParser.php`
- **Change:** Add trait, parse resets_on from feat description
- **Test:** Add test for resets_on parsing

#### Task 3.3: Update ClassImporter to save resets_on
- **File:** `app/Services/Importers/ClassImporter.php`
- **Change:** Include `resets_on` when creating ClassFeature records
- **Verify:** Run `sail artisan import:classes` and check database

#### Task 3.4: Update FeatImporter to save resets_on
- **File:** `app/Services/Importers/FeatImporter.php`
- **Change:** Include `resets_on` when creating Feat records
- **Verify:** Run `sail artisan import:feats` and check database

**Commit after Batch 3:** `feat(#110): Parse and import resets_on from XML`

---

### Batch 4: SpellSlotService

#### Task 4.1: Write failing tests for SpellSlotService
- **File:** `tests/Unit/Services/SpellSlotServiceTest.php`
- **Test cases:**
  - `getSlots()` returns empty for non-caster
  - `getSlots()` returns correct slots for Wizard level 5
  - `getSlots()` returns separate pact magic for Warlock
  - `useSlot()` increments used_slots
  - `useSlot()` throws when no slots available
  - `resetSlots('standard')` resets only standard slots
  - `resetSlots('pact_magic')` resets only pact magic slots
  - `resetAllSlots()` resets all slots
  - `recalculateMaxSlots()` updates max based on class levels

#### Task 4.2: Implement SpellSlotService
- **File:** `app/Services/SpellSlotService.php`
- **Dependencies:** `MulticlassSpellSlotCalculator`, `CharacterStatCalculator`
- **Verify:** All tests pass

#### Task 4.3: Create InsufficientSpellSlotsException
- **File:** `app/Exceptions/InsufficientSpellSlotsException.php`

**Commit after Batch 4:** `feat(#110): Add SpellSlotService for slot tracking`

---

### Batch 5: RestService

#### Task 5.1: Create DTOs
- **File:** `app/DTOs/ShortRestResultDTO.php`
- **File:** `app/DTOs/LongRestResultDTO.php`

#### Task 5.2: Write failing tests for RestService
- **File:** `tests/Unit/Services/RestServiceTest.php`
- **Test cases:**
  - `shortRest()` without hit dice spending
  - `shortRest()` with hit dice spending
  - `shortRest()` resets pact magic slots
  - `shortRest()` resets short_rest features only
  - `longRest()` restores HP to max
  - `longRest()` recovers hit dice (half, min 1)
  - `longRest()` resets all spell slots
  - `longRest()` resets all features with uses
  - `longRest()` resets death saves

#### Task 5.3: Implement RestService
- **File:** `app/Services/RestService.php`
- **Dependencies:** `HitDiceService`, `SpellSlotService`
- **Verify:** All tests pass

**Commit after Batch 5:** `feat(#110): Add RestService for rest orchestration`

---

### Batch 6: API Layer

#### Task 6.1: Create ShortRestRequest
- **File:** `app/Http/Requests/ShortRestRequest.php`
- **Validation:** `hit_dice` array optional, each with `die_type` (d6/d8/d10/d12) and `quantity` (min 1)

#### Task 6.2: Create API Resources
- **File:** `app/Http/Resources/ShortRestResource.php`
- **File:** `app/Http/Resources/LongRestResource.php`

#### Task 6.3: Write failing feature tests for RestController
- **File:** `tests/Feature/Api/RestControllerTest.php`
- **Test cases:**
  - POST short-rest returns correct structure
  - POST short-rest with hit dice heals character
  - POST short-rest resets pact slots
  - POST long-rest restores full HP
  - POST long-rest recovers hit dice
  - POST long-rest resets spell slots and features

#### Task 6.4: Implement RestController
- **File:** `app/Http/Controllers/Api/RestController.php`
- **Methods:** `shortRest()`, `longRest()`

#### Task 6.5: Add routes
- **File:** `routes/api.php`
- **Routes:**
  - `POST /characters/{character}/short-rest`
  - `POST /characters/{character}/long-rest`
- **Verify:** All feature tests pass

**Commit after Batch 6:** `feat(#110): Add REST endpoints for short/long rest`

---

### Batch 7: Integration

#### Task 7.1: Update AddClassService to initialize spell slots
- **File:** `app/Services/AddClassService.php`
- **Change:** After adding a class, call `SpellSlotService::recalculateMaxSlots()`
- **Test:** Add test case verifying spell slots created when caster class added

#### Task 7.2: Update LevelUpService to recalculate spell slots
- **File:** `app/Services/LevelUpService.php`
- **Change:** After level up, call `SpellSlotService::recalculateMaxSlots()`
- **Test:** Add test case verifying spell slots updated on level up

#### Task 7.3: Add spell slots to CharacterStatsResource
- **File:** `app/Http/Resources/CharacterStatsResource.php`
- **Change:** Include current spell slot usage

**Commit after Batch 7:** `feat(#110): Integrate spell slots with class/level services`

---

### Batch 8: Quality Gates & Documentation

#### Task 8.1: Run full test suite
```bash
sail artisan test --testsuite=Unit-Pure
sail artisan test --testsuite=Unit-DB
sail artisan test --testsuite=Feature-DB
```

#### Task 8.2: Run Pint
```bash
sail php ./vendor/bin/pint
```

#### Task 8.3: Update CHANGELOG.md
- Add entry under [Unreleased]

#### Task 8.4: Re-run importers to populate resets_on
```bash
sail artisan import:classes
sail artisan import:feats
```

**Final Commit:** `chore(#110): Quality gates and documentation`

---

## Quality Checklist

- [ ] All new code has tests written FIRST (TDD)
- [ ] All tests pass
- [ ] Pint formatting clean
- [ ] CHANGELOG.md updated
- [ ] Migrations run without errors
- [ ] Importers populate resets_on correctly
- [ ] API endpoints documented in controller PHPDoc
