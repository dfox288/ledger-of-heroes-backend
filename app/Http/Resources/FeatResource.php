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
            // Use entitySpellRecords for EntitySpell pivot records (includes spell choices)
            'spells' => EntitySpellResource::collection($this->whenLoaded('entitySpellRecords')),
            'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),

            // Computed: grouped spell choices for easier frontend consumption
            /** @var array<int, SpellChoiceResource>|null */
            'spell_choices' => $this->when(
                $this->relationLoaded('entitySpellRecords'),
                fn () => $this->getGroupedSpellChoices()
            ),
        ];
    }

    /**
     * Group spell choices by choice_group for frontend consumption.
     *
     * @return array<int, SpellChoiceResource>|null
     */
    private function getGroupedSpellChoices(): ?array
    {
        $choices = $this->entitySpellRecords->where('is_choice', true);

        if ($choices->isEmpty()) {
            return null;
        }

        return $choices->groupBy('choice_group')->map(function ($group, $groupName) {
            return new SpellChoiceResource($group, $groupName);
        })->values()->all();
    }
}
