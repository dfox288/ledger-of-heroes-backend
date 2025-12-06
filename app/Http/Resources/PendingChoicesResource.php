<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for a collection of pending choices with summary.
 *
 * Wraps multiple PendingChoice DTOs with summary metadata including
 * total count, remaining choices, and counts by type.
 *
 * @property array{choices: \Illuminate\Support\Collection, summary: array} $resource
 */
class PendingChoicesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     choices: array<array{id: string, type: string, subtype: string|null, source: string, source_name: string, level_granted: int, required: bool, quantity: int, remaining: int, selected: array<string>, options: array|null, options_endpoint: string|null, metadata: array}>,
     *     summary: array{total_pending: int, required_pending: int, optional_pending: int, by_type: array<string, int>, by_source: array<string, int>}
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var array<array{id: string, type: string, subtype: string|null, source: string, source_name: string, level_granted: int, required: bool, quantity: int, remaining: int, selected: array<string>, options: array|null, options_endpoint: string|null, metadata: array}> All pending choices */
            'choices' => $this->resource['choices']->map(fn ($choice) => $choice->toArray())->values()->all(),
            /** @var array{total_pending: int, required_pending: int, optional_pending: int, by_type: array<string, int>, by_source: array<string, int>} Summary statistics */
            'summary' => $this->resource['summary'],
        ];
    }
}
