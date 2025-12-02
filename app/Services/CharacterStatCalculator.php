<?php

namespace App\Services;

use App\Enums\ItemTypeCode;
use App\Models\Character;

class CharacterStatCalculator
{
    /**
     * Full caster spell slot progression table (levels 1-20).
     * Used by: Wizard, Cleric, Druid, Sorcerer, Bard
     */
    private const FULL_CASTER_SLOTS = [
        1 => [1 => 2],
        2 => [1 => 3],
        3 => [1 => 4, 2 => 2],
        4 => [1 => 4, 2 => 3],
        5 => [1 => 4, 2 => 3, 3 => 2],
        6 => [1 => 4, 2 => 3, 3 => 3],
        7 => [1 => 4, 2 => 3, 3 => 3, 4 => 1],
        8 => [1 => 4, 2 => 3, 3 => 3, 4 => 2],
        9 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 1],
        10 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2],
        11 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1],
        12 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1],
        13 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1],
        14 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1],
        15 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1],
        16 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1],
        17 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 1, 7 => 1, 8 => 1, 9 => 1],
        18 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 1, 7 => 1, 8 => 1, 9 => 1],
        19 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 2, 7 => 1, 8 => 1, 9 => 1],
        20 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 2, 7 => 2, 8 => 1, 9 => 1],
    ];

    /**
     * Half caster spell slot progression (levels 2-20).
     * Used by: Paladin, Ranger
     * Note: No spellcasting at level 1
     */
    private const HALF_CASTER_SLOTS = [
        2 => [1 => 2],
        3 => [1 => 3],
        4 => [1 => 3],
        5 => [1 => 4, 2 => 2],
        6 => [1 => 4, 2 => 2],
        7 => [1 => 4, 2 => 3],
        8 => [1 => 4, 2 => 3],
        9 => [1 => 4, 2 => 3, 3 => 2],
        10 => [1 => 4, 2 => 3, 3 => 2],
        11 => [1 => 4, 2 => 3, 3 => 3],
        12 => [1 => 4, 2 => 3, 3 => 3],
        13 => [1 => 4, 2 => 3, 3 => 3, 4 => 1],
        14 => [1 => 4, 2 => 3, 3 => 3, 4 => 1],
        15 => [1 => 4, 2 => 3, 3 => 3, 4 => 2],
        16 => [1 => 4, 2 => 3, 3 => 3, 4 => 2],
        17 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 1],
        18 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 1],
        19 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2],
        20 => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 2],
    ];

    /**
     * Warlock Pact Magic progression.
     * Warlocks get fewer slots but they're all at the highest available level.
     * Format: level => [slot_level => count]
     */
    private const WARLOCK_PACT_SLOTS = [
        1 => [1 => 1],
        2 => [1 => 2],
        3 => [2 => 2],
        4 => [2 => 2],
        5 => [3 => 2],
        6 => [3 => 2],
        7 => [4 => 2],
        8 => [4 => 2],
        9 => [5 => 2],
        10 => [5 => 2],
        11 => [5 => 3],
        12 => [5 => 3],
        13 => [5 => 3],
        14 => [5 => 3],
        15 => [5 => 3],
        16 => [5 => 3],
        17 => [5 => 4],
        18 => [5 => 4],
        19 => [5 => 4],
        20 => [5 => 4],
    ];

    /**
     * Full caster classes (use full progression).
     */
    private const FULL_CASTERS = ['wizard', 'cleric', 'druid', 'sorcerer', 'bard'];

    /**
     * Half caster classes (use half progression).
     */
    private const HALF_CASTERS = ['paladin', 'ranger'];

    /**
     * Classes that know spells instead of preparing (returns null for preparation limit).
     */
    private const KNOWN_CASTERS = ['sorcerer', 'bard', 'warlock', 'ranger'];

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
     *
     * @param  int  $abilityModifier  The modifier for the ability this skill uses
     * @param  bool  $proficient  Whether the character is proficient in this skill
     * @param  bool  $expertise  Whether the character has expertise (double proficiency)
     * @param  int  $proficiencyBonus  The character's proficiency bonus
     */
    public function skillModifier(
        int $abilityModifier,
        bool $proficient,
        bool $expertise,
        int $proficiencyBonus
    ): int {
        $modifier = $abilityModifier;

        if ($proficient) {
            $modifier += $proficiencyBonus;
        }

        if ($expertise) {
            $modifier += $proficiencyBonus; // Double proficiency
        }

        return $modifier;
    }

    /**
     * Calculate max HP using average hit die method.
     *
     * Level 1: max hit die + CON modifier
     * Higher levels: add (average hit die + CON modifier) per level
     * Average hit die = (die / 2) + 1 (e.g., d6=4, d8=5, d10=6, d12=7)
     *
     * @param  int  $hitDie  The hit die size (6, 8, 10, or 12)
     * @param  int  $level  The character's level
     * @param  int  $conModifier  The CON ability modifier
     */
    public function calculateMaxHP(int $hitDie, int $level, int $conModifier): int
    {
        // Level 1: max hit die + CON modifier
        $hp = $hitDie + $conModifier;

        // Levels 2+: average hit die + CON modifier per level
        if ($level > 1) {
            $avgHitDie = (int) ($hitDie / 2) + 1;
            $hp += ($level - 1) * ($avgHitDie + $conModifier);
        }

        // Minimum 1 HP even with very negative CON
        return max(1, $hp);
    }

    /**
     * Calculate armor class.
     *
     * @param  int  $dexModifier  The DEX ability modifier
     * @param  int|null  $armorBaseAC  Base AC of armor (null = unarmored)
     * @param  int|null  $armorMaxDex  Maximum DEX bonus for armor (null = unlimited, 0 = none)
     * @param  int  $shieldBonus  Bonus from shield (typically +2)
     * @param  int  $otherBonuses  Other AC bonuses (magic items, etc.)
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
     * Calculate armor class from a Character's equipped items.
     *
     * This method looks up the character's equipped armor and shield,
     * then delegates to calculateAC with the appropriate values.
     */
    public function calculateArmorClass(Character $character): int
    {
        $dexMod = $this->abilityModifier($character->dexterity ?? 10);

        $equippedArmor = $character->equippedArmor();
        $equippedShield = $character->equippedShield();

        // Determine armor values
        $armorBaseAC = null;
        $armorMaxDex = null;

        if ($equippedArmor !== null) {
            $armor = $equippedArmor->item;
            $armorBaseAC = $armor->armor_class ?? 10;
            $armorTypeCode = $armor->itemType?->code;

            // Set max DEX based on armor type code
            $armorMaxDex = match ($armorTypeCode) {
                ItemTypeCode::LIGHT_ARMOR->value => null,   // No limit
                ItemTypeCode::MEDIUM_ARMOR->value => 2,     // Max +2
                ItemTypeCode::HEAVY_ARMOR->value => 0,      // No DEX bonus
                default => null,
            };
        }

        // Get shield bonus
        $shieldBonus = 0;
        if ($equippedShield !== null) {
            $shieldBonus = $equippedShield->item->armor_class ?? 2;
        }

        return $this->calculateAC($dexMod, $armorBaseAC, $armorMaxDex, $shieldBonus, 0);
    }

    /**
     * Get spell slots for a class at a given level.
     *
     * @param  string  $classSlug  The class slug (wizard, cleric, paladin, etc.)
     * @param  int  $level  The character's level in this class
     * @return array<int, int> Spell slots indexed by spell level
     */
    public function getSpellSlots(string $classSlug, int $level): array
    {
        $classSlug = strtolower($classSlug);

        // Warlock uses Pact Magic (special progression)
        if ($classSlug === 'warlock') {
            return self::WARLOCK_PACT_SLOTS[$level] ?? [];
        }

        // Full casters
        if (in_array($classSlug, self::FULL_CASTERS)) {
            return self::FULL_CASTER_SLOTS[$level] ?? [];
        }

        // Half casters (no spellcasting at level 1)
        if (in_array($classSlug, self::HALF_CASTERS)) {
            return self::HALF_CASTER_SLOTS[$level] ?? [];
        }

        // Non-casters return empty array
        return [];
    }

    /**
     * Get preparation limit for prepared casters.
     * Returns null for "known" casters (sorcerer, bard, warlock, ranger).
     *
     * @param  string  $classSlug  The class slug
     * @param  int  $level  The character's level
     * @param  int  $abilityModifier  The spellcasting ability modifier
     * @return int|null Preparation limit, or null for known casters
     */
    public function getPreparationLimit(string $classSlug, int $level, int $abilityModifier): ?int
    {
        $classSlug = strtolower($classSlug);

        // Known casters don't prepare spells
        if (in_array($classSlug, self::KNOWN_CASTERS)) {
            return null;
        }

        $limit = match ($classSlug) {
            'wizard', 'cleric', 'druid' => $abilityModifier + $level,
            'paladin' => $abilityModifier + (int) floor($level / 2),
            default => null,
        };

        // Minimum 1 prepared spell for prepared casters
        if ($limit !== null) {
            return max(1, $limit);
        }

        return null;
    }
}
