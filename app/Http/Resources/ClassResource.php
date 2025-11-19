<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'features' => ClassFeatureResource::collection($this->whenLoaded('features')),
            'level_progression' => ClassLevelProgressionResource::collection($this->whenLoaded('levelProgression')),
            'counters' => ClassCounterResource::collection($this->whenLoaded('counters')),
            'spells' => SpellResource::collection($this->whenLoaded('spells')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
        ];
    }
}
