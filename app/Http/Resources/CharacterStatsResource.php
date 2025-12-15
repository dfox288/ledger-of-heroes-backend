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

            // Issue #498.3.3: Encumbrance tracking
            /** @var float Total weight of all equipment in pounds */
            'current_weight' => $this->resource->currentWeight,
            /** @var array{status: string, speed_penalty: int, has_disadvantage: bool, threshold_encumbered: int, threshold_heavily_encumbered: int}|null Encumbrance status and effects */
            'encumbrance' => $this->resource->encumbrance,

            /** @var array{ability: string, ability_modifier: int, spell_save_dc: int, spell_attack_bonus: int}|null Spellcasting info */
            'spellcasting' => $this->resource->spellcasting,
            /**
             * Issue #618: Enriched spell slots with tracking
             *
             * @var array{slots: object<string, array{total: int, spent: int, available: int}>, pact_magic: array{level: int, total: int, spent: int, available: int}|null}
             */
            'spell_slots' => [
                // Cast to object to preserve spell level keys ("1", "2") in JSON output
                'slots' => (object) ($this->resource->spellSlots['slots'] ?? []),
                'pact_magic' => $this->resource->spellSlots['pact_magic'] ?? null,
            ],
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

            // Issue #497: Fighting styles and combat modifiers
            /** @var array<string> Fighting styles the character has (e.g., "Archery", "Defense") */
            'fighting_styles' => $this->resource->fightingStyles,
            /** @var int Bonus to ranged weapon attack rolls (from Archery fighting style) */
            'ranged_attack_bonus' => $this->resource->rangedAttackBonus,
            /** @var int Bonus to melee weapon damage (from Dueling fighting style, conditional) */
            'melee_damage_bonus' => $this->resource->meleeDamageBonus,

            // Issue #498.3.1: Weapon attack/damage calculation
            /** @var array<int, array{name: string, damage_dice: string|null, attack_bonus: int, damage_bonus: int, ability_used: string, is_proficient: bool}> Equipped weapon stats */
            'weapons' => $this->resource->weapons,

            // Issue #675: Spell preparation method
            /** @var string|null Spell preparation method: known, spellbook, prepared, mixed, or null for non-casters */
            'preparation_method' => $this->resource->preparationMethod,
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
