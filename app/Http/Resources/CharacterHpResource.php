<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for character HP modification results.
 *
 * Returns only HP-related fields for lightweight response.
 *
 * @property array{current_hit_points: int, max_hit_points: int, temp_hit_points: int, death_save_successes: int, death_save_failures: int} $resource
 */
class CharacterHpResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'current_hit_points' => $this->resource['current_hit_points'],
            'max_hit_points' => $this->resource['max_hit_points'],
            'temp_hit_points' => $this->resource['temp_hit_points'],
            'death_save_successes' => $this->resource['death_save_successes'],
            'death_save_failures' => $this->resource['death_save_failures'],
        ];
    }
}
