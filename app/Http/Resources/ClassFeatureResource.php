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
            'parent_feature_id' => $this->parent_feature_id,
            'sort_order' => $this->sort_order,

            // Relationships
            'random_tables' => RandomTableResource::collection(
                $this->whenLoaded('randomTables')
            ),

            // Nested child features (choice options)
            'choice_options' => ClassFeatureResource::collection(
                $this->whenLoaded('childFeatures')
            ),
        ];
    }
}
