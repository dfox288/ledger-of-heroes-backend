<?php

namespace App\Http\Resources;

use App\DTOs\CharacterStatsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for character statistics.
 *
 * @property CharacterStatsDTO $resource
 */
class CharacterStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'character_id' => $this->resource->characterId,
            'level' => $this->resource->level,
            'proficiency_bonus' => $this->resource->proficiencyBonus,

            /** @var array<string, array{score: int|null, modifier: int|null}> Ability scores keyed by code (STR, DEX, etc.) */
            'ability_scores' => $this->formatAbilityScores(),
            /** @var array<string, array{modifier: int|null, proficient: bool, total: int|null}> Saving throws with proficiency status */
            'saving_throws' => $this->resource->savingThrows,

            'armor_class' => $this->resource->armorClass,
            'hit_points' => [
                'max' => $this->resource->maxHitPoints,
                'current' => $this->resource->currentHitPoints,
                'temporary' => $this->resource->tempHitPoints,
            ],

            // Derived combat stats
            'initiative_bonus' => $this->resource->initiativeBonus,
            // Note: Individual passive fields kept for backwards compatibility.
            // New 'passive' object provides grouped access (Issue #255).
            'passive_perception' => $this->resource->passivePerception,
            'passive_investigation' => $this->resource->passiveInvestigation,
            'passive_insight' => $this->resource->passiveInsight,
            'carrying_capacity' => $this->resource->carryingCapacity,
            'push_drag_lift' => $this->resource->pushDragLift,

            /** @var array{ability: string, ability_modifier: int, spell_save_dc: int, spell_attack_bonus: int}|null Spellcasting info */
            'spellcasting' => $this->resource->spellcasting,
            /** @var array<string, int> Spell slots keyed by level (e.g., "1" => 4, "2" => 3) */
            'spell_slots' => $this->resource->spellSlots,
            'preparation_limit' => $this->resource->preparationLimit,
            'prepared_spell_count' => $this->resource->preparedSpellCount,

            /** @var array<array{die: string, total: int, current: int}> Hit dice by die type */
            'hit_dice' => $this->resource->hitDice,

            // Issue #255: Enhanced stats
            /** @var array<int, array{name: string, slug: string, ability: string, ability_modifier: int|null, proficient: bool, expertise: bool, modifier: int|null, passive: int|null}> All 18 skills with full breakdown */
            'skills' => $this->resource->skills,
            /** @var array{walk: int, fly: int|null, swim: int|null, climb: int|null, burrow: int|null} Movement speeds */
            'speed' => $this->resource->speed,
            /** @var array{perception: int|null, investigation: int|null, insight: int|null} Grouped passive scores */
            'passive' => $this->resource->passive,

            // Issue #417: Defensive traits
            /** @var array<int, array{type: string, condition: string|null, source: string}> Damage resistances from race and feats */
            'damage_resistances' => $this->resource->damageResistances,
            /** @var array<int, array{type: string, condition: string|null, source: string}> Damage immunities from race and feats */
            'damage_immunities' => $this->resource->damageImmunities,
            /** @var array<int, array{type: string, condition: string|null, source: string}> Damage vulnerabilities from race and feats */
            'damage_vulnerabilities' => $this->resource->damageVulnerabilities,
            /** @var array<int, array{condition: string, effect: string, source: string}> Condition advantages from race and feats */
            'condition_advantages' => $this->resource->conditionAdvantages,
            /** @var array<int, array{condition: string, effect: string, source: string}> Condition disadvantages from race and feats */
            'condition_disadvantages' => $this->resource->conditionDisadvantages,
            /** @var array<int, array{condition: string, effect: string, source: string}> Condition immunities from race and feats */
            'condition_immunities' => $this->resource->conditionImmunities,
            // Issue #429: Skill check advantages
            /** @var array<int, array{skill: string, skill_slug: string, condition: string|null, source: string}> Skill check advantages from race and feats */
            'skill_advantages' => $this->resource->skillAdvantages,
        ];
    }

    /**
     * Format ability scores with both score and modifier.
     */
    private function formatAbilityScores(): array
    {
        $formatted = [];

        foreach ($this->resource->abilityScores as $code => $score) {
            $formatted[$code] = [
                'score' => $score,
                'modifier' => $this->resource->abilityModifiers[$code],
            ];
        }

        return $formatted;
    }
}
