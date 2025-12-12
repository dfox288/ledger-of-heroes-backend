<?php

namespace App\Services\Parsers\Traits;

use App\Models\EntityChoice;

/**
 * Trait for parsers that need to create entity choice records.
 *
 * Provides helper methods to create choices in the unified entity_choices table.
 */
trait ParsesChoices
{
    /**
     * Create an unrestricted language choice.
     */
    protected function createLanguageChoice(
        string $referenceType,
        int $referenceId,
        string $choiceGroup,
        int $quantity = 1,
        int $levelGranted = 1,
        ?array $constraints = null
    ): EntityChoice {
        return EntityChoice::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'choice_type' => 'language',
            'choice_group' => $choiceGroup,
            'quantity' => $quantity,
            'level_granted' => $levelGranted,
            'constraints' => $constraints,
            'is_required' => true,
        ]);
    }

    /**
     * Create a restricted language choice option.
     */
    protected function createRestrictedLanguageChoice(
        string $referenceType,
        int $referenceId,
        string $choiceGroup,
        string $languageSlug,
        int $choiceOption,
        int $quantity = 1,
        int $levelGranted = 1
    ): EntityChoice {
        return EntityChoice::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'choice_type' => 'language',
            'choice_group' => $choiceGroup,
            'quantity' => $quantity,
            'choice_option' => $choiceOption,
            'target_type' => 'language',
            'target_slug' => $languageSlug,
            'level_granted' => $levelGranted,
            'is_required' => true,
        ]);
    }

    /**
     * Create a spell choice (cantrip or leveled).
     */
    protected function createSpellChoice(
        string $referenceType,
        int $referenceId,
        string $choiceGroup,
        int $quantity = 1,
        int $maxLevel = 0,
        ?string $classSlug = null,
        ?string $schoolSlug = null,
        int $levelGranted = 1,
        ?array $constraints = null
    ): EntityChoice {
        return EntityChoice::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'choice_type' => 'spell',
            'choice_group' => $choiceGroup,
            'quantity' => $quantity,
            'spell_max_level' => $maxLevel,
            'spell_list_slug' => $classSlug,
            'spell_school_slug' => $schoolSlug,
            'level_granted' => $levelGranted,
            'constraints' => $constraints,
            'is_required' => true,
        ]);
    }

    /**
     * Create a restricted spell choice option (specific spell).
     */
    protected function createRestrictedSpellChoice(
        string $referenceType,
        int $referenceId,
        string $choiceGroup,
        string $spellSlug,
        int $choiceOption,
        int $quantity = 1,
        int $levelGranted = 1
    ): EntityChoice {
        return EntityChoice::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'choice_type' => 'spell',
            'choice_group' => $choiceGroup,
            'quantity' => $quantity,
            'choice_option' => $choiceOption,
            'target_type' => 'spell',
            'target_slug' => $spellSlug,
            'level_granted' => $levelGranted,
            'is_required' => true,
        ]);
    }

    /**
     * Create an unrestricted proficiency choice.
     */
    protected function createProficiencyChoice(
        string $referenceType,
        int $referenceId,
        string $choiceGroup,
        string $proficiencyType,
        int $quantity = 1,
        int $levelGranted = 1,
        ?array $constraints = null
    ): EntityChoice {
        return EntityChoice::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'choice_type' => 'proficiency',
            'choice_group' => $choiceGroup,
            'quantity' => $quantity,
            'proficiency_type' => $proficiencyType,
            'level_granted' => $levelGranted,
            'constraints' => $constraints,
            'is_required' => true,
        ]);
    }

    /**
     * Create a restricted proficiency choice option.
     */
    protected function createRestrictedProficiencyChoice(
        string $referenceType,
        int $referenceId,
        string $choiceGroup,
        string $proficiencyType,
        string $targetType,
        string $targetSlug,
        int $choiceOption,
        int $quantity = 1,
        int $levelGranted = 1
    ): EntityChoice {
        return EntityChoice::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'choice_type' => 'proficiency',
            'choice_group' => $choiceGroup,
            'quantity' => $quantity,
            'choice_option' => $choiceOption,
            'proficiency_type' => $proficiencyType,
            'target_type' => $targetType,
            'target_slug' => $targetSlug,
            'level_granted' => $levelGranted,
            'is_required' => true,
        ]);
    }

    /**
     * Create an ability score choice.
     */
    protected function createAbilityScoreChoice(
        string $referenceType,
        int $referenceId,
        string $choiceGroup,
        int $quantity = 1,
        ?string $constraint = 'different',
        int $levelGranted = 1,
        ?array $constraints = null
    ): EntityChoice {
        return EntityChoice::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'choice_type' => 'ability_score',
            'choice_group' => $choiceGroup,
            'quantity' => $quantity,
            'constraint' => $constraint,
            'level_granted' => $levelGranted,
            'constraints' => $constraints,
            'is_required' => true,
        ]);
    }

    /**
     * Create an equipment choice option.
     *
     * @param  string|null  $itemSlug  Specific item slug (for single item options)
     * @param  string|null  $categorySlug  Category slug (for "any X weapon" options)
     * @param  array|null  $constraints  Additional constraints including items array for bundles
     */
    protected function createEquipmentChoice(
        string $referenceType,
        int $referenceId,
        string $choiceGroup,
        int $choiceOption,
        ?string $itemSlug = null,
        ?string $categorySlug = null,
        ?string $description = null,
        int $levelGranted = 1,
        ?array $constraints = null
    ): EntityChoice {
        $targetType = null;
        $targetSlug = null;

        if ($itemSlug) {
            $targetType = 'item';
            $targetSlug = $itemSlug;
        } elseif ($categorySlug) {
            $targetType = 'proficiency_type';
            $targetSlug = $categorySlug;
        }

        return EntityChoice::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'choice_type' => 'equipment',
            'choice_group' => $choiceGroup,
            'quantity' => 1,
            'choice_option' => $choiceOption,
            'target_type' => $targetType,
            'target_slug' => $targetSlug,
            'description' => $description,
            'level_granted' => $levelGranted,
            'constraints' => $constraints,
            'is_required' => true,
        ]);
    }
}
