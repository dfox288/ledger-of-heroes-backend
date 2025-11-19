<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackgroundResource extends JsonResource
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
            'traits' => TraitResource::collection($this->whenLoaded('traits')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),
            'equipment' => EntityItemResource::collection($this->whenLoaded('equipment')),
            'random_tables' => RandomTableResource::collection($this->whenLoaded('randomTables')),
        ];
    }
}
