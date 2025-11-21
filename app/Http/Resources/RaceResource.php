<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RaceResource extends JsonResource
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
            'size' => new SizeResource($this->whenLoaded('size')),
            'speed' => $this->speed,
            'traits' => TraitResource::collection($this->whenLoaded('traits')),
            'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'parent_race' => $this->when($this->parent_race_id, function () {
                return new RaceResource($this->whenLoaded('parent'));
            }),
            'subraces' => RaceResource::collection($this->whenLoaded('subraces')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),
            'conditions' => EntityConditionResource::collection($this->whenLoaded('conditions')),
            'spells' => EntitySpellResource::collection($this->whenLoaded('spells')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
