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
                return [
                    'id' => $this->abilityScore->id,
                    'name' => $this->abilityScore->name,
                    'code' => $this->abilityScore->code,
                ];
            }),
            'value' => $this->value,
            'condition' => $this->condition,
        ];
    }
}
