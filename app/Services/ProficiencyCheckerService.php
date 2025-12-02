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

    /**
     * Check if character has proficiency with the given equipment item.
     */
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

        // Non-armor/weapon items don't require proficiency
        return new ProficiencyStatus(hasProficiency: true);
    }

    /**
     * Check if character has proficiency with the given armor.
     */
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

    /**
     * Check if character has proficiency with shields.
     */
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

    /**
     * Check if character has proficiency with the given weapon.
     */
    public function checkWeaponProficiency(Character $character, Item $item): ProficiencyStatus
    {
        $proficiencies = $this->getCharacterProficiencies($character, 'weapon');
        $isMartial = $item->properties->contains('code', 'M');
        $weaponName = $item->name;

        // Check specific weapon proficiency first (e.g., "Longswords", "longsword")
        $specificMatches = [$weaponName, strtolower($weaponName), $weaponName.'s'];
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

    /**
     * Get all proficiencies of a given type from character's class, race, and background.
     */
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

    /**
     * Get the required proficiency name for an armor item based on its type code.
     */
    private function getRequiredArmorProficiency(Item $item): string
    {
        return match ($item->itemType?->code) {
            ItemTypeCode::LIGHT_ARMOR->value => 'Light Armor',
            ItemTypeCode::MEDIUM_ARMOR->value => 'Medium Armor',
            ItemTypeCode::HEAVY_ARMOR->value => 'Heavy Armor',
            default => '',
        };
    }

    /**
     * Check if any of the character's proficiencies match the given list (case-insensitive).
     */
    private function hasProficiencyMatch(Collection $proficiencies, array $matches): bool
    {
        $normalizedProficiencies = $proficiencies->map(fn ($p) => strtolower(trim((string) $p)));
        $normalizedMatches = array_map(fn ($m) => strtolower(trim($m)), $matches);

        return $normalizedProficiencies->intersect($normalizedMatches)->isNotEmpty();
    }

    /**
     * Find which source (class, race, or background) granted a proficiency.
     */
    private function findProficiencySource(Character $character, string $type, string $name): ?string
    {
        $normalizedName = strtolower(trim($name));

        // Check class
        if ($character->characterClass) {
            $hasProf = $character->characterClass->proficiencies
                ->where('proficiency_type', $type)
                ->pluck('proficiency_name')
                ->map(fn ($p) => strtolower(trim((string) $p)))
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
                ->map(fn ($p) => strtolower(trim((string) $p)))
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
                ->map(fn ($p) => strtolower(trim((string) $p)))
                ->contains($normalizedName);

            if ($hasProf) {
                return $character->background->name;
            }
        }

        return null;
    }
}
