<?php

namespace App\Http\Resources;

use App\Models\Character;
use App\Services\CharacterStatCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for party stats endpoint - provides DM dashboard data.
 *
 * @mixin Character
 */
class PartyCharacterStatsResource extends JsonResource
{
    private CharacterStatCalculator $calculator;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->calculator = new CharacterStatCalculator;
    }

    public function toArray(Request $request): array
    {
        $finalScores = $this->resource->getFinalAbilityScoresArray();
        $modifiers = $this->calculateModifiers($finalScores);
        $proficiencyBonus = $this->calculator->proficiencyBonus($this->total_level ?: 1);

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name' => $this->name,
            'level' => $this->total_level,
            'class_name' => $this->getPrimaryClassName(),
            'hit_points' => [
                'current' => $this->current_hit_points,
                'max' => $this->max_hit_points,
                'temp' => $this->temp_hit_points ?? 0,
            ],
            'armor_class' => $this->armor_class,
            'proficiency_bonus' => $proficiencyBonus,
            'passive_skills' => $this->calculatePassiveSkills($modifiers, $proficiencyBonus),
            'saving_throws' => $this->calculateSavingThrows($modifiers, $proficiencyBonus),
            'conditions' => $this->formatConditions(),
            'spell_slots' => $this->formatSpellSlots(),
        ];
    }

    /**
     * Get the primary class name.
     */
    private function getPrimaryClassName(): ?string
    {
        $primaryPivot = $this->characterClasses->firstWhere('is_primary', true);

        return $primaryPivot?->characterClass?->name;
    }

    /**
     * Calculate modifiers for all ability scores.
     */
    private function calculateModifiers(array $scores): array
    {
        $modifiers = [];

        foreach ($scores as $code => $score) {
            $modifiers[$code] = $score !== null
                ? $this->calculator->abilityModifier($score)
                : 0;
        }

        return $modifiers;
    }

    /**
     * Calculate passive skill scores (Perception, Investigation, Insight).
     */
    private function calculatePassiveSkills(array $modifiers, int $proficiencyBonus): array
    {
        // Map skills to their associated ability scores
        $passiveSkillAbilities = [
            'perception' => 'WIS',
            'investigation' => 'INT',
            'insight' => 'WIS',
        ];

        $passiveSkills = [];

        foreach ($passiveSkillAbilities as $skillName => $abilityCode) {
            $abilityModifier = $modifiers[$abilityCode] ?? 0;
            $proficient = $this->hasProficiencyInSkill($skillName);
            $expertise = $this->hasExpertiseInSkill($skillName);

            $passiveSkills[$skillName] = $this->calculator->calculatePassiveSkill(
                $abilityModifier,
                $proficient,
                $expertise,
                $proficiencyBonus
            );
        }

        return $passiveSkills;
    }

    /**
     * Check if character has proficiency in a skill.
     */
    private function hasProficiencyInSkill(string $skillName): bool
    {
        if (! $this->relationLoaded('proficiencies')) {
            return false;
        }

        return $this->proficiencies
            ->where('skill_slug', $skillName)
            ->isNotEmpty();
    }

    /**
     * Check if character has expertise in a skill.
     */
    private function hasExpertiseInSkill(string $skillName): bool
    {
        if (! $this->relationLoaded('proficiencies')) {
            return false;
        }

        return $this->proficiencies
            ->where('skill_slug', $skillName)
            ->where('expertise', true)
            ->isNotEmpty();
    }

    /**
     * Calculate saving throw modifiers for all abilities.
     *
     * Note: For now, returns base ability modifiers only.
     * Proficiency in saves would require checking class save proficiencies.
     */
    private function calculateSavingThrows(array $modifiers, int $proficiencyBonus): array
    {
        // For now, just return ability modifiers
        // Future: Check class save proficiencies and add proficiency bonus
        return [
            'STR' => $modifiers['STR'] ?? 0,
            'DEX' => $modifiers['DEX'] ?? 0,
            'CON' => $modifiers['CON'] ?? 0,
            'INT' => $modifiers['INT'] ?? 0,
            'WIS' => $modifiers['WIS'] ?? 0,
            'CHA' => $modifiers['CHA'] ?? 0,
        ];
    }

    /**
     * Format active conditions.
     */
    private function formatConditions(): array
    {
        if (! $this->relationLoaded('conditions')) {
            return [];
        }

        return $this->conditions->map(fn ($cc) => [
            'name' => $cc->condition?->name,
            'slug' => $cc->condition_slug,
            'level' => $cc->level,
        ])->values()->all();
    }

    /**
     * Format spell slots (current/max for each level).
     */
    private function formatSpellSlots(): ?array
    {
        if (! $this->relationLoaded('spellSlots')) {
            return null;
        }

        if ($this->spellSlots->isEmpty()) {
            return null;
        }

        $slots = [];
        foreach ($this->spellSlots as $slot) {
            $slots[$slot->level] = [
                'current' => $slot->current,
                'max' => $slot->max,
            ];
        }

        return $slots;
    }
}
