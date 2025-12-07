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
            'slug' => $this->slug,
            'full_slug' => $this->full_slug,
            'name' => $this->name,
            'level' => $this->level,
            'school' => new SpellSchoolResource($this->whenLoaded('spellSchool')),
            'casting_time' => $this->casting_time,
            'casting_time_type' => $this->casting_time_type,
            'range' => $this->range,
            'components' => $this->components,
            'material_components' => $this->material_components,
            'material_cost_gp' => $this->material_cost_gp,
            'material_consumed' => $this->material_consumed,
            'duration' => $this->duration,
            'needs_concentration' => $this->needs_concentration,
            'is_ritual' => $this->is_ritual,
            'description' => $this->description,
            'higher_levels' => $this->higher_levels,
            // Component breakdown (computed from components string)
            'requires_verbal' => str_contains($this->components ?? '', 'V'),
            'requires_somatic' => str_contains($this->components ?? '', 'S'),
            'requires_material' => str_contains($this->components ?? '', 'M'),
            // Area of effect (parsed from description)
            'area_of_effect' => $this->when(
                $this->area_of_effect !== null,
                fn () => new AreaOfEffectResource($this->area_of_effect)
            ),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'effects' => SpellEffectResource::collection($this->whenLoaded('effects')),
            'classes' => ClassResource::collection($this->whenLoaded('classes')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'saving_throws' => SavingThrowResource::collection($this->whenLoaded('savingThrows')),
            'data_tables' => EntityDataTableResource::collection($this->whenLoaded('dataTables')),
        ];
    }
}
