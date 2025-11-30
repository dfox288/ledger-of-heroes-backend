<?php

namespace App\Models\Concerns;

use App\Models\EntityPrerequisite;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasPrerequisites
 *
 * Provides the polymorphic prerequisites relationship for entities with prerequisites.
 * Used by: Item, Feat, OptionalFeature
 */
trait HasPrerequisites
{
    /**
     * Get all prerequisites for this entity.
     */
    public function prerequisites(): MorphMany
    {
        return $this->morphMany(EntityPrerequisite::class, 'reference');
    }
}
