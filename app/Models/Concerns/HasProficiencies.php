<?php

namespace App\Models\Concerns;

use App\Models\Proficiency;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasProficiencies
 *
 * Provides the polymorphic proficiencies relationship for entities that grant proficiencies.
 * Used by: CharacterClass, Race, Background, Feat, Item
 */
trait HasProficiencies
{
    /**
     * Get all proficiencies granted by this entity.
     */
    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }
}
