<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Condition - Lookup model for D&D conditions (Blinded, Charmed, etc.).
 *
 * Table: conditions
 *
 * Inverse relationships show which entities interact with this condition:
 * - spells(): Spells that inflict this condition
 * - monsters(): Monsters that inflict this condition
 * - feats(): Feats that grant immunity to this condition
 * - races(): Races that grant immunity to this condition
 */
class Condition extends BaseModel
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get spells that inflict this condition
     *
     * Uses polymorphic many-to-many via entity_conditions table.
     * Only returns spells with effect_type = 'inflicts'.
     */
    public function spells(): MorphToMany
    {
        return $this->morphedByMany(
            Spell::class,
            'reference',
            'entity_conditions',
            'condition_id',
            'reference_id'
        )
            ->withPivot('effect_type', 'description')
            ->wherePivot('effect_type', 'inflicts');
    }

    /**
     * Get monsters that inflict this condition
     *
     * Uses polymorphic many-to-many via entity_conditions table.
     * Only returns monsters with effect_type = 'inflicts'.
     */
    public function monsters(): MorphToMany
    {
        return $this->morphedByMany(
            Monster::class,
            'reference',
            'entity_conditions',
            'condition_id',
            'reference_id'
        )
            ->withPivot('effect_type', 'description')
            ->wherePivot('effect_type', 'inflicts');
    }

    /**
     * Get feats that grant immunity to this condition.
     *
     * Uses polymorphic many-to-many via entity_conditions table.
     */
    public function feats(): MorphToMany
    {
        return $this->morphedByMany(
            Feat::class,
            'reference',
            'entity_conditions',
            'condition_id',
            'reference_id'
        )->withPivot('effect_type', 'description');
    }

    /**
     * Get races that grant immunity to this condition.
     *
     * Uses polymorphic many-to-many via entity_conditions table.
     */
    public function races(): MorphToMany
    {
        return $this->morphedByMany(
            Race::class,
            'reference',
            'entity_conditions',
            'condition_id',
            'reference_id'
        )->withPivot('effect_type', 'description');
    }
}
