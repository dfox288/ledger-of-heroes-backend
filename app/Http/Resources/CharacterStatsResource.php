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
            /** @var array<string, int|null> Saving throw modifiers keyed by ability code */
            'saving_throws' => $this->resource->savingThrows,

            'armor_class' => $this->resource->armorClass,
            'hit_points' => [
                'max' => $this->resource->maxHitPoints,
                'current' => $this->resource->currentHitPoints,
                'temporary' => $this->resource->tempHitPoints,
            ],

            // Derived combat stats
            'initiative_bonus' => $this->resource->initiativeBonus,
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
