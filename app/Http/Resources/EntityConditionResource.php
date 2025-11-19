<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'condition_id' => $this->condition_id,
            'condition' => new ConditionResource($this->whenLoaded('condition')),
            'effect_type' => $this->effect_type,
            'description' => $this->description,
        ];
    }
}
