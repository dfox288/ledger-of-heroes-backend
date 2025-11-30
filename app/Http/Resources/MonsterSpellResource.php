<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for spells attached to monsters (via entity_spells pivot table)
 * Shows spell details with usage information from the pivot
 */
class MonsterSpellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'level' => $this->level,
            'school' => SpellSchoolResource::make($this->whenLoaded('spellSchool')),

            // Usage metadata from pivot table (entity_spells)
            'usage_limit' => $this->pivot?->usage_limit,
            'level_requirement' => $this->pivot?->level_requirement,
            'is_cantrip' => $this->pivot?->is_cantrip,
        ];
    }
}
