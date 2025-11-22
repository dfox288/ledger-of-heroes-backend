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
        ];
    }
}
