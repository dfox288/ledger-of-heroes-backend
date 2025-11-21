<?php

namespace App\Services\Importers\Concerns;

use App\Models\Modifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing modifiers (ability scores, skills, damage resistances, etc.).
 *
 * Handles the common pattern of:
 * 1. Clear existing modifiers
 * 2. Create new modifiers with polymorphic reference
 * 3. Link to ability scores, skills, or damage types as needed
 */
trait ImportsModifiers
{
    /**
     * Import modifiers for an entity.
     *
     * Clears existing modifiers and creates new Modifier records.
     *
     * @param  Model  $entity  The entity (Race, Item, Feat, etc.)
     * @param  array  $modifiersData  Array of modifier data
     */
    protected function importEntityModifiers(Model $entity, array $modifiersData): void
    {
        // Clear existing modifiers for this entity
        $entity->modifiers()->delete();

        foreach ($modifiersData as $modData) {
            $modifier = [
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'modifier_category' => $modData['category'],
                'value' => $modData['value'],
                'ability_score_id' => $modData['ability_score_id'] ?? null,
                'skill_id' => $modData['skill_id'] ?? null,
                'damage_type_id' => $modData['damage_type_id'] ?? null,
                'is_choice' => $modData['is_choice'] ?? false,
                'choice_count' => $modData['choice_count'] ?? null,
                'choice_constraint' => $modData['choice_constraint'] ?? null,
            ];

            Modifier::create($modifier);
        }
    }
}
