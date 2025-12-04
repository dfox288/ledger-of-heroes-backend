<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for short rest action results.
 *
 * Transforms short rest service response into API format with
 * pact magic reset status and features that were reset.
 *
 * @property array{pact_magic_reset: bool, features_reset: array<string>} $resource
 */
class ShortRestResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pact_magic_reset' => $this->resource['pact_magic_reset'],
            'features_reset' => $this->resource['features_reset'] ?? [],
        ];
    }
}
