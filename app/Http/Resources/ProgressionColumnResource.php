<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for progression table column definitions.
 *
 * @property string $key Column identifier key
 * @property string $label Display label for the column
 * @property string $type Value type: integer, bonus, string, dice
 */
class ProgressionColumnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{key: string, label: string, type: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => (string) $this->resource['key'],
            'label' => (string) $this->resource['label'],
            'type' => (string) $this->resource['type'],
        ];
    }
}
