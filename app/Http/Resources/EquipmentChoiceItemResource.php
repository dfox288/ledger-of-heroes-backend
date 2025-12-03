<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentChoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'proficiency_type' => new ProficiencyTypeResource($this->whenLoaded('proficiencyType')),
            'item' => new ItemResource($this->whenLoaded('item')),
            'quantity' => $this->quantity,
        ];
    }
}
