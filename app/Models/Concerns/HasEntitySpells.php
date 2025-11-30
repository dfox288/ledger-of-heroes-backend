<?php

namespace App\Models\Concerns;

use App\Models\EntitySpell;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasEntitySpells
 *
 * Provides the polymorphic spells relationship for entities that grant innate spells.
 * This is for entities referencing spells via the entity_spells pivot table (MorphMany to EntitySpell).
 * Used by: Race, Feat
 *
 * Note: This is different from the MorphToMany relationship used by Monster and Item
 * which directly relate to Spell model with pivot data.
 */
trait HasEntitySpells
{
    /**
     * Get all innate spells granted by this entity.
     */
    public function spells(): MorphMany
    {
        return $this->morphMany(EntitySpell::class, 'reference', 'reference_type', 'reference_id');
    }
}
