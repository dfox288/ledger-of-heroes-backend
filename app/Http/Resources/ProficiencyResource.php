<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProficiencyResource extends JsonResource
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
            'proficiency_type' => $this->proficiency_type,
            'skill' => $this->when($this->skill_id, function () {
                return new SkillResource($this->whenLoaded('skill'));
            }),
            'item' => $this->when($this->item_id, function () {
                return [
                    'id' => $this->item->id,
                    'name' => $this->item->name,
                ];
            }),
            'ability_score' => $this->when($this->ability_score_id, function () {
                return new AbilityScoreResource($this->whenLoaded('abilityScore'));
            }),
            'proficiency_name' => $this->proficiency_name,
        ];
    }
}
