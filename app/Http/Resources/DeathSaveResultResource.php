<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for death save roll results.
 *
 * @property array $resource The death save result data
 */
class DeathSaveResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     death_save_successes: int,
     *     death_save_failures: int,
     *     current_hit_points: int,
     *     result: string,
     *     outcome: ?string,
     *     is_stable: bool,
     *     is_dead: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int Current success count (0-3) */
            'death_save_successes' => $this->resource['death_save_successes'],
            /** @var int Current failure count (0-3) */
            'death_save_failures' => $this->resource['death_save_failures'],
            /** @var int Character's current HP */
            'current_hit_points' => $this->resource['current_hit_points'],
            /** @var string Roll result: success, failure, critical_success, critical_failure, damage, critical_damage */
            'result' => $this->resource['result'],
            /** @var string|null Final outcome if determined: stable, dead, conscious */
            'outcome' => $this->resource['outcome'],
            /** @var bool True if character is stable (3+ successes) */
            'is_stable' => $this->resource['is_stable'],
            /** @var bool True if character is dead (3+ failures) */
            'is_dead' => $this->resource['is_dead'],
        ];
    }
}
