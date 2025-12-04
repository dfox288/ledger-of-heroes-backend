# Hit Dice Tracking Design

**Issue:** #111 - Character Builder: Hit Dice Tracking
**Date:** 2025-12-04
**Status:** Approved

## Overview

Add API endpoints to view, spend, and recover hit dice for characters. This enables short rest healing mechanics and is a prerequisite for the full rest mechanics feature (#110).

## Existing Infrastructure

The storage layer already exists:
- `character_classes.hit_dice_spent` column tracks spent dice per class
- `CharacterClassPivot` model has `max_hit_dice` and `available_hit_dice` accessors
- `CharacterClass.hit_die` stores the die type (d6, d8, d10, d12)

## API Design

### Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/v1/characters/{character}/hit-dice` | View hit dice by die type |
| `POST` | `/api/v1/characters/{character}/hit-dice/spend` | Spend hit dice |
| `POST` | `/api/v1/characters/{character}/hit-dice/recover` | Recover hit dice |

### GET /characters/{character}/hit-dice

Returns hit dice grouped by die type with totals.

**Response:**
```json
{
  "data": {
    "hit_dice": {
      "d10": { "available": 3, "max": 5, "spent": 2 },
      "d6": { "available": 2, "max": 2, "spent": 0 }
    },
    "total": { "available": 5, "max": 7, "spent": 2 }
  }
}
```

### POST /characters/{character}/hit-dice/spend

Spend one or more hit dice of a specific type.

**Request:**
```json
{
  "die_type": "d10",
  "quantity": 2
}
```

**Validation:**
- `die_type` required, must be one the character has (d6, d8, d10, d12)
- `quantity` required, integer 1+, cannot exceed available dice of that type

**Response:**
```json
{
  "data": {
    "spent": { "die_type": "d10", "quantity": 2 },
    "hit_dice": {
      "d10": { "available": 1, "max": 5, "spent": 4 },
      "d6": { "available": 2, "max": 2, "spent": 0 }
    },
    "total": { "available": 3, "max": 7, "spent": 4 }
  }
}
```

### POST /characters/{character}/hit-dice/recover

Recover spent hit dice (typically on long rest).

**Request:**
```json
{
  "quantity": 3
}
```

Or omit quantity for D&D 5e standard recovery (half of total max, minimum 1).

**Validation:**
- `quantity` optional, integer 1+, cannot exceed total spent dice

**Recovery Logic:**
- Recover from largest die types first (d12 → d10 → d8 → d6) to maximize healing potential
- If quantity not specified, recover `max(1, floor(total_max / 2))`

**Response:**
```json
{
  "data": {
    "recovered": 3,
    "hit_dice": {
      "d10": { "available": 4, "max": 5, "spent": 1 },
      "d6": { "available": 2, "max": 2, "spent": 0 }
    },
    "total": { "available": 6, "max": 7, "spent": 1 }
  }
}
```

## Architecture

### New Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/HitDiceController.php` | Route handler |
| `app/Services/HitDiceService.php` | Business logic |
| `app/Http/Resources/HitDiceResource.php` | Response formatting |
| `app/Http/Requests/HitDice/SpendHitDiceRequest.php` | Spend validation |
| `app/Http/Requests/HitDice/RecoverHitDiceRequest.php` | Recover validation |

### Service Methods

```php
class HitDiceService
{
    public function getHitDice(Character $character): array;
    public function spend(Character $character, string $dieType, int $quantity): array;
    public function recover(Character $character, ?int $quantity = null): array;
}
```

### CharacterStatsResource Integration

Add hit dice to the existing stats endpoint for convenience:

```php
// In CharacterStatsResource::toArray()
'hit_dice' => $this->resource->hitDice,
```

This allows the frontend to get hit dice with other stats without an extra API call.

## Error Handling

| Scenario | HTTP Status | Error |
|----------|-------------|-------|
| Character not found | 404 | Standard Laravel |
| Invalid die type | 422 | Validation error |
| Insufficient dice | 422 | `{"die_type": ["Not enough d10 hit dice available. Have 1, need 3."]}` |
| Quantity exceeds spent | 422 | `{"quantity": ["Cannot recover 5 hit dice. Only 3 are spent."]}` |

## Testing Strategy

### Unit Tests (HitDiceService)
- `getHitDice()` returns correct structure for single/multiclass
- `spend()` decrements correct class's hit_dice_spent
- `spend()` throws when insufficient dice
- `recover()` increments correctly, preferring larger dice
- `recover()` with null quantity uses half-total rule

### Feature Tests (API)
- GET returns correct format
- POST spend updates database
- POST recover updates database
- Validation rejects invalid inputs
- 404 for non-existent character

## Implementation Order

1. HitDiceService with unit tests
2. HitDiceResource
3. Form Requests
4. HitDiceController with feature tests
5. Routes
6. CharacterStatsResource integration
7. Update CHANGELOG.md
