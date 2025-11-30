<?php

namespace App\Models\Concerns;

use App\Models\EntityCondition;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasConditions
 *
 * Provides the polymorphic conditions relationship for entities that can grant condition immunities/resistances.
 * Used by: Race, Feat
 *
 * Note: Monster uses a different conditions relationship (MorphToMany to Condition model).
 */
trait HasConditions
{
    /**
     * Get all condition immunities/resistances for this entity.
     */
    public function conditions(): MorphMany
    {
        return $this->morphMany(EntityCondition::class, 'reference');
    }
}
