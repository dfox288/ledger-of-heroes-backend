<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for pack content items.
 *
 * Transforms EntityItem records that represent the contents of an equipment pack
 * (e.g., Explorer's Pack, Burglar's Pack).
 */
class PackContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'quantity' => $this->quantity,
            'item' => $this->when($this->relationLoaded('item') && $this->item, function () {
                return [
                    'id' => $this->item->id,
                    'name' => $this->item->name,
                    'slug' => $this->item->slug,
                    'weight' => $this->item->weight,
                    'cost_cp' => $this->item->cost_cp,
                ];
            }),
        ];
    }
}
