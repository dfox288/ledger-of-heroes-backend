<?php

namespace App\Http\Resources;

use App\Models\OptionalFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OptionalFeature
 */
class OptionalFeatureResource extends JsonResource
{
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
            'action_cost' => $this->action_cost?->value,
            'action_cost_label' => $this->action_cost?->label(),
            'range' => $this->range,
            'duration' => $this->duration,
            'spell_school' => new SpellSchoolResource($this->whenLoaded('spellSchool')),
            // Resource properties
            'resource_type' => $this->resource_type?->value,
            'resource_cost' => $this->resource_cost,
            'cost_formula' => $this->cost_formula,
            // Computed attribute
            'has_spell_mechanics' => $this->has_spell_mechanics,
            // Relationships
            'classes' => ClassResource::collection($this->whenLoaded('classes')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'prerequisites' => EntityPrerequisiteResource::collection($this->whenLoaded('prerequisites')),
        ];
    }
}
