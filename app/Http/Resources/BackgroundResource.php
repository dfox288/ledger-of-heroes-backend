<?php

namespace App\Http\Resources;

use App\Models\Background;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Background
 */
class BackgroundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,

            // Feature extraction (computed from traits with category='feature')
            'feature_name' => $this->feature_name,
            'feature_description' => $this->feature_description,

            // Relationships
            'traits' => TraitResource::collection($this->whenLoaded('traits')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),
            'equipment' => EntityItemResource::collection($this->whenLoaded('equipment')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),

            // === CHOICES ===
            /** @var array<EntityChoiceResource> Non-equipment choices (language, proficiency) */
            'choices' => EntityChoiceResource::collection($this->whenLoaded('nonEquipmentChoices')),
            /** @var array<array{choice_group: string, quantity: int, options: array}> Equipment choices (grouped by option) */
            'equipment_choices' => $this->when(
                $this->relationLoaded('equipmentChoices'),
                fn () => EntityChoiceResource::groupedByChoiceGroup($this->equipmentChoices)
            ),

            // Convenience field: flattened data tables from all traits
            // Includes Personality Traits, Ideals, Bonds, Flaws roll tables
            'data_tables' => $this->when(
                $this->relationLoaded('traits'),
                fn () => $this->getFlattenedDataTables()
            ),
        ];
    }

    /**
     * Flatten data tables from all traits into a single array.
     *
     * Background data tables (Personality Trait, Ideal, Bond, Flaw) are stored
     * on CharacterTrait models. This flattens them for easier frontend consumption.
     *
     * @return array<int, DataTableResource>|null
     */
    private function getFlattenedDataTables(): ?array
    {
        $allTables = $this->traits
            ->filter(fn ($trait) => $trait->relationLoaded('dataTables'))
            ->flatMap(fn ($trait) => $trait->dataTables);

        if ($allTables->isEmpty()) {
            return null;
        }

        return EntityDataTableResource::collection($allTables)->resolve();
    }
}
