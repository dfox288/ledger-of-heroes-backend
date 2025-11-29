<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityDataTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table_name' => $this->table_name,
            'dice_type' => $this->dice_type,
            'table_type' => $this->table_type?->value,
            'description' => $this->description,
            'entries' => EntityDataTableEntryResource::collection($this->whenLoaded('entries')),
        ];
    }
}
