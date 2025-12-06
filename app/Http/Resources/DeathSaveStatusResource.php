<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for death save status (stabilize/reset results).
 *
 * @property array $resource The death save status data
 */
class DeathSaveStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     death_save_successes: int,
     *     death_save_failures: int,
     *     is_stable?: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $data = [
            /** @var int Current success count (0-3) */
            'death_save_successes' => $this->resource['death_save_successes'],
            /** @var int Current failure count (0-3) */
            'death_save_failures' => $this->resource['death_save_failures'],
        ];

        if (isset($this->resource['is_stable'])) {
            /** @var bool True if character is stable */
            $data['is_stable'] = $this->resource['is_stable'];
        }

        return $data;
    }
}
