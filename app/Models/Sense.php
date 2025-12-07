<?php

namespace App\Models;

/**
 * Lookup model for D&D 5e sense types.
 *
 * The 4 core sense types:
 * - Darkvision: See in darkness as dim light
 * - Blindsight: Perceive surroundings without sight
 * - Tremorsense: Detect vibrations through ground
 * - Truesight: See through illusions, invisibility, into Ethereal
 */
class Sense extends BaseModel
{
    protected $fillable = [
        'slug',
        'full_slug',
        'name',
    ];

    /**
     * Get all monsters with this sense type.
     */
    public function monsters()
    {
        return $this->morphedByMany(
            Monster::class,
            'reference',
            'entity_senses',
            'sense_id',
            'reference_id'
        )->withPivot(['range_feet', 'is_limited', 'notes']);
    }

    /**
     * Get all races with this sense type.
     */
    public function races()
    {
        return $this->morphedByMany(
            Race::class,
            'reference',
            'entity_senses',
            'sense_id',
            'reference_id'
        )->withPivot(['range_feet', 'is_limited', 'notes']);
    }
}
