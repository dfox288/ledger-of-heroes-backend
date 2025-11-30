<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitySpellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'spell_id' => $this->spell_id,
            'spell' => new SpellResource($this->whenLoaded('spell')),
            'ability_score_id' => $this->ability_score_id,
            'ability_score' => new AbilityScoreResource($this->whenLoaded('abilityScore')),
            'level_requirement' => $this->level_requirement,
            'usage_limit' => $this->usage_limit,
            'is_cantrip' => $this->is_cantrip,

            // Charge costs (for items that cast spells)
            'charges_cost_min' => $this->charges_cost_min,
            'charges_cost_max' => $this->charges_cost_max,
            'charges_cost_formula' => $this->charges_cost_formula,

            // Choice support
            'is_choice' => $this->is_choice,
            'choice_count' => $this->when($this->is_choice, $this->choice_count),
            'choice_group' => $this->when($this->is_choice, $this->choice_group),
            'max_level' => $this->when($this->is_choice, $this->max_level),
            'school' => new SpellSchoolResource($this->whenLoaded('school')),
            'character_class' => new CharacterClassResource($this->whenLoaded('characterClass')),
            'is_ritual_only' => $this->when($this->is_choice, $this->is_ritual_only),
        ];
    }
}
