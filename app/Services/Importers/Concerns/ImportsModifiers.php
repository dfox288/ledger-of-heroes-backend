<?php

namespace App\Services\Importers\Concerns;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\EntityChoice;
use App\Models\Modifier;
use App\Models\Skill;
use App\Services\Parsers\Traits\ParsesChoices;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing modifiers (ability scores, skills, damage resistances, etc.).
 *
 * Handles the common pattern of:
 * 1. Clear existing modifiers
 * 2. Create/update modifiers with polymorphic reference using updateOrCreate
 * 3. Link to ability scores, skills, or damage types as needed
 * 4. Create EntityChoice records for ability score choices
 *
 * Uses updateOrCreate to prevent duplicates on re-import.
 */
trait ImportsModifiers
{
    use ParsesChoices;

    /**
     * Import modifiers for an entity.
     *
     * - Fixed modifiers go to entity_modifiers table
     * - Choice modifiers (ability score choices) go to entity_choices table
     *
     * @param  Model  $entity  The entity (Race, Item, Feat, etc.)
     * @param  array  $modifiersData  Array of modifier data
     */
    protected function importEntityModifiers(Model $entity, array $modifiersData): void
    {
        // Clear existing modifiers for this entity
        $entity->modifiers()->delete();

        // Clear existing ability score choices for this entity
        EntityChoice::where('reference_type', get_class($entity))
            ->where('reference_id', $entity->id)
            ->where('choice_type', 'ability_score')
            ->delete();

        // Track choice index for unique group names
        $abilityChoiceIndex = 0;

        foreach ($modifiersData as $modData) {
            $isChoice = $modData['is_choice'] ?? false;

            // Handle ability score choices
            if ($isChoice && ($modData['modifier_category'] ?? $modData['category'] ?? null) === 'ability_score') {
                $abilityChoiceIndex++;
                $quantity = $modData['choice_count'] ?? 1;
                $constraint = $modData['choice_constraint'] ?? 'different';
                $description = $modData['condition'] ?? null;

                // Store the value in constraints for the handler to use
                $constraints = ['value' => $modData['value'] ?? '+1'];

                $this->createAbilityScoreChoice(
                    referenceType: get_class($entity),
                    referenceId: $entity->id,
                    choiceGroup: 'ability_score_choice_'.$abilityChoiceIndex,
                    quantity: $quantity,
                    constraint: $constraint,
                    levelGranted: $modData['level'] ?? 1,
                    constraints: $constraints
                );

                continue;
            }

            // Handle fixed modifiers
            $this->importFixedModifier($entity, $modData);
        }
    }

    /**
     * Import a fixed modifier (no choice involved).
     */
    private function importFixedModifier(Model $entity, array $modData): void
    {
        // Resolve skill_id from skill_name if needed
        $skillId = $modData['skill_id'] ?? null;
        if (! $skillId && isset($modData['skill_name'])) {
            $skill = Skill::where('name', $modData['skill_name'])->first();
            $skillId = $skill?->id;
        }

        // Resolve ability_score_id from ability_score_code if needed
        $abilityScoreId = $modData['ability_score_id'] ?? null;
        if (! $abilityScoreId && isset($modData['ability_score_code'])) {
            $ability = AbilityScore::where('code', $modData['ability_score_code'])->first();
            $abilityScoreId = $ability?->id;
        }

        // Resolve damage_type_id from damage_type_name or damage_type_code if needed
        $damageTypeId = $modData['damage_type_id'] ?? null;
        if (! $damageTypeId && isset($modData['damage_type_name'])) {
            // Prefer lookup by name (matches seeder data exactly)
            $damageType = DamageType::where('name', $modData['damage_type_name'])->first();
            $damageTypeId = $damageType?->id;
        } elseif (! $damageTypeId && isset($modData['damage_type_code'])) {
            // Fallback to lookup by code (for backward compatibility)
            $damageType = DamageType::where('code', $modData['damage_type_code'])->first();
            $damageTypeId = $damageType?->id;
        }

        // Build unique keys for updateOrCreate
        $uniqueKeys = [
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'modifier_category' => $modData['modifier_category'] ?? $modData['category'],
            'level' => $modData['level'] ?? null,
            'ability_score_id' => $abilityScoreId,
            'skill_id' => $skillId,
            'damage_type_id' => $damageTypeId,
        ];

        // Build values to set/update
        $values = [
            'value' => $modData['value'],
            'condition' => $modData['condition'] ?? null,
        ];

        // Use updateOrCreate to prevent duplicates on re-import
        Modifier::updateOrCreate($uniqueKeys, array_merge($uniqueKeys, $values));
    }

    /**
     * Import a single modifier with deduplication.
     *
     * Convenience method for importing individual modifiers.
     *
     * @param  Model  $entity  The entity (Class, Race, etc.)
     * @param  string  $category  Modifier category (ability_score, skill, speed, etc.)
     * @param  array  $data  Modifier data (value, condition, level, etc.)
     */
    protected function importModifier(Model $entity, string $category, array $data): Modifier
    {
        $uniqueKeys = [
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'modifier_category' => $category,
            'level' => $data['level'] ?? null,
            'ability_score_id' => $data['ability_score_id'] ?? null,
            'skill_id' => $data['skill_id'] ?? null,
            'damage_type_id' => $data['damage_type_id'] ?? null,
        ];

        // Filter out choice-related fields that no longer exist
        $values = array_intersect_key($data, array_flip([
            'value', 'condition',
        ]));

        return Modifier::updateOrCreate($uniqueKeys, array_merge($uniqueKeys, $values));
    }

    /**
     * Import ASI choice for a class (standard ASI at levels 4, 8, 12, 16, 19).
     *
     * Creates an EntityChoice record for ability score improvement.
     */
    protected function importAsiModifier(Model $entity, int $level, string $value = '+2'): EntityChoice
    {
        $description = 'Choose one ability score to increase by 2, or two ability scores to increase by 1 each';

        return $this->createAbilityScoreChoice(
            referenceType: get_class($entity),
            referenceId: $entity->id,
            choiceGroup: 'asi_level_'.$level,
            quantity: 2,  // Choose 2 abilities (for +1 each) or 1 (for +2)
            constraint: 'different',
            levelGranted: $level,
            constraints: ['value' => $value, 'description' => $description]
        );
    }
}
