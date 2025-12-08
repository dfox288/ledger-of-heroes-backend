# HP Validation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Protect calculated HP from direct modification, allow mode switching, and support manual dice rolls.

**Architecture:** Add 'manual' selection type to HitPointRollChoiceHandler with roll_result validation. Add prohibited_if rule to CharacterUpdateRequest to block max_hit_points updates for calculated mode.

**Tech Stack:** Laravel 12.x, Pest 3.x, Form Requests, Choice Handlers

**Design Doc:** `../dnd-rulebook-project/docs/backend/plans/2025-12-08-hp-validation-design.md`

---

## Task 1: Add Manual Roll Support to HitPointRollChoiceHandler

**Files:**
- Modify: `app/Services/ChoiceHandlers/HitPointRollChoiceHandler.php:105-129`
- Test: `tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php`

### Step 1: Write failing test for manual roll with valid roll_result

Add to `tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php`:

```php
/** @test */
public function it_resolves_manual_roll_with_valid_roll_result(): void
{
    $class = CharacterClass::factory()->create([
        'name' => 'Fighter',
        'hit_die' => 10,
    ]);

    $character = Character::factory()->create([
        'constitution' => 14, // +2 modifier
        'max_hit_points' => 12,
        'current_hit_points' => 12,
    ]);

    $pivot = CharacterClassPivot::factory()->create([
        'character_id' => $character->id,
        'class_slug' => $class->full_slug,
        'level' => 2,
        'is_primary' => true,
    ]);
    $pivot->setRelation('characterClass', $class);

    $choice = new PendingChoice(
        id: 'hit_points:levelup:'.$character->id.':2',
        type: 'hit_points',
        subtype: null,
        source: 'level_up',
        sourceName: 'Level 2',
        levelGranted: 2,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: [
            'hit_die' => 'd10',
            'con_modifier' => 2,
            'class_slug' => 'fighter',
        ],
    );

    $this->handler->resolve($character, $choice, [
        'selected' => 'manual',
        'roll_result' => 7,
    ]);

    $character->refresh();

    // HP should be increased by exactly 9 (7 roll + 2 CON)
    $this->assertEquals(21, $character->max_hit_points); // 12 + 9
    $this->assertEquals(21, $character->current_hit_points);
}
```

### Step 2: Run test to verify it fails

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php --filter="it_resolves_manual_roll_with_valid_roll_result"
```

Expected: FAIL with "Selection must be \"roll\" or \"average\""

### Step 3: Write failing test for manual roll without roll_result

Add to test file:

```php
/** @test */
public function it_throws_exception_when_manual_roll_missing_roll_result(): void
{
    $this->expectException(InvalidSelectionException::class);
    $this->expectExceptionMessage('roll_result is required');

    $character = Character::factory()->create();

    $choice = new PendingChoice(
        id: 'hit_points:levelup:1:2',
        type: 'hit_points',
        subtype: null,
        source: 'level_up',
        sourceName: 'Level 2',
        levelGranted: 2,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: [
            'hit_die' => 'd10',
            'con_modifier' => 0,
        ],
    );

    $this->handler->resolve($character, $choice, ['selected' => 'manual']);
}
```

### Step 4: Write failing test for roll_result below 1

```php
/** @test */
public function it_throws_exception_when_roll_result_below_1(): void
{
    $this->expectException(InvalidSelectionException::class);
    $this->expectExceptionMessage('roll_result must be between 1 and 10');

    $character = Character::factory()->create();

    $choice = new PendingChoice(
        id: 'hit_points:levelup:1:2',
        type: 'hit_points',
        subtype: null,
        source: 'level_up',
        sourceName: 'Level 2',
        levelGranted: 2,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: [
            'hit_die' => 'd10',
            'con_modifier' => 0,
        ],
    );

    $this->handler->resolve($character, $choice, [
        'selected' => 'manual',
        'roll_result' => 0,
    ]);
}
```

### Step 5: Write failing test for roll_result above hit_die

```php
/** @test */
public function it_throws_exception_when_roll_result_above_hit_die(): void
{
    $this->expectException(InvalidSelectionException::class);
    $this->expectExceptionMessage('roll_result must be between 1 and 6');

    $character = Character::factory()->create();

    $choice = new PendingChoice(
        id: 'hit_points:levelup:1:2',
        type: 'hit_points',
        subtype: null,
        source: 'level_up',
        sourceName: 'Level 2',
        levelGranted: 2,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: [
            'hit_die' => 'd6',
            'con_modifier' => 0,
        ],
    );

    $this->handler->resolve($character, $choice, [
        'selected' => 'manual',
        'roll_result' => 7,
    ]);
}
```

### Step 6: Write failing test for manual roll with negative CON

```php
/** @test */
public function it_enforces_minimum_1_hp_on_manual_roll_with_negative_con(): void
{
    $class = CharacterClass::factory()->create([
        'name' => 'Wizard',
        'hit_die' => 6,
    ]);

    $character = Character::factory()->create([
        'constitution' => 3, // -4 modifier
        'max_hit_points' => 2,
        'current_hit_points' => 2,
    ]);

    $pivot = CharacterClassPivot::factory()->create([
        'character_id' => $character->id,
        'class_slug' => $class->full_slug,
        'level' => 2,
        'is_primary' => true,
    ]);
    $pivot->setRelation('characterClass', $class);

    $choice = new PendingChoice(
        id: 'hit_points:levelup:'.$character->id.':2',
        type: 'hit_points',
        subtype: null,
        source: 'level_up',
        sourceName: 'Level 2',
        levelGranted: 2,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: [
            'hit_die' => 'd6',
            'con_modifier' => -4,
            'class_slug' => 'wizard',
        ],
    );

    // Roll of 1 with -4 CON = -3, but min is 1
    $this->handler->resolve($character, $choice, [
        'selected' => 'manual',
        'roll_result' => 1,
    ]);

    $character->refresh();

    $this->assertEquals(3, $character->max_hit_points); // 2 + 1 (min)
    $this->assertEquals(3, $character->current_hit_points);
}
```

### Step 7: Implement manual roll support in HitPointRollChoiceHandler

Replace the resolve method validation block in `app/Services/ChoiceHandlers/HitPointRollChoiceHandler.php`:

```php
public function resolve(Character $character, PendingChoice $choice, array $selection): void
{
    $selected = $selection['selected'] ?? null;

    if (! $selected) {
        throw new InvalidSelectionException($choice->id, 'null', 'Selection is required for hit point choice');
    }

    if (! in_array($selected, ['roll', 'average', 'manual'])) {
        throw new InvalidSelectionException($choice->id, $selected, 'Selection must be "roll", "average", or "manual"');
    }

    $conModifier = $choice->metadata['con_modifier'] ?? 0;
    $hitDieString = $choice->metadata['hit_die'] ?? 'd8';
    $hitDie = (int) str_replace('d', '', $hitDieString);

    if ($selected === 'manual') {
        $rollResult = $selection['roll_result'] ?? null;

        if ($rollResult === null) {
            throw new InvalidSelectionException(
                $choice->id,
                'manual',
                'roll_result is required when using manual selection'
            );
        }

        if (! is_int($rollResult) && ! is_numeric($rollResult)) {
            throw new InvalidSelectionException(
                $choice->id,
                'manual',
                'roll_result must be an integer'
            );
        }

        $rollResult = (int) $rollResult;

        if ($rollResult < 1 || $rollResult > $hitDie) {
            throw new InvalidSelectionException(
                $choice->id,
                'manual',
                "roll_result must be between 1 and {$hitDie}"
            );
        }

        $hpGained = max(1, $rollResult + $conModifier);
    } elseif ($selected === 'roll') {
        // Server-side roll - NEVER trust client
        $roll = random_int(1, $hitDie);
        $hpGained = max(1, $roll + $conModifier);
    } else {
        // Average
        $average = (int) floor($hitDie / 2) + 1;
        $hpGained = max(1, $average + $conModifier);
    }

    // Update character HP
    $character->max_hit_points = ($character->max_hit_points ?? 0) + $hpGained;
    $character->current_hit_points = ($character->current_hit_points ?? 0) + $hpGained;

    // Mark this level's HP as resolved
    $resolvedLevels = $character->hp_levels_resolved ?? [];
    $resolvedLevels[] = $choice->levelGranted;
    $character->hp_levels_resolved = array_unique($resolvedLevels);

    $character->save();

    // Mark this level's HP as resolved
    $character->markHpResolvedForLevel($choice->levelGranted);
}
```

### Step 8: Run all manual roll tests

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php --filter="manual"
```

Expected: All 5 new tests PASS

### Step 9: Run full handler test suite

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php
```

Expected: All tests PASS (existing + new)

### Step 10: Commit

```bash
git add app/Services/ChoiceHandlers/HitPointRollChoiceHandler.php tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php
git commit -m "feat(#357): Add manual roll support to HP choice handler

- Add 'manual' selection type with roll_result parameter
- Validate roll_result is between 1 and hit_die
- Enforce minimum 1 HP gained even with negative CON
- Add 5 new tests for manual roll scenarios

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: Add Manual Roll Option to Choice Options

**Files:**
- Modify: `app/Services/ChoiceHandlers/HitPointRollChoiceHandler.php:76-90`
- Test: `tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php`

### Step 1: Write failing test for manual option in choices

```php
/** @test */
public function it_includes_manual_option_in_hp_choices(): void
{
    $class = CharacterClass::factory()->create([
        'name' => 'Fighter',
        'hit_die' => 10,
    ]);

    $character = Character::factory()->create([
        'constitution' => 16, // +3 modifier
    ]);

    $pivot = CharacterClassPivot::factory()->create([
        'character_id' => $character->id,
        'class_slug' => $class->full_slug,
        'level' => 2,
        'is_primary' => true,
    ]);
    $pivot->setRelation('characterClass', $class);
    $character->setRelation('characterClasses', collect([$pivot]));

    $choices = $this->handler->getChoices($character);
    $choice = $choices->first();

    $this->assertCount(3, $choice->options);

    $manualOption = collect($choice->options)->firstWhere('id', 'manual');
    $this->assertNotNull($manualOption);
    $this->assertEquals('Manual Roll', $manualOption['name']);
    $this->assertEquals(1, $manualOption['min_roll']);
    $this->assertEquals(10, $manualOption['max_roll']);
}
```

### Step 2: Run test to verify it fails

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php --filter="it_includes_manual_option"
```

Expected: FAIL - only 2 options exist

### Step 3: Add manual option to getChoices method

In `app/Services/ChoiceHandlers/HitPointRollChoiceHandler.php`, update the options array (around line 76):

```php
$choice = new PendingChoice(
    id: $this->generateChoiceId('hit_points', 'levelup', (string) $character->id, $level, 'hp'),
    type: 'hit_points',
    subtype: null,
    source: 'level_up',
    sourceName: "Level {$level}",
    levelGranted: $level,
    required: true,
    quantity: 1,
    remaining: 1,
    selected: [],
    options: [
        [
            'id' => 'roll',
            'name' => 'Roll',
            'description' => "Roll 1d{$hitDie}".($conModifier >= 0 ? ' + ' : ' - ').abs($conModifier).' (CON mod)',
            'min_result' => $minRoll,
            'max_result' => $maxRoll,
        ],
        [
            'id' => 'average',
            'name' => 'Average',
            'description' => "Take {$average}".($conModifier >= 0 ? ' + ' : ' - ').abs($conModifier)." (CON mod) = {$averageResult} HP",
            'fixed_result' => $averageResult,
        ],
        [
            'id' => 'manual',
            'name' => 'Manual Roll',
            'description' => "Enter your own d{$hitDie} roll result (for physical dice)",
            'min_roll' => 1,
            'max_roll' => $hitDie,
        ],
    ],
    optionsEndpoint: null,
    metadata: [
        'hit_die' => "d{$hitDie}",
        'con_modifier' => $conModifier,
        'class_slug' => $classSlug,
    ],
);
```

### Step 4: Run test to verify it passes

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php --filter="it_includes_manual_option"
```

Expected: PASS

### Step 5: Update existing test that checks option count

Update `it_includes_roll_and_average_options` test to expect 3 options:

```php
$this->assertCount(3, $choice->options);
```

### Step 6: Run full test suite

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php
```

Expected: All tests PASS

### Step 7: Commit

```bash
git add app/Services/ChoiceHandlers/HitPointRollChoiceHandler.php tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php
git commit -m "feat(#357): Add manual roll option to HP choice options

- Include 'manual' option in getChoices response
- Document min_roll and max_roll for client validation

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: Add HP Protection to CharacterUpdateRequest

**Files:**
- Modify: `app/Http/Requests/Character/CharacterUpdateRequest.php:63`
- Create: `tests/Feature/Api/CharacterHpProtectionTest.php`

### Step 1: Create new test file with failing test for calculated mode rejection

Create `tests/Feature/Api/CharacterHpProtectionTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterHpProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_max_hit_points_update_for_calculated_mode(): void
    {
        $character = Character::factory()->create([
            'hp_calculation_method' => 'calculated',
            'max_hit_points' => 10,
            'current_hit_points' => 10,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'max_hit_points' => 50,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_hit_points']);

        // HP should not have changed
        $character->refresh();
        $this->assertEquals(10, $character->max_hit_points);
    }
}
```

### Step 2: Run test to verify it fails

```bash
docker compose exec php ./vendor/bin/pest tests/Feature/Api/CharacterHpProtectionTest.php --filter="it_rejects_max_hit_points_update_for_calculated_mode"
```

Expected: FAIL - returns 200, HP is updated to 50

### Step 3: Write test for allowing HP update in manual mode

```php
/** @test */
public function it_allows_max_hit_points_update_for_manual_mode(): void
{
    $character = Character::factory()->create([
        'hp_calculation_method' => 'manual',
        'max_hit_points' => 10,
        'current_hit_points' => 10,
    ]);

    $response = $this->patchJson("/api/v1/characters/{$character->id}", [
        'max_hit_points' => 50,
    ]);

    $response->assertOk();

    $character->refresh();
    $this->assertEquals(50, $character->max_hit_points);
}
```

### Step 4: Write test for mode switching

```php
/** @test */
public function it_allows_switching_from_calculated_to_manual_mode(): void
{
    $character = Character::factory()->create([
        'hp_calculation_method' => 'calculated',
        'max_hit_points' => 10,
    ]);

    $response = $this->patchJson("/api/v1/characters/{$character->id}", [
        'hp_calculation_method' => 'manual',
    ]);

    $response->assertOk();

    $character->refresh();
    $this->assertEquals('manual', $character->hp_calculation_method);
}
```

### Step 5: Write test for mode switching with HP update in same request

```php
/** @test */
public function it_allows_max_hit_points_when_switching_to_manual_in_same_request(): void
{
    $character = Character::factory()->create([
        'hp_calculation_method' => 'calculated',
        'max_hit_points' => 10,
        'current_hit_points' => 10,
    ]);

    $response = $this->patchJson("/api/v1/characters/{$character->id}", [
        'hp_calculation_method' => 'manual',
        'max_hit_points' => 50,
    ]);

    $response->assertOk();

    $character->refresh();
    $this->assertEquals('manual', $character->hp_calculation_method);
    $this->assertEquals(50, $character->max_hit_points);
}
```

### Step 6: Write test for switching back to calculated mode

```php
/** @test */
public function it_allows_switching_from_manual_to_calculated_mode(): void
{
    $character = Character::factory()->create([
        'hp_calculation_method' => 'manual',
        'max_hit_points' => 50,
    ]);

    $response = $this->patchJson("/api/v1/characters/{$character->id}", [
        'hp_calculation_method' => 'calculated',
    ]);

    $response->assertOk();

    $character->refresh();
    $this->assertEquals('calculated', $character->hp_calculation_method);
    // HP should be preserved
    $this->assertEquals(50, $character->max_hit_points);
}
```

### Step 7: Write test for invalid mode value

```php
/** @test */
public function it_rejects_invalid_hp_calculation_method(): void
{
    $character = Character::factory()->create([
        'hp_calculation_method' => 'calculated',
    ]);

    $response = $this->patchJson("/api/v1/characters/{$character->id}", [
        'hp_calculation_method' => 'invalid',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['hp_calculation_method']);
}
```

### Step 8: Implement Form Request validation

Update `app/Http/Requests/Character/CharacterUpdateRequest.php` rules() method.

Add to the rules array:

```php
// HP calculation method
'hp_calculation_method' => ['sometimes', 'string', 'in:calculated,manual'],

// Hit points - protected when using calculated mode
'max_hit_points' => [
    'sometimes',
    'nullable',
    'integer',
    'min:1',
    Rule::prohibitedIf(function () {
        // Check if we're trying to keep calculated mode
        $newMethod = $this->input('hp_calculation_method');

        // If switching to manual in same request, allow HP update
        if ($newMethod === 'manual') {
            return false;
        }

        // If explicitly setting to calculated, prohibit
        if ($newMethod === 'calculated') {
            return true;
        }

        // Check current character's mode
        $character = $this->route('character');
        if ($character && $character->hp_calculation_method === 'calculated') {
            return true;
        }

        return false;
    }),
],
```

Add the Rule import at the top:

```php
use Illuminate\Validation\Rule;
```

### Step 9: Add custom error message

Add to messages() method:

```php
'max_hit_points.prohibited' => 'Cannot modify max_hit_points when using calculated HP mode. Switch to manual mode or use HP choices.',
```

### Step 10: Run all HP protection tests

```bash
docker compose exec php ./vendor/bin/pest tests/Feature/Api/CharacterHpProtectionTest.php
```

Expected: All 6 tests PASS

### Step 11: Run Feature-DB suite to check for regressions

```bash
docker compose exec php ./vendor/bin/pest --testsuite=Feature-DB
```

Expected: All tests PASS

### Step 12: Commit

```bash
git add app/Http/Requests/Character/CharacterUpdateRequest.php tests/Feature/Api/CharacterHpProtectionTest.php
git commit -m "feat(#357): Add HP protection for calculated mode

- Add prohibited rule for max_hit_points when hp_calculation_method is 'calculated'
- Allow HP updates when switching to manual mode in same request
- Add hp_calculation_method validation (calculated|manual)
- Add 6 new feature tests for HP protection scenarios

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: Update CHANGELOG and Final Verification

**Files:**
- Modify: `CHANGELOG.md`

### Step 1: Run Unit-DB suite

```bash
docker compose exec php ./vendor/bin/pest --testsuite=Unit-DB
```

Expected: All tests PASS

### Step 2: Run Feature-DB suite

```bash
docker compose exec php ./vendor/bin/pest --testsuite=Feature-DB
```

Expected: All tests PASS

### Step 3: Run Pint

```bash
docker compose exec php ./vendor/bin/pint
```

### Step 4: Update CHANGELOG.md

Add under `[Unreleased]`:

```markdown
### Added
- HP validation for calculated characters (#357)
  - Manual roll option for HP choices (physical dice support)
  - Form Request validation prevents direct max_hit_points modification in calculated mode
  - Mode switching between 'calculated' and 'manual' HP modes
```

### Step 5: Commit changelog

```bash
git add CHANGELOG.md
git commit -m "docs(#357): Update CHANGELOG for HP validation feature

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

### Step 6: Push branch

```bash
git push -u origin feature/issue-357-hp-validation
```

### Step 7: Create PR

```bash
gh pr create --title "feat(#357): HP Validation for Calculated Characters" --body "$(cat <<'EOF'
## Summary
- Add manual roll support for HP choices (physical dice)
- Protect calculated HP from direct modification via API
- Allow mode switching between calculated and manual modes

## Changes
- `HitPointRollChoiceHandler`: Add 'manual' selection with roll_result validation
- `CharacterUpdateRequest`: Add prohibited_if rule for max_hit_points
- New test file: `CharacterHpProtectionTest.php`

## Test Plan
- [x] Manual roll with valid roll_result works
- [x] Manual roll validates roll_result range (1 to hit_die)
- [x] Calculated mode rejects direct max_hit_points updates
- [x] Manual mode allows direct max_hit_points updates
- [x] Mode switching works in both directions
- [x] Mode switch + HP update in same request works

Closes #357

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Summary

| Task | Description | Tests |
|------|-------------|-------|
| 1 | Manual roll support in resolve() | 5 unit tests |
| 2 | Manual option in getChoices() | 1 unit test |
| 3 | HP protection in Form Request | 6 feature tests |
| 4 | CHANGELOG + PR | - |

**Total new tests:** 12
