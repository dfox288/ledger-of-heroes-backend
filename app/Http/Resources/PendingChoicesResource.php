<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * API Resource for a collection of pending choices with summary.
 *
 * Wraps multiple PendingChoice DTOs with summary metadata including
 * total count, remaining choices, and counts by type.
 *
 * @property-read Collection<int, \App\DTOs\PendingChoice> $choices
 * @property-read array{total: int, remaining: int, by_type: array<string, int>} $summary
 */
class PendingChoicesResource extends JsonResource
{
    /**
     * Create a new resource instance.
     *
     * @param  Collection<int, \App\DTOs\PendingChoice>  $choices  Collection of PendingChoice DTOs
     * @param  array{total: int, remaining: int, by_type: array<string, int>}  $summary  Summary metadata
     */
    public function __construct(
        private readonly Collection $choices,
        private readonly array $summary
    ) {
        parent::__construct($choices);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'choices' => $this->choices->map(fn ($choice) => $choice->toArray())->values()->all(),
            'summary' => $this->summary,
        ];
    }
}
