<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for counter (limited-use class features) responses.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $current
 * @property int $max
 * @property string|null $reset_on
 * @property string $source_slug
 * @property string $source_type
 * @property bool $unlimited
 */
class CounterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     slug: string,
     *     name: string,
     *     current: int,
     *     max: int,
     *     reset_on: string|null,
     *     source_slug: string,
     *     source_type: string,
     *     unlimited: bool
     * }
     */
    public function toArray(Request $request): array
    {
        // Handle both array and object access (from collection or single)
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return [
            'id' => (int) $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'current' => (int) $this->current,
            'max' => (int) $this->max,
            'reset_on' => $this->reset_on,
            'source_slug' => $this->source_slug,
            'source_type' => $this->source_type,
            'unlimited' => (bool) $this->unlimited,
        ];
    }
}
