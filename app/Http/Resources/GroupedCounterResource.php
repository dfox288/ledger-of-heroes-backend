<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Resource for counters grouped by name with level progression.
 *
 * This resource transforms flat counter records into a grouped format
 * that shows how each counter's value progresses across levels.
 *
 * Input: Collection of ClassCounter models with same counter_name
 * Output: { name, reset_timing, progression: [{level, value}, ...] }
 *
 * @property string $counter_name
 * @property string $reset_timing
 * @property int $level
 * @property int|string $counter_value
 */
class GroupedCounterResource extends JsonResource
{
    /**
     * Transform a group of counters into the grouped format.
     *
     * @return array{name: string, reset_timing: string, progression: array<array{level: int, value: int|string}>}
     */
    public function toArray(Request $request): array
    {
        // $this->resource is a Collection of counters with the same counter_name
        $counters = $this->resource;
        $first = $counters->first();

        return [
            'name' => $first->counter_name,
            'reset_timing' => match ($first->reset_timing) {
                'S' => 'Short Rest',
                'L' => 'Long Rest',
                default => 'Does Not Reset',
            },
            'progression' => $counters->sortBy('level')->map(fn ($counter) => [
                'level' => $counter->level,
                'value' => $counter->counter_value === -1 ? 'Unlimited' : $counter->counter_value,
            ])->values()->all(),
        ];
    }

    /**
     * Create a collection of grouped counter resources from flat counters.
     *
     * Groups counters by counter_name and returns a collection of GroupedCounterResource.
     *
     * @param  Collection  $counters  Flat collection of ClassCounter models
     * @return array<GroupedCounterResource>
     */
    public static function fromCounters(Collection $counters): array
    {
        return $counters->groupBy('counter_name')
            ->map(fn ($group) => new self($group))
            ->values()
            ->all();
    }
}
