<?php

namespace App\Http\Resources;

use App\DTOs\CharacterValidationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CharacterValidationResult
 */
class CharacterValidationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'valid' => $this->valid,
            'dangling_references' => $this->danglingReferences,
            'summary' => [
                'total_references' => $this->totalReferences,
                'valid_references' => $this->validReferences,
                'dangling_count' => $this->danglingCount,
            ],
        ];
    }
}
