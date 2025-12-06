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
     * @return array{
     *     character: array{id: int, name: string, total_level: int},
     *     pending_choices: array{proficiencies: int, languages: int, spells: int, optional_features: int, asi: int},
     *     resources: array{hit_points: array{current: int|null, max: int|null, temp: int}, hit_dice: array{available: int, max: int}, spell_slots: array, features_with_uses: array},
     *     combat_state: array{conditions: array<string>, death_saves: array{successes: int, failures: int}, is_conscious: bool},
     *     creation_complete: bool,
     *     missing_required: array<string>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var array{id: int, name: string, total_level: int} Basic character info */
            'character' => $this->resource->character,
            /** @var array{proficiencies: int, languages: int, spells: int, optional_features: int, asi: int} Pending choices requiring user input */
            'pending_choices' => $this->resource->pendingChoices,
            /** @var array{hit_points: array{current: int|null, max: int|null, temp: int}, hit_dice: array{available: int, max: int}, spell_slots: array, features_with_uses: array} Current resource states */
            'resources' => $this->resource->resources,
            /** @var array{conditions: array<string>, death_saves: array{successes: int, failures: int}, is_conscious: bool} Combat state information */
            'combat_state' => $this->resource->combatState,
            /** @var bool Whether character creation is complete */
            'creation_complete' => $this->resource->creationComplete,
            /** @var array<string> List of missing required fields/choices */
            'missing_required' => $this->resource->missingRequired,
        ];
    }
}
