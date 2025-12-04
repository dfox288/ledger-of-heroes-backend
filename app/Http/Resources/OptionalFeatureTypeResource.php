<?php

namespace App\Http\Resources;

use App\Enums\OptionalFeatureType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for OptionalFeatureType enum values.
 *
 * Transforms OptionalFeatureType enum cases into API responses with
 * value, label, and default class/subclass information.
 *
 * @property OptionalFeatureType $resource The enum case
 */
class OptionalFeatureTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource->value,
            'label' => $this->resource->label(),
            'default_class' => $this->resource->defaultClassName(),
            'default_subclass' => $this->resource->defaultSubclassName(),
        ];
    }
}
