<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RandomTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table_name' => $this->table_name,
            'dice_type' => $this->dice_type,
            'description' => $this->description,
            'entries' => RandomTableEntryResource::collection($this->whenLoaded('entries')),
        ];
    }
}
