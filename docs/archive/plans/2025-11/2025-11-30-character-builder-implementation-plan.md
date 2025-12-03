# Character Builder API - Implementation Plan

**Date:** 2025-11-30
**Design Doc:** `2025-11-30-character-builder-api-design.md`
**GitHub Issue:** #21
**Estimated Effort:** 12-15 hours (v1)
**Status:** ✅ COMPLETED (2025-12-01)
**PR:** https://github.com/dfox288/dnd-rulebook-parser/pull/9

---

## Completion Summary

| Phase | Description | Status | Tests |
|-------|-------------|--------|-------|
| 1 | Foundation (migrations, models, CharacterStatCalculator) | ✅ Complete | 28 unit |
| 2 | Character CRUD API with calculated stats | ✅ Complete | 22 feature |
| 3 | Spell Management (learn, prepare, available) | ✅ Complete | 17 feature |
| 4 | Integration & Polish (stats endpoint, caching) | ✅ Complete | 9 integration |

**Total: 76 tests, 1,600+ assertions, all passing**

---

## Development Approach

### TDD Mandate
All code will be written using Test-Driven Development:
1. **RED**: Write a failing test first
2. **GREEN**: Write minimal code to make the test pass
3. **REFACTOR**: Clean up while keeping tests green

No implementation code without a failing test first. This is non-negotiable.

### Code Review
After each phase completion, use `superpowers:code-reviewer` agent to:
- Review implementation against this plan
- Check for security vulnerabilities
- Verify D&D 5e rule accuracy
- Ensure coding standards are followed

### Branch Strategy
- Work on dedicated branch: `feature/character-builder-api`
- Commit after each task completion
- Squash merge to main after full phase completion and review

---

## Pre-flight Checklist

- [x] Runner: Docker Compose / Sail (`docker compose exec php ...`)
- [x] Branch: Create `feature/character-builder-api` from main
- [x] Design doc reviewed and approved
- [x] Existing tests passing before starting
- [x] Git status clean on main branch

---

## Phase 1: Foundation (4-5 hours)

### Task 1.1: Create migrations

**Files:**
- `database/migrations/xxxx_create_characters_table.php`
- `database/migrations/xxxx_create_character_classes_table.php`
- `database/migrations/xxxx_create_character_spells_table.php`
- `database/migrations/xxxx_create_character_proficiencies_table.php`
- `database/migrations/xxxx_create_character_features_table.php`
- `database/migrations/xxxx_create_character_equipment_table.php`
- `database/migrations/xxxx_create_character_ability_adjustments_table.php`

**Steps:**
```bash
# Create all migrations
docker compose exec php php artisan make:migration create_characters_table
docker compose exec php php artisan make:migration create_character_classes_table
docker compose exec php php artisan make:migration create_character_spells_table
docker compose exec php php artisan make:migration create_character_proficiencies_table
docker compose exec php php artisan make:migration create_character_features_table
docker compose exec php php artisan make:migration create_character_equipment_table
docker compose exec php php artisan make:migration create_character_ability_adjustments_table
```

**Schema:** See design doc for full column definitions.

**Validation:**
```bash
docker compose exec php php artisan migrate
docker compose exec php php artisan migrate:rollback
docker compose exec php php artisan migrate
```

---

### Task 1.2: Create Character model with relationships

**File:** `app/Models/Character.php`

**Relationships:**
```php
public function race(): BelongsTo
public function background(): BelongsTo
public function classes(): HasMany  // CharacterClass pivot
public function spells(): HasMany   // CharacterSpell
public function proficiencies(): HasMany
public function features(): HasMany
public function equipment(): HasMany
public function abilityAdjustments(): HasMany
```

**Computed accessors:**
```php
public function getTotalLevelAttribute(): int  // Sum of class levels
```

---

### Task 1.3: Create related models

**Files:**
- `app/Models/CharacterClassPivot.php` (or `CharacterCharacterClass`)
- `app/Models/CharacterSpell.php`
- `app/Models/CharacterProficiency.php`
- `app/Models/CharacterFeature.php`
- `app/Models/CharacterEquipment.php`
- `app/Models/CharacterAbilityAdjustment.php`

Each with appropriate `$fillable`, `$casts`, and relationships.

---

### Task 1.4: Create factories

**Files:**
- `database/factories/CharacterFactory.php`
- `database/factories/CharacterClassPivotFactory.php`
- `database/factories/CharacterSpellFactory.php`
- `database/factories/CharacterProficiencyFactory.php`
- `database/factories/CharacterFeatureFactory.php`
- `database/factories/CharacterEquipmentFactory.php`
- `database/factories/CharacterAbilityAdjustmentFactory.php`

**CharacterFactory example:**
```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'user_id' => null,
        'race_id' => Race::inRandomOrder()->first()->id,
        'background_id' => Background::inRandomOrder()->first()->id,
        'base_str' => fake()->numberBetween(8, 15),
        'base_dex' => fake()->numberBetween(8, 15),
        'base_con' => fake()->numberBetween(8, 15),
        'base_int' => fake()->numberBetween(8, 15),
        'base_wis' => fake()->numberBetween(8, 15),
        'base_cha' => fake()->numberBetween(8, 15),
        'current_hp' => 10,
        'temp_hp' => 0,
        'status' => 'draft',
    ];
}

public function complete(): static
{
    return $this->state(['status' => 'complete']);
}

public function withClass(int $classId, int $level = 1): static
{
    return $this->afterCreating(function (Character $character) use ($classId, $level) {
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $classId,
            'level' => $level,
            'is_primary' => true,
        ]);
    });
}
```

**Validation:**
```bash
docker compose exec php php artisan tinker --execute="App\Models\Character::factory()->create()"
```

---

### Task 1.5: Write CharacterStatCalculator unit tests (TDD RED)

**File:** `tests/Unit/Services/CharacterStatCalculatorTest.php`

**Test cases:**
```php
#[Test]
public function it_calculates_ability_modifier()
{
    $calc = new CharacterStatCalculator();

    expect($calc->abilityModifier(1))->toBe(-5);
    expect($calc->abilityModifier(8))->toBe(-1);
    expect($calc->abilityModifier(10))->toBe(0);
    expect($calc->abilityModifier(11))->toBe(0);
    expect($calc->abilityModifier(12))->toBe(1);
    expect($calc->abilityModifier(15))->toBe(2);
    expect($calc->abilityModifier(18))->toBe(4);
    expect($calc->abilityModifier(20))->toBe(5);
}

#[Test]
public function it_calculates_proficiency_bonus_by_level()
{
    $calc = new CharacterStatCalculator();

    expect($calc->proficiencyBonus(1))->toBe(2);
    expect($calc->proficiencyBonus(4))->toBe(2);
    expect($calc->proficiencyBonus(5))->toBe(3);
    expect($calc->proficiencyBonus(8))->toBe(3);
    expect($calc->proficiencyBonus(9))->toBe(4);
    expect($calc->proficiencyBonus(12))->toBe(4);
    expect($calc->proficiencyBonus(13))->toBe(5);
    expect($calc->proficiencyBonus(16))->toBe(5);
    expect($calc->proficiencyBonus(17))->toBe(6);
    expect($calc->proficiencyBonus(20))->toBe(6);
}

#[Test]
public function it_calculates_spell_save_dc()
{
    // DC = 8 + proficiency + ability modifier
    $calc = new CharacterStatCalculator();

    // Level 1, INT 16 (+3): DC = 8 + 2 + 3 = 13
    expect($calc->spellSaveDC(proficiencyBonus: 2, abilityModifier: 3))->toBe(13);

    // Level 5, WIS 18 (+4): DC = 8 + 3 + 4 = 15
    expect($calc->spellSaveDC(proficiencyBonus: 3, abilityModifier: 4))->toBe(15);
}

#[Test]
public function it_calculates_skill_modifier_without_proficiency()
{
    $calc = new CharacterStatCalculator();

    // DEX 14 (+2), not proficient
    expect($calc->skillModifier(abilityModifier: 2, proficient: false, expertise: false, proficiencyBonus: 2))->toBe(2);
}

#[Test]
public function it_calculates_skill_modifier_with_proficiency()
{
    $calc = new CharacterStatCalculator();

    // DEX 14 (+2), proficient, level 1 (+2 prof)
    expect($calc->skillModifier(abilityModifier: 2, proficient: true, expertise: false, proficiencyBonus: 2))->toBe(4);
}

#[Test]
public function it_calculates_skill_modifier_with_expertise()
{
    $calc = new CharacterStatCalculator();

    // DEX 14 (+2), expertise, level 1 (+2 prof doubled = +4)
    expect($calc->skillModifier(abilityModifier: 2, proficient: true, expertise: true, proficiencyBonus: 2))->toBe(6);
}

#[Test]
public function it_calculates_wizard_spell_slots()
{
    $calc = new CharacterStatCalculator();

    // Level 1 Wizard: 2 first-level slots
    expect($calc->getSpellSlots('wizard', 1))->toBe([1 => 2]);

    // Level 3 Wizard: 4 first, 2 second
    expect($calc->getSpellSlots('wizard', 3))->toBe([1 => 4, 2 => 2]);

    // Level 5 Wizard: 4 first, 3 second, 2 third
    expect($calc->getSpellSlots('wizard', 5))->toBe([1 => 4, 2 => 3, 3 => 2]);
}

#[Test]
public function it_calculates_max_hp_for_level_1()
{
    $calc = new CharacterStatCalculator();

    // Wizard (d6) with CON 14 (+2): 6 + 2 = 8
    expect($calc->calculateMaxHP(hitDie: 6, level: 1, conModifier: 2))->toBe(8);

    // Fighter (d10) with CON 16 (+3): 10 + 3 = 13
    expect($calc->calculateMaxHP(hitDie: 10, level: 1, conModifier: 3))->toBe(13);
}

#[Test]
public function it_calculates_max_hp_for_higher_levels()
{
    $calc = new CharacterStatCalculator();

    // Wizard (d6) level 5, CON 14 (+2)
    // Level 1: 6 + 2 = 8
    // Levels 2-5: 4 × (avg 4 + 2) = 4 × 6 = 24
    // Total: 8 + 24 = 32
    expect($calc->calculateMaxHP(hitDie: 6, level: 5, conModifier: 2))->toBe(32);
}

#[Test]
public function it_calculates_armor_class_unarmored()
{
    $calc = new CharacterStatCalculator();

    // No armor, DEX 14 (+2): AC = 10 + 2 = 12
    expect($calc->calculateAC(dexModifier: 2, armorBaseAC: null, armorMaxDex: null, shieldBonus: 0, otherBonuses: 0))->toBe(12);
}

#[Test]
public function it_calculates_armor_class_with_light_armor()
{
    $calc = new CharacterStatCalculator();

    // Leather (11 base), DEX 16 (+3): AC = 11 + 3 = 14
    expect($calc->calculateAC(dexModifier: 3, armorBaseAC: 11, armorMaxDex: null, shieldBonus: 0, otherBonuses: 0))->toBe(14);
}

#[Test]
public function it_calculates_armor_class_with_medium_armor()
{
    $calc = new CharacterStatCalculator();

    // Half Plate (15 base, max +2 DEX), DEX 18 (+4): AC = 15 + 2 = 17 (capped)
    expect($calc->calculateAC(dexModifier: 4, armorBaseAC: 15, armorMaxDex: 2, shieldBonus: 0, otherBonuses: 0))->toBe(17);
}

#[Test]
public function it_calculates_armor_class_with_heavy_armor()
{
    $calc = new CharacterStatCalculator();

    // Plate (18 base, no DEX), DEX 14 (+2): AC = 18 (DEX ignored)
    expect($calc->calculateAC(dexModifier: 2, armorBaseAC: 18, armorMaxDex: 0, shieldBonus: 0, otherBonuses: 0))->toBe(18);
}

#[Test]
public function it_calculates_armor_class_with_shield()
{
    $calc = new CharacterStatCalculator();

    // Chain Mail (16 base, no DEX) + Shield (+2): AC = 18
    expect($calc->calculateAC(dexModifier: 2, armorBaseAC: 16, armorMaxDex: 0, shieldBonus: 2, otherBonuses: 0))->toBe(18);
}

#[Test]
public function it_calculates_wizard_preparation_limit()
{
    $calc = new CharacterStatCalculator();

    // Wizard: INT mod + level
    // Level 1, INT 16 (+3): 3 + 1 = 4
    expect($calc->getPreparationLimit('wizard', level: 1, abilityModifier: 3))->toBe(4);

    // Level 5, INT 18 (+4): 4 + 5 = 9
    expect($calc->getPreparationLimit('wizard', level: 5, abilityModifier: 4))->toBe(9);
}

#[Test]
public function it_calculates_paladin_preparation_limit()
{
    $calc = new CharacterStatCalculator();

    // Paladin: CHA mod + half level (rounded down)
    // Level 2, CHA 16 (+3): 3 + 1 = 4
    expect($calc->getPreparationLimit('paladin', level: 2, abilityModifier: 3))->toBe(4);

    // Level 5, CHA 16 (+3): 3 + 2 = 5
    expect($calc->getPreparationLimit('paladin', level: 5, abilityModifier: 3))->toBe(5);
}

#[Test]
public function it_returns_null_preparation_limit_for_known_casters()
{
    $calc = new CharacterStatCalculator();

    // Sorcerer, Bard, Warlock don't prepare - they know a fixed number
    expect($calc->getPreparationLimit('sorcerer', level: 5, abilityModifier: 4))->toBeNull();
    expect($calc->getPreparationLimit('bard', level: 5, abilityModifier: 4))->toBeNull();
    expect($calc->getPreparationLimit('warlock', level: 5, abilityModifier: 4))->toBeNull();
}
```

**Run tests (should fail):**
```bash
docker compose exec php php artisan test --filter=CharacterStatCalculatorTest
```

---

### Task 1.6: Implement CharacterStatCalculator (TDD GREEN)

**File:** `app/Services/CharacterStatCalculator.php`

```php
<?php

namespace App\Services;

class CharacterStatCalculator
{
    /**
     * Calculate ability modifier from score.
     * Formula: floor((score - 10) / 2)
     */
    public function abilityModifier(int $score): int
    {
        return (int) floor(($score - 10) / 2);
    }

    /**
     * Calculate proficiency bonus from total character level.
     * Formula: 2 + floor((level - 1) / 4)
     */
    public function proficiencyBonus(int $level): int
    {
        return 2 + (int) floor(($level - 1) / 4);
    }

    /**
     * Calculate spell save DC.
     * Formula: 8 + proficiency bonus + spellcasting ability modifier
     */
    public function spellSaveDC(int $proficiencyBonus, int $abilityModifier): int
    {
        return 8 + $proficiencyBonus + $abilityModifier;
    }

    /**
     * Calculate skill modifier.
     */
    public function skillModifier(int $abilityModifier, bool $proficient, bool $expertise, int $proficiencyBonus): int
    {
        $modifier = $abilityModifier;

        if ($proficient) {
            $modifier += $proficiencyBonus;
        }

        if ($expertise) {
            $modifier += $proficiencyBonus; // Doubles proficiency
        }

        return $modifier;
    }

    /**
     * Calculate max HP.
     * Level 1: max hit die + CON mod
     * Higher levels: add (avg hit die + CON mod) per level
     */
    public function calculateMaxHP(int $hitDie, int $level, int $conModifier): int
    {
        // Level 1: max hit die
        $hp = $hitDie + $conModifier;

        // Levels 2+: average hit die (rounded up) + CON
        if ($level > 1) {
            $avgHitDie = (int) ceil($hitDie / 2) + 1; // d6=4, d8=5, d10=6, d12=7
            $hp += ($level - 1) * ($avgHitDie + $conModifier);
        }

        return max(1, $hp); // Minimum 1 HP
    }

    /**
     * Calculate armor class.
     */
    public function calculateAC(
        int $dexModifier,
        ?int $armorBaseAC,
        ?int $armorMaxDex,
        int $shieldBonus,
        int $otherBonuses
    ): int {
        // No armor: 10 + DEX
        if ($armorBaseAC === null) {
            return 10 + $dexModifier + $shieldBonus + $otherBonuses;
        }

        // With armor: base + limited DEX
        $dexBonus = $dexModifier;
        if ($armorMaxDex !== null) {
            $dexBonus = min($dexModifier, $armorMaxDex);
        }

        return $armorBaseAC + $dexBonus + $shieldBonus + $otherBonuses;
    }

    /**
     * Get spell slots for a class at a given level.
     */
    public function getSpellSlots(string $classSlug, int $level): array
    {
        // Full caster spell slot table
        $fullCasterSlots = [
            1 => [1 => 2],
            2 => [1 => 3],
            3 => [1 => 4, 2 => 2],
            4 => [1 => 4, 2 => 3],
            5 => [1 => 4, 2 => 3, 3 => 2],
            // ... continue for all 20 levels
        ];

        // TODO: Handle half-casters, third-casters, Warlock pact magic
        return $fullCasterSlots[$level] ?? [];
    }

    /**
     * Get preparation limit for prepared casters.
     * Returns null for "known" casters (sorcerer, bard, warlock).
     */
    public function getPreparationLimit(string $classSlug, int $level, int $abilityModifier): ?int
    {
        return match ($classSlug) {
            'wizard', 'cleric', 'druid' => $abilityModifier + $level,
            'paladin', 'ranger' => $abilityModifier + (int) floor($level / 2),
            default => null, // Sorcerer, Bard, Warlock know spells, don't prepare
        };
    }
}
```

**Run tests (should pass):**
```bash
docker compose exec php php artisan test --filter=CharacterStatCalculatorTest
```

---

### Task 1.7: Quality gate

```bash
docker compose exec php ./vendor/bin/pint
docker compose exec php php artisan test --testsuite=Unit-Pure
```

### Task 1.8: Phase 1 Code Review

Use `superpowers:code-reviewer` agent to review:
- [ ] Migrations follow Laravel conventions
- [ ] Models have proper relationships and $fillable
- [ ] Factories create valid test data
- [ ] CharacterStatCalculator math matches D&D 5e rules
- [ ] All tests are meaningful (not just testing Laravel)

**Commit:** `feat: add character builder foundation - models, migrations, stat calculator`

---

## Phase 2: Creation Flow (5-6 hours)

### Task 2.1: Write failing feature tests for character creation

**File:** `tests/Feature/Api/CharacterBuilderTest.php`

```php
#[Test]
public function it_creates_a_draft_character()
{
    $response = $this->postJson('/api/v1/characters', [
        'name' => 'Gandalf',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Gandalf')
        ->assertJsonPath('data.status', 'draft');
}

#[Test]
public function it_chooses_race_for_character()
{
    $character = Character::factory()->create();
    $race = Race::where('name', 'High Elf')->first();

    $response = $this->postJson("/api/v1/characters/{$character->id}/race", [
        'race_id' => $race->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.race.name', 'High Elf');
}

#[Test]
public function it_validates_point_buy_ability_scores()
{
    $character = Character::factory()->create();

    // Valid point buy (27 points)
    $response = $this->postJson("/api/v1/characters/{$character->id}/abilities", [
        'method' => 'point_buy',
        'scores' => [
            'STR' => 8, 'DEX' => 14, 'CON' => 13,
            'INT' => 15, 'WIS' => 12, 'CHA' => 10,
        ],
    ]);

    $response->assertStatus(200);
}

#[Test]
public function it_rejects_invalid_point_buy()
{
    $character = Character::factory()->create();

    // Invalid: too many points
    $response = $this->postJson("/api/v1/characters/{$character->id}/abilities", [
        'method' => 'point_buy',
        'scores' => [
            'STR' => 15, 'DEX' => 15, 'CON' => 15,
            'INT' => 15, 'WIS' => 15, 'CHA' => 15,
        ],
    ]);

    $response->assertStatus(422);
}

#[Test]
public function it_returns_pending_choices()
{
    $character = Character::factory()->create();
    // Set up character with Wizard class that needs skill/spell choices

    $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'skills',
                'cantrips',
                'spells',
            ],
        ]);
}
```

---

### Task 2.2: Create DTOs

**Files:**
- `app/DTOs/CharacterStatsDTO.php`
- `app/DTOs/PendingChoicesDTO.php`
- `app/DTOs/AbilityScoresDTO.php`

---

### Task 2.3: Create Form Requests

**Files:**
- `app/Http/Requests/Character/StoreCharacterRequest.php`
- `app/Http/Requests/Character/ChooseRaceRequest.php`
- `app/Http/Requests/Character/ChooseClassRequest.php`
- `app/Http/Requests/Character/AssignAbilitiesRequest.php`
- `app/Http/Requests/Character/ChooseBackgroundRequest.php`
- `app/Http/Requests/Character/ResolveChoicesRequest.php`

---

### Task 2.4: Create CharacterBuilderService

**File:** `app/Services/CharacterBuilderService.php`

Implement methods from design doc.

---

### Task 2.5: Create ChoiceValidationService

**File:** `app/Services/ChoiceValidationService.php`

Key validations:
- Point buy: exactly 27 points, scores 8-15
- Standard array: exactly [15, 14, 13, 12, 10, 8]
- Skill choices: from allowed list, correct count

---

### Task 2.6: Create CharacterController

**File:** `app/Http/Controllers/Api/CharacterController.php`

---

### Task 2.7: Create CharacterResource

**File:** `app/Http/Resources/CharacterResource.php`

Include computed stats from CharacterStatCalculator.

---

### Task 2.8: Add routes

**File:** `routes/api.php`

```php
Route::prefix('characters')->group(function () {
    Route::post('/', [CharacterController::class, 'store']);
    Route::get('/{character}', [CharacterController::class, 'show']);
    Route::patch('/{character}', [CharacterController::class, 'update']);
    Route::delete('/{character}', [CharacterController::class, 'destroy']);

    Route::post('/{character}/race', [CharacterController::class, 'chooseRace']);
    Route::post('/{character}/class', [CharacterController::class, 'chooseClass']);
    Route::post('/{character}/abilities', [CharacterController::class, 'assignAbilities']);
    Route::post('/{character}/background', [CharacterController::class, 'chooseBackground']);
    Route::get('/{character}/pending-choices', [CharacterController::class, 'pendingChoices']);
    Route::post('/{character}/choices', [CharacterController::class, 'resolveChoices']);
    Route::post('/{character}/finalize', [CharacterController::class, 'finalize']);

    Route::get('/{character}/stats', [CharacterController::class, 'stats']);
});
```

---

### Task 2.9: Run feature tests and iterate

```bash
docker compose exec php php artisan test --filter=CharacterBuilderTest
```

### Task 2.10: Phase 2 Code Review

Use `superpowers:code-reviewer` agent to review:
- [ ] Form Requests have proper validation rules
- [ ] CharacterBuilderService follows single responsibility
- [ ] ChoiceValidationService correctly enforces D&D rules
- [ ] CharacterResource includes all necessary computed fields
- [ ] Routes follow RESTful conventions
- [ ] Error responses are clear and helpful

**Commit:** `feat: add character creation flow - race, class, abilities, background`

---

## Phase 3: Spell Management (3-4 hours)

### Task 3.1: Write failing spell management tests

**File:** `tests/Feature/Api/CharacterSpellTest.php`

```php
#[Test]
public function it_lists_available_spells_for_wizard()
{
    $character = Character::factory()
        ->withClass($wizardId, level: 1)
        ->create();

    $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells");

    $response->assertStatus(200);
    // Should only include wizard spells up to level 1
}

#[Test]
public function it_learns_a_spell()
{
    $character = Character::factory()->withClass($wizardId)->create();
    $spell = Spell::where('name', 'Magic Missile')->first();

    $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
        'spell_id' => $spell->id,
    ]);

    $response->assertStatus(201);
    expect($character->spells()->where('spell_id', $spell->id)->exists())->toBeTrue();
}

#[Test]
public function it_rejects_spell_not_on_class_list()
{
    $character = Character::factory()->withClass($wizardId)->create();
    $spell = Spell::where('name', 'Cure Wounds')->first(); // Cleric spell

    $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
        'spell_id' => $spell->id,
    ]);

    $response->assertStatus(422);
}

#[Test]
public function it_prepares_and_unprepares_spells()
{
    // ... test preparation toggle
}
```

---

### Task 3.2: Create SpellManagerService

**File:** `app/Services/SpellManagerService.php`

---

### Task 3.3: Add spell routes and controller methods

---

### Task 3.4: Quality gate

```bash
docker compose exec php ./vendor/bin/pint
docker compose exec php php artisan test --testsuite=Feature-DB --filter=Character
```

### Task 3.5: Phase 3 Code Review

Use `superpowers:code-reviewer` agent to review:
- [ ] SpellManagerService correctly filters by class spell list
- [ ] Preparation limits enforced per D&D 5e rules
- [ ] "Always prepared" spells (domain, etc.) handled correctly
- [ ] Wizard spellbook vs known spells distinction implemented
- [ ] Meilisearch used efficiently for spell filtering

**Commit:** `feat: add spell management - learn, prepare, available spells`

---

## Phase 4: Integration & Polish (2-3 hours)

### Task 4.1: Full character creation integration test

**File:** `tests/Feature/Api/CharacterCreationFlowTest.php`

```php
#[Test]
public function it_creates_complete_character_from_scratch()
{
    // 1. Create draft
    $response = $this->postJson('/api/v1/characters', ['name' => 'Elara']);
    $characterId = $response->json('data.id');

    // 2. Choose High Elf
    $this->postJson("/api/v1/characters/{$characterId}/race", [
        'race_id' => $highElfId,
    ])->assertStatus(200);

    // 3. Choose Wizard
    $this->postJson("/api/v1/characters/{$characterId}/class", [
        'class_id' => $wizardId,
    ])->assertStatus(200);

    // 4. Assign abilities (point buy)
    $this->postJson("/api/v1/characters/{$characterId}/abilities", [
        'method' => 'point_buy',
        'scores' => ['STR' => 8, 'DEX' => 14, 'CON' => 13, 'INT' => 15, 'WIS' => 12, 'CHA' => 10],
    ])->assertStatus(200);

    // 5. Choose Sage background
    $this->postJson("/api/v1/characters/{$characterId}/background", [
        'background_id' => $sageId,
    ])->assertStatus(200);

    // 6. Check pending choices
    $choices = $this->getJson("/api/v1/characters/{$characterId}/pending-choices");

    // 7. Resolve choices (skills, cantrips, spells)
    $this->postJson("/api/v1/characters/{$characterId}/choices", [
        'skills' => [$arcanaId, $historyId],
        'cantrips' => [$lightId, $mageHandId, $prestidigitationId],
        'spells' => [$magicMissileId, $shieldId, $detectMagicId, $sleepId, $featherFallId, $charmPersonId],
    ])->assertStatus(200);

    // 8. Finalize
    $this->postJson("/api/v1/characters/{$characterId}/finalize")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'complete');

    // 9. Verify computed stats
    $stats = $this->getJson("/api/v1/characters/{$characterId}/stats");
    $stats->assertJsonPath('data.ability_scores.INT.total', 16) // 15 base + 1 High Elf
          ->assertJsonPath('data.proficiency_bonus', 2)
          ->assertJsonPath('data.spellcasting.spell_save_dc', 14); // 8 + 2 + 4
}
```

---

### Task 4.2: Add caching for stats endpoint

```php
// In CharacterController::stats()
return Cache::remember(
    "character:{$character->id}:stats",
    now()->addMinutes(15),
    fn() => new CharacterStatsResource($this->statCalculator->calculate($character))
);
```

---

### Task 4.3: Add events for cache invalidation

**Files:**
- `app/Events/CharacterUpdated.php`
- `app/Listeners/InvalidateCharacterCache.php`

---

### Task 4.4: Final quality gate

```bash
docker compose exec php ./vendor/bin/pint
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 4.5: Final Code Review

Use `superpowers:code-reviewer` agent for comprehensive review:
- [ ] All D&D 5e calculations are accurate
- [ ] No N+1 query issues (check eager loading)
- [ ] Caching strategy implemented correctly
- [ ] Events fire and invalidate cache properly
- [ ] Integration test covers complete happy path
- [ ] Error handling is consistent
- [ ] No security vulnerabilities (SQL injection, mass assignment)
- [ ] Code follows project patterns (gold standard: SpellController)

**Commit:** `feat: complete character builder v1 - integration and polish`

### Task 4.6: Merge to Main

```bash
git checkout main
git pull
git merge --squash feature/character-builder-api
git commit -m "feat: Character Builder API v1 (Issue #21)

Implements D&D 5e character creation with:
- 7 new tables for character data
- CharacterStatCalculator for all derived stats
- Full creation flow (race → class → abilities → background → choices)
- Spell management (learn, prepare, available)
- 75+ tests covering all functionality

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
git push
```

---

## Validation Checklist

Before marking v1 complete:

- [x] All 5 migrations run cleanly (up and down)
- [x] All factories create valid records
- [x] CharacterStatCalculator has 20+ passing unit tests (28 tests)
- [x] Character creation flow has 15+ passing feature tests (22 tests)
- [x] Spell management has 10+ passing tests (17 tests)
- [x] Integration test creates complete character (9 tests)
- [x] Stats endpoint returns all computed values
- [x] Pint passes with no changes
- [x] All test suites green

---

## Post-v1 Backlog

- [ ] Point buy / Standard array ability scores (#87)
- [ ] Level up flow
- [ ] Feat selection at ASI levels
- [ ] Equipment stat integration
- [ ] Multiclass support
- [ ] User authentication / ownership
- [ ] OpenAPI documentation via Scramble

---

**✅ COMPLETED - PR #9 ready for merge**
