<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeatResource extends JsonResource
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
            'prerequisites_text' => $this->prerequisites_text,
            'prerequisites' => EntityPrerequisiteResource::collection($this->whenLoaded('prerequisites')),
            'description' => $this->description,

            // Computed fields
            'is_half_feat' => $this->is_half_feat,
            'parent_feat_slug' => $this->parent_feat_slug,

            // Relationships
            'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'conditions' => EntityConditionResource::collection($this->whenLoaded('conditions')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'spells' => EntitySpellResource::collection($this->whenLoaded('spells')),

            // Computed: grouped spell choices for easier frontend consumption
            'spell_choices' => $this->when(
                $this->relationLoaded('spells'),
                fn () => $this->getGroupedSpellChoices()
            ),
        ];
    }

    /**
     * Group spell choices by choice_group for frontend consumption.
     *
     * @return array<string, array<string, mixed>>|null
     */
    private function getGroupedSpellChoices(): ?array
    {
        $choices = $this->spells->where('is_choice', true);

        if ($choices->isEmpty()) {
            return null;
        }

        return $choices->groupBy('choice_group')->map(function ($group, $groupName) {
            $first = $group->first();

            return [
                'choice_group' => $groupName,
                'choice_count' => $first->choice_count,
                'max_level' => $first->max_level,
                'is_ritual_only' => $first->is_ritual_only,
                'allowed_schools' => $group
                    ->filter(fn ($s) => $s->school_id !== null)
                    ->map(fn ($s) => [
                        'id' => $s->school_id,
                        'name' => $s->school?->name,
                    ])
                    ->values()
                    ->all(),
                'allowed_class' => $first->class_id ? [
                    'id' => $first->class_id,
                    'name' => $first->characterClass?->name,
                ] : null,
            ];
        })->values()->all();
    }
}
