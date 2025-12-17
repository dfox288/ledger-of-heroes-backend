<?php

namespace App\Http\Resources;

use App\Models\FeatureSelection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for character's optional features (invocations, infusions, metamagic, etc.)
 *
 * Wraps OptionalFeatureResource with character-specific data (class_slug, level_acquired).
 *
 * @mixin FeatureSelection
 */
class CharacterOptionalFeatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $optionalFeature = $this->optionalFeature;

        // If the optional feature doesn't exist (dangling reference), return minimal data
        if (! $optionalFeature) {
            return [
                'slug' => $this->optional_feature_slug,
                'name' => null,
                'is_dangling' => true,
                'class_slug' => $this->class_slug,
                'level_acquired' => $this->level_acquired,
            ];
        }

        return [
            // Core optional feature data
            'id' => $optionalFeature->id,
            'slug' => $optionalFeature->slug,
            'name' => $optionalFeature->name,
            'feature_type' => $optionalFeature->feature_type?->value,
            'feature_type_label' => $optionalFeature->feature_type?->label(),
            'level_requirement' => $optionalFeature->level_requirement,
            'prerequisite_text' => $optionalFeature->prerequisite_text,
            'description' => $optionalFeature->description,
            // Spell-like properties
            'casting_time' => $optionalFeature->casting_time,
            'action_cost' => $optionalFeature->action_cost?->value,
            'action_cost_label' => $optionalFeature->action_cost?->label(),
            'range' => $optionalFeature->range,
            'duration' => $optionalFeature->duration,
            'spell_school' => new SpellSchoolResource($this->whenLoaded('optionalFeature.spellSchool', $optionalFeature->spellSchool)),
            // Resource properties
            'resource_type' => $optionalFeature->resource_type?->value,
            'resource_cost' => $optionalFeature->resource_cost,
            'cost_formula' => $optionalFeature->cost_formula,
            // Computed attribute
            'has_spell_mechanics' => $optionalFeature->has_spell_mechanics,
            // Relationships from optional feature
            'classes' => ClassResource::collection($this->whenLoaded('optionalFeature.classes', $optionalFeature->classes)),
            'sources' => EntitySourceResource::collection($this->whenLoaded('optionalFeature.sources', $optionalFeature->sources)),
            'tags' => TagResource::collection($this->whenLoaded('optionalFeature.tags', $optionalFeature->tags)),
            'prerequisites' => EntityPrerequisiteResource::collection($this->whenLoaded('optionalFeature.prerequisites', $optionalFeature->prerequisites)),
            // Character-specific data
            'class_slug' => $this->class_slug,
            'subclass_name' => $this->subclass_name,
            'level_acquired' => $this->level_acquired,
        ];
    }
}
