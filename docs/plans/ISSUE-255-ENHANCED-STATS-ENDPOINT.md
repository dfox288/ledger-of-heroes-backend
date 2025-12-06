# Issue #255: Enhanced Stats Endpoint

**Goal:** Enhance `/characters/{id}/stats` to return complete character statistics in a single response.

**Runner:** Sail (`docker compose exec php ...`)

**Branch:** `feature/issue-255-enhanced-stats-endpoint`

---

## Phase 1: Saving Throw Proficiencies

### Task 1.1: Add getSavingThrowProficiencies to CharacterStatsDTO

**File:** `app/DTOs/CharacterStatsDTO.php`

Add method to extract saving throw proficiencies from primary class:

```php
/**
 * Get saving throw proficiency status from primary class.
 *
 * @return array<string, bool> Keyed by ability code (STR, DEX, etc.)
 */
private static function getSavingThrowProficiencies(Character $character): array
{
    $result = [
        'STR' => false,
        'DEX' => false,
        'CON' => false,
        'INT' => false,
        'WIS' => false,
        'CHA' => false,
    ];

    $primaryClass = $character->primary_class;
    if (!$primaryClass) {
        return $result;
    }

    // Load proficiencies if not loaded
    if (!$primaryClass->relationLoaded('proficiencies')) {
        $primaryClass->load('proficiencies');
    }

    // Map ability names to codes
    $nameToCode = [
        'Strength' => 'STR',
        'Dexterity' => 'DEX',
        'Constitution' => 'CON',
        'Intelligence' => 'INT',
        'Wisdom' => 'WIS',
        'Charisma' => 'CHA',
    ];

    $savingThrows = $primaryClass->proficiencies
        ->where('proficiency_type', 'saving_throw');

    foreach ($savingThrows as $prof) {
        $code = $nameToCode[$prof->proficiency_name] ?? null;
        if ($code) {
            $result[$code] = true;
        }
    }

    return $result;
}
```

### Task 1.2: Update saving throw calculation in fromCharacter

**File:** `app/DTOs/CharacterStatsDTO.php`

Change saving throw structure from `{code: modifier}` to `{code: {modifier, proficient, total}}`:

```php
// Get saving throw proficiencies from primary class
$savingThrowProficiencies = self::getSavingThrowProficiencies($character);

// Saving throws with proficiency
$savingThrows = [];
foreach ($abilityScores as $code => $score) {
    $baseMod = $abilityModifiers[$code];
    $proficient = $savingThrowProficiencies[$code] ?? false;
    $total = $baseMod !== null
        ? $baseMod + ($proficient ? $proficiencyBonus : 0)
        : null;

    $savingThrows[$code] = [
        'modifier' => $baseMod,
        'proficient' => $proficient,
        'total' => $total,
    ];
}
```

### Task 1.3: Update CharacterStatsResource for new saving throw format

**File:** `app/Http/Resources/CharacterStatsResource.php`

No changes needed - already passes through `$this->resource->savingThrows`.

### Task 1.4: Write tests for saving throw proficiencies

**File:** `tests/Unit/DTOs/CharacterStatsDTOTest.php`

```php
it('includes saving throw proficiency from primary class', function () {
    // Create fighter (STR/CON saves)
    $fighter = CharacterClass::where('slug', 'fighter')->first();
    $character = Character::factory()->create();
    $character->characterClasses()->create([
        'character_class_id' => $fighter->id,
        'level' => 1,
        'order' => 1,
        'is_primary' => true,
    ]);

    $calculator = app(CharacterStatCalculator::class);
    $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $calculator);

    expect($dto->savingThrows['STR']['proficient'])->toBeTrue();
    expect($dto->savingThrows['CON']['proficient'])->toBeTrue();
    expect($dto->savingThrows['DEX']['proficient'])->toBeFalse();
});

it('calculates saving throw total with proficiency bonus', function () {
    $fighter = CharacterClass::where('slug', 'fighter')->first();
    $character = Character::factory()->create([
        'strength' => 16, // +3 modifier
    ]);
    $character->characterClasses()->create([
        'character_class_id' => $fighter->id,
        'level' => 5, // +3 proficiency
        'order' => 1,
        'is_primary' => true,
    ]);

    $calculator = app(CharacterStatCalculator::class);
    $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $calculator);

    // STR save: +3 (mod) + 3 (prof) = +6
    expect($dto->savingThrows['STR']['total'])->toBe(6);
    // DEX save: modifier only (no proficiency)
    expect($dto->savingThrows['DEX']['total'])->toBe($dto->savingThrows['DEX']['modifier']);
});
```

---

## Phase 2: Full Skills Array

### Task 2.1: Add buildSkills method to CharacterStatsDTO

**File:** `app/DTOs/CharacterStatsDTO.php`

```php
/**
 * Build complete skills array with all 18 skills.
 *
 * @return array<int, array{
 *   name: string,
 *   slug: string,
 *   ability: string,
 *   ability_modifier: int|null,
 *   proficient: bool,
 *   expertise: bool,
 *   modifier: int|null,
 *   passive: int
 * }>
 */
private static function buildSkills(
    Character $character,
    array $abilityModifiers,
    array $skillProficiencies,
    int $proficiencyBonus,
    CharacterStatCalculator $calculator
): array {
    $skills = Skill::with('abilityScore')->get();

    return $skills->map(function ($skill) use ($abilityModifiers, $skillProficiencies, $proficiencyBonus, $calculator) {
        $abilityCode = $skill->abilityScore->code;
        $abilityMod = $abilityModifiers[$abilityCode];
        $profData = $skillProficiencies[$skill->slug] ?? ['proficient' => false, 'expertise' => false];
        $proficient = $profData['proficient'];
        $expertise = $profData['expertise'];

        $modifier = $abilityMod !== null
            ? $calculator->skillModifier($abilityMod, $proficient, $expertise, $proficiencyBonus)
            : null;

        $passive = $abilityMod !== null
            ? $calculator->calculatePassiveSkill($abilityMod, $proficient, $expertise, $proficiencyBonus)
            : null;

        return [
            'name' => $skill->name,
            'slug' => $skill->slug,
            'ability' => $abilityCode,
            'ability_modifier' => $abilityMod,
            'proficient' => $proficient,
            'expertise' => $expertise,
            'modifier' => $modifier,
            'passive' => $passive,
        ];
    })->sortBy('name')->values()->all();
}
```

### Task 2.2: Add skills property to DTO constructor and fromCharacter

**File:** `app/DTOs/CharacterStatsDTO.php`

Add to constructor:
```php
public readonly array $skills,
```

Add to fromCharacter before return:
```php
$skills = self::buildSkills($character, $abilityModifiers, $skillProficiencies, $proficiencyBonus, $calculator);
```

### Task 2.3: Update CharacterStatsResource to include skills

**File:** `app/Http/Resources/CharacterStatsResource.php`

Add to toArray:
```php
/** @var array Skills with full breakdown */
'skills' => $this->resource->skills,
```

### Task 2.4: Write tests for skills array

**File:** `tests/Unit/DTOs/CharacterStatsDTOTest.php`

```php
it('returns all 18 skills with correct structure', function () {
    $character = Character::factory()->create();

    $calculator = app(CharacterStatCalculator::class);
    $dto = CharacterStatsDTO::fromCharacter($character, $calculator);

    expect($dto->skills)->toHaveCount(18);
    expect($dto->skills[0])->toHaveKeys([
        'name', 'slug', 'ability', 'ability_modifier',
        'proficient', 'expertise', 'modifier', 'passive'
    ]);
});

it('calculates skill modifier with proficiency', function () {
    $character = Character::factory()->create(['dexterity' => 16]); // +3 mod

    // Add Stealth proficiency
    $stealthSkill = Skill::where('slug', 'stealth')->first();
    $character->proficiencies()->create([
        'skill_id' => $stealthSkill->id,
        'expertise' => false,
    ]);

    $calculator = app(CharacterStatCalculator::class);
    $dto = CharacterStatsDTO::fromCharacter($character->fresh()->load('proficiencies.skill'), $calculator);

    $stealth = collect($dto->skills)->firstWhere('slug', 'stealth');

    expect($stealth['proficient'])->toBeTrue();
    expect($stealth['modifier'])->toBe(5); // +3 DEX + 2 prof (level 1)
});

it('calculates skill modifier with expertise', function () {
    $character = Character::factory()->create(['dexterity' => 16]); // +3 mod

    // Add Stealth expertise
    $stealthSkill = Skill::where('slug', 'stealth')->first();
    $character->proficiencies()->create([
        'skill_id' => $stealthSkill->id,
        'expertise' => true,
    ]);

    $calculator = app(CharacterStatCalculator::class);
    $dto = CharacterStatsDTO::fromCharacter($character->fresh()->load('proficiencies.skill'), $calculator);

    $stealth = collect($dto->skills)->firstWhere('slug', 'stealth');

    expect($stealth['expertise'])->toBeTrue();
    expect($stealth['modifier'])->toBe(7); // +3 DEX + 4 expertise (2x prof)
});
```

---

## Phase 3: Speed Details

### Task 3.1: Add speed property to DTO

**File:** `app/DTOs/CharacterStatsDTO.php`

Add to constructor:
```php
public readonly array $speed,
```

Add helper method:
```php
/**
 * Build speed array from character's race.
 */
private static function buildSpeed(Character $character): array
{
    $speed = [
        'walk' => $character->speed ?? 30,
        'fly' => null,
        'swim' => null,
        'climb' => null,
        'burrow' => null,
    ];

    // Check race for additional speeds
    if ($character->race) {
        // Parse race speed modifiers if available
        foreach ($character->race->modifiers ?? [] as $mod) {
            if ($mod->modifier_type === 'speed' && $mod->speed_type) {
                $speed[$mod->speed_type] = $mod->value;
            }
        }
    }

    return $speed;
}
```

### Task 3.2: Update CharacterStatsResource for speed

**File:** `app/Http/Resources/CharacterStatsResource.php`

```php
'speed' => $this->resource->speed,
```

### Task 3.3: Write tests for speed

**File:** `tests/Unit/DTOs/CharacterStatsDTOTest.php`

```php
it('returns speed with walk as default', function () {
    $character = Character::factory()->create(['speed' => 30]);

    $calculator = app(CharacterStatCalculator::class);
    $dto = CharacterStatsDTO::fromCharacter($character, $calculator);

    expect($dto->speed)->toBe([
        'walk' => 30,
        'fly' => null,
        'swim' => null,
        'climb' => null,
        'burrow' => null,
    ]);
});
```

---

## Phase 4: Passive Scores Consolidation

### Task 4.1: Add passive property to DTO

**File:** `app/DTOs/CharacterStatsDTO.php`

Add to constructor:
```php
public readonly array $passive,
```

Build in fromCharacter:
```php
$passive = [
    'perception' => $passivePerception,
    'investigation' => $passiveInvestigation,
    'insight' => $passiveInsight,
];
```

### Task 4.2: Update CharacterStatsResource

Keep individual passive_* fields for backwards compatibility, add grouped:
```php
'passive' => $this->resource->passive,
```

---

## Phase 5: Quality & Cleanup

### Task 5.1: Run test suite

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/DTOs/CharacterStatsDTOTest.php
docker compose exec php ./vendor/bin/pest --testsuite=Feature-DB --filter=CharacterStats
```

### Task 5.2: Run Pint

```bash
docker compose exec php ./vendor/bin/pint
```

### Task 5.3: Update CHANGELOG.md

```markdown
### Added
- Enhanced `/characters/{id}/stats` endpoint with full skill breakdown (#255)
- Saving throw proficiency status from primary class
- Complete skills array with all 18 skills, modifiers, and passive scores
- Speed breakdown (walk, fly, swim, climb, burrow)
- Grouped passive scores object
```

### Task 5.4: Create PR

```bash
gh pr create --title "feat(#255): Enhanced stats endpoint with skills and saving throws" \
  --body "Closes #255"
```

---

## Verification Checklist

- [ ] All 18 skills returned with full breakdown
- [ ] Saving throws show proficiency from primary class
- [ ] Saving throw totals include proficiency bonus when proficient
- [ ] Passive Perception/Investigation/Insight in grouped object
- [ ] Speed includes all movement types
- [ ] Tests pass for all new calculations
- [ ] Pint formatting clean
- [ ] CHANGELOG updated
