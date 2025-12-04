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

            'ability_scores' => $this->formatAbilityScores(),
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

            'spellcasting' => $this->resource->spellcasting,
            'spell_slots' => $this->resource->spellSlots,
            'preparation_limit' => $this->resource->preparationLimit,
            'prepared_spell_count' => $this->resource->preparedSpellCount,

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
