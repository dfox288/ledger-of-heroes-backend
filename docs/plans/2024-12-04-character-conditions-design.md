# Character Condition Tracking Design

**Issue:** #117 - Character Builder: Condition Tracking
**Date:** 2024-12-04
**Status:** Approved

## Overview

Track active conditions on characters (blinded, poisoned, exhaustion, etc.). Conditions are standard D&D 5e status effects that affect gameplay.

## Database Schema

### Table: `character_conditions`

```php
Schema::create('character_conditions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('character_id')->constrained()->cascadeOnDelete();
    $table->foreignId('condition_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('level')->nullable(); // Only for exhaustion (1-6)
    $table->string('source')->nullable();             // What caused it
    $table->string('duration')->nullable();           // How long it lasts
    $table->timestamps();

    $table->unique(['character_id', 'condition_id']); // Prevent duplicates
});
```

**Design decisions:**
- Unique constraint prevents duplicate conditions (D&D conditions don't stack)
- `level` is only used for exhaustion (1-6 levels with cumulative effects)
- `source` and `duration` are optional metadata for DM tracking
- Cascade delete removes conditions when character is deleted

## Models

### CharacterCondition

```php
class CharacterCondition extends Model
{
    protected $fillable = ['character_id', 'condition_id', 'level', 'source', 'duration'];

    protected $casts = ['level' => 'integer'];

    public function character(): BelongsTo
    public function condition(): BelongsTo
}
```

### Character (add relationship)

```php
public function conditions(): HasMany
{
    return $this->hasMany(CharacterCondition::class);
}
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/characters/{character}/conditions` | List active conditions |
| POST | `/characters/{character}/conditions` | Add or update condition |
| DELETE | `/characters/{character}/conditions/{condition}` | Remove condition |

### POST /characters/{character}/conditions

**Request:**
```json
{
    "condition_id": 15,
    "level": 2,
    "source": "Failed Constitution save",
    "duration": "Until long rest"
}
```

**Validation:**
- `condition_id` - required, exists in conditions table
- `level` - nullable, integer 1-6, only valid for exhaustion
- `source` - nullable, string max 255
- `duration` - nullable, string max 255

**Behavior:**
- If condition already exists on character: upsert (update level/source/duration)
- If adding exhaustion without existing: level defaults to 1
- If updating exhaustion: new level replaces old level

### DELETE /characters/{character}/conditions/{condition}

- `{condition}` can be condition ID or slug
- Returns 404 if character doesn't have the condition

## Response Format

### CharacterConditionResource

```json
{
    "data": {
        "id": 1,
        "condition": {
            "id": 15,
            "name": "Exhaustion",
            "slug": "exhaustion"
        },
        "level": 2,
        "source": "Forced march",
        "duration": null,
        "is_exhaustion": true,
        "exhaustion_warning": null
    }
}
```

**Exhaustion warnings:**
- Level 6: `"Level 6 exhaustion results in death"`

### CharacterResource (conditions included)

```json
{
    "id": 1,
    "name": "Thorin",
    "conditions": [
        {
            "id": 10,
            "name": "Poisoned",
            "slug": "poisoned",
            "level": null,
            "source": "Giant spider bite"
        },
        {
            "id": 15,
            "name": "Exhaustion",
            "slug": "exhaustion",
            "level": 2,
            "source": "Forced march"
        }
    ]
}
```

## Exhaustion Special Handling

Exhaustion is unique among D&D conditions - it has 6 cumulative levels:

| Level | Effects |
|-------|---------|
| 1 | Disadvantage on ability checks |
| 2 | Speed halved |
| 3 | Disadvantage on attack rolls and saving throws |
| 4 | Hit point maximum halved |
| 5 | Speed reduced to 0 |
| 6 | Death |

**Implementation:**
- When adding exhaustion, level defaults to 1 if not specified
- When updating exhaustion, level can be increased or decreased
- Level 6 triggers a warning in the response (actual death is DM decision)
- Long rest reduces exhaustion by 1 level (future enhancement in RestService)

## Files to Create/Modify

### New Files
- `database/migrations/xxxx_create_character_conditions_table.php`
- `app/Models/CharacterCondition.php`
- `database/factories/CharacterConditionFactory.php`
- `app/Http/Controllers/Api/CharacterConditionController.php`
- `app/Http/Requests/CharacterCondition/StoreCharacterConditionRequest.php`
- `app/Http/Resources/CharacterConditionResource.php`
- `tests/Feature/Api/CharacterConditionApiTest.php`
- `tests/Feature/Models/CharacterConditionModelTest.php`

### Modified Files
- `app/Models/Character.php` - add `conditions()` relationship
- `app/Http/Resources/CharacterResource.php` - include conditions
- `routes/api.php` - add routes

## Test Plan

### Unit Tests (CharacterConditionModelTest)
- [ ] Can create condition on character
- [ ] Belongs to character
- [ ] Belongs to condition
- [ ] Factory creates valid records

### Feature Tests (CharacterConditionApiTest)
- [ ] GET index returns empty array for character with no conditions
- [ ] GET index returns all conditions for character
- [ ] POST creates new condition on character
- [ ] POST with existing condition upserts (updates)
- [ ] POST exhaustion defaults level to 1
- [ ] POST exhaustion validates level 1-6
- [ ] POST non-exhaustion ignores level
- [ ] POST returns 422 for invalid condition_id
- [ ] DELETE removes condition from character
- [ ] DELETE by slug works
- [ ] DELETE returns 404 if character doesn't have condition
- [ ] CharacterResource includes conditions

## Future Enhancements (Not in Scope)

- Condition effects on stats (disadvantage, speed reduction)
- Long rest reduces exhaustion by 1 level
- Conditions removed by specific spells/abilities
- Condition immunity from race/class features
