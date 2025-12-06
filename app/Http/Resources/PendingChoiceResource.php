<?php

namespace App\Http\Resources;

use App\DTOs\PendingChoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for a single pending choice.
 *
 * Transforms a PendingChoice DTO into API format for character creation/level-up choices.
 *
 * @property PendingChoice $resource
 */
class PendingChoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
