<?php

namespace App\Http\Resources;

use App\Models\ClassFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClassFeature
 */
class ClassFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'level' => (int) $this->level,
            'feature_name' => $this->feature_name,
            'description' => $this->description,
            'is_optional' => (bool) $this->is_optional,
            'is_multiclass_only' => (bool) $this->is_multiclass_only,
            'is_choice_option' => (bool) $this->is_choice_option,
            'is_always_prepared' => (bool) $this->is_always_prepared,
            'parent_feature_id' => $this->parent_feature_id ? (int) $this->parent_feature_id : null,
            'sort_order' => (int) $this->sort_order,
            'resets_on' => $this->resets_on?->value,
            'resets_on_label' => $this->resets_on?->label(),
            'action_cost' => $this->action_cost?->value,
            'action_cost_label' => $this->action_cost?->label(),

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
