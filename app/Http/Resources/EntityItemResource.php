<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityItemResource extends JsonResource
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
            'item_id' => $this->item_id,
            'item' => new ItemResource($this->whenLoaded('item')),
            'quantity' => $this->quantity,
            'is_choice' => $this->is_choice,
            'choice_group' => $this->choice_group,
            'choice_option' => $this->choice_option,
            'choice_description' => $this->choice_description,
            'proficiency_subcategory' => $this->proficiency_subcategory,
            'description' => $this->description,
        ];
    }
}
