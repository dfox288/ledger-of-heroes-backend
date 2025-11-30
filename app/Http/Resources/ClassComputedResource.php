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
             */
            'hit_points' => $this->when(
                $this->hit_points !== null,
                fn () => new HitPointsResource($this->hit_points)
            ),

            /**
             * Spell slot summary for frontend column visibility optimization.
             */
            'spell_slot_summary' => $this->when(
                $this->relationLoaded('levelProgression') && $this->spell_slot_summary !== null,
                fn () => new SpellSlotSummaryResource($this->spell_slot_summary)
            ),

            /**
             * Section counts for lazy-loading accordions in UI.
             */
            'section_counts' => $this->when(
                $this->features_count !== null || $this->proficiencies_count !== null,
                fn () => new SectionCountsResource([
                    'features' => $this->features_count,
                    'multiclass_features' => $this->multiclass_features_count,
                    'proficiencies' => $this->proficiencies_count,
                    'traits' => $this->traits_count,
                    'subclasses' => $this->subclasses_count,
                    'spells' => $this->spells_count,
                    'counters' => $this->counters_count,
                    'optional_features' => $this->optional_features_count,
                ])
            ),

            /**
             * Pre-computed progression table for detail views.
             */
            'progression_table' => $this->when(
                $request->routeIs('classes.show') || $request->routeIs('classes.progression'),
                function () {
                    $generator = app(ClassProgressionTableGenerator::class);

                    return new ProgressionTableResource($generator->generate($this->resource));
                }
            ),
        ];
    }
}
