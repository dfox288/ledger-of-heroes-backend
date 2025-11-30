<?php

namespace App\Models\Concerns;

use App\Models\Modifier;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasModifiers
 *
 * Provides the polymorphic modifiers relationship for entities that can grant stat modifiers.
 * Used by: Monster, CharacterClass, Race, Item, Feat
 */
trait HasModifiers
{
    /**
     * Get all stat modifiers for this entity.
     */
    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }
}
