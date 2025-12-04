<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterOptionalFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'optional_feature' => [
                'id' => $this->optionalFeature->id,
                'slug' => $this->optionalFeature->slug,
                'name' => $this->optionalFeature->name,
                'feature_type' => $this->optionalFeature->feature_type?->value,
                'description' => $this->optionalFeature->description,
                'level_requirement' => $this->optionalFeature->level_requirement,
                'prerequisite_text' => $this->optionalFeature->prerequisite_text,
                'resource_type' => $this->optionalFeature->resource_type?->value,
                'resource_cost' => $this->optionalFeature->resource_cost,
            ],
            'class' => $this->when($this->characterClass, [
                'id' => $this->characterClass?->id,
                'name' => $this->characterClass?->name,
            ]),
            'subclass_name' => $this->subclass_name,
            'level_acquired' => $this->level_acquired,
            'uses_remaining' => $this->uses_remaining,
            'max_uses' => $this->max_uses,
            'has_limited_uses' => $this->hasLimitedUses(),
        ];
    }
}
