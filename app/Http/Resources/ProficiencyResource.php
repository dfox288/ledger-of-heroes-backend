<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsRelatedModels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProficiencyResource extends JsonResource
{
    use FormatsRelatedModels;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proficiency_type' => $this->proficiency_type,
            'proficiency_subcategory' => $this->proficiency_subcategory,
            'proficiency_type_id' => $this->proficiency_type_id,
            'proficiency_type_detail' => $this->when($this->proficiency_type_id, function () {
                return new ProficiencyTypeResource($this->whenLoaded('proficiencyType'));
            }),
            'skill' => $this->when($this->skill_id, function () {
                return new SkillResource($this->whenLoaded('skill'));
            }),
            /**
             * Linked item reference (for weapon/armor proficiencies).
             *
             * @var array{id: int, name: string}|null
             */
            'item' => $this->when(
                $this->item_id,
                fn () => $this->formatEntity($this->item, ['id', 'name'])
            ),
            'ability_score' => $this->when($this->ability_score_id, function () {
                return new AbilityScoreResource($this->whenLoaded('abilityScore'));
            }),
            'proficiency_name' => $this->proficiency_name,
            'grants' => $this->grants,
            'is_choice' => $this->is_choice,
            'choice_group' => $this->choice_group,
            'choice_option' => $this->choice_option,
            'quantity' => $this->quantity,
            'level' => $this->level,
        ];
    }
}
