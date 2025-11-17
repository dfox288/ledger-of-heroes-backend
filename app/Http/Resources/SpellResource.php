<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'school' => [
                'id' => $this->spellSchool->id,
                'code' => $this->spellSchool->code,
                'name' => $this->spellSchool->name,
            ],
            'casting_time' => $this->casting_time,
            'range' => $this->range,
            'components' => $this->components,
            'material_components' => $this->material_components,
            'duration' => $this->duration,
            'needs_concentration' => $this->needs_concentration,
            'is_ritual' => $this->is_ritual,
            'description' => $this->description,
            'higher_levels' => $this->higher_levels,
            'source' => [
                'id' => $this->source->id,
                'code' => $this->source->code,
                'name' => $this->source->name,
            ],
            'source_pages' => $this->source_pages,
            'effects' => SpellEffectResource::collection($this->whenLoaded('effects')),
        ];
    }
}
