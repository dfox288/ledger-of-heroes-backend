<?php

namespace App\Models\Concerns;

use App\Models\EntityLanguage;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasEntityLanguages
 *
 * Provides the polymorphic languages relationship for entities that grant languages.
 * Named HasEntityLanguages to avoid potential conflicts.
 * Used by: Race, Background
 */
trait HasEntityLanguages
{
    /**
     * Get all languages granted by this entity.
     */
    public function languages(): MorphMany
    {
        return $this->morphMany(EntityLanguage::class, 'reference');
    }
}
