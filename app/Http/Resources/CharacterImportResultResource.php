<?php

namespace App\Http\Resources;

use App\DTOs\CharacterImportResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for character import result.
 *
 * @mixin CharacterImportResult
 */
class CharacterImportResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'character' => [
                'public_id' => $this->character->public_id,
                'name' => $this->character->name,
                'race' => $this->character->race_slug,
                'background' => $this->character->background_slug,
            ],
            'warnings' => $this->warnings,
        ];
    }
}
