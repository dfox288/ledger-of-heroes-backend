<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for a single spell slot.
 *
 * Expected input structure:
 * [
 *     'level' => int,
 *     'total' => int,
 *     'spent' => int,
 *     'available' => int,
 *     'slot_type' => 'standard'|'pact_magic',
 * ]
 */
class SpellSlotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'level' => $this->resource['level'],
            'total' => $this->resource['total'],
            'spent' => $this->resource['spent'],
            'available' => $this->resource['available'],
            'slot_type' => $this->resource['slot_type'],
        ];
    }
}
