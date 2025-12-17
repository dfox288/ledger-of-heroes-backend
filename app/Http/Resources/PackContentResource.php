<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsRelatedModels;
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
    use FormatsRelatedModels;

    public function toArray(Request $request): array
    {
        return [
            'quantity' => (int) $this->quantity,
            'item' => $this->when(
                $this->relationLoaded('item') && $this->item,
                fn () => $this->formatEntity($this->item, ['id', 'name', 'slug', 'weight', 'cost_cp'])
            ),
        ];
    }
}
