<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing character traits (racial traits, background features, etc.).
 *
 * Handles the common pattern of:
 * 1. Clear existing traits
 * 2. Create new traits with polymorphic reference
 */
trait ImportsTraits
{
    /**
     * Import traits for an entity.
     *
     * Clears existing traits and creates new CharacterTrait records.
     *
     * @param  Model  $entity  The entity (Race, Background, Class, etc.)
     * @param  array  $traitsData  Array of trait data
     * @return array Created CharacterTrait models (for further processing)
     */
    protected function importEntityTraits(Model $entity, array $traitsData): array
    {
        // Clear existing traits for this entity
        $entity->traits()->delete();

        $createdTraits = [];

        foreach ($traitsData as $traitData) {
            $trait = CharacterTrait::create([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'name' => $traitData['name'],
                'category' => $traitData['category'] ?? null,
                'description' => $traitData['description'],
                'sort_order' => $traitData['sort_order'] ?? 0,
            ]);

            $createdTraits[] = $trait;
        }

        return $createdTraits;
    }
}
