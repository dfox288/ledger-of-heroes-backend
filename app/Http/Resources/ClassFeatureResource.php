<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassFeatureResource extends JsonResource
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
            'level' => $this->level,
            'feature_name' => $this->feature_name,
            'description' => $this->description,
            'is_optional' => $this->is_optional,
            'is_multiclass_only' => $this->is_multiclass_only,
            'is_choice_option' => $this->is_choice_option,
            'is_always_prepared' => $this->is_always_prepared,
            'parent_feature_id' => $this->parent_feature_id,
            'sort_order' => $this->sort_order,

            // Relationships
            'data_tables' => EntityDataTableResource::collection(
                $this->whenLoaded('dataTables')
            ),

            // Subclass spells (domain, circle, expanded)
            'spells' => $this->whenLoaded('spells', function () {
                return $this->spells->map(fn ($spell) => [
                    'spell' => new SpellResource($spell),
                    'level_requirement' => $spell->pivot->level_requirement,
                    'is_cantrip' => $spell->pivot->is_cantrip,
                ]);
            }),

            // Nested child features (choice options)
            'choice_options' => ClassFeatureResource::collection(
                $this->whenLoaded('childFeatures')
            ),
        ];
    }
}
