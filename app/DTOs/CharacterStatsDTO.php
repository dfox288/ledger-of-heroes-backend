<?php

namespace App\DTOs;

use App\Models\Character;
use App\Models\Skill;
use App\Services\CharacterStatCalculator;

/**
 * Data Transfer Object for computed character statistics.
 *
 * Encapsulates all derived stats for a D&D 5e character.
 */
class CharacterStatsDTO
{
    public function __construct(
        public readonly int $characterId,
        public readonly int $level,
        public readonly int $proficiencyBonus,
        public readonly array $abilityScores,
        public readonly array $abilityModifiers,
        public readonly array $savingThrows,
        public readonly ?int $armorClass,
        public readonly ?int $maxHitPoints,
        public readonly ?int $currentHitPoints,
        public readonly int $tempHitPoints,
        public readonly ?array $spellcasting,
        public readonly array $spellSlots,
        public readonly ?int $preparationLimit,
        public readonly int $preparedSpellCount,
        // Derived combat stats
        public readonly ?int $initiativeBonus,
        public readonly ?int $passivePerception,
        public readonly ?int $passiveInvestigation,
        public readonly ?int $passiveInsight,
        public readonly ?int $carryingCapacity,
        public readonly ?int $pushDragLift,
    ) {}

    /**
     * Build stats DTO from a Character model.
     */
    public static function fromCharacter(Character $character, CharacterStatCalculator $calculator): self
    {
        $level = $character->total_level;
        $proficiencyBonus = $calculator->proficiencyBonus($level);

        // Ability scores and modifiers
        $abilityScores = $character->getAbilityScoresArray();
        $abilityModifiers = [];
        foreach ($abilityScores as $code => $score) {
            $abilityModifiers[$code] = $score !== null ? $calculator->abilityModifier($score) : null;
        }

        // Saving throws (proficiency TBD - depends on class)
        $savingThrows = [];
        foreach ($abilityScores as $code => $score) {
            $savingThrows[$code] = $abilityModifiers[$code]; // Base modifier only for now
        }

        // Spellcasting info (uses primary class)
        $spellcasting = null;
        $spellSlots = [];
        $preparationLimit = null;

        $primaryClass = $character->primary_class;
        if ($primaryClass) {
            $baseClassName = $primaryClass->parent_class_id
                ? strtolower($primaryClass->parentClass->name ?? '')
                : strtolower($primaryClass->name);

            $spellcastingAbility = $primaryClass->effective_spellcasting_ability;

            if ($spellcastingAbility) {
                $abilityCode = $spellcastingAbility->code;
                $abilityMod = $abilityModifiers[$abilityCode] ?? 0;

                $spellcasting = [
                    'ability' => $abilityCode,
                    'ability_modifier' => $abilityMod,
                    'spell_save_dc' => $calculator->spellSaveDC($proficiencyBonus, $abilityMod),
                    'spell_attack_bonus' => $proficiencyBonus + $abilityMod,
                ];

                $spellSlots = $calculator->getSpellSlots($baseClassName, $level);
                $preparationLimit = $calculator->getPreparationLimit($baseClassName, $level, $abilityMod);
            }
        }

        // Count prepared spells
        $preparedSpellCount = $character->spells()
            ->where('preparation_status', 'prepared')
            ->whereHas('spell', fn ($q) => $q->where('level', '>', 0))
            ->count();

        // Calculate derived combat stats
        $dexMod = $abilityModifiers['DEX'] ?? 0;
        $wisMod = $abilityModifiers['WIS'] ?? 0;
        $intMod = $abilityModifiers['INT'] ?? 0;
        $strScore = $abilityScores['STR'] ?? 10;

        // Initiative bonus (DEX modifier + any bonuses from features)
        // TODO: Add feature bonuses (Alert feat, etc.) when feature system tracks them
        $initiativeBonus = $dexMod !== null ? $calculator->calculateInitiative($dexMod) : null;

        // Get skill proficiencies for passive skills
        $skillProficiencies = self::getSkillProficiencies($character);

        // Passive Perception (WIS-based)
        $passivePerception = $wisMod !== null ? $calculator->calculatePassiveSkill(
            $wisMod,
            $skillProficiencies['perception']['proficient'] ?? false,
            $skillProficiencies['perception']['expertise'] ?? false,
            $proficiencyBonus
        ) : null;

        // Passive Investigation (INT-based)
        $passiveInvestigation = $intMod !== null ? $calculator->calculatePassiveSkill(
            $intMod,
            $skillProficiencies['investigation']['proficient'] ?? false,
            $skillProficiencies['investigation']['expertise'] ?? false,
            $proficiencyBonus
        ) : null;

        // Passive Insight (WIS-based)
        $passiveInsight = $wisMod !== null ? $calculator->calculatePassiveSkill(
            $wisMod,
            $skillProficiencies['insight']['proficient'] ?? false,
            $skillProficiencies['insight']['expertise'] ?? false,
            $proficiencyBonus
        ) : null;

        // Carrying capacity (based on STR and size)
        $size = $character->size ?? 'Medium';
        $carryingCapacity = $strScore !== null ? $calculator->calculateCarryingCapacity($strScore, $size) : null;
        $pushDragLift = $strScore !== null ? $calculator->calculatePushDragLift($strScore, $size) : null;

        return new self(
            characterId: $character->id,
            level: $level,
            proficiencyBonus: $proficiencyBonus,
            abilityScores: $abilityScores,
            abilityModifiers: $abilityModifiers,
            savingThrows: $savingThrows,
            armorClass: $character->armor_class,
            maxHitPoints: $character->max_hit_points,
            currentHitPoints: $character->current_hit_points,
            tempHitPoints: $character->temp_hit_points ?? 0,
            spellcasting: $spellcasting,
            spellSlots: $spellSlots,
            preparationLimit: $preparationLimit,
            preparedSpellCount: $preparedSpellCount,
            initiativeBonus: $initiativeBonus,
            passivePerception: $passivePerception,
            passiveInvestigation: $passiveInvestigation,
            passiveInsight: $passiveInsight,
            carryingCapacity: $carryingCapacity,
            pushDragLift: $pushDragLift,
        );
    }

    /**
     * Get skill proficiency and expertise status for a character.
     *
     * @return array<string, array{proficient: bool, expertise: bool}>
     */
    private static function getSkillProficiencies(Character $character): array
    {
        $result = [];

        // Load character's skill proficiencies with skill relationship
        $proficiencies = $character->proficiencies()
            ->whereNotNull('skill_id')
            ->with('skill')
            ->get();

        foreach ($proficiencies as $proficiency) {
            if ($proficiency->skill) {
                $slug = $proficiency->skill->slug;
                $result[$slug] = [
                    'proficient' => true,
                    'expertise' => $proficiency->expertise ?? false,
                ];
            }
        }

        return $result;
    }
}
