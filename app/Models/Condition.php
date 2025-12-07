<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Condition - Lookup model for D&D conditions (Blinded, Charmed, etc.).
 *
 * Table: conditions
 *
 * Inverse relationships show which entities interact with this condition:
 * - spells(): Spells that inflict this condition (effect_type = 'inflicts')
 * - monsters(): Monsters that inflict this condition (effect_type = 'inflicts')
 * - feats(): Feats that interact with this condition (advantage, negates_disadvantage, etc.)
 * - races(): Races that interact with this condition (advantage on saves, etc.)
 */
class Condition extends BaseModel
{
    protected $fillable = [
        'name',
        'slug',
        'full_slug',
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
     * Get feats that interact with this condition.
     *
     * Uses polymorphic many-to-many via entity_conditions table.
     * Effect types include: advantage, disadvantage, negates_disadvantage.
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
     * Get races that interact with this condition.
     *
     * Uses polymorphic many-to-many via entity_conditions table.
     * Effect types include: advantage (on saving throws against this condition).
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
