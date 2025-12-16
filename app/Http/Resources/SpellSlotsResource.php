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
        $data = $this->resource;

        // If we have the new consolidated structure (has prepared_count),
        // convert slots to an object to preserve spell level keys in JSON
        if (isset($data['slots']) && isset($data['prepared_count'])) {
            // Cast to object so JSON encoding preserves keys like "1", "2"
            $data['slots'] = (object) $data['slots'];
        }

        // Issue #715: Cast preparation_limits to object to preserve class slug keys
        if (isset($data['preparation_limits']) && is_array($data['preparation_limits'])) {
            $data['preparation_limits'] = (object) $data['preparation_limits'];
        }

        // For the old structure (standard/pact_magic with sub-arrays),
        // convert nested arrays to objects to preserve spell level keys
        if (isset($data['standard']) && is_array($data['standard'])) {
            $data['standard'] = (object) $data['standard'];
        }
        if (isset($data['pact_magic']) && is_array($data['pact_magic'])) {
            $data['pact_magic'] = (object) $data['pact_magic'];
        }

        return $data;
    }
}
