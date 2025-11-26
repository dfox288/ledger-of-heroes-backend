<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OptionalFeatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'feature_type' => $this->feature_type?->value,
            'feature_type_label' => $this->feature_type?->label(),
            'level_requirement' => $this->level_requirement,
            'prerequisite_text' => $this->prerequisite_text,
            'description' => $this->description,
            // Spell-like properties
            'casting_time' => $this->casting_time,
            'range' => $this->range,
            'duration' => $this->duration,
            'spell_school' => new SpellSchoolResource($this->whenLoaded('spellSchool')),
            // Resource properties
            'resource_type' => $this->resource_type?->value,
            'resource_cost' => $this->resource_cost,
            // Computed attribute
            'has_spell_mechanics' => $this->has_spell_mechanics,
            // Relationships
            'classes' => ClassResource::collection($this->whenLoaded('classes')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
