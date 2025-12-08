<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class PendingChoice
{
    /**
     * @param  string  $id  Deterministic choice ID: {type}:{source}:{sourceId}:{level}:{group}
     * @param  string  $type  proficiency, language, equipment, spell, asi_or_feat, subclass, optional_feature, expertise, fighting_style, feat, hit_point_roll, ability_score
     * @param  string|null  $subtype  skill, tool, cantrip, invocation, etc.
     * @param  string  $source  class, race, background, feat
     * @param  string  $sourceName  Human-readable: "Rogue", "High Elf", etc.
     * @param  int  $levelGranted  Character level when choice became available
     * @param  bool  $required  Blocks completion if unresolved
     * @param  int  $quantity  How many selections needed
     * @param  int  $remaining  Quantity minus already selected
     * @param  array<string>  $selected  Already chosen option IDs/slugs
     * @param  array<mixed>|null  $options  Available options (null if external endpoint)
     * @param  string|null  $optionsEndpoint  URL for dynamic options
     * @param  array<string, mixed>  $metadata  Type-specific extra data
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $subtype,
        public string $source,
        public string $sourceName,
        public int $levelGranted,
        public bool $required,
        public int $quantity,
        public int $remaining,
        public array $selected,
        public ?array $options,
        public ?string $optionsEndpoint,
        public array $metadata = [],
    ) {}

    /**
     * Check if this choice has been fully resolved
     */
    public function isComplete(): bool
    {
        return $this->remaining === 0;
    }

    /**
     * Convert to array with snake_case keys
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'subtype' => $this->subtype,
            'source' => $this->source,
            'source_name' => $this->sourceName,
            'level_granted' => $this->levelGranted,
            'required' => $this->required,
            'quantity' => $this->quantity,
            'remaining' => $this->remaining,
            'selected' => $this->selected,
            'options' => $this->options,
            'options_endpoint' => $this->optionsEndpoint,
            'metadata' => $this->metadata,
        ];
    }
}
