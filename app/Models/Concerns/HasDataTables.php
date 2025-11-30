<?php

namespace App\Models\Concerns;

use App\Models\EntityDataTable;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasDataTables
 *
 * Provides the polymorphic dataTables relationship for entities with structured data tables.
 * Used by: Item, Spell, ClassFeature, CharacterTrait
 */
trait HasDataTables
{
    /**
     * Get all data tables for this entity (damage scaling, random tables, etc.).
     */
    public function dataTables(): MorphMany
    {
        return $this->morphMany(EntityDataTable::class, 'reference');
    }
}
