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
            'slug' => $this->slug,
            'name' => $this->name,
            'size' => new SizeResource($this->whenLoaded('size')),
            'speed' => $this->speed,
            'fly_speed' => $this->fly_speed,
            'swim_speed' => $this->swim_speed,
            'climb_speed' => $this->climb_speed,
            'is_subrace' => $this->is_subrace,
            'traits' => TraitResource::collection($this->whenLoaded('traits')),
            'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'parent_race' => $this->when($this->parent_race_id, function () {
                return new RaceResource($this->whenLoaded('parent'));
            }),
            'subraces' => RaceResource::collection($this->whenLoaded('subraces')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),
            'conditions' => EntityConditionResource::collection($this->whenLoaded('conditions')),
            // Use entitySpellRecords for EntitySpell pivot records (includes pivot data like level_requirement)
            'spells' => EntitySpellResource::collection($this->whenLoaded('entitySpellRecords')),
            'senses' => EntitySenseResource::collection($this->whenLoaded('senses')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),

            // === INHERITED DATA (subraces only) ===
            /**
             * Data inherited from parent race. Only included for subraces when parent is loaded.
             * Contains the base race's traits, modifiers, proficiencies, languages, conditions, and senses.
             *
             * @var array{traits: array<TraitResource>|null, modifiers: array<ModifierResource>|null, proficiencies: array<ProficiencyResource>|null, languages: array<EntityLanguageResource>|null, conditions: array<EntityConditionResource>|null, senses: array<EntitySenseResource>|null}|null
             */
            'inherited_data' => $this->when(
                $this->is_subrace && $this->relationLoaded('parent') && $this->parent,
                function () {
                    $parent = $this->parent;

                    return [
                        'traits' => $parent->relationLoaded('traits')
                            ? TraitResource::collection($parent->traits)
                            : null,
                        'modifiers' => $parent->relationLoaded('modifiers')
                            ? ModifierResource::collection($parent->modifiers)
                            : null,
                        'proficiencies' => $parent->relationLoaded('proficiencies')
                            ? ProficiencyResource::collection($parent->proficiencies)
                            : null,
                        'languages' => $parent->relationLoaded('languages')
                            ? EntityLanguageResource::collection($parent->languages)
                            : null,
                        'conditions' => $parent->relationLoaded('conditions')
                            ? EntityConditionResource::collection($parent->conditions)
                            : null,
                        'senses' => $parent->relationLoaded('senses')
                            ? EntitySenseResource::collection($parent->senses)
                            : null,
                    ];
                }
            ),
        ];
    }
}
