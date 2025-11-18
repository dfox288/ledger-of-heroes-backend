<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemAbilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'ability_type' => $this->ability_type,
            'spell_id' => $this->spell_id,
            'name' => $this->name,
            'description' => $this->description,
            'roll_formula' => $this->roll_formula,
            'charges_cost' => $this->charges_cost,
            'usage_limit' => $this->usage_limit,
            'save_dc' => $this->save_dc,
            'attack_bonus' => $this->attack_bonus,
            'sort_order' => $this->sort_order,

            // Relationships
            'spell' => SpellResource::make($this->whenLoaded('spell')),
        ];
    }
}
