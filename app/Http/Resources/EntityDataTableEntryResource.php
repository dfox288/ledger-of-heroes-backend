<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityDataTableEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'roll_min' => $this->roll_min,
            'roll_max' => $this->roll_max,
            'result_text' => $this->result_text,
            'level' => $this->level,
            'sort_order' => $this->sort_order,
            'resource_cost' => $this->when($this->resource_cost !== null, $this->resource_cost),
        ];
    }
}
