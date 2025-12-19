<?php

namespace App\Http\Resources;

use App\DTOs\CharacterStatsDTO;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Complete character sheet aggregate resource.
 *
 * Returns all character data in a single response, reducing
 * frontend round-trips for character sheet displays.
 *
 * @property Character $resource
 */
class CharacterSheetResource extends JsonResource
{
    public function __construct(
        Character $character,
        private CharacterStatsDTO $stats
    ) {
        parent::__construct($character);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Full character data (reuse existing resource)
            'character' => (new CharacterResource($this->resource))->resolve(),

            // Computed stats
            'stats' => (new CharacterStatsResource($this->stats))->resolve(),

            // All spells
            'spells' => CharacterSpellResource::collection($this->resource->spells)->resolve(),

            // All equipment
            'equipment' => CharacterEquipmentResource::collection($this->resource->equipment)->resolve(),

            // All features
            'features' => CharacterFeatureResource::collection($this->resource->features)->resolve(),

            // Notes grouped by category
            'notes' => (new CharacterNotesGroupedResource($this->resource->notes))->resolve()['data'] ?? [],

            // Proficiencies
            'proficiencies' => CharacterProficiencyResource::collection($this->resource->proficiencies)->resolve(),

            // Languages
            'languages' => CharacterLanguageResource::collection($this->resource->languages)->resolve(),
        ];
    }
}
