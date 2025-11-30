<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for a single counter progression entry.
 *
 * Represents a level/value pair showing how a counter changes at a specific level.
 *
 * @property int $level
 * @property int|string $counter_value
 */
class CounterProgressionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{level: int, value: int|string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int Character level (1-20) */
            'level' => (int) $this->level,
            /** @var int|string Counter value at this level, or "Unlimited" for infinite uses */
            'value' => $this->counter_value === -1 ? 'Unlimited' : (int) $this->counter_value,
        ];
    }
}
