<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeatureSelectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'optional_feature' => $this->optionalFeature ? [
                'id' => $this->optionalFeature->id,
                'slug' => $this->optionalFeature->slug,
                'name' => $this->optionalFeature->name,
                'feature_type' => $this->optionalFeature->feature_type?->value,
                'description' => $this->optionalFeature->description,
                'level_requirement' => $this->optionalFeature->level_requirement,
                'prerequisite_text' => $this->optionalFeature->prerequisite_text,
                'resource_type' => $this->optionalFeature->resource_type?->value,
                'resource_cost' => $this->optionalFeature->resource_cost,
            ] : null,
            'optional_feature_slug' => $this->optional_feature_slug,
            'class' => $this->when($this->characterClass, [
                'id' => $this->characterClass?->id,
                'name' => $this->characterClass?->name,
            ]),
            'class_slug' => $this->class_slug,
            'is_dangling' => $this->optionalFeature === null,
            'subclass_name' => $this->subclass_name,
            'level_acquired' => $this->level_acquired,
            // Note: Limited-use tracking moved to character_counters table
            // Counter info is exposed via CharacterResource.counters
        ];
    }
}
