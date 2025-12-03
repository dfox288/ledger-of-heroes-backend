<?php

namespace App\Http\Resources;

use App\DTOs\AsiChoiceResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AsiChoiceResult $resource
 */
class AsiChoiceResource extends JsonResource
{
    /**
     * The resource instance.
     *
     * @var AsiChoiceResult
     */
    public $resource;

    /**
     * Create a new resource instance.
     */
    public function __construct(AsiChoiceResult $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'choice_type' => $this->resource->choiceType,
            'asi_choices_remaining' => $this->resource->asiChoicesRemaining,
            'changes' => [
                'feat' => $this->resource->feat,
                'ability_increases' => $this->resource->abilityIncreases,
                'proficiencies_gained' => $this->resource->proficienciesGained,
                'spells_gained' => $this->resource->spellsGained,
            ],
            'new_ability_scores' => $this->resource->newAbilityScores,
        ];
    }
}
