<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Determine if we should include base class features for subclasses
        $includeBaseFeatures = $request->boolean('include_base_features', true);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'hit_die' => $this->hit_die,
            'description' => $this->description,
            'primary_ability' => $this->primary_ability,
            'spellcasting_ability' => $this->when($this->spellcasting_ability_id, function () {
                return new AbilityScoreResource($this->whenLoaded('spellcastingAbility'));
            }),
            'parent_class_id' => $this->parent_class_id,
            'is_base_class' => $this->is_base_class,
            'parent_class' => $this->when($this->parent_class_id, function () {
                return new ClassResource($this->whenLoaded('parentClass'));
            }),
            'subclasses' => ClassResource::collection($this->whenLoaded('subclasses')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'traits' => TraitResource::collection($this->whenLoaded('traits')),

            // Features: Use getAllFeatures() to merge base + subclass features when appropriate
            'features' => $this->when($this->relationLoaded('features'), function () use ($includeBaseFeatures) {
                return ClassFeatureResource::collection($this->getAllFeatures($includeBaseFeatures));
            }),

            'level_progression' => ClassLevelProgressionResource::collection($this->whenLoaded('levelProgression')),
            'counters' => ClassCounterResource::collection($this->whenLoaded('counters')),
            'spells' => SpellResource::collection($this->whenLoaded('spells')),
            'equipment' => EntityItemResource::collection($this->whenLoaded('equipment')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
