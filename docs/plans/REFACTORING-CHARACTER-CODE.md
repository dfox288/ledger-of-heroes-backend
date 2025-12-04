# Character Code Refactoring Plan

## Overview

This plan addresses duplication and inconsistencies found in the character-related code through a comprehensive audit. The refactoring is split into two phases: quick wins (traits) and larger consolidation (services).

---

## Phase 1: Extract `HasLimitedUses` Trait (Quick Win) ✅ COMPLETED

**Estimated Time:** 2-3 hours
**Actual Time:** ~1 hour
**Files Affected:** 4 files (3 + test)
**Lines Eliminated:** ~30 lines

**Completed:** 2025-12-04
**PR:** feature/issue-142-has-limited-uses-trait

### Problem

`CharacterFeature` and `CharacterOptionalFeature` have **identical** methods:

```php
// Both models have these 4 methods (lines 48-78 and 51-81 respectively)
public function hasLimitedUses(): bool
public function hasUsesRemaining(): bool
public function useFeature(): bool
public function resetUses(): void
```

### Solution

Create `app/Models/Concerns/HasLimitedUses.php`:

```php
<?php

namespace App\Models\Concerns;

trait HasLimitedUses
{
    /**
     * Check if this feature has limited uses (max_uses is set).
     */
    public function hasLimitedUses(): bool
    {
        return $this->max_uses !== null;
    }

    /**
     * Check if uses are remaining (unlimited or uses_remaining > 0).
     */
    public function hasUsesRemaining(): bool
    {
        return $this->uses_remaining === null || $this->uses_remaining > 0;
    }

    /**
     * Consume one use. Returns false if no uses remaining.
     */
    public function useFeature(): bool
    {
        if (! $this->hasLimitedUses()) {
            return true;
        }

        if ($this->uses_remaining > 0) {
            $this->decrement('uses_remaining');
            return true;
        }

        return false;
    }

    /**
     * Reset uses to max_uses (for rest mechanics).
     */
    public function resetUses(): void
    {
        if ($this->hasLimitedUses()) {
            $this->update(['uses_remaining' => $this->max_uses]);
        }
    }
}
```

### Implementation Steps

1. [x] Create `app/Models/Concerns/HasLimitedUses.php` with trait
2. [x] Update `CharacterFeature.php`:
   - Add `use HasLimitedUses;`
   - Remove 4 duplicated methods (lines 48-78)
3. [x] Update `CharacterOptionalFeature.php`:
   - Add `use HasLimitedUses;`
   - Remove 4 duplicated methods (lines 51-81)
4. [x] Run existing tests to verify no regressions
5. [x] Add dedicated trait test: `tests/Unit/Models/Concerns/HasLimitedUsesTest.php` (11 tests)

### Tests Created

```bash
docker compose exec php php artisan test tests/Unit/Models/Concerns/HasLimitedUsesTest.php
# 11 passed (17 assertions)
```

---

## Phase 2: Consolidate Populate/Choice Pattern (Larger Effort)

**Estimated Time:** 6-8 hours
**Files Affected:** 6+ files
**Lines Eliminated:** ~200+ lines

### Problem

Three services implement nearly identical patterns:

| Service | Populate Methods | Choice Methods | Lines |
|---------|------------------|----------------|-------|
| `CharacterLanguageService` | `populateFixed()`, `populateFromRace()`, etc. | `getPendingChoices()`, `makeChoice()` | 421 |
| `CharacterProficiencyService` | `populateAll()`, `populateFromClass()`, etc. | `getPendingChoices()`, `makeSkillChoice()` | 322 |
| `CharacterFeatureService` | `populateFromRace()`, `populateFromClass()`, etc. | (limited) | ~200 |

**Common Pattern:**
1. Check if source entity exists (race, background, class, feat)
2. Get fixed items from entity (is_choice = false)
3. Check if character already has item
4. Create CharacterX record with source tracking
5. Refresh relationship

### Solution Architecture

#### Option A: Abstract Base Class (Recommended)

```
app/Services/
├── Concerns/
│   └── PopulatesCharacterEntities.php    # Shared populate logic
├── CharacterLanguageService.php           # Uses trait, adds language-specific logic
├── CharacterProficiencyService.php        # Uses trait, adds proficiency-specific logic
└── CharacterFeatureService.php            # Uses trait, adds feature-specific logic
```

#### Option B: Dedicated Orchestrator

```
app/Services/
├── CharacterPopulateService.php           # Orchestrates all populate operations
├── Populators/
│   ├── LanguagePopulator.php
│   ├── ProficiencyPopulator.php
│   └── FeaturePopulator.php
```

**Recommendation:** Option A is less disruptive and maintains existing service interfaces.

### Trait Design: `PopulatesCharacterEntities`

```php
<?php

namespace App\Services\Concerns;

use App\Models\Character;
use Illuminate\Database\Eloquent\Model;

trait PopulatesCharacterEntities
{
    /**
     * Populate fixed items from an entity.
     *
     * @param Character $character
     * @param Model|null $entity The source entity (Race, Background, CharacterClass, Feat)
     * @param string $source Source identifier ('race', 'background', 'class', 'feat')
     */
    protected function populateFixedFromEntity(Character $character, ?Model $entity, string $source): void
    {
        if (! $entity) {
            return;
        }

        $fixedItems = $this->getFixedItemsFromEntity($entity);

        foreach ($fixedItems as $item) {
            if ($this->characterAlreadyHasItem($character, $item, $source)) {
                continue;
            }

            $this->createCharacterRecord($character, $item, $source);
        }

        $this->refreshCharacterRelationship($character);
    }

    /**
     * Get fixed items from the source entity.
     * Must be implemented by using class.
     */
    abstract protected function getFixedItemsFromEntity(Model $entity);

    /**
     * Check if character already has this item.
     * Must be implemented by using class.
     */
    abstract protected function characterAlreadyHasItem(Character $character, $item, string $source): bool;

    /**
     * Create the character record.
     * Must be implemented by using class.
     */
    abstract protected function createCharacterRecord(Character $character, $item, string $source): void;

    /**
     * Refresh the relevant relationship.
     * Must be implemented by using class.
     */
    abstract protected function refreshCharacterRelationship(Character $character): void;
}
```

### Implementation Steps

1. [ ] Create `app/Services/Concerns/PopulatesCharacterEntities.php`
2. [ ] Refactor `CharacterLanguageService`:
   - Add `use PopulatesCharacterEntities;`
   - Implement abstract methods
   - Replace `populateFixedLanguages()` with trait call
3. [ ] Refactor `CharacterProficiencyService`:
   - Add `use PopulatesCharacterEntities;`
   - Implement abstract methods
   - Replace `populateFixedProficiencies()` with trait call
4. [ ] Refactor `CharacterFeatureService` (if applicable)
5. [ ] Add tests for the trait
6. [ ] Run full test suite

### Choice Pattern Consolidation (Sub-phase)

The choice pattern is more complex due to different validation rules per type:
- Languages: Any language not already known
- Proficiencies: From a predefined list per choice group
- Features: Level requirements, class restrictions

**Recommendation:** Keep choice logic in individual services but extract common response formatting.

```php
// app/DTOs/PendingChoicesDTO.php
class PendingChoicesDTO
{
    public function __construct(
        public array $known,
        public int $quantity,
        public int $remaining,
        public array $selected,
        public array $options,
    ) {}

    public function toArray(): array
    {
        return [
            'known' => $this->known,
            'choices' => [
                'quantity' => $this->quantity,
                'remaining' => $this->remaining,
                'selected' => $this->selected,
                'options' => $this->options,
            ],
        ];
    }
}
```

---

## Phase 3: Additional Quick Wins (Optional)

### 3a. Create Source Enum

```php
// app/Enums/CharacterSource.php
enum CharacterSource: string
{
    case RACE = 'race';
    case BACKGROUND = 'background';
    case CLASS = 'class';
    case FEAT = 'feat';
    case ITEM = 'item';
}
```

**Usage:**
```php
// Before
if (! in_array($source, ['race', 'background', 'feat'])) { ... }

// After
CharacterSource::tryFrom($source) ?? throw new InvalidArgumentException();
```

### 3b. Resource Formatting Trait

```php
// app/Http/Resources/Concerns/FormatsRelatedModels.php
trait FormatsRelatedModels
{
    protected function formatEntity(?Model $entity, array $fields = ['id', 'name', 'slug']): ?array
    {
        if (! $entity) {
            return null;
        }

        return collect($fields)
            ->mapWithKeys(fn($field) => [$field => $entity->$field])
            ->toArray();
    }
}
```

---

## Testing Strategy

### Existing Test Coverage

Verify these test files cover the refactored code:

```bash
tests/Feature/Api/Character/
├── CharacterLanguageTest.php
├── CharacterProficiencyTest.php
├── CharacterFeatureTest.php
└── CharacterOptionalFeatureTest.php
```

### New Tests to Add

1. `tests/Unit/Models/Concerns/HasLimitedUsesTest.php` - Trait behavior
2. `tests/Unit/Services/Concerns/PopulatesCharacterEntitiesTest.php` - Populate pattern

---

## Rollout Plan

| Phase | Scope | Risk | Rollback |
|-------|-------|------|----------|
| 1 | `HasLimitedUses` trait | Low | Revert single commit |
| 2 | Populate trait | Medium | Revert, restore original services |
| 3a | Source enum | Low | String literals still work |
| 3b | Resource trait | Low | Revert single commit |

---

## Success Metrics

- [ ] ~200+ lines of code eliminated
- [ ] All existing tests pass
- [ ] No new N+1 queries introduced
- [ ] Services easier to test in isolation
- [ ] New "populate" systems (e.g., Fighting Styles) can reuse pattern

---

## Files Reference

### Phase 1 Files
- `app/Models/CharacterFeature.php` (lines 48-78)
- `app/Models/CharacterOptionalFeature.php` (lines 51-81)
- **New:** `app/Models/Concerns/HasLimitedUses.php`

### Phase 2 Files
- `app/Services/CharacterLanguageService.php` (421 lines)
- `app/Services/CharacterProficiencyService.php` (322 lines)
- `app/Services/CharacterFeatureService.php` (~200 lines)
- **New:** `app/Services/Concerns/PopulatesCharacterEntities.php`

---

*Created: 2025-12-04*
*Status: Planning*
