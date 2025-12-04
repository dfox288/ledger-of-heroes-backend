<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Generic resource for simple slug/name lookup values.
 *
 * Used for derived lookup data like armor types, alignments, monster types,
 * and rarities that are extracted from database values rather than stored
 * in dedicated lookup tables.
 *
 * @property array $resource The lookup data with slug and name keys
 */
class LookupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->resource['slug'],
            'name' => $this->resource['name'],
        ];
    }
}
