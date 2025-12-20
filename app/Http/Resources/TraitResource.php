<?php

namespace App\Http\Resources;

use App\Models\CharacterTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CharacterTrait
 */
class TraitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'sort_order' => (int) $this->sort_order,
            'data_tables' => EntityDataTableResource::collection($this->whenLoaded('dataTables')),
        ];
    }
}
