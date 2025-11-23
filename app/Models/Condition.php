<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

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
}
