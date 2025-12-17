<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for fixed spell grants.
 *
 * Note: Spell choices are stored in entity_choices table and exposed via EntityChoiceResource.
 */
class EntitySpellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'spell_id' => (int) $this->spell_id,
            'spell' => new SpellResource($this->whenLoaded('spell')),
            'ability_score_id' => $this->ability_score_id !== null ? (int) $this->ability_score_id : null,
            'ability_score' => new AbilityScoreResource($this->whenLoaded('abilityScore')),
            'level_requirement' => $this->level_requirement !== null ? (int) $this->level_requirement : null,
            'usage_limit' => $this->usage_limit,
            'is_cantrip' => (bool) $this->is_cantrip,

            // Charge costs (for items that cast spells)
            'charges_cost_min' => $this->charges_cost_min !== null ? (int) $this->charges_cost_min : null,
            'charges_cost_max' => $this->charges_cost_max !== null ? (int) $this->charges_cost_max : null,
            'charges_cost_formula' => $this->charges_cost_formula,
        ];
    }
}
