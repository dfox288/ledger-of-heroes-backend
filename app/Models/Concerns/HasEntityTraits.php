<?php

namespace App\Models\Concerns;

use App\Models\CharacterTrait;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasEntityTraits
 *
 * Provides the polymorphic traits relationship for entities that can have character traits.
 * Named HasEntityTraits to avoid conflict with PHP's trait keyword.
 * Used by: CharacterClass, Race, Background
 *
 * Note: Monster uses entityTraits() method directly rather than this trait due to naming conflicts.
 */
trait HasEntityTraits
{
    /**
     * Get all character traits for this entity.
     */
    public function traits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference');
    }
}
