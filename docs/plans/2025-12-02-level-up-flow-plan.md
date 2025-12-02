# Level-Up Flow - Implementation Plan

**Date:** 2025-12-02
**Issue:** #91 - Character Builder v2: Level-Up Flow
**Branch:** `feature/issue-91-level-up-flow`

---

## Overview

Implement milestone-based level-up functionality for characters. XP-based leveling deferred to issue #95.

### Features
- Level up character (1→20)
- HP increase (hit die average + CON modifier)
- Auto-grant class features at new level
- Update spell slots for casters
- Track pending ASI/Feat choices at levels 4, 8, 12, 16, 19

### D&D 5e Rules

**HP on Level Up:**
- Level 1: Max hit die + CON modifier (already handled at creation)
- Levels 2+: Average hit die + CON modifier
- Average: d6=4, d8=5, d10=6, d12=7

**ASI Levels (standard):**
- Most classes: 4, 8, 12, 16, 19
- Fighter: 4, 6, 8, 12, 14, 16, 19
- Rogue: 4, 8, 10, 12, 16, 19

---

## Pre-flight Checklist

- [x] Runner: Docker Compose (`docker compose exec php ...`)
- [x] Branch: `feature/issue-91-level-up-flow` created
- [ ] Existing tests passing
- [ ] Git status clean

---

## Phase 1: Data Model Updates

### Task 1.1: Add migration for asi_choices_remaining field

**File:** `database/migrations/xxxx_add_asi_choices_remaining_to_characters_table.php`

```php
Schema::table('characters', function (Blueprint $table) {
    $table->unsignedTinyInteger('asi_choices_remaining')->default(0)->after('armor_class_override');
});
```

### Task 1.2: Update Character model

Add `asi_choices_remaining` to fillable and casts.

---

## Phase 2: DTOs

### Task 2.1: Create LevelUpResult DTO

**File:** `app/DTOs/LevelUpResult.php`

```php
final readonly class LevelUpResult
{
    public function __construct(
        public int $previousLevel,
        public int $newLevel,
        public int $hpIncrease,
        public int $newMaxHp,
        public array $featuresGained,
        public array $spellSlots,
        public bool $asiPending,
    ) {}

    public function toArray(): array { ... }
}
```

---

## Phase 3: Service Layer (TDD)

### Task 3.1: Write failing tests for LevelUpService

**File:** `tests/Unit/Services/LevelUpServiceTest.php`

Test cases:
1. `it_increases_character_level_by_one`
2. `it_calculates_hp_increase_with_con_modifier`
3. `it_uses_average_hit_die_for_hp` (d8=5, d10=6, etc.)
4. `it_grants_class_features_for_new_level`
5. `it_returns_updated_spell_slots_for_casters`
6. `it_sets_asi_pending_at_level_4`
7. `it_sets_asi_pending_at_fighter_level_6`
8. `it_throws_exception_at_max_level`
9. `it_throws_exception_for_incomplete_character`
10. `it_handles_negative_con_modifier` (minimum 1 HP gained)
11. `it_increments_asi_choices_remaining`

### Task 3.2: Implement LevelUpService

**File:** `app/Services/LevelUpService.php`

```php
class LevelUpService
{
    private const ASI_LEVELS_STANDARD = [4, 8, 12, 16, 19];
    private const ASI_LEVELS_FIGHTER = [4, 6, 8, 12, 14, 16, 19];
    private const ASI_LEVELS_ROGUE = [4, 8, 10, 12, 16, 19];

    public function levelUp(Character $character): LevelUpResult
    {
        $this->validateCanLevelUp($character);

        $previousLevel = $character->level;
        $newLevel = $previousLevel + 1;

        $hpIncrease = $this->calculateHpIncrease($character);
        $featuresGained = $this->grantClassFeatures($character, $newLevel);
        $asiPending = $this->isAsiLevel($character, $newLevel);

        $character->level = $newLevel;
        $character->max_hit_points += $hpIncrease;
        $character->current_hit_points += $hpIncrease;

        if ($asiPending) {
            $character->asi_choices_remaining++;
        }

        $character->save();

        $spellSlots = $this->getSpellSlots($character);

        return new LevelUpResult(
            previousLevel: $previousLevel,
            newLevel: $newLevel,
            hpIncrease: $hpIncrease,
            newMaxHp: $character->max_hit_points,
            featuresGained: $featuresGained,
            spellSlots: $spellSlots,
            asiPending: $asiPending,
        );
    }

    public function calculateHpIncrease(Character $character): int
    {
        $hitDie = $character->characterClass->effective_hit_die;
        $averageRoll = (int) floor($hitDie / 2) + 1;
        $conModifier = app(CharacterStatCalculator::class)
            ->abilityModifier($character->constitution ?? 10);

        return max(1, $averageRoll + $conModifier);
    }

    private function grantClassFeatures(Character $character, int $newLevel): array
    {
        $features = $character->characterClass->features()
            ->where('level', $newLevel)
            ->where('is_optional', false)
            ->get();

        $granted = [];
        foreach ($features as $feature) {
            CharacterFeature::create([
                'character_id' => $character->id,
                'feature_type' => ClassFeature::class,
                'feature_id' => $feature->id,
                'source' => 'class',
                'level_acquired' => $newLevel,
            ]);

            $granted[] = [
                'id' => $feature->id,
                'name' => $feature->feature_name,
                'description' => $feature->description,
            ];
        }

        return $granted;
    }

    private function isAsiLevel(Character $character, int $level): bool
    {
        $classSlug = $character->characterClass->slug;

        $asiLevels = match ($classSlug) {
            'fighter' => self::ASI_LEVELS_FIGHTER,
            'rogue' => self::ASI_LEVELS_ROGUE,
            default => self::ASI_LEVELS_STANDARD,
        };

        return in_array($level, $asiLevels);
    }

    private function getSpellSlots(Character $character): array
    {
        return app(CharacterStatCalculator::class)
            ->getSpellSlots($character->characterClass->slug, $character->level);
    }

    private function validateCanLevelUp(Character $character): void
    {
        if ($character->level >= 20) {
            throw new MaxLevelReachedException($character);
        }

        if (!$character->is_complete) {
            throw new IncompleteCharacterException($character);
        }
    }
}
```

### Task 3.3: Create custom exceptions

**Files:**
- `app/Exceptions/MaxLevelReachedException.php`
- `app/Exceptions/IncompleteCharacterException.php`

Both should be renderable with appropriate HTTP status codes (422).

---

## Phase 4: API Layer

### Task 4.1: Write failing feature tests for level-up endpoint

**File:** `tests/Feature/Api/CharacterLevelUpApiTest.php`

Test cases:
1. `it_levels_up_character`
2. `it_returns_level_up_details`
3. `it_returns_422_at_max_level`
4. `it_returns_422_for_incomplete_character`
5. `it_returns_404_for_nonexistent_character`
6. `it_grants_class_features_at_new_level`
7. `it_includes_spell_slots_for_casters`
8. `it_sets_asi_pending_at_level_4`
9. `it_increments_hp_correctly`

### Task 4.2: Create CharacterLevelUpController

**File:** `app/Http/Controllers/Api/CharacterLevelUpController.php`

```php
class CharacterLevelUpController extends Controller
{
    public function __construct(
        private LevelUpService $levelUpService
    ) {}

    /**
     * Level up a character.
     *
     * @group Character Builder
     * @urlParam character_id integer required The character ID.
     * @response 200 {"previous_level": 3, "new_level": 4, ...}
     * @response 404 {"message": "Character not found"}
     * @response 422 {"message": "Character is already at maximum level"}
     */
    public function __invoke(Character $character): JsonResponse
    {
        $result = $this->levelUpService->levelUp($character);

        return response()->json($result->toArray());
    }
}
```

### Task 4.3: Add route

**File:** `routes/api.php`

```php
Route::post('/characters/{character}/level-up', CharacterLevelUpController::class);
```

---

## Phase 5: Update CharacterResource

### Task 5.1: Add asi_choices_remaining to CharacterResource

Include in response:
```php
'asi_choices_remaining' => $this->asi_choices_remaining,
```

---

## Phase 6: Quality Gates

### Task 6.1: Run test suites

```bash
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 6.2: Run Pint

```bash
docker compose exec php ./vendor/bin/pint
```

### Task 6.3: Update CHANGELOG.md

Add under `[Unreleased]`:
```markdown
### Added
- Level-Up Flow (Issue #91)
  - `POST /api/v1/characters/{id}/level-up` endpoint for milestone leveling
  - `LevelUpService` handles HP increase, feature grants, spell slot updates
  - `LevelUpResult` DTO with detailed level-up information
  - HP increase: average hit die + CON modifier (minimum 1)
  - Auto-grant class features for new level
  - Track ASI pending at levels 4, 8, 12, 16, 19 (class-specific variations)
  - `asi_choices_remaining` field on Character model
  - `MaxLevelReachedException` and `IncompleteCharacterException`
```

---

## Implementation Notes

### HP Calculation

| Hit Die | Average |
|---------|---------|
| d6 | 4 |
| d8 | 5 |
| d10 | 6 |
| d12 | 7 |

Formula: `floor(hitDie / 2) + 1 + conModifier` (minimum 1)

### ASI Levels by Class

| Class | ASI Levels |
|-------|------------|
| Standard | 4, 8, 12, 16, 19 |
| Fighter | 4, 6, 8, 12, 14, 16, 19 |
| Rogue | 4, 8, 10, 12, 16, 19 |

### Spell Slot Updates

Spell slots are computed, not stored. The `CharacterStatCalculator::getSpellSlots()` method already handles this based on class and level.

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `database/migrations/xxxx_add_asi_choices_remaining_to_characters_table.php` | Create |
| `app/Models/Character.php` | Modify (add field) |
| `app/DTOs/LevelUpResult.php` | Create |
| `app/Services/LevelUpService.php` | Create |
| `app/Exceptions/MaxLevelReachedException.php` | Create |
| `app/Exceptions/IncompleteCharacterException.php` | Create |
| `app/Http/Controllers/Api/CharacterLevelUpController.php` | Create |
| `app/Http/Resources/CharacterResource.php` | Modify |
| `routes/api.php` | Modify |
| `tests/Unit/Services/LevelUpServiceTest.php` | Create |
| `tests/Feature/Api/CharacterLevelUpApiTest.php` | Create |
| `CHANGELOG.md` | Update |

---

## Future Enhancements (Out of Scope)

- XP-based leveling (#95)
- ASI/Feat selection endpoint (#93)
- Multiclass level-up (#92)
- Subclass selection at appropriate level

---

## Success Criteria

- [ ] All new tests pass
- [ ] Existing tests still pass
- [ ] Level-up endpoint works correctly
- [ ] HP increases by average + CON mod
- [ ] Class features granted at new level
- [ ] ASI tracked at appropriate levels
- [ ] Pint formatting clean
- [ ] CHANGELOG updated
