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
            'school' => new SpellSchoolResource($this->whenLoaded('spellSchool')),
            'casting_time' => $this->casting_time,
            'range' => $this->range,
            'components' => $this->components,
            'material_components' => $this->material_components,
            'duration' => $this->duration,
            'needs_concentration' => $this->needs_concentration,
            'is_ritual' => $this->is_ritual,
            'description' => $this->description,
            'higher_levels' => $this->higher_levels,
            'sources' => $this->whenLoaded('sources', function () {
                return $this->sources->map(function ($entitySource) {
                    return [
                        'code' => $entitySource->source->code,
                        'name' => $entitySource->source->name,
                        'pages' => $entitySource->pages,
                    ];
                });
            }),
            'effects' => SpellEffectResource::collection($this->whenLoaded('effects')),
            'classes' => ClassResource::collection($this->whenLoaded('classes')),
        ];
    }
}
