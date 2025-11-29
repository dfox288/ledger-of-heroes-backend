<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for the EntitySense pivot model.
 *
 * Returns structured sense data with type, range, and optional notes.
 */
class EntitySenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->sense?->slug,
            'name' => $this->sense?->name,
            'range' => $this->range_feet,
            'is_limited' => $this->is_limited,
            'notes' => $this->notes,
        ];
    }
}
