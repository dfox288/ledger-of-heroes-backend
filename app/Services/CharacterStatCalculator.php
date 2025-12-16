<?php

namespace App\Services;

use App\Enums\ItemTypeCode;
use App\Models\Character;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Modifier;

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
    private const HALF_CASTERS = ['paladin', 'ranger', 'artificer'];

    /**
     * Classes that know spells instead of preparing (returns null for preparation limit).
     *
     * @see \App\Models\CharacterClass::getSpellPreparationMethodAttribute() for canonical source
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
     * When unarmored, checks for Unarmored Defense-type features.
     */
    public function calculateArmorClass(Character $character): int
    {
        $dexMod = $this->abilityModifier($character->dexterity ?? 10);

        $equippedArmor = $character->equippedArmor();
        $equippedShield = $character->equippedShield();

        // Get shield bonus
        $shieldBonus = 0;
        if ($equippedShield !== null) {
            $shieldBonus = $equippedShield->item->armor_class ?? 2;
        }

        // If wearing armor, use armor AC calculation
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

            return $this->calculateAC($dexMod, $armorBaseAC, $armorMaxDex, $shieldBonus, 0);
        }

        // No armor - check for Unarmored Defense modifiers from character's classes
        $unarmoredAC = $this->calculateUnarmoredDefenseAC($character, $dexMod, $shieldBonus);
        if ($unarmoredAC !== null) {
            return $unarmoredAC;
        }

        // Default: 10 + DEX + shield
        return $this->calculateAC($dexMod, null, null, $shieldBonus, 0);
    }

    /**
     * Calculate Unarmored Defense AC from character's class modifiers.
     *
     * Checks if any of the character's classes have ac_unarmored modifiers
     * and calculates AC based on the formula: base + DEX + optional secondary ability.
     *
     * @return int|null AC if Unarmored Defense applies, null otherwise
     */
    private function calculateUnarmoredDefenseAC(Character $character, int $dexMod, int $shieldBonus): ?int
    {
        // Get all class slugs for this character
        $classSlugs = $character->characterClasses()->pluck('class_slug')->toArray();
        if (empty($classSlugs)) {
            return null;
        }

        // Find ac_unarmored modifiers for any of the character's classes
        $unarmoredModifier = \App\Models\Modifier::where('modifier_category', 'ac_unarmored')
            ->where('reference_type', \App\Models\CharacterClass::class)
            ->whereIn('reference_id', function ($query) use ($classSlugs) {
                $query->select('id')
                    ->from('classes')
                    ->whereIn('slug', $classSlugs);
            })
            ->first();

        if ($unarmoredModifier === null) {
            return null;
        }

        // Calculate AC: base + DEX + secondary ability (if any)
        $baseAC = (int) $unarmoredModifier->value;
        $ac = $baseAC + $dexMod;

        // Add secondary ability modifier if present
        if ($unarmoredModifier->secondary_ability_score_id !== null) {
            $secondaryAbilityScore = $this->getCharacterAbilityScore($character, $unarmoredModifier->secondary_ability_score_id);
            $ac += $this->abilityModifier($secondaryAbilityScore);
        }

        // Add shield if allowed
        if ($shieldBonus > 0) {
            $allowsShield = str_contains($unarmoredModifier->condition ?? '', 'allows_shield: true');
            if ($allowsShield) {
                $ac += $shieldBonus;
            }
        }

        return $ac;
    }

    /**
     * Get a character's ability score by ability_score_id.
     */
    private function getCharacterAbilityScore(Character $character, int $abilityScoreId): int
    {
        $abilityScore = \App\Models\AbilityScore::find($abilityScoreId);
        if ($abilityScore === null) {
            return 10;
        }

        // Map ability code to character attribute
        return match ($abilityScore->code) {
            'STR' => $character->strength ?? 10,
            'DEX' => $character->dexterity ?? 10,
            'CON' => $character->constitution ?? 10,
            'INT' => $character->intelligence ?? 10,
            'WIS' => $character->wisdom ?? 10,
            'CHA' => $character->charisma ?? 10,
            default => 10,
        };
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
     * Note: This method uses hardcoded class name checks. The canonical source of
     * spell preparation method is CharacterClass::spell_preparation_method accessor.
     *
     * @param  string  $classSlug  The class name (lowercase), not the prefixed slug
     * @param  int  $level  The character's level
     * @param  int  $abilityModifier  The spellcasting ability modifier
     * @return int|null Preparation limit, or null for known casters
     *
     * @see \App\Models\CharacterClass::getSpellPreparationMethodAttribute()
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

    // ========================================================================
    // Data-Driven Spellcasting Methods
    // ========================================================================

    /**
     * Get spell slots from a class's level progression data.
     *
     * This is the data-driven replacement for getSpellSlots(). It reads
     * spell slot counts directly from the CharacterClass's levelProgression
     * relationship, supporting any class including those not in hardcoded lists.
     *
     * @param  \App\Models\CharacterClass  $class  The class with levelProgression loaded
     * @param  int  $level  The character's level in this class
     * @return array<int, int> Spell slots indexed by spell level (1-9)
     */
    public function getSpellSlotsFromClass(\App\Models\CharacterClass $class, int $level): array
    {
        // Load progression if not already loaded
        if (! $class->relationLoaded('levelProgression')) {
            $class->load('levelProgression');
        }

        // Find the progression row for this level
        $progression = $class->levelProgression->firstWhere('level', $level);

        if (! $progression) {
            return [];
        }

        // Map spell slot columns to spell levels
        $slotColumns = [
            'spell_slots_1st' => 1,
            'spell_slots_2nd' => 2,
            'spell_slots_3rd' => 3,
            'spell_slots_4th' => 4,
            'spell_slots_5th' => 5,
            'spell_slots_6th' => 6,
            'spell_slots_7th' => 7,
            'spell_slots_8th' => 8,
            'spell_slots_9th' => 9,
        ];

        $slots = [];
        foreach ($slotColumns as $column => $spellLevel) {
            $count = $progression->$column ?? 0;
            if ($count > 0) {
                $slots[$spellLevel] = $count;
            }
        }

        return $slots;
    }

    /**
     * Get preparation limit using the class's spell_preparation_method.
     *
     * This is the data-driven replacement for getPreparationLimit(). It uses
     * the CharacterClass's spell_preparation_method attribute to determine
     * how spells are prepared, supporting any class.
     *
     * D&D 5e preparation formulas:
     * - 'known': Returns null (spells are known, not prepared)
     * - 'spellbook': ability modifier + level (Wizard)
     * - 'prepared': ability modifier + level (Cleric, Druid, Artificer)
     *               or ability modifier + half level (Paladin, Ranger)
     *
     * @param  \App\Models\CharacterClass  $class  The class to check
     * @param  int  $level  The character's level in this class
     * @param  int  $abilityModifier  The spellcasting ability modifier
     * @return int|null Preparation limit, or null for known casters
     */
    public function getPreparationLimitFromClass(\App\Models\CharacterClass $class, int $level, int $abilityModifier): ?int
    {
        $prepMethod = $class->spell_preparation_method;

        // Non-casters and known casters don't prepare spells
        if ($prepMethod === null || $prepMethod === 'known') {
            return null;
        }

        // Determine if this is a half-caster (Paladin, Ranger use half level)
        // Half-casters can be detected by their spellcasting_type
        $casterType = $class->spellcasting_type;
        $isHalfCaster = $casterType === 'half';

        // Calculate preparation limit
        if ($isHalfCaster) {
            // Paladin/Ranger formula: ability modifier + half level (rounded down)
            $limit = $abilityModifier + (int) floor($level / 2);
        } else {
            // Full caster formula: ability modifier + level
            // Used by: Wizard (spellbook), Cleric, Druid, Artificer (prepared)
            $limit = $abilityModifier + $level;
        }

        // Minimum 1 prepared spell
        return max(1, $limit);
    }

    // ========================================================================
    // Derived Combat Stats
    // ========================================================================

    /**
     * Calculate initiative bonus.
     * Base: DEX modifier
     * Can be modified by features (e.g., Alert feat adds +5).
     *
     * @param  int  $dexModifier  The DEX ability modifier
     * @param  int  $bonuses  Additional bonuses from features/items
     */
    public function calculateInitiative(int $dexModifier, int $bonuses = 0): int
    {
        return $dexModifier + $bonuses;
    }

    /**
     * Get total initiative modifiers from character's feats and equipped items.
     * Queries the Modifier table for initiative bonuses from:
     * - Feats the character has taken (e.g., Alert +5)
     * - Items the character has equipped (e.g., Weapon of Warning +2)
     */
    public function getInitiativeModifiers(Character $character): int
    {
        // Get feat IDs from character features
        $featIds = $character->features()
            ->where('feature_type', Feat::class)
            ->pluck('feature_id')
            ->toArray();

        $featBonus = empty($featIds) ? 0 : (int) Modifier::query()
            ->where('reference_type', Feat::class)
            ->whereIn('reference_id', $featIds)
            ->where('modifier_category', 'initiative')
            ->sum('value');

        // Get equipped item slugs, then resolve to item IDs
        $equippedItemSlugs = $character->equipment()
            ->where('equipped', true)
            ->pluck('item_slug')
            ->toArray();

        $itemBonus = 0;
        if (! empty($equippedItemSlugs)) {
            $itemIds = Item::whereIn('slug', $equippedItemSlugs)->pluck('id')->toArray();
            if (! empty($itemIds)) {
                $itemBonus = (int) Modifier::query()
                    ->where('reference_type', Item::class)
                    ->whereIn('reference_id', $itemIds)
                    ->where('modifier_category', 'initiative')
                    ->sum('value');
            }
        }

        return $featBonus + $itemBonus;
    }

    /**
     * Calculate a passive skill score.
     * Formula: 10 + skill modifier
     * Advantage grants +5, disadvantage gives -5.
     *
     * @param  int  $abilityModifier  The modifier for the skill's ability
     * @param  bool  $proficient  Whether proficient in the skill
     * @param  bool  $expertise  Whether has expertise (double proficiency)
     * @param  int  $proficiencyBonus  The character's proficiency bonus
     * @param  int  $advantageModifier  +5 for advantage, -5 for disadvantage, 0 for neither
     */
    public function calculatePassiveSkill(
        int $abilityModifier,
        bool $proficient,
        bool $expertise,
        int $proficiencyBonus,
        int $advantageModifier = 0
    ): int {
        $skillMod = $this->skillModifier($abilityModifier, $proficient, $expertise, $proficiencyBonus);

        return 10 + $skillMod + $advantageModifier;
    }

    /**
     * Calculate carrying capacity in pounds.
     * Base: STR × 15
     * Modified by size: Tiny = ×0.5, Small/Medium = ×1, Large = ×2, Huge = ×4, Gargantuan = ×8
     *
     * @param  int  $strengthScore  The STR ability score (not modifier)
     * @param  string  $size  The creature size (Tiny, Small, Medium, Large, Huge, Gargantuan)
     */
    public function calculateCarryingCapacity(int $strengthScore, string $size = 'Medium'): int
    {
        $baseCapacity = $strengthScore * 15;

        $multiplier = $this->getSizeMultiplier($size);

        return (int) ($baseCapacity * $multiplier);
    }

    /**
     * Calculate push/drag/lift limit in pounds.
     * This is double the carrying capacity.
     *
     * @param  int  $strengthScore  The STR ability score
     * @param  string  $size  The creature size
     */
    public function calculatePushDragLift(int $strengthScore, string $size = 'Medium'): int
    {
        return $this->calculateCarryingCapacity($strengthScore, $size) * 2;
    }

    /**
     * Get size multiplier for carrying capacity.
     */
    private function getSizeMultiplier(string $size): float
    {
        return match (strtolower($size)) {
            'tiny' => 0.5,
            'small', 'medium' => 1.0,
            'large' => 2.0,
            'huge' => 4.0,
            'gargantuan' => 8.0,
            default => 1.0,
        };
    }

    /**
     * Calculate encumbrance status based on Variant Encumbrance rules.
     *
     * - Unencumbered: Weight <= STR × 5
     * - Encumbered: Weight > STR × 5 (-10 ft speed)
     * - Heavily Encumbered: Weight > STR × 10 (-20 ft speed, disadvantage on STR/DEX/CON checks, attacks, saves)
     *
     * @param  int  $strengthScore  The STR ability score
     * @param  float  $currentWeight  The total weight being carried
     * @return array{status: string, speed_penalty: int, has_disadvantage: bool, threshold_encumbered: int, threshold_heavily_encumbered: int}
     */
    public function calculateEncumbrance(int $strengthScore, float $currentWeight): array
    {
        $thresholdEncumbered = $strengthScore * 5;
        $thresholdHeavy = $strengthScore * 10;

        if ($currentWeight > $thresholdHeavy) {
            return [
                'status' => 'heavily_encumbered',
                'speed_penalty' => 20,
                'has_disadvantage' => true,
                'threshold_encumbered' => $thresholdEncumbered,
                'threshold_heavily_encumbered' => $thresholdHeavy,
            ];
        }

        if ($currentWeight > $thresholdEncumbered) {
            return [
                'status' => 'encumbered',
                'speed_penalty' => 10,
                'has_disadvantage' => false,
                'threshold_encumbered' => $thresholdEncumbered,
                'threshold_heavily_encumbered' => $thresholdHeavy,
            ];
        }

        return [
            'status' => 'unencumbered',
            'speed_penalty' => 0,
            'has_disadvantage' => false,
            'threshold_encumbered' => $thresholdEncumbered,
            'threshold_heavily_encumbered' => $thresholdHeavy,
        ];
    }
}
