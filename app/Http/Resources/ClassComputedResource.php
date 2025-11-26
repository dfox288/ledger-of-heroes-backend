<?php

namespace App\Http\Resources;

use App\Services\ClassProgressionTableGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for computed/aggregated class data.
 *
 * Separates derived data from base entity fields for API clarity.
 * Only included on show endpoint responses.
 */
class ClassComputedResource extends JsonResource
{
    /**
     * Transform computed/aggregated class data.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Pre-computed hit points using D&D 5e formulas.
             *
             * @var array{hit_die: string, hit_die_numeric: int, first_level: array{value: int, description: string}, higher_levels: array{roll: string, average: int, description: string}}|null
             */
            'hit_points' => $this->hit_points,

            /**
             * Spell slot summary for frontend column visibility optimization.
             *
             * @var array{has_spell_slots: bool, max_spell_level: int|null, available_levels: array<int>, has_cantrips: bool, caster_type: string|null}|null
             */
            'spell_slot_summary' => $this->when(
                $this->relationLoaded('levelProgression'),
                fn () => $this->spell_slot_summary
            ),

            /**
             * Section counts for lazy-loading accordions in UI.
             *
             * @var array{features: int|null, proficiencies: int|null, traits: int|null, subclasses: int|null, spells: int|null, counters: int|null, optional_features: int|null}|null
             */
            'section_counts' => $this->when(
                $this->features_count !== null || $this->proficiencies_count !== null,
                fn () => [
                    'features' => $this->features_count,
                    'proficiencies' => $this->proficiencies_count,
                    'traits' => $this->traits_count,
                    'subclasses' => $this->subclasses_count,
                    'spells' => $this->spells_count,
                    'counters' => $this->counters_count,
                    'optional_features' => $this->optional_features_count,
                ]
            ),

            /**
             * Pre-computed progression table for detail views.
             *
             * @var array{columns: array<array{key: string, label: string, type: string}>, rows: array<array<string, mixed>>}|null
             */
            'progression_table' => $this->when(
                $request->routeIs('classes.show') || $request->routeIs('classes.progression'),
                function () {
                    $generator = app(ClassProgressionTableGenerator::class);

                    return $generator->generate($this->resource);
                }
            ),
        ];
    }
}
