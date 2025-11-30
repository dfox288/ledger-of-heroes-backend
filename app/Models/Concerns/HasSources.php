<?php

namespace App\Models\Concerns;

use App\Models\EntitySource;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasSources
 *
 * Provides the polymorphic sources relationship for entities that can have source citations.
 * Used by: Monster, CharacterClass, Race, Item, Background, Feat, Spell, OptionalFeature
 */
trait HasSources
{
    /**
     * Get all source citations for this entity.
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference');
    }
}
