<?php

namespace App\Http\Resources;

use App\Models\CharacterClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CharacterClass
 */
class ClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * ## Field Inheritance (Subclasses)
     *
     * D&D 5e subclasses inherit certain properties from their parent class.
     * This resource automatically resolves inheritance so the API returns
     * the effective values:
     *
     * - **hit_die**: Subclasses inherit from parent class (Death Domain → 8 from Cleric)
     * - **spellcasting_ability**: Subclasses inherit from parent class (Death Domain → Wisdom from Cleric)
     *
     * The raw database values (0 for hit_die, null for spellcasting_ability_id) are
     * never exposed. Instead, the model's `effective_hit_die` and `effective_spellcasting_ability`
     * accessors resolve inheritance automatically.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Determine if we should include base class features for subclasses
        $includeBaseFeatures = $request->boolean('include_base_features', true);

        return [
            // === BASE FIELDS ===
            // Note: hit_die and spellcasting_ability use effective values that
            // inherit from parent class for subclasses (see class docblock)
            'id' => (int) $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'hit_die' => (int) $this->effective_hit_die,
            'description' => $this->description,
            'archetype' => $this->archetype,
            'primary_ability' => $this->primary_ability,
            'spellcasting_ability' => $this->when($this->effective_spellcasting_ability, function () {
                return new AbilityScoreResource($this->effective_spellcasting_ability);
            }),
            'parent_class_id' => $this->parent_class_id,
            'is_base_class' => (bool) $this->is_base_class,
            'subclass_level' => $this->subclass_level !== null ? (int) $this->subclass_level : null,
            'spellcasting_type' => $this->spellcasting_type,
            'spell_count' => $this->spells_count !== null ? (int) $this->spells_count : null,

            // === STARTING WEALTH (Gold Alternative) ===
            /**
             * Pre-computed starting wealth data for the gold alternative to starting equipment.
             * D&D 5e allows players to choose starting gold instead of fixed equipment.
             * Returns null for subclasses (they don't have separate starting wealth).
             *
             * @var array{dice: string, multiplier: int, average: int, formula: string, description: string}|null
             */
            'starting_wealth' => $this->when($this->is_base_class, fn () => $this->starting_wealth),

            // === MULTICLASS REQUIREMENTS ===
            'multiclass_requirements' => $this->when(
                $this->relationLoaded('multiclassRequirements'),
                fn () => MulticlassRequirementResource::collectionWithType($this->multiclassRequirements)
            ),

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
            /** @var array<GroupedCounterResource>|null Class counters grouped by name */
            'counters' => $this->when($this->relationLoaded('counters'), fn () => GroupedCounterResource::fromCounters($this->counters)),
            'spells' => SpellResource::collection($this->whenLoaded('spells')),
            'optional_features' => OptionalFeatureResource::collection($this->whenLoaded('optionalFeatures')),
            'equipment' => EntityItemResource::collection($this->whenLoaded('equipment')),
            /** @var array<array{choice_group: string, choice_type: string, quantity: int, options: array}>|array Starting equipment choices (pick one from each group) */
            'equipment_choices' => $this->when(
                $this->relationLoaded('equipmentChoices'),
                fn () => EntityChoiceResource::groupedByChoiceGroup($this->equipmentChoices)
            ),
            'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),

            // === INHERITED DATA (subclasses only) ===
            /**
             * Pre-resolved inheritance data from parent class.
             * Only present for subclasses - contains data they inherit from their base class.
             * Note: hit_points is NOT included here - use computed.hit_points instead (resolves inheritance automatically).
             *
             * @var array{hit_die: int|null, spellcasting_ability: array{id: int, code: string, name: string}|null, counters: array<GroupedCounterResource>|null, traits: array<TraitResource>|null, level_progression: array<ClassLevelProgressionResource>|null, equipment: array<EntityItemResource>|null, proficiencies: array<ProficiencyResource>|null, spell_slot_summary: array{has_spell_slots: bool, max_spell_level: int|null, available_levels: array<int>, has_cantrips: bool, caster_type: string|null}|null}|null
             */
            'inherited_data' => $this->when(
                ! $this->is_base_class && $this->relationLoaded('parentClass') && $this->parentClass,
                function () {
                    $parent = $this->parentClass;

                    return [
                        'hit_die' => $parent->hit_die,
                        // hit_points removed - use computed.hit_points instead (resolves inheritance)
                        'spellcasting_ability' => $parent->spellcasting_ability_id
                            ? new AbilityScoreResource($parent->spellcastingAbility)
                            : null,
                        'counters' => $parent->relationLoaded('counters')
                            ? GroupedCounterResource::fromCounters($parent->counters)
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
