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
     * @return array{
     *     id: string,
     *     type: string,
     *     subtype: string|null,
     *     source: string,
     *     source_name: string,
     *     level_granted: int,
     *     required: bool,
     *     quantity: int,
     *     remaining: int,
     *     selected: array<string>,
     *     options: array|null,
     *     options_endpoint: string|null,
     *     metadata: array
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string Deterministic choice ID: {type}:{source}:{sourceId}:{level}:{group} */
            'id' => $this->resource->id,
            /** @var string Choice type: proficiency, language, equipment, spell, asi_or_feat, subclass, optional_feature */
            'type' => $this->resource->type,
            /** @var string|null Subtype: skill, tool, cantrip, invocation, etc. */
            'subtype' => $this->resource->subtype,
            /** @var string Source: class, race, background, feat */
            'source' => $this->resource->source,
            /** @var string Human-readable source name: "Rogue", "High Elf", etc. */
            'source_name' => $this->resource->sourceName,
            /** @var int Character level when choice became available */
            'level_granted' => $this->resource->levelGranted,
            /** @var bool Whether choice blocks completion if unresolved */
            'required' => $this->resource->required,
            /** @var int How many selections needed */
            'quantity' => $this->resource->quantity,
            /** @var int Selections still needed (quantity - selected count) */
            'remaining' => $this->resource->remaining,
            /** @var array<string> Already chosen option IDs/slugs */
            'selected' => $this->resource->selected,
            /** @var array|null Available options (null if external endpoint) */
            'options' => $this->resource->options,
            /** @var string|null URL for dynamic options */
            'options_endpoint' => $this->resource->optionsEndpoint,
            /** @var array Type-specific extra data */
            'metadata' => $this->resource->metadata,
        ];
    }
}
