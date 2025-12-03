# Implementation Plan: ASI Choice Endpoint

**Issue:** #93 - Character Builder v2: Feat Selection
**Branch:** `feature/issue-93-feat-selection`
**Date:** 2025-12-03

---

## Overview

Unified endpoint for spending ASI choices on feats or ability score increases.

```
POST /api/v1/characters/{id}/asi-choice
```

---

## 1. Scaffolding

**Runner:** Sail (`docker compose exec php ...`)
**Branch:** `feature/issue-93-feat-selection` (created)
**Workspace:** Main repo (no worktree needed)

---

## 2. Data Model

### 2.1 No new migrations needed

Existing tables support all requirements:
- `characters.asi_choices_remaining` - already exists
- `character_features` - polymorphic, supports `Feat::class`
- `character_proficiencies` - for feat-granted proficiencies
- `character_spells` - for feat-granted spells
- `entity_prerequisites` - feat prerequisites already imported

---

## 3. Services & Interfaces

### Task 3.1: Create PrerequisiteCheckerService

**File:** `app/Services/PrerequisiteCheckerService.php`

**Purpose:** Evaluate if a character meets a feat's prerequisites.

**Methods:**
```php
public function checkFeatPrerequisites(Character $character, Feat $feat): PrerequisiteResult
```

**Logic:**
- Load feat's prerequisites via `$feat->prerequisites` relation
- For each prerequisite, check by type:
  - `AbilityScore::class` - character's ability >= `minimum_value`
  - `ProficiencyType::class` - character has proficiency (via class/race/background)
  - `Race::class` - character's race matches (future)
- Return `PrerequisiteResult` DTO with `met: bool`, `unmet: array`

**Test file:** `tests/Unit/Services/PrerequisiteCheckerServiceTest.php`

---

### Task 3.2: Create AsiChoiceResult DTO

**File:** `app/DTOs/AsiChoiceResult.php`

**Properties:**
```php
public function __construct(
    public string $choiceType,           // 'feat' or 'ability_increase'
    public int $asiChoicesRemaining,
    public array $abilityIncreases,      // ['CON' => 1] or ['STR' => 2]
    public array $newAbilityScores,      // All 6 scores after changes
    public ?array $feat,                 // {id, name, slug} or null
    public array $proficienciesGained,   // Names of proficiencies
    public array $spellsGained,          // {id, name, slug} for each spell
) {}
```

---

### Task 3.3: Create Custom Exceptions

**Files:**
- `app/Exceptions/NoAsiChoicesRemainingException.php`
- `app/Exceptions/PrerequisitesNotMetException.php`
- `app/Exceptions/FeatAlreadyTakenException.php`
- `app/Exceptions/AbilityScoreCapExceededException.php`
- `app/Exceptions/AbilityChoiceRequiredException.php`

Each returns 422 with descriptive message and relevant data.

---

### Task 3.4: Create AsiChoiceService

**File:** `app/Services/AsiChoiceService.php`

**Dependencies:**
- `PrerequisiteCheckerService`
- `CharacterStatCalculator` (for ability modifiers if needed)

**Methods:**
```php
public function applyChoice(Character $character, AsiChoiceData $data): AsiChoiceResult
```

**Logic (in DB transaction):**

**For `choice_type: 'feat'`:**
1. Validate `asi_choices_remaining > 0` → `NoAsiChoicesRemainingException`
2. Load feat with relations: `prerequisites`, `modifiers`, `proficiencies`, `spells`
3. Check not already taken → `FeatAlreadyTakenException`
4. Check prerequisites met → `PrerequisitesNotMetException`
5. If half-feat, validate `ability_choice` provided → `AbilityChoiceRequiredException`
6. If half-feat, validate chosen ability is allowed by feat's modifiers
7. Validate ability score won't exceed 20 → `AbilityScoreCapExceededException`
8. Apply changes:
   - Decrement `asi_choices_remaining`
   - Create `CharacterFeature` (feature_type: `Feat::class`, source: 'asi_choice')
   - Apply ability increase if half-feat
   - Create `CharacterProficiency` records for each proficiency
   - Create `CharacterSpell` records for each spell (prepared: false, source: 'feat')
9. Return `AsiChoiceResult`

**For `choice_type: 'ability_increase'`:**
1. Validate `asi_choices_remaining > 0`
2. Validate increases total exactly 2 points
3. Validate each increase is 1 or 2, max 2 abilities
4. Validate no ability would exceed 20
5. Apply changes:
   - Decrement `asi_choices_remaining`
   - Update character ability score columns
6. Return `AsiChoiceResult`

**Test file:** `tests/Unit/Services/AsiChoiceServiceTest.php`

---

### Task 3.5: Create AsiChoiceRequest Form Request

**File:** `app/Http/Requests/AsiChoiceRequest.php`

**Rules:**
```php
public function rules(): array
{
    return [
        'choice_type' => ['required', 'string', Rule::in(['feat', 'ability_increase'])],

        // Feat choice
        'feat_id' => ['required_if:choice_type,feat', 'integer', 'exists:feats,id'],
        'ability_choice' => ['nullable', 'string', Rule::in(['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'])],

        // Ability increase choice
        'ability_increases' => ['required_if:choice_type,ability_increase', 'array', 'max:2'],
        'ability_increases.*' => ['integer', 'min:1', 'max:2'],
    ];
}
```

**Custom validation:**
- `ability_increases` keys must be valid ability codes
- `ability_increases` values must sum to exactly 2

---

### Task 3.6: Create AsiChoiceController

**File:** `app/Http/Controllers/Api/AsiChoiceController.php`

**Method:**
```php
/**
 * Apply an ASI choice (feat or ability score increase).
 *
 * @operationId applyAsiChoice
 *
 * @tags Characters, Character Builder
 *
 * @throws NoAsiChoicesRemainingException 422
 * @throws PrerequisitesNotMetException 422
 * @throws FeatAlreadyTakenException 422
 * @throws AbilityScoreCapExceededException 422
 * @throws AbilityChoiceRequiredException 422
 */
public function store(AsiChoiceRequest $request, Character $character): AsiChoiceResource
{
    $this->authorize('update', $character);

    $result = $this->asiChoiceService->applyChoice(
        $character,
        AsiChoiceData::fromRequest($request)
    );

    return new AsiChoiceResource($result);
}
```

---

### Task 3.7: Create AsiChoiceResource

**File:** `app/Http/Resources/AsiChoiceResource.php`

**Output:**
```php
public function toArray($request): array
{
    return [
        'success' => true,
        'choice_type' => $this->choiceType,
        'asi_choices_remaining' => $this->asiChoicesRemaining,
        'changes' => [
            'feat' => $this->feat,
            'ability_increases' => $this->abilityIncreases,
            'proficiencies_gained' => $this->proficienciesGained,
            'spells_gained' => $this->spellsGained,
        ],
        'new_ability_scores' => $this->newAbilityScores,
    ];
}
```

---

### Task 3.8: Add Route

**File:** `routes/api.php`

```php
Route::post('characters/{character}/asi-choice', [AsiChoiceController::class, 'store'])
    ->middleware('auth:sanctum');
```

---

## 4. Tests (TDD)

### Task 4.1: PrerequisiteCheckerService Unit Tests

**File:** `tests/Unit/Services/PrerequisiteCheckerServiceTest.php`

**Tests:**
1. `it_passes_when_feat_has_no_prerequisites`
2. `it_passes_when_ability_score_prerequisite_met`
3. `it_fails_when_ability_score_prerequisite_not_met`
4. `it_passes_when_proficiency_prerequisite_met`
5. `it_fails_when_proficiency_prerequisite_not_met`
6. `it_returns_all_unmet_prerequisites`
7. `it_handles_feats_with_multiple_prerequisites`

---

### Task 4.2: AsiChoiceService Unit Tests (Feat Path)

**File:** `tests/Unit/Services/AsiChoiceServiceTest.php`

**Tests:**
1. `it_throws_when_no_asi_choices_remaining`
2. `it_throws_when_feat_already_taken`
3. `it_throws_when_prerequisites_not_met`
4. `it_throws_when_half_feat_missing_ability_choice`
5. `it_throws_when_ability_choice_not_valid_for_feat`
6. `it_throws_when_ability_would_exceed_cap`
7. `it_decrements_asi_choices_remaining`
8. `it_creates_character_feature_for_feat`
9. `it_applies_half_feat_ability_increase`
10. `it_grants_proficiencies_from_feat`
11. `it_grants_spells_from_feat`
12. `it_rolls_back_on_failure`

---

### Task 4.3: AsiChoiceService Unit Tests (Ability Increase Path)

**Additional tests in same file:**

13. `it_applies_plus_two_to_single_ability`
14. `it_applies_plus_one_to_two_abilities`
15. `it_throws_when_increase_total_not_two`
16. `it_throws_when_ability_increase_exceeds_cap`
17. `it_returns_correct_new_ability_scores`

---

### Task 4.4: AsiChoiceController Feature Tests

**File:** `tests/Feature/Api/AsiChoiceApiTest.php`

**Tests:**
1. `it_requires_authentication`
2. `it_forbids_updating_other_users_character`
3. `it_applies_feat_choice_successfully`
4. `it_applies_ability_increase_successfully`
5. `it_returns_422_when_no_asi_choices`
6. `it_returns_422_when_feat_not_found`
7. `it_returns_422_when_prerequisites_not_met`
8. `it_returns_422_when_feat_already_taken`
9. `it_returns_422_when_ability_exceeds_cap`
10. `it_validates_request_structure`

---

## 5. Quality Gates

### Task 5.1: Run Tests
```bash
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 5.2: Format Code
```bash
docker compose exec php ./vendor/bin/pint
```

### Task 5.3: Update CHANGELOG.md
Add under `[Unreleased]`:
```markdown
### Added
- ASI choice endpoint (`POST /characters/{id}/asi-choice`)
- Feat selection with prerequisite validation
- Ability score increase option (+2 or +1/+1)
- Half-feat ability choice support
- Auto-grant proficiencies and spells from feats
```

---

## 6. Implementation Order (TDD)

Execute in this sequence:

| Batch | Tasks | Description |
|-------|-------|-------------|
| 1 | 3.2, 3.3 | DTOs and Exceptions (no tests needed) |
| 2 | 4.1, 3.1 | PrerequisiteCheckerService (TDD) |
| 3 | 4.2, 4.3, 3.4 | AsiChoiceService (TDD) |
| 4 | 3.5 | Form Request |
| 5 | 3.6, 3.7, 3.8 | Controller, Resource, Route |
| 6 | 4.4 | Feature tests |
| 7 | 5.1, 5.2, 5.3 | Quality gates |

---

## 7. Files to Create/Modify

### New Files
```
app/DTOs/AsiChoiceResult.php
app/Services/PrerequisiteCheckerService.php
app/Services/AsiChoiceService.php
app/Http/Controllers/Api/AsiChoiceController.php
app/Http/Requests/AsiChoiceRequest.php
app/Http/Resources/AsiChoiceResource.php
app/Exceptions/NoAsiChoicesRemainingException.php
app/Exceptions/PrerequisitesNotMetException.php
app/Exceptions/FeatAlreadyTakenException.php
app/Exceptions/AbilityScoreCapExceededException.php
app/Exceptions/AbilityChoiceRequiredException.php
tests/Unit/Services/PrerequisiteCheckerServiceTest.php
tests/Unit/Services/AsiChoiceServiceTest.php
tests/Feature/Api/AsiChoiceApiTest.php
```

### Modified Files
```
routes/api.php
CHANGELOG.md
docs/PROJECT-STATUS.md (after completion)
```

---

## 8. Estimated Test Count

- PrerequisiteCheckerService: 7 unit tests
- AsiChoiceService: 17 unit tests
- AsiChoiceController: 10 feature tests
- **Total: ~34 new tests**

---

## 9. Success Criteria

- [ ] All 34+ tests pass
- [ ] Pint formatted
- [ ] Can take a feat via API
- [ ] Can increase ability scores via API
- [ ] Prerequisites properly validated
- [ ] Duplicate feats blocked
- [ ] Half-feats require and apply ability choice
- [ ] Feat proficiencies and spells granted
- [ ] CHANGELOG updated
- [ ] PR created referencing #93
