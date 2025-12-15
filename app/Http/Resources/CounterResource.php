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
 * @property string $source
 * @property string $source_type
 * @property bool $unlimited
 */
class CounterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle both array and object access (from collection or single)
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'current' => $this->current,
            'max' => $this->max,
            'reset_on' => $this->reset_on,
            'source' => $this->source,
            'source_type' => $this->source_type,
            'unlimited' => $this->unlimited,
        ];
    }
}
