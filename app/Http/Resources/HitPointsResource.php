<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for class hit point formulas.
 *
 * Wraps the computed hit points array from CharacterClass::getHitPointsAttribute()
 * with proper type information for OpenAPI documentation.
 *
 * @property string $hit_die Hit die notation (e.g., "d8", "d10")
 * @property int $hit_die_numeric Numeric hit die value (e.g., 8, 10)
 * @property array{value: int, description: string} $first_level First level HP formula
 * @property array{roll: string, average: int, description: string} $higher_levels Higher level HP formula
 */
class HitPointsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *   hit_die: string,
     *   hit_die_numeric: int,
     *   first_level: array{value: int, description: string},
     *   higher_levels: array{roll: string, average: int, description: string}
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'hit_die' => (string) $this->resource['hit_die'],
            'hit_die_numeric' => (int) $this->resource['hit_die_numeric'],
            'first_level' => [
                'value' => (int) $this->resource['first_level']['value'],
                'description' => (string) $this->resource['first_level']['description'],
            ],
            'higher_levels' => [
                'roll' => (string) $this->resource['higher_levels']['roll'],
                'average' => (int) $this->resource['higher_levels']['average'],
                'description' => (string) $this->resource['higher_levels']['description'],
            ],
        ];
    }
}
