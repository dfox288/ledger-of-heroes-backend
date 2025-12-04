<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Generic resource for choice data.
 *
 * Wraps the choice array structure returned by various services
 * (proficiency choices, language choices, optional feature choices).
 * Preserves the existing structure while using proper Resource pattern.
 *
 * @property array $resource The choices data structure
 */
class ChoicesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Return the resource as-is since the structure varies by endpoint
        // and is already properly formatted by the service layer
        return $this->resource;
    }
}
