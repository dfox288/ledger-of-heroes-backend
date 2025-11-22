<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for spells attached to items (via entity_spells pivot table)
 * Shows spell details with charge cost information from the pivot
 */
class ItemSpellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'level' => $this->level,
            'school' => SpellSchoolResource::make($this->whenLoaded('spellSchool')),

            // Charge cost data from pivot table (entity_spells)
            'charges_cost_min' => $this->pivot?->charges_cost_min,
            'charges_cost_max' => $this->pivot?->charges_cost_max,
            'charges_cost_formula' => $this->pivot?->charges_cost_formula,

            // Other usage metadata from pivot
            'usage_limit' => $this->pivot?->usage_limit,
            'level_requirement' => $this->pivot?->level_requirement,
        ];
    }
}
