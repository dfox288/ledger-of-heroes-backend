# Tough Feat HP Implementation Plan

**Issue:** #356
**Branch:** `feature/issue-356-tough-feat-hp`
**Goal:** When Tough feat is granted, add +2 HP per level retroactively; future level-ups include +2 HP bonus.

**Related:** #366 (race HP modifiers - future enhancement)

---

## Task 1: Add HP Per-Level Parsing to FeatXmlParser

**Files:**
- Modify: `app/Services/Parsers/FeatXmlParser.php`
- Test: `tests/Unit/Parsers/FeatXmlParserTest.php`

**Steps:**
1. Write failing test for HP per-level extraction from Tough feat text
2. Add `parseHitPointPerLevelModifiers()` method to detect pattern: "hit point maximum increases by an additional N hit points"
3. Call method in `parseFeat()` and merge into modifiers array
4. Verify test passes

---

## Task 2: Re-import Feats

**Steps:**
1. Run `docker compose exec php php artisan import:feats`
2. Verify Tough feat has `hp: 2` modifier in DB

---

## Task 3: Add HitPointService.getFeatHpBonus()

**Files:**
- Modify: `app/Services/HitPointService.php`
- Test: `tests/Unit/Services/HitPointServiceTest.php`

**Steps:**
1. Write failing tests for:
   - Returns hp bonus from feat with hp modifier
   - Returns zero when no feats with hp modifiers
   - Sums hp bonuses from multiple feats
2. Implement `getFeatHpBonus(Character): int` - queries feat modifiers
3. Verify tests pass

---

## Task 4: Apply Retroactive HP in AsiChoiceService

**Files:**
- Modify: `app/Services/AsiChoiceService.php`
- Test: `tests/Unit/Services/AsiChoiceServiceTest.php`

**Steps:**
1. Write failing tests for:
   - Retroactive HP when granting Tough feat (2 × level)
   - No HP change for feats without hp modifier
2. Add `applyRetroactiveHpBonus()` method called after creating CharacterFeature
3. Verify tests pass

---

## Task 5: Include HP Bonus in Level-Up

**Files:**
- Modify: `app/Services/ChoiceHandlers/HitPointRollChoiceHandler.php`
- Test: `tests/Unit/Services/ChoiceHandlers/HitPointRollChoiceHandlerTest.php`

**Steps:**
1. Write failing test for feat HP bonus in level-up HP gain
2. Inject `HitPointService` into handler
3. Add feat HP bonus to `hpGained` calculation in `resolve()`
4. Verify test passes

---

## Task 6: Feature Tests

**Files:**
- Create: `tests/Feature/Services/ToughFeatHpTest.php`

**Tests:**
- Tough feat grants retroactive HP at level 5
- Level-up with Tough includes bonus
- Complete flow: grant Tough then level up

---

## Task 7: CHANGELOG + PR

1. Run Unit-DB and Feature-DB test suites
2. Run Pint
3. Update CHANGELOG.md
4. Push and create PR

---

## Summary

| Task | Tests |
|------|-------|
| Parser HP extraction | 1 |
| HitPointService.getFeatHpBonus() | 3 |
| Retroactive HP in AsiChoiceService | 2 |
| HP bonus in level-up | 1 |
| End-to-end feature tests | 3 |
| **Total** | **10** |
