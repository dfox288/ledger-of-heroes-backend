<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntityChoice;
use App\Models\Proficiency;
use App\Models\Skill;
use App\Services\Parsers\Traits\ParsesChoices;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing proficiencies (skills, weapons, armor, tools, etc.).
 *
 * Handles the common pattern of:
 * 1. Clear existing proficiencies and proficiency choices
 * 2. Create Proficiency records for fixed proficiencies
 * 3. Create EntityChoice records for proficiency choices
 */
trait ImportsProficiencies
{
    use ParsesChoices;

    /**
     * Import proficiencies for an entity.
     *
     * - Fixed proficiencies go to entity_proficiencies table
     * - Choice-based proficiencies go to entity_choices table
     *
     * @param  Model  $entity  The entity (Race, Background, Class, Item, etc.)
     * @param  array  $proficienciesData  Array of proficiency data
     * @param  bool  $grants  Whether the entity grants proficiency (default: true for races/classes/backgrounds)
     */
    protected function importEntityProficiencies(Model $entity, array $proficienciesData, bool $grants = true): void
    {
        // Clear existing proficiencies for this entity
        $entity->proficiencies()->delete();

        // Clear existing proficiency choices for this entity
        EntityChoice::where('reference_type', get_class($entity))
            ->where('reference_id', $entity->id)
            ->where('choice_type', 'proficiency')
            ->delete();

        // Track choice index for unique group names when no group is provided
        $choiceIndex = 0;

        foreach ($proficienciesData as $profData) {
            $isChoice = $profData['is_choice'] ?? false;

            if ($isChoice) {
                // Handle proficiency choices
                $this->importProficiencyChoice($entity, $profData, $choiceIndex);
            } else {
                // Handle fixed proficiencies
                $this->importFixedProficiency($entity, $profData, $grants);
            }
        }
    }

    /**
     * Import a fixed proficiency.
     */
    private function importFixedProficiency(Model $entity, array $profData, bool $defaultGrants): void
    {
        $proficiency = [
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'proficiency_type' => $profData['type'],
            'proficiency_name' => $profData['name'],
            'proficiency_type_id' => $profData['proficiency_type_id'] ?? null,
            'proficiency_subcategory' => $profData['proficiency_subcategory'] ?? null,
            'grants' => $profData['grants'] ?? $defaultGrants,
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

    /**
     * Import a proficiency choice.
     */
    private function importProficiencyChoice(Model $entity, array $profData, int &$choiceIndex): void
    {
        $proficiencyType = $profData['type'];
        $choiceGroup = $profData['choice_group'] ?? null;
        $choiceOption = $profData['choice_option'] ?? null;
        $quantity = $profData['quantity'] ?? 1;
        $name = $profData['name'] ?? null;
        $proficiencySubcategory = $profData['proficiency_subcategory'] ?? null;

        // For category-based choices (e.g., "artisan tools", "musical instruments")
        // These have a subcategory constraint - treat as unrestricted within that subcategory
        if (! empty($proficiencySubcategory)) {
            $groupName = $choiceGroup ?? $proficiencyType.'_choice_'.++$choiceIndex;

            $this->createProficiencyChoice(
                referenceType: get_class($entity),
                referenceId: $entity->id,
                choiceGroup: $groupName,
                proficiencyType: $proficiencyType,
                quantity: $quantity,
                levelGranted: 1,
                constraints: ['subcategory' => $proficiencySubcategory]
            );

            return;
        }

        // For unrestricted choices (no specific name, just type and maybe quantity)
        if ($choiceGroup === null && $name === null) {
            $choiceIndex++;
            $groupName = $proficiencyType.'_choice_'.$choiceIndex;

            $this->createProficiencyChoice(
                referenceType: get_class($entity),
                referenceId: $entity->id,
                choiceGroup: $groupName,
                proficiencyType: $proficiencyType,
                quantity: $quantity,
                levelGranted: 1,
                constraints: null
            );

            return;
        }

        // For restricted choices (specific options within a group)
        if ($choiceGroup !== null && $name !== null) {
            // Resolve target slug based on proficiency type
            $targetType = 'proficiency_type';
            $targetSlug = strtolower(str_replace(' ', '-', $name));

            // For skills, use skill slug format
            if ($proficiencyType === 'skill') {
                $skill = Skill::where('name', $name)->first();
                if ($skill) {
                    $targetType = 'skill';
                    $targetSlug = $skill->slug ?? strtolower(str_replace(' ', '-', $name));
                }
            }

            $this->createRestrictedProficiencyChoice(
                referenceType: get_class($entity),
                referenceId: $entity->id,
                choiceGroup: $choiceGroup,
                proficiencyType: $proficiencyType,
                targetType: $targetType,
                targetSlug: $targetSlug,
                choiceOption: $choiceOption ?? 1,
                quantity: $quantity,
                levelGranted: 1
            );

            return;
        }

        // Edge case: has name but no choice_group (treat as fixed proficiency)
        // This shouldn't happen with proper parser data, but handle gracefully
        if ($name !== null && $choiceGroup === null) {
            $proficiency = [
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'proficiency_type' => $proficiencyType,
                'proficiency_name' => $name,
                'proficiency_type_id' => $profData['proficiency_type_id'] ?? null,
                'grants' => $profData['grants'] ?? true,
            ];

            if ($proficiencyType === 'skill') {
                $skill = Skill::where('name', $name)->first();
                if ($skill) {
                    $proficiency['skill_id'] = $skill->id;
                }
            }

            Proficiency::create($proficiency);
        }
    }
}
