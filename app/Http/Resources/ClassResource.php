<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Determine if we should include base class features for subclasses
        $includeBaseFeatures = $request->boolean('include_base_features', true);

        return [
            // === BASE FIELDS ===
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'hit_die' => $this->hit_die,
            'effective_hit_die' => $this->effective_hit_die,
            'description' => $this->description,
            'primary_ability' => $this->primary_ability,
            'spellcasting_ability' => $this->when($this->spellcasting_ability_id, function () {
                return new AbilityScoreResource($this->whenLoaded('spellcastingAbility'));
            }),
            'parent_class_id' => $this->parent_class_id,
            'is_base_class' => $this->is_base_class,

            // === RELATIONSHIPS ===
            'parent_class' => $this->when($this->parent_class_id, function () {
                return new ClassResource($this->whenLoaded('parentClass'));
            }),
            'subclasses' => ClassResource::collection($this->whenLoaded('subclasses')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'traits' => TraitResource::collection($this->whenLoaded('traits')),

            // Features: Use getAllFeatures() to merge base + subclass features when appropriate
            'features' => $this->when($this->relationLoaded('features'), function () use ($includeBaseFeatures) {
                return ClassFeatureResource::collection($this->getAllFeatures($includeBaseFeatures));
            }),

            'level_progression' => ClassLevelProgressionResource::collection($this->whenLoaded('levelProgression')),
            'counters' => ClassCounterResource::collection($this->whenLoaded('counters')),
            'spells' => SpellResource::collection($this->whenLoaded('spells')),
            'optional_features' => OptionalFeatureResource::collection($this->whenLoaded('optionalFeatures')),
            'equipment' => EntityItemResource::collection($this->whenLoaded('equipment')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),

            // === INHERITED DATA (subclasses only) ===
            /**
             * Pre-resolved inheritance data from parent class.
             * Only present for subclasses - contains data they inherit from their base class.
             *
             * @var array{hit_die: int|null, hit_points: array{hit_die: string, hit_die_numeric: int, first_level: array{value: int, description: string}, higher_levels: array{roll: string, average: int, description: string}}|null, counters: array<ClassCounterResource>|null, traits: array<TraitResource>|null, level_progression: array<ClassLevelProgressionResource>|null, equipment: array<EntityItemResource>|null, proficiencies: array<ProficiencyResource>|null, spell_slot_summary: array{has_spell_slots: bool, max_spell_level: int|null, available_levels: array<int>, has_cantrips: bool, caster_type: string|null}|null}|null
             */
            'inherited_data' => $this->when(
                ! $this->is_base_class && $this->relationLoaded('parentClass') && $this->parentClass,
                function () {
                    $parent = $this->parentClass;

                    return [
                        'hit_die' => $parent->hit_die,
                        'hit_points' => $parent->hit_points,
                        'counters' => $parent->relationLoaded('counters')
                            ? ClassCounterResource::collection($parent->counters)
                            : null,
                        'traits' => $parent->relationLoaded('traits')
                            ? TraitResource::collection($parent->traits)
                            : null,
                        'level_progression' => $parent->relationLoaded('levelProgression')
                            ? ClassLevelProgressionResource::collection($parent->levelProgression)
                            : null,
                        'equipment' => $parent->relationLoaded('equipment')
                            ? EntityItemResource::collection($parent->equipment)
                            : null,
                        'proficiencies' => $parent->relationLoaded('proficiencies')
                            ? ProficiencyResource::collection($parent->proficiencies)
                            : null,
                        'spell_slot_summary' => $parent->relationLoaded('levelProgression')
                            ? $parent->spell_slot_summary
                            : null,
                    ];
                }
            ),

            // === COMPUTED DATA (show endpoint only) ===
            /**
             * Computed and aggregated data for detail views.
             * Only included on show endpoint for performance.
             *
             * @var ClassComputedResource|null
             */
            'computed' => $this->when(
                $request->routeIs('classes.show'),
                fn () => new ClassComputedResource($this->resource)
            ),
        ];
    }
}
