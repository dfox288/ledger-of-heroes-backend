# Ability Score Methods - Implementation Plan

**Date:** 2025-12-01
**Issue:** #87 - Character Builder: Add point buy and standard array ability score methods
**Branch:** `feature/issue-87-ability-score-methods`
**Estimated Effort:** 2-3 hours

---

## Overview

Add support for point buy and standard array ability score assignment methods to the Character Builder API. Currently only manual entry (range 3-20) is supported.

### Methods

| Method | Description | Constraints |
|--------|-------------|-------------|
| `manual` | Direct assignment | Scores 3-20, partial updates allowed |
| `point_buy` | 27 points to spend | Scores 8-15 only, all 6 required together |
| `standard_array` | Fixed set assignment | Exactly [15,14,13,12,10,8], each used once |

### Point Buy Costs (PHB)

| Score | Cost |
|-------|------|
| 8 | 0 |
| 9 | 1 |
| 10 | 2 |
| 11 | 3 |
| 12 | 4 |
| 13 | 5 |
| 14 | 7 |
| 15 | 9 |

---

## Pre-flight Checklist

- [ ] Runner: Docker Compose (`docker compose exec php ...`)
- [ ] Branch: Create from current feature branch or main
- [ ] Existing tests passing
- [ ] Git status clean

---

## Phase 1: Foundation (Migration + Enum)

### Task 1.1: Create migration for ability_score_method column

**File:** `database/migrations/xxxx_add_ability_score_method_to_characters_table.php`

```php
Schema::table('characters', function (Blueprint $table) {
    $table->string('ability_score_method', 20)->default('manual')->after('charisma');
});
```

**Verification:**
```bash
docker compose exec php php artisan migrate
docker compose exec php php artisan migrate:rollback
docker compose exec php php artisan migrate
```

### Task 1.2: Create AbilityScoreMethod enum

**File:** `app/Enums/AbilityScoreMethod.php`

```php
<?php

namespace App\Enums;

enum AbilityScoreMethod: string
{
    case Manual = 'manual';
    case PointBuy = 'point_buy';
    case StandardArray = 'standard_array';
}
```

### Task 1.3: Update Character model

**File:** `app/Models/Character.php`

- Add `ability_score_method` to `$fillable`
- Add cast: `'ability_score_method' => AbilityScoreMethod::class`

**Commit:** `feat(#87): add ability_score_method column and enum`

---

## Phase 2: Validation Service (TDD)

### Task 2.1: Write failing unit tests for AbilityScoreValidatorService

**File:** `tests/Unit/Services/AbilityScoreValidatorServiceTest.php`

```php
#[Test]
public function it_calculates_point_buy_cost_for_each_score()
{
    $service = new AbilityScoreValidatorService();

    expect($service->getPointBuyCost(8))->toBe(0);
    expect($service->getPointBuyCost(9))->toBe(1);
    expect($service->getPointBuyCost(10))->toBe(2);
    expect($service->getPointBuyCost(11))->toBe(3);
    expect($service->getPointBuyCost(12))->toBe(4);
    expect($service->getPointBuyCost(13))->toBe(5);
    expect($service->getPointBuyCost(14))->toBe(7);
    expect($service->getPointBuyCost(15))->toBe(9);
}

#[Test]
public function it_throws_for_invalid_point_buy_score()
{
    $service = new AbilityScoreValidatorService();

    expect(fn() => $service->getPointBuyCost(7))->toThrow(InvalidArgumentException::class);
    expect(fn() => $service->getPointBuyCost(16))->toThrow(InvalidArgumentException::class);
}

#[Test]
public function it_calculates_total_point_buy_cost()
{
    $service = new AbilityScoreValidatorService();

    // 15+14+13+12+10+8 = standard array costs 9+7+5+4+2+0 = 27
    $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
    expect($service->calculateTotalCost($scores))->toBe(27);
}

#[Test]
public function it_validates_valid_point_buy()
{
    $service = new AbilityScoreValidatorService();

    $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
    expect($service->validatePointBuy($scores))->toBeTrue();
}

#[Test]
public function it_rejects_point_buy_over_budget()
{
    $service = new AbilityScoreValidatorService();

    // 15+15+15+8+8+8 = 9+9+9+0+0+0 = 27, but let's try 15+15+14+10+8+8 = 9+9+7+2+0+0 = 27
    // Actually over: 15+15+15+10+8+8 = 9+9+9+2+0+0 = 29
    $scores = ['STR' => 15, 'DEX' => 15, 'CON' => 15, 'INT' => 10, 'WIS' => 8, 'CHA' => 8];
    expect($service->validatePointBuy($scores))->toBeFalse();
}

#[Test]
public function it_rejects_point_buy_under_budget()
{
    $service = new AbilityScoreValidatorService();

    // All 8s = 0 points
    $scores = ['STR' => 8, 'DEX' => 8, 'CON' => 8, 'INT' => 8, 'WIS' => 8, 'CHA' => 8];
    expect($service->validatePointBuy($scores))->toBeFalse();
}

#[Test]
public function it_validates_valid_standard_array()
{
    $service = new AbilityScoreValidatorService();

    $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
    expect($service->validateStandardArray($scores))->toBeTrue();

    // Different arrangement
    $scores2 = ['STR' => 8, 'DEX' => 10, 'CON' => 12, 'INT' => 13, 'WIS' => 14, 'CHA' => 15];
    expect($service->validateStandardArray($scores2))->toBeTrue();
}

#[Test]
public function it_rejects_standard_array_with_wrong_values()
{
    $service = new AbilityScoreValidatorService();

    // 16 is not in standard array
    $scores = ['STR' => 16, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
    expect($service->validateStandardArray($scores))->toBeFalse();
}

#[Test]
public function it_rejects_standard_array_with_duplicates()
{
    $service = new AbilityScoreValidatorService();

    // Two 15s
    $scores = ['STR' => 15, 'DEX' => 15, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
    expect($service->validateStandardArray($scores))->toBeFalse();
}

#[Test]
public function it_requires_all_six_scores_for_point_buy()
{
    $service = new AbilityScoreValidatorService();

    $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13]; // Missing 3
    expect($service->validatePointBuy($scores))->toBeFalse();
}

#[Test]
public function it_requires_all_six_scores_for_standard_array()
{
    $service = new AbilityScoreValidatorService();

    $scores = ['STR' => 15, 'DEX' => 14]; // Missing 4
    expect($service->validateStandardArray($scores))->toBeFalse();
}
```

**Run tests (should fail):**
```bash
docker compose exec php php artisan test --filter=AbilityScoreValidatorServiceTest
```

### Task 2.2: Implement AbilityScoreValidatorService

**File:** `app/Services/AbilityScoreValidatorService.php`

```php
<?php

namespace App\Services;

use InvalidArgumentException;

class AbilityScoreValidatorService
{
    public const STANDARD_ARRAY = [15, 14, 13, 12, 10, 8];

    public const POINT_BUY_COSTS = [
        8 => 0,
        9 => 1,
        10 => 2,
        11 => 3,
        12 => 4,
        13 => 5,
        14 => 7,
        15 => 9,
    ];

    public const POINT_BUY_BUDGET = 27;

    public const REQUIRED_ABILITIES = ['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'];

    public function getPointBuyCost(int $score): int
    {
        if (!isset(self::POINT_BUY_COSTS[$score])) {
            throw new InvalidArgumentException(
                "Score {$score} is invalid for point buy. Must be 8-15."
            );
        }

        return self::POINT_BUY_COSTS[$score];
    }

    public function calculateTotalCost(array $scores): int
    {
        $total = 0;
        foreach ($scores as $score) {
            $total += $this->getPointBuyCost($score);
        }
        return $total;
    }

    public function validatePointBuy(array $scores): bool
    {
        // Must have all 6 abilities
        if (!$this->hasAllAbilities($scores)) {
            return false;
        }

        // All scores must be 8-15
        foreach ($scores as $score) {
            if ($score < 8 || $score > 15) {
                return false;
            }
        }

        // Must spend exactly 27 points
        try {
            $total = $this->calculateTotalCost($scores);
            return $total === self::POINT_BUY_BUDGET;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function validateStandardArray(array $scores): bool
    {
        // Must have all 6 abilities
        if (!$this->hasAllAbilities($scores)) {
            return false;
        }

        // Sort both arrays and compare
        $values = array_values($scores);
        sort($values);

        $standard = self::STANDARD_ARRAY;
        sort($standard);

        return $values === $standard;
    }

    private function hasAllAbilities(array $scores): bool
    {
        foreach (self::REQUIRED_ABILITIES as $ability) {
            if (!isset($scores[$ability])) {
                return false;
            }
        }
        return count($scores) === 6;
    }
}
```

**Run tests (should pass):**
```bash
docker compose exec php php artisan test --filter=AbilityScoreValidatorServiceTest
```

**Commit:** `feat(#87): add AbilityScoreValidatorService with point buy and standard array validation`

---

## Phase 3: Form Request Validation (TDD)

### Task 3.1: Write failing feature tests for ability score method validation

**File:** `tests/Feature/Api/CharacterAbilityScoreMethodTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterAbilityScoreMethodTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_accepts_valid_point_buy_scores()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 15,
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.ability_score_method', 'point_buy')
            ->assertJsonPath('data.strength', 15);
    }

    #[Test]
    public function it_rejects_point_buy_over_budget()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 15,
            'dexterity' => 15,
            'constitution' => 15,
            'intelligence' => 10,
            'wisdom' => 8,
            'charisma' => 8,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ability_scores']);
    }

    #[Test]
    public function it_rejects_point_buy_with_score_outside_range()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 16, // Invalid: max is 15 for point buy
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_point_buy_with_missing_scores()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 15,
            'dexterity' => 14,
            // Missing other 4 scores
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_accepts_valid_standard_array_scores()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'standard_array',
            'strength' => 8,
            'dexterity' => 10,
            'constitution' => 12,
            'intelligence' => 13,
            'wisdom' => 14,
            'charisma' => 15,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.ability_score_method', 'standard_array');
    }

    #[Test]
    public function it_rejects_standard_array_with_wrong_values()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'standard_array',
            'strength' => 16, // Not in standard array
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_standard_array_with_duplicate_values()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'standard_array',
            'strength' => 15,
            'dexterity' => 15, // Duplicate
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_allows_manual_scores_with_partial_update()
    {
        $character = Character::factory()->create(['strength' => 10]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'manual',
            'strength' => 18, // Only updating one score
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.strength', 18);
    }

    #[Test]
    public function it_allows_manual_scores_in_full_range()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'manual',
            'strength' => 3,  // Min allowed for manual
            'dexterity' => 20, // Max allowed for manual
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function it_defaults_to_manual_when_method_not_specified()
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'strength' => 18,
        ]);

        $response->assertStatus(200);

        $character->refresh();
        expect($character->ability_score_method->value)->toBe('manual');
    }

    #[Test]
    public function it_allows_switching_from_point_buy_to_manual()
    {
        $character = Character::factory()->create([
            'ability_score_method' => 'point_buy',
            'strength' => 15,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'manual',
            'strength' => 18, // Now allowed since we're in manual mode
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.ability_score_method', 'manual')
            ->assertJsonPath('data.strength', 18);
    }

    #[Test]
    public function it_includes_ability_score_method_in_response()
    {
        $character = Character::factory()->create([
            'ability_score_method' => 'point_buy',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.ability_score_method', 'point_buy');
    }
}
```

### Task 3.2: Update CharacterUpdateRequest with conditional validation

**File:** `app/Http/Requests/Character/CharacterUpdateRequest.php`

Add custom validation using `withValidator()` or `after()` to call `AbilityScoreValidatorService`.

### Task 3.3: Update CharacterResource to include ability_score_method

**File:** `app/Http/Resources/CharacterResource.php`

### Task 3.4: Update CharacterFactory for new field

**File:** `database/factories/CharacterFactory.php`

**Commit:** `feat(#87): add ability score method validation to CharacterUpdateRequest`

---

## Phase 4: Quality Gates

### Task 4.1: Run all quality checks

```bash
docker compose exec php ./vendor/bin/pint
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 4.2: Update CHANGELOG.md

Add entry under [Unreleased]:
```markdown
### Added
- Point buy ability score method (27 points, scores 8-15)
- Standard array ability score method ([15, 14, 13, 12, 10, 8])
- `ability_score_method` field on characters
- `AbilityScoreValidatorService` for D&D 5e score validation
```

**Commit:** `docs: update CHANGELOG for ability score methods`

---

## Validation Checklist

Before marking complete:

- [ ] Migration runs cleanly (up and down)
- [ ] AbilityScoreValidatorService has 10+ unit tests passing
- [ ] Feature tests cover all validation scenarios (12+ tests)
- [ ] CharacterResource includes ability_score_method
- [ ] Pint passes with no changes
- [ ] All test suites green
- [ ] CHANGELOG updated

---

## Files to Create/Modify

### New Files
- `database/migrations/xxxx_add_ability_score_method_to_characters_table.php`
- `app/Enums/AbilityScoreMethod.php`
- `app/Services/AbilityScoreValidatorService.php`
- `tests/Unit/Services/AbilityScoreValidatorServiceTest.php`
- `tests/Feature/Api/CharacterAbilityScoreMethodTest.php`

### Modified Files
- `app/Models/Character.php` - Add fillable + cast
- `app/Http/Requests/Character/CharacterUpdateRequest.php` - Add conditional validation
- `app/Http/Resources/CharacterResource.php` - Add ability_score_method
- `database/factories/CharacterFactory.php` - Add ability_score_method
- `CHANGELOG.md` - Add entry

---

**Ready to execute with `laravel:executing-plans`**
