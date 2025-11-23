<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'modifier_category' => $this->modifier_category,
            'ability_score' => $this->when($this->ability_score_id, function () {
                return new AbilityScoreResource($this->whenLoaded('abilityScore'));
            }),
            'skill' => $this->when($this->skill_id, function () {
                return new SkillResource($this->whenLoaded('skill'));
            }),
            // Always include damage_type field for damage_resistance category, even if NULL
            // This is important for items like Potion of Invulnerability (resistance:all)
            'damage_type' => $this->when(
                $this->modifier_category === 'damage_resistance' || $this->damage_type_id,
                function () {
                    return $this->damage_type_id
                        ? new DamageTypeResource($this->whenLoaded('damageType'))
                        : null;
                }
            ),
            'value' => $this->value,
            'condition' => $this->condition,
            'is_choice' => $this->is_choice,
            'choice_count' => $this->choice_count,
            'choice_constraint' => $this->choice_constraint,
            'level' => $this->level,
        ];
    }
}
