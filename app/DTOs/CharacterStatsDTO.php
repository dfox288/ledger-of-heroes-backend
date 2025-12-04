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
        // Use null to properly track missing ability scores (not 0, which is a valid modifier)
        $dexMod = $abilityModifiers['DEX'];
        $wisMod = $abilityModifiers['WIS'];
        $intMod = $abilityModifiers['INT'];
        $strScore = $abilityScores['STR'];

        // Get skill proficiencies for passive skills (eager-load if not already loaded)
        if (! $character->relationLoaded('proficiencies')) {
            $character->load('proficiencies.skill');
        }
        $skillProficiencies = self::getSkillProficiencies($character);

        // Initiative bonus (DEX modifier + any bonuses from features)
        // TODO: Add feature bonuses (Alert feat, etc.) when feature system tracks them
        $initiativeBonus = $dexMod !== null
            ? $calculator->calculateInitiative($dexMod)
            : null;

        // Passive skills - helper to reduce duplication
        $calculatePassive = fn (?int $mod, string $skill) => $mod !== null
            ? $calculator->calculatePassiveSkill(
                $mod,
                $skillProficiencies[$skill]['proficient'] ?? false,
                $skillProficiencies[$skill]['expertise'] ?? false,
                $proficiencyBonus
            )
            : null;

        $passivePerception = $calculatePassive($wisMod, 'perception');
        $passiveInvestigation = $calculatePassive($intMod, 'investigation');
        $passiveInsight = $calculatePassive($wisMod, 'insight');

        // Carrying capacity (based on STR and size)
        $size = $character->size ?? 'Medium';
        $carryingCapacity = $strScore !== null
            ? $calculator->calculateCarryingCapacity($strScore, $size)
            : null;
        $pushDragLift = $strScore !== null
            ? $calculator->calculatePushDragLift($strScore, $size)
            : null;

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
     * Expects proficiencies.skill to be eager-loaded to avoid N+1 queries.
     *
     * @return array<string, array{proficient: bool, expertise: bool}>
     */
    private static function getSkillProficiencies(Character $character): array
    {
        $result = [];

        // Use the already-loaded proficiencies relation (filtered in memory)
        $proficiencies = $character->proficiencies
            ->filter(fn ($p) => $p->skill_id !== null);

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
