# Armor/Weapon Proficiency Validation - Implementation Plan

**Date:** 2025-12-02
**Issue:** #94 - Character Builder: Armor/Weapon Proficiency Validation
**Branch:** `feature/issue-94-proficiency-validation`

---

## Overview

Add proficiency validation when equipping armor and weapons. Uses **soft validation** - allows equipping without proficiency but tracks penalties per D&D 5e rules.

### D&D 5e Rules

**Armor without proficiency (PHB p144):**
- Disadvantage on ability checks, saving throws, and attack rolls involving STR or DEX
- Cannot cast spells

**Weapons without proficiency:**
- Don't add proficiency bonus to attack rolls

### Data Context

**Armor proficiency names in DB:** `Light Armor`, `Medium Armor`, `Shields`, `all armor`, `heavy armor`

**Weapon proficiency names in DB:** `Simple Weapons`, `Martial Weapons`, plus specific weapons (`Longswords`, `Rapiers`, etc.)

**Martial weapon distinction:** Items with property code `M` are martial; without = simple

---

## Pre-flight Checklist

- [x] Runner: Docker Compose (`docker compose exec php ...`)
- [x] Branch: `feature/issue-94-proficiency-validation` created
- [ ] Existing tests passing
- [ ] Git status clean

---

## Phase 1: DTO & Service (TDD)

### Task 1.1: Create ProficiencyStatus DTO

**File:** `app/DTOs/ProficiencyStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ProficiencyStatus
{
    /**
     * @param bool $hasProficiency Whether character has proficiency
     * @param array<string> $penalties Active penalties if not proficient
     * @param string|null $source What granted proficiency (class/race/background name)
     */
    public function __construct(
        public bool $hasProficiency,
        public array $penalties = [],
        public ?string $source = null,
    ) {}

    public function toArray(): array
    {
        return [
            'has_proficiency' => $this->hasProficiency,
            'penalties' => $this->penalties,
            'source' => $this->source,
        ];
    }
}
```

### Task 1.2: Write failing tests for ProficiencyCheckerService

**File:** `tests/Unit/Services/ProficiencyCheckerServiceTest.php`

Test cases:
1. `it_returns_proficient_for_fighter_with_heavy_armor` - Fighter has all armor
2. `it_returns_not_proficient_for_wizard_with_heavy_armor` - Wizard only has none
3. `it_returns_armor_penalties_when_not_proficient` - Check penalty array
4. `it_returns_proficient_for_fighter_with_martial_weapon` - Fighter has martial weapons
5. `it_returns_proficient_for_wizard_with_simple_weapon` - Wizard has simple weapons
6. `it_returns_not_proficient_for_wizard_with_martial_weapon` - Wizard lacks martial
7. `it_returns_weapon_penalty_when_not_proficient` - Check penalty array
8. `it_returns_proficient_for_specific_weapon_proficiency` - Rogue with rapier
9. `it_checks_race_proficiencies` - Dwarf with battleaxe
10. `it_checks_background_proficiencies` - If any grant weapon proficiencies
11. `it_returns_proficient_for_shield_with_shield_proficiency`
12. `it_includes_source_when_proficient` - Shows "Fighter" or "Dwarf" as source

### Task 1.3: Implement ProficiencyCheckerService

**File:** `app/Services/ProficiencyCheckerService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ProficiencyStatus;
use App\Enums\ItemTypeCode;
use App\Models\Character;
use App\Models\Item;
use Illuminate\Support\Collection;

class ProficiencyCheckerService
{
    private const ARMOR_PENALTIES = [
        'disadvantage_str_dex_checks',
        'disadvantage_str_dex_saves',
        'disadvantage_attack_rolls',
        'cannot_cast_spells',
    ];

    private const WEAPON_PENALTIES = [
        'no_proficiency_bonus_to_attack',
    ];

    public function checkEquipmentProficiency(Character $character, Item $item): ProficiencyStatus
    {
        $typeCode = $item->itemType?->code;

        if (in_array($typeCode, ItemTypeCode::armorCodes())) {
            return $this->checkArmorProficiency($character, $item);
        }

        if ($typeCode === ItemTypeCode::SHIELD->value) {
            return $this->checkShieldProficiency($character, $item);
        }

        if (in_array($typeCode, ItemTypeCode::weaponCodes())) {
            return $this->checkWeaponProficiency($character, $item);
        }

        // Non-armor/weapon items don't need proficiency
        return new ProficiencyStatus(hasProficiency: true);
    }

    public function checkArmorProficiency(Character $character, Item $item): ProficiencyStatus
    {
        $proficiencies = $this->getCharacterProficiencies($character, 'armor');
        $requiredProficiency = $this->getRequiredArmorProficiency($item);

        // Check for "all armor" first
        if ($this->hasProficiencyMatch($proficiencies, ['all armor'])) {
            return new ProficiencyStatus(
                hasProficiency: true,
                source: $this->findProficiencySource($character, 'armor', 'all armor')
            );
        }

        // Check specific armor type
        if ($this->hasProficiencyMatch($proficiencies, [$requiredProficiency])) {
            return new ProficiencyStatus(
                hasProficiency: true,
                source: $this->findProficiencySource($character, 'armor', $requiredProficiency)
            );
        }

        return new ProficiencyStatus(
            hasProficiency: false,
            penalties: self::ARMOR_PENALTIES
        );
    }

    public function checkShieldProficiency(Character $character, Item $item): ProficiencyStatus
    {
        $proficiencies = $this->getCharacterProficiencies($character, 'armor');

        $shieldMatches = ['Shields', 'shield', 'all armor'];

        foreach ($shieldMatches as $match) {
            if ($this->hasProficiencyMatch($proficiencies, [$match])) {
                return new ProficiencyStatus(
                    hasProficiency: true,
                    source: $this->findProficiencySource($character, 'armor', $match)
                );
            }
        }

        return new ProficiencyStatus(
            hasProficiency: false,
            penalties: self::ARMOR_PENALTIES
        );
    }

    public function checkWeaponProficiency(Character $character, Item $item): ProficiencyStatus
    {
        $proficiencies = $this->getCharacterProficiencies($character, 'weapon');
        $isMartial = $item->properties->contains('code', 'M');
        $weaponName = $item->name;

        // Check specific weapon proficiency first (e.g., "Longswords", "longsword")
        $specificMatches = [$weaponName, strtolower($weaponName), $weaponName . 's'];
        foreach ($specificMatches as $match) {
            if ($this->hasProficiencyMatch($proficiencies, [$match])) {
                return new ProficiencyStatus(
                    hasProficiency: true,
                    source: $this->findProficiencySource($character, 'weapon', $match)
                );
            }
        }

        // Check category proficiency
        $categoryMatch = $isMartial ? 'Martial Weapons' : 'Simple Weapons';
        if ($this->hasProficiencyMatch($proficiencies, [$categoryMatch])) {
            return new ProficiencyStatus(
                hasProficiency: true,
                source: $this->findProficiencySource($character, 'weapon', $categoryMatch)
            );
        }

        return new ProficiencyStatus(
            hasProficiency: false,
            penalties: self::WEAPON_PENALTIES
        );
    }

    private function getCharacterProficiencies(Character $character, string $type): Collection
    {
        $proficiencies = collect();

        // From class
        if ($character->characterClass) {
            $proficiencies = $proficiencies->merge(
                $character->characterClass->proficiencies
                    ->where('proficiency_type', $type)
                    ->pluck('proficiency_name')
            );
        }

        // From race
        if ($character->race) {
            $proficiencies = $proficiencies->merge(
                $character->race->proficiencies
                    ->where('proficiency_type', $type)
                    ->pluck('proficiency_name')
            );
        }

        // From background (rarely grants armor/weapon but possible)
        if ($character->background) {
            $proficiencies = $proficiencies->merge(
                $character->background->proficiencies
                    ->where('proficiency_type', $type)
                    ->pluck('proficiency_name')
            );
        }

        return $proficiencies->filter()->unique();
    }

    private function getRequiredArmorProficiency(Item $item): string
    {
        return match ($item->itemType?->code) {
            ItemTypeCode::LIGHT_ARMOR->value => 'Light Armor',
            ItemTypeCode::MEDIUM_ARMOR->value => 'Medium Armor',
            ItemTypeCode::HEAVY_ARMOR->value => 'Heavy Armor',
            default => '',
        };
    }

    private function hasProficiencyMatch(Collection $proficiencies, array $matches): bool
    {
        $normalizedProficiencies = $proficiencies->map(fn ($p) => strtolower(trim($p)));
        $normalizedMatches = array_map(fn ($m) => strtolower(trim($m)), $matches);

        return $normalizedProficiencies->intersect($normalizedMatches)->isNotEmpty();
    }

    private function findProficiencySource(Character $character, string $type, string $name): ?string
    {
        $normalizedName = strtolower(trim($name));

        // Check class
        if ($character->characterClass) {
            $hasProf = $character->characterClass->proficiencies
                ->where('proficiency_type', $type)
                ->pluck('proficiency_name')
                ->map(fn ($p) => strtolower(trim($p)))
                ->contains($normalizedName);

            if ($hasProf) {
                return $character->characterClass->name;
            }
        }

        // Check race
        if ($character->race) {
            $hasProf = $character->race->proficiencies
                ->where('proficiency_type', $type)
                ->pluck('proficiency_name')
                ->map(fn ($p) => strtolower(trim($p)))
                ->contains($normalizedName);

            if ($hasProf) {
                return $character->race->name;
            }
        }

        // Check background
        if ($character->background) {
            $hasProf = $character->background->proficiencies
                ->where('proficiency_type', $type)
                ->pluck('proficiency_name')
                ->map(fn ($p) => strtolower(trim($p)))
                ->contains($normalizedName);

            if ($hasProf) {
                return $character->background->name;
            }
        }

        return null;
    }
}
```

---

## Phase 2: API Resource Integration

### Task 2.1: Write failing feature tests for proficiency in API response

**File:** `tests/Feature/Api/CharacterEquipmentProficiencyTest.php`

Test cases:
1. `it_includes_proficiency_status_for_equipped_armor`
2. `it_includes_proficiency_status_for_equipped_weapon`
3. `it_does_not_include_proficiency_status_for_unequipped_items`
4. `it_shows_penalties_for_non_proficient_armor`
5. `it_shows_source_when_proficient`

### Task 2.2: Update CharacterEquipmentResource

**File:** `app/Http/Resources/CharacterEquipmentResource.php`

Add `proficiency_status` field that includes:
- `has_proficiency`: boolean
- `penalties`: array (empty if proficient)
- `source`: string|null (class/race/background name if proficient)

Only include when item is equipped.

---

## Phase 3: Character Resource Enhancement

### Task 3.1: Add proficiency penalties summary to CharacterResource

The CharacterResource should include a summary of active proficiency penalties affecting the character.

**File:** `app/Http/Resources/CharacterResource.php`

Add to response:
```php
'proficiency_penalties' => [
    'has_armor_penalty' => bool,
    'has_weapon_penalty' => bool,
    'penalties' => [...],  // Aggregated penalty list
],
```

### Task 3.2: Write tests for CharacterResource proficiency summary

Verify the character response includes penalty summary based on equipped items.

---

## Phase 4: Quality Gates

### Task 4.1: Run test suites

```bash
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 4.2: Run Pint

```bash
docker compose exec php ./vendor/bin/pint
```

### Task 4.3: Update CHANGELOG.md

Add under `[Unreleased]`:
```markdown
### Added
- Proficiency validation for equipped armor and weapons
- ProficiencyCheckerService for checking character proficiencies
- ProficiencyStatus DTO for proficiency check results
- Proficiency status in CharacterEquipmentResource for equipped items
- Proficiency penalty summary in CharacterResource
```

---

## Implementation Notes

### Proficiency Matching Logic

**Armor:**
- `LA` → "Light Armor"
- `MA` → "Medium Armor"
- `HA` → "Heavy Armor" (also matches "heavy armor" lowercase)
- `S` → "Shields" (also matches "shield")
- "all armor" matches everything

**Weapons:**
1. Check specific weapon name first (e.g., "Longsword", "Longswords")
2. Check category ("Simple Weapons" or "Martial Weapons")
3. Martial weapons have property code `M`
4. Weapons without `M` property are Simple

### Eager Loading

Ensure `CharacterEquipmentResource` has access to:
- `character.characterClass.proficiencies`
- `character.race.proficiencies`
- `character.background.proficiencies`
- `item.itemType`
- `item.properties`

---

## Test Data Requirements

Tests will use factories with specific class/race combinations:
- **Fighter:** All armor, all weapons (martial + simple)
- **Wizard:** No armor, simple weapons only
- **Rogue:** Light armor, simple weapons + rapiers/shortswords
- **Dwarf race:** Battleaxe, handaxe, warhammer proficiency

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `app/DTOs/ProficiencyStatus.php` | Create |
| `app/Services/ProficiencyCheckerService.php` | Create |
| `app/Http/Resources/CharacterEquipmentResource.php` | Modify |
| `app/Http/Resources/CharacterResource.php` | Modify |
| `tests/Unit/Services/ProficiencyCheckerServiceTest.php` | Create |
| `tests/Feature/Api/CharacterEquipmentProficiencyTest.php` | Create |
| `CHANGELOG.md` | Update |

---

## Success Criteria

- [ ] All new tests pass
- [ ] Existing equipment tests still pass
- [ ] API returns proficiency status for equipped items
- [ ] Penalties correctly applied per D&D 5e rules
- [ ] Source attribution works for class/race/background
- [ ] Pint formatting clean
- [ ] CHANGELOG updated
