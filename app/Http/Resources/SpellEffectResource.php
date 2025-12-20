<?php

namespace App\Http\Resources;

use App\Models\SpellEffect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpellEffect
 */
class SpellEffectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'effect_type' => $this->effect_type,
            'description' => $this->description,
            'dice_formula' => $this->dice_formula,
            'base_value' => $this->base_value ? (int) $this->base_value : null,
            'scaling_type' => $this->scaling_type,
            'min_character_level' => $this->min_character_level ? (int) $this->min_character_level : null,
            'min_spell_slot' => $this->min_spell_slot ? (int) $this->min_spell_slot : null,
            'scaling_increment' => $this->scaling_increment ? (int) $this->scaling_increment : null,
            'projectile_count' => $this->projectile_count ? (int) $this->projectile_count : null,
            'projectile_per_level' => $this->projectile_per_level ? (int) $this->projectile_per_level : null,
            'projectile_name' => $this->projectile_name,
            'damage_type' => $this->when($this->damage_type_id, function () {
                return new DamageTypeResource($this->whenLoaded('damageType'));
            }),
        ];
    }
}
