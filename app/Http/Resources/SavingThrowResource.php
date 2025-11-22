<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingThrowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * This resource represents an AbilityScore relationship with pivot data
     * from the entity_saving_throws table.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ability_score' => [
                'id' => $this->id,
                'code' => $this->code,
                'name' => $this->name,
            ],
            'dc' => $this->pivot?->dc,
            'save_effect' => $this->pivot?->save_effect,
            'is_initial_save' => (bool) ($this->pivot?->is_initial_save ?? true),
            'save_modifier' => $this->pivot?->save_modifier, // 'advantage', 'disadvantage', or null
        ];
    }
}
