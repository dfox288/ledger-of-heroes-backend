<?php

namespace App\Services\Importers\Concerns;

use App\Models\Proficiency;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing proficiencies (skills, weapons, armor, tools, etc.).
 *
 * Handles the common pattern of:
 * 1. Clear existing proficiencies
 * 2. Create new proficiencies with polymorphic reference
 * 3. Link skill_id for skill proficiencies
 */
trait ImportsProficiencies
{
    /**
     * Import proficiencies for an entity.
     *
     * Clears existing proficiencies and creates new Proficiency records.
     *
     * @param  Model  $entity  The entity (Race, Background, Class, Item, etc.)
     * @param  array  $proficienciesData  Array of proficiency data
     * @param  bool  $grants  Whether the entity grants proficiency (default: true for races/classes/backgrounds)
     */
    protected function importEntityProficiencies(Model $entity, array $proficienciesData, bool $grants = true): void
    {
        // Clear existing proficiencies for this entity
        $entity->proficiencies()->delete();

        foreach ($proficienciesData as $profData) {
            $proficiency = [
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'proficiency_type' => $profData['type'],
                'proficiency_name' => $profData['name'], // Always store name as fallback
                'proficiency_type_id' => $profData['proficiency_type_id'] ?? null, // From parser
                'grants' => $profData['grants'] ?? $grants, // Use provided or default
                'is_choice' => $profData['is_choice'] ?? false, // Choice-based proficiency
                'choice_group' => $profData['choice_group'] ?? null, // Group related choices together
                'choice_option' => $profData['choice_option'] ?? null, // Option number within group
                'quantity' => $profData['quantity'] ?? null, // Number of choices (only set for first in group)
            ];

            // Handle skill proficiencies - link to skills table
            if ($profData['type'] === 'skill' && ! empty($profData['name'])) {
                $skill = Skill::where('name', $profData['name'])->first();
                if ($skill) {
                    $proficiency['skill_id'] = $skill->id;
                }
            }

            Proficiency::create($proficiency);
        }
    }
}
