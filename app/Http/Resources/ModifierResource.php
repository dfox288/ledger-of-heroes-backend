<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for fixed modifiers.
 *
 * Note: Ability score choices are stored in entity_choices table and exposed via EntityChoiceResource.
 */
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
            'id' => (int) $this->id,
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
            'level' => $this->level !== null ? (int) $this->level : null,
        ];
    }
}
