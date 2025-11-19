<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassFeatureResource extends JsonResource
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
            'level' => $this->level,
            'feature_name' => $this->feature_name,
            'description' => $this->description,
            'is_optional' => $this->is_optional,
            'sort_order' => $this->sort_order,
        ];
    }
}
