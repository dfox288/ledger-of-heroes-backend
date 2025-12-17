<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for hit dice information.
 *
 * @property array $resource The hit dice data from HitDiceService
 */
class HitDiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     hit_dice: array<string, array{available: int, max: int, spent: int}>,
     *     total: array{available: int, max: int, spent: int}
     * }
     */
    public function toArray(Request $request): array
    {
        // The resource receives the array directly from HitDiceService
        // which already has the correct structure
        return $this->resource;
    }
}
