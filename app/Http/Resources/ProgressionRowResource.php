<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for a single row in a class progression table.
 *
 * Rows contain dynamic columns based on the class (spell slots, counters, etc.).
 * The level, proficiency_bonus, and features columns are always present.
 * Additional columns depend on the class characteristics.
 *
 * @property int $level Character level (1-20)
 * @property string $proficiency_bonus Formatted bonus (e.g., "+2", "+3")
 * @property string $features Comma-separated feature names for this level
 */
class ProgressionRowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Returns a row object with level, proficiency_bonus, features,
     * and any class-specific columns (counters, spell slots, etc.).
     *
     * @return array{
     *   level: int,
     *   proficiency_bonus: string,
     *   features: string,
     *   sneak_attack?: string,
     *   ki_points?: int,
     *   martial_arts?: string,
     *   rage_damage?: string,
     *   rages?: int,
     *   cantrips_known?: int,
     *   spell_slots_1st?: int,
     *   spell_slots_2nd?: int,
     *   spell_slots_3rd?: int,
     *   spell_slots_4th?: int,
     *   spell_slots_5th?: int,
     *   spell_slots_6th?: int,
     *   spell_slots_7th?: int,
     *   spell_slots_8th?: int,
     *   spell_slots_9th?: int
     * }
     */
    public function toArray(Request $request): array
    {
        // Pass through all row data as-is (keys vary by class)
        return $this->resource;
    }
}
