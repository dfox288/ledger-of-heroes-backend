<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for class spell slot summary.
 *
 * Wraps the computed spell slot summary from CharacterClass::getSpellSlotSummaryAttribute()
 * with proper type information for OpenAPI documentation.
 *
 * Used by frontend to determine which spell slot columns to render without scanning all rows.
 *
 * @property bool $has_spell_slots Whether the class has any spell slots
 * @property int|null $max_spell_level Highest spell level the class can cast (1-9)
 * @property int[] $available_levels Array of available spell levels (e.g., [1, 2, 3, 4, 5])
 * @property bool $has_cantrips Whether the class knows cantrips
 * @property string|null $caster_type Caster type: full, half, third, other, or null
 */
class SpellSlotSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *   has_spell_slots: bool,
     *   max_spell_level: int|null,
     *   available_levels: int[],
     *   has_cantrips: bool,
     *   caster_type: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'has_spell_slots' => (bool) $this->resource['has_spell_slots'],
            'max_spell_level' => $this->resource['max_spell_level'],
            'available_levels' => (array) $this->resource['available_levels'],
            'has_cantrips' => (bool) $this->resource['has_cantrips'],
            'caster_type' => $this->resource['caster_type'],
        ];
    }
}
