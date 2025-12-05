<?php

namespace App\Http\Resources;

use App\DTOs\CharacterSummaryDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for character summary.
 *
 * @property CharacterSummaryDTO $resource
 */
class CharacterSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'character' => $this->resource->character,
            'pending_choices' => $this->resource->pendingChoices,
            'resources' => $this->resource->resources,
            'combat_state' => $this->resource->combatState,
            'creation_complete' => $this->resource->creationComplete,
            'missing_required' => $this->resource->missingRequired,
        ];
    }
}
