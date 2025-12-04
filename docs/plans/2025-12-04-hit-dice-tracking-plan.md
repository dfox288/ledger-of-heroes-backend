# Hit Dice Tracking Implementation Plan

**Issue:** #111 - Character Builder: Hit Dice Tracking
**Design:** [2025-12-04-hit-dice-tracking-design.md](./2025-12-04-hit-dice-tracking-design.md)
**Branch:** `feature/issue-111-hit-dice-tracking`

## Pre-Flight

- [ ] Runner: Sail (`sail artisan`, `sail composer`)
- [ ] Create feature branch from main
- [ ] Verify tests pass before starting

```bash
git checkout -b feature/issue-111-hit-dice-tracking
docker compose exec php php artisan test --testsuite=Unit-DB
```

---

## Task 1: HitDiceService with Unit Tests

**Files:**
- `app/Services/HitDiceService.php`
- `tests/Unit/Services/HitDiceServiceTest.php`

**Steps:**
1. Write failing test for `getHitDice()` - single class character
2. Write failing test for `getHitDice()` - multiclass character
3. Implement `getHitDice()` to pass tests
4. Write failing test for `spend()` - valid spend
5. Write failing test for `spend()` - insufficient dice throws exception
6. Implement `spend()` to pass tests
7. Write failing test for `recover()` - specific quantity
8. Write failing test for `recover()` - null quantity uses half-total rule
9. Write failing test for `recover()` - prefers larger dice
10. Implement `recover()` to pass tests

**Service signature:**
```php
class HitDiceService
{
    public function getHitDice(Character $character): array
    public function spend(Character $character, string $dieType, int $quantity): array
    public function recover(Character $character, ?int $quantity = null): array
}
```

**Verify:**
```bash
docker compose exec php php artisan test --filter=HitDiceServiceTest
```

---

## Task 2: HitDiceResource

**Files:**
- `app/Http/Resources/HitDiceResource.php`

**Steps:**
1. Create resource that formats the service output
2. Structure: `hit_dice` (by die type) + `total`

**No separate test needed** - covered by feature tests.

---

## Task 3: Form Requests

**Files:**
- `app/Http/Requests/HitDice/SpendHitDiceRequest.php`
- `app/Http/Requests/HitDice/RecoverHitDiceRequest.php`

**SpendHitDiceRequest rules:**
```php
[
    'die_type' => ['required', 'string', 'in:d6,d8,d10,d12'],
    'quantity' => ['required', 'integer', 'min:1'],
]
```

**RecoverHitDiceRequest rules:**
```php
[
    'quantity' => ['nullable', 'integer', 'min:1'],
]
```

---

## Task 4: HitDiceController with Feature Tests

**Files:**
- `app/Http/Controllers/Api/HitDiceController.php`
- `tests/Feature/Api/HitDiceControllerTest.php`

**Steps:**
1. Write failing test: GET returns hit dice structure
2. Write failing test: POST spend updates database
3. Write failing test: POST spend validates die_type
4. Write failing test: POST spend rejects insufficient dice
5. Write failing test: POST recover updates database
6. Write failing test: POST recover with null uses default
7. Implement controller methods to pass tests

**Controller methods:**
```php
public function index(Character $character): HitDiceResource
public function spend(SpendHitDiceRequest $request, Character $character): HitDiceResource
public function recover(RecoverHitDiceRequest $request, Character $character): HitDiceResource
```

**Verify:**
```bash
docker compose exec php php artisan test --filter=HitDiceControllerTest
```

---

## Task 5: Routes

**File:** `routes/api.php`

**Add:**
```php
Route::prefix('characters/{character}')->group(function () {
    Route::get('hit-dice', [HitDiceController::class, 'index']);
    Route::post('hit-dice/spend', [HitDiceController::class, 'spend']);
    Route::post('hit-dice/recover', [HitDiceController::class, 'recover']);
});
```

---

## Task 6: CharacterStatsResource Integration

**Files:**
- `app/DTOs/CharacterStatsDTO.php`
- `app/Http/Resources/CharacterStatsResource.php`
- `tests/Feature/Api/CharacterControllerTest.php` (add test)

**Steps:**
1. Add `hitDice` property to CharacterStatsDTO
2. Populate from HitDiceService in `fromCharacter()`
3. Add `hit_dice` to CharacterStatsResource output
4. Write test verifying stats endpoint includes hit_dice

**Verify:**
```bash
docker compose exec php php artisan test --filter=CharacterControllerTest::test_stats
```

---

## Task 7: Quality Gates & Documentation

**Steps:**
1. Run Pint: `docker compose exec php ./vendor/bin/pint`
2. Run full test suite: `docker compose exec php php artisan test --testsuite=Unit-DB`
3. Run feature tests: `docker compose exec php php artisan test --testsuite=Feature-DB`
4. Update CHANGELOG.md under [Unreleased]

**CHANGELOG entry:**
```markdown
### Added
- Hit dice tracking API endpoints (`GET/POST /characters/{id}/hit-dice`)
- Spend and recover hit dice for short/long rest mechanics
- Hit dice included in character stats endpoint
```

---

## Task 8: Commit & PR

**Steps:**
1. Stage all changes
2. Commit with message referencing issue
3. Push to feature branch
4. Create PR

```bash
git add -A
git commit -m "feat(#111): Add hit dice tracking API endpoints"
gh pr create --title "feat(#111): Hit Dice Tracking" --body "Closes #111"
```

---

## Verification Checklist

- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] Pint shows no issues
- [ ] GET /characters/{id}/hit-dice returns correct structure
- [ ] POST spend decrements hit_dice_spent correctly
- [ ] POST recover increments correctly (prefers larger dice)
- [ ] Stats endpoint includes hit_dice
- [ ] CHANGELOG.md updated
