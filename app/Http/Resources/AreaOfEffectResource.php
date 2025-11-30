<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for spell area of effect data.
 *
 * Wraps the parsed area of effect array from Spell::getAreaOfEffectAttribute()
 * with proper type information for OpenAPI documentation.
 *
 * @property string $type Area type: cone, sphere, cube, line, cylinder
 * @property int $size Primary dimension in feet (radius for sphere/cylinder, length for cone/line/cube)
 * @property int|null $width Width in feet (lines only)
 * @property int|null $height Height in feet (cylinders only)
 */
class AreaOfEffectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{type: string, size: int, width?: int, height?: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => (string) $this->resource['type'],
            'size' => (int) $this->resource['size'],
            'width' => $this->when(
                isset($this->resource['width']),
                fn () => (int) $this->resource['width']
            ),
            'height' => $this->when(
                isset($this->resource['height']),
                fn () => (int) $this->resource['height']
            ),
        ];
    }
}
