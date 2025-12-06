<?php

namespace App\DTOs;

use App\Models\Character;
use App\Models\Skill;
use App\Services\CharacterStatCalculator;
use App\Services\HitDiceService;

/**
 * Data Transfer Object for computed character statistics.
 *
 * Encapsulates all derived stats for a D&D 5e character.
 * Issue #255: Enhanced to include full skills, saving throw proficiencies, speed, and passive scores.
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
        // Hit dice
        public readonly array $hitDice,
        // Issue #255: New properties
        public readonly array $skills,
        public readonly array $speed,
        public readonly array $passive,
    ) {}

    /**
     * Build stats DTO from a Character model.
     */
    public static function fromCharacter(Character $character, CharacterStatCalculator $calculator): self
    {
        $level = $character->total_level;
        $proficiencyBonus = $calculator->proficiencyBonus($level);

        // Ability scores and modifiers (includes racial bonuses)
        $abilityScores = $character->getFinalAbilityScoresArray();
        $abilityModifiers = [];
        foreach ($abilityScores as $code => $score) {
            $abilityModifiers[$code] = $score !== null ? $calculator->abilityModifier($score) : null;
        }

        // Get saving throw proficiencies from primary class
        $savingThrowProficiencies = self::getSavingThrowProficiencies($character);

        // Saving throws with proficiency info
        $savingThrows = [];
        foreach ($abilityScores as $code => $score) {
            $baseMod = $abilityModifiers[$code];
            $proficient = $savingThrowProficiencies[$code] ?? false;
            $total = $baseMod !== null
                ? $baseMod + ($proficient ? $proficiencyBonus : 0)
                : null;

            $savingThrows[$code] = [
                'modifier' => $baseMod,
                'proficient' => $proficient,
                'total' => $total,
            ];
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
        // See GitHub Issue #196 for feature bonus implementation
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

        // Grouped passive scores object
        $passive = [
            'perception' => $passivePerception,
            'investigation' => $passiveInvestigation,
            'insight' => $passiveInsight,
        ];

        // Carrying capacity (based on STR and size)
        $size = $character->size ?? 'Medium';
        $carryingCapacity = $strScore !== null
            ? $calculator->calculateCarryingCapacity($strScore, $size)
            : null;
        $pushDragLift = $strScore !== null
            ? $calculator->calculatePushDragLift($strScore, $size)
            : null;

        // Get hit dice data
        $hitDiceService = app(HitDiceService::class);
        $hitDiceData = $hitDiceService->getHitDice($character);

        // Build full skills array
        $skills = self::buildSkills($abilityModifiers, $skillProficiencies, $proficiencyBonus, $calculator);

        // Build speed array
        $speed = self::buildSpeed($character);

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
            hitDice: $hitDiceData['hit_dice'],
            skills: $skills,
            speed: $speed,
            passive: $passive,
        );
    }

    /**
     * Get saving throw proficiency status from primary class.
     *
     * @return array<string, bool> Keyed by ability code (STR, DEX, etc.)
     */
    private static function getSavingThrowProficiencies(Character $character): array
    {
        $result = [
            'STR' => false,
            'DEX' => false,
            'CON' => false,
            'INT' => false,
            'WIS' => false,
            'CHA' => false,
        ];

        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return $result;
        }

        // Load proficiencies if not loaded
        if (! $primaryClass->relationLoaded('proficiencies')) {
            $primaryClass->load('proficiencies');
        }

        // Map ability names to codes
        $nameToCode = [
            'Strength' => 'STR',
            'Dexterity' => 'DEX',
            'Constitution' => 'CON',
            'Intelligence' => 'INT',
            'Wisdom' => 'WIS',
            'Charisma' => 'CHA',
        ];

        $savingThrows = $primaryClass->proficiencies
            ->where('proficiency_type', 'saving_throw');

        foreach ($savingThrows as $prof) {
            $code = $nameToCode[$prof->proficiency_name] ?? null;
            if ($code) {
                $result[$code] = true;
            }
        }

        return $result;
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

    /**
     * Build complete skills array with all 18 skills.
     *
     * @return array<int, array{
     *   name: string,
     *   slug: string,
     *   ability: string,
     *   ability_modifier: int|null,
     *   proficient: bool,
     *   expertise: bool,
     *   modifier: int|null,
     *   passive: int|null
     * }>
     */
    private static function buildSkills(
        array $abilityModifiers,
        array $skillProficiencies,
        int $proficiencyBonus,
        CharacterStatCalculator $calculator
    ): array {
        $skills = Skill::with('abilityScore')->get();

        return $skills->map(function ($skill) use ($abilityModifiers, $skillProficiencies, $proficiencyBonus, $calculator) {
            $abilityCode = $skill->abilityScore->code;
            $abilityMod = $abilityModifiers[$abilityCode];
            $profData = $skillProficiencies[$skill->slug] ?? ['proficient' => false, 'expertise' => false];
            $proficient = $profData['proficient'];
            $expertise = $profData['expertise'];

            $modifier = $abilityMod !== null
                ? $calculator->skillModifier($abilityMod, $proficient, $expertise, $proficiencyBonus)
                : null;

            $passive = $abilityMod !== null
                ? $calculator->calculatePassiveSkill($abilityMod, $proficient, $expertise, $proficiencyBonus)
                : null;

            return [
                'name' => $skill->name,
                'slug' => $skill->slug,
                'ability' => $abilityCode,
                'ability_modifier' => $abilityMod,
                'proficient' => $proficient,
                'expertise' => $expertise,
                'modifier' => $modifier,
                'passive' => $passive,
            ];
        })->sortBy('name')->values()->all();
    }

    /**
     * Build speed array from character's race.
     *
     * @return array{walk: int, fly: int|null, swim: int|null, climb: int|null, burrow: int|null}
     */
    private static function buildSpeed(Character $character): array
    {
        $race = $character->race;

        return [
            'walk' => $race?->speed ?? 30,
            'fly' => $race?->fly_speed,
            'swim' => $race?->swim_speed,
            'climb' => $race?->climb_speed,
            'burrow' => null, // Not currently tracked on races
        ];
    }
}
