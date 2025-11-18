<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpellEffectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'effect_type' => $this->effect_type,
            'description' => $this->description,
            'dice_formula' => $this->dice_formula,
            'base_value' => $this->base_value,
            'scaling_type' => $this->scaling_type,
            'min_character_level' => $this->min_character_level,
            'min_spell_slot' => $this->min_spell_slot,
            'scaling_increment' => $this->scaling_increment,
            'damage_type' => $this->when($this->damage_type_id, function () {
                return new DamageTypeResource($this->whenLoaded('damageType'));
            }),
        ];
    }
}
