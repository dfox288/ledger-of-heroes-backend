# Multiclass Support Design

**Issue:** #92 - Character Builder v2: Multiclass Support
**Date:** 2025-12-03
**Status:** Approved

## Overview

Add D&D 5e multiclass support to the Character Builder, allowing characters to have levels in multiple classes with proper prerequisite validation, combined spell slot calculation, and per-class hit dice tracking.

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Architecture | Junction table with migration | Clean relational design, single source of truth |
| Validation | Strict + `force` flag | Enforce PHB prerequisites by default, DM override available |
| Warlock slots | Separate Pact Magic tracking | RAW-accurate (Pact Magic doesn't combine with slot casters) |
| Spell slots | Multiclass table seeded from PHB p165 | Static lookup, already have per-class tables |
| Hit dice | Track spent per class | Full short rest recovery support |

## Database Schema

### New Table: `character_classes`

Junction table for character-class relationship with per-class tracking.

```sql
CREATE TABLE character_classes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    subclass_id BIGINT UNSIGNED NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    `order` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    hit_dice_spent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT character_classes_character_id_foreign
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    CONSTRAINT character_classes_class_id_foreign
        FOREIGN KEY (class_id) REFERENCES classes(id),
    CONSTRAINT character_classes_subclass_id_foreign
        FOREIGN KEY (subclass_id) REFERENCES classes(id),
    CONSTRAINT character_classes_character_class_unique
        UNIQUE (character_id, class_id),

    INDEX character_classes_character_id_index (character_id),
    INDEX character_classes_class_id_index (class_id)
);
```

**Columns:**
- `character_id` - FK to characters table
- `class_id` - FK to classes table (base class only)
- `subclass_id` - FK to classes table (subclass choice, nullable)
- `level` - This class's level (1-20)
- `is_primary` - First class taken (determines starting proficiencies)
- `order` - Order classes were taken
- `hit_dice_spent` - Tracks spent hit dice for short rest recovery

### New Table: `multiclass_spell_slots`

Static lookup table for multiclass spellcaster slots (PHB p165).

```sql
CREATE TABLE multiclass_spell_slots (
    caster_level TINYINT UNSIGNED PRIMARY KEY,
    slots_1st TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slots_2nd TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slots_3rd TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slots_4th TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slots_5th TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slots_6th TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slots_7th TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slots_8th TINYINT UNSIGNED NOT NULL DEFAULT 0,
    slots_9th TINYINT UNSIGNED NOT NULL DEFAULT 0
);
```

Seeded with 20 rows from PHB p165 multiclass spellcaster table.

### Migration: Existing Data

```php
// Move existing class_id to junction table
Character::whereNotNull('class_id')->each(function ($char) {
    CharacterClass::create([
        'character_id' => $char->id,
        'class_id' => $char->class_id,
        'level' => $char->level,
        'is_primary' => true,
        'order' => 1,
    ]);
});

// Then drop class_id FK from characters table
```

## Model Changes

### Character.php

```php
// New relationship
public function characterClasses(): HasMany
{
    return $this->hasMany(CharacterClassPivot::class)->orderBy('order');
}

// Computed attributes
public function getPrimaryClassAttribute(): ?CharacterClass
{
    return $this->characterClasses->firstWhere('is_primary', true)?->characterClass;
}

public function getTotalLevelAttribute(): int
{
    return $this->characterClasses->sum('level');
}

public function isMulticlassAttribute(): bool
{
    return $this->characterClasses->count() > 1;
}
```

### New Model: CharacterClassPivot.php

```php
class CharacterClassPivot extends Model
{
    protected $table = 'character_classes';

    protected $fillable = [
        'character_id',
        'class_id',
        'subclass_id',
        'level',
        'is_primary',
        'order',
        'hit_dice_spent',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    public function subclass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'subclass_id');
    }

    public function getMaxHitDiceAttribute(): int
    {
        return $this->level;
    }

    public function getAvailableHitDiceAttribute(): int
    {
        return $this->level - $this->hit_dice_spent;
    }
}
```

### New Model: MulticlassSpellSlot.php

```php
class MulticlassSpellSlot extends Model
{
    protected $primaryKey = 'caster_level';
    public $incrementing = false;
    public $timestamps = false;

    public static function forCasterLevel(int $level): ?self
    {
        return self::find(min($level, 20));
    }
}
```

## API Endpoints

### New Endpoints

| Method | Endpoint | Controller | Purpose |
|--------|----------|------------|---------|
| GET | `/characters/{id}/classes` | CharacterClassController@index | List character's classes |
| POST | `/characters/{id}/classes` | CharacterClassController@store | Add a class (multiclass) |
| DELETE | `/characters/{id}/classes/{classId}` | CharacterClassController@destroy | Remove class |
| POST | `/characters/{id}/classes/{classId}/level-up` | CharacterClassController@levelUp | Level specific class |
| PATCH | `/characters/{id}/classes/{classId}/subclass` | CharacterClassController@updateSubclass | Choose subclass |

### Modified Endpoints

| Endpoint | Change |
|----------|--------|
| `GET /characters/{id}` | Response includes `classes` array instead of single `class` |
| `POST /characters/{id}/level-up` | Now requires `class_id` in request body |

### Request/Response Examples

**POST /characters/{id}/classes**
```json
// Request
{
    "class_id": 5,
    "force": false  // Optional: bypass prerequisite check
}

// Response 201
{
    "data": {
        "class": { "id": 5, "name": "Wizard", "slug": "wizard" },
        "subclass": null,
        "level": 1,
        "is_primary": false,
        "hit_dice": { "die": "d6", "max": 1, "spent": 0, "available": 1 }
    }
}

// Response 422 (prerequisites not met)
{
    "message": "Multiclass prerequisites not met",
    "errors": {
        "class_id": ["Wizard requires Intelligence 13 (current: 10)"]
    }
}
```

**GET /characters/{id}**
```json
{
    "data": {
        "id": 42,
        "name": "Gandalf the Multiclassed",
        "total_level": 8,
        "experience_points": 34000,
        "classes": [
            {
                "class": { "id": 1, "name": "Fighter", "slug": "fighter" },
                "subclass": { "id": 12, "name": "Champion", "slug": "champion" },
                "level": 5,
                "is_primary": true,
                "hit_dice": { "die": "d10", "max": 5, "spent": 2, "available": 3 }
            },
            {
                "class": { "id": 9, "name": "Wizard", "slug": "wizard" },
                "subclass": null,
                "level": 3,
                "is_primary": false,
                "hit_dice": { "die": "d6", "max": 3, "spent": 0, "available": 3 }
            }
        ],
        "spell_slots": {
            "standard": { "1st": 4, "2nd": 3, "3rd": 2 },
            "pact": null
        },
        ...
    }
}
```

## Services

### New: MulticlassValidationService

Validates multiclass prerequisites per PHB p163.

```php
class MulticlassValidationService
{
    /**
     * Check if character can add a new class.
     * Must meet requirements for ALL current classes AND the new class.
     */
    public function canAddClass(
        Character $character,
        CharacterClass $newClass,
        bool $force = false
    ): ValidationResult;

    /**
     * Check if character meets a specific class's multiclass requirements.
     */
    public function meetsRequirements(
        Character $character,
        CharacterClass $class
    ): RequirementCheck;
}
```

Uses existing `multiclassRequirements()` relationship on CharacterClass model.

### New: MulticlassSpellSlotCalculator

Calculates combined spell slots for multiclass spellcasters.

```php
class MulticlassSpellSlotCalculator
{
    private const CASTER_MULTIPLIERS = [
        'full' => 1,      // Wizard, Cleric, Druid, Bard, Sorcerer
        'half' => 0.5,    // Paladin, Ranger
        'third' => 0.334, // Eldritch Knight, Arcane Trickster
        'pact' => 0,      // Warlock (separate)
        'none' => 0,
    ];

    /**
     * Calculate spell slots for a multiclass character.
     */
    public function calculate(Character $character): SpellSlotResult;

    /**
     * Calculate combined caster level from all classes.
     */
    public function calculateCasterLevel(Character $character): int;

    /**
     * Get Pact Magic slots if character has Warlock levels.
     */
    public function getPactMagicSlots(Character $character): ?PactSlotInfo;
}
```

### New: AddClassService

Handles adding a class to a character with proper proficiency grants.

```php
class AddClassService
{
    public function __construct(
        private MulticlassValidationService $validator
    ) {}

    /**
     * Add a class to character.
     * Grants multiclass-only proficiencies (not full starting set).
     */
    public function addClass(
        Character $character,
        CharacterClass $class,
        bool $force = false
    ): CharacterClassPivot;
}
```

### Modified: LevelUpService

Now requires specifying which class to level.

```php
class LevelUpService
{
    /**
     * Level up a specific class.
     *
     * @throws MaxLevelReachedException if total level >= 20
     * @throws ClassNotFoundException if character doesn't have this class
     */
    public function levelUp(Character $character, CharacterClass $class): Character;

    /**
     * Check if this class level grants an ASI.
     * ASI levels are class-specific (Fighter/Rogue differ from standard).
     */
    public function isAsiLevel(CharacterClass $class, int $classLevel): bool;
}
```

## Validation Rules

### Multiclass Prerequisites (PHB p163)

To multiclass, character must meet:
1. Minimum ability score(s) for **current** class(es)
2. Minimum ability score(s) for **new** class

Example requirements (already in database):
- Barbarian: Strength 13
- Bard: Charisma 13
- Fighter: Strength 13 OR Dexterity 13
- Paladin: Strength 13 AND Charisma 13

### Subclass Requirements

- Subclass choice required at class-specific level (usually 1, 2, or 3)
- Cannot multiclass into a subclass directly (must pick base class)
- Subclass belongs to parent class (validated via `parent_class_id`)

## Testing Strategy

### Unit Tests
- `MulticlassValidationServiceTest` - prerequisite checking
- `MulticlassSpellSlotCalculatorTest` - caster level calculation, slot lookup
- `AddClassServiceTest` - class addition with proficiency grants

### Feature Tests
- `CharacterMulticlassApiTest` - all new endpoints
- `CharacterMulticlassLevelUpTest` - leveling specific classes
- `CharacterMulticlassSpellSlotsTest` - combined spell slot responses
- `CharacterMulticlassHitDiceTest` - hit dice tracking per class

### Migration Tests
- Verify existing single-class characters migrate correctly
- Verify API responses maintain backwards compatibility shape

## Migration Plan

1. Create `multiclass_spell_slots` table and seed
2. Create `character_classes` table
3. Migrate existing character class data to junction table
4. Add new model, services, controllers
5. Update existing endpoints for new response shape
6. Drop deprecated `class_id` FK from characters table

## Files to Create

- `database/migrations/xxxx_create_multiclass_spell_slots_table.php`
- `database/migrations/xxxx_create_character_classes_table.php`
- `database/migrations/xxxx_migrate_character_class_data.php`
- `database/migrations/xxxx_drop_class_id_from_characters.php`
- `database/seeders/MulticlassSpellSlotSeeder.php`
- `app/Models/CharacterClassPivot.php`
- `app/Models/MulticlassSpellSlot.php`
- `app/Services/MulticlassValidationService.php`
- `app/Services/MulticlassSpellSlotCalculator.php`
- `app/Services/AddClassService.php`
- `app/Http/Controllers/Api/CharacterClassController.php`
- `app/Http/Requests/Character/AddCharacterClassRequest.php`
- `app/Http/Requests/Character/CharacterClassLevelUpRequest.php`
- `app/Http/Resources/CharacterClassPivotResource.php`
- `tests/Unit/Services/MulticlassValidationServiceTest.php`
- `tests/Unit/Services/MulticlassSpellSlotCalculatorTest.php`
- `tests/Feature/Api/CharacterMulticlassApiTest.php`

## Files to Modify

- `app/Models/Character.php` - new relationships, computed attributes
- `app/Services/LevelUpService.php` - require class_id parameter
- `app/Services/CharacterStatCalculator.php` - use new spell slot calculator
- `app/Http/Resources/CharacterResource.php` - classes array response
- `app/Http/Controllers/Api/CharacterLevelUpController.php` - require class_id
- `routes/api.php` - new routes
