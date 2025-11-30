<?php

namespace App\Models\Concerns;

use App\Models\EntitySense;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasSenses
 *
 * Provides the polymorphic senses relationship for entities with special senses.
 * Used by: Monster, Race
 */
trait HasSenses
{
    /**
     * Get all special senses for this entity (darkvision, blindsight, etc.).
     */
    public function senses(): MorphMany
    {
        return $this->morphMany(EntitySense::class, 'reference');
    }
}
