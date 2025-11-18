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
            'name' => $this->name,
            'size' => new SizeResource($this->whenLoaded('size')),
            'speed' => $this->speed,
            'description' => $this->description,
            'source' => $this->whenLoaded('source', function () {
                return [
                    'id' => $this->source->id,
                    'code' => $this->source->code,
                    'name' => $this->source->name,
                ];
            }),
            'source_pages' => $this->source_pages,
            'parent_race' => $this->when($this->parent_race_id, function () {
                return new RaceResource($this->whenLoaded('parent'));
            }),
            'subraces' => RaceResource::collection($this->whenLoaded('subraces')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
        ];
    }
}
