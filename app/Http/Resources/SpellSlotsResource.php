<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for spell slot data.
 *
 * Supports two data structures:
 * 1. SpellSlotService: { standard: {...}, pact_magic: {...} }
 * 2. SpellManagerService: { slots: [...], preparation_limit: int|null }
 *
 * The resource passes through the data as-is since both structures
 * are valid and serve different purposes.
 */
class SpellSlotsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Pass through the data as-is - structure varies by service
        return $this->resource;
    }
}
