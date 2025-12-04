<?php

namespace App\Services;

use App\DTOs\RequirementCheck;
use App\DTOs\ValidationResult;
use App\Models\Character;
use App\Models\CharacterClass;

class MulticlassValidationService
{
    /**
     * Ability score ID to column name mapping.
     */
    private const ABILITY_MAP = [
        1 => 'strength',     // STR
        2 => 'dexterity',    // DEX
        3 => 'constitution', // CON
        4 => 'intelligence', // INT
        5 => 'wisdom',       // WIS
        6 => 'charisma',     // CHA
    ];

    /**
     * Check if a character can add a new class.
     * Must meet requirements for ALL current classes AND the new class.
     */
    public function canAddClass(
        Character $character,
        CharacterClass $newClass,
        bool $force = false
    ): ValidationResult {
        if ($force) {
            return ValidationResult::success();
        }

        $errors = [];

        // Check requirements for all current classes
        foreach ($character->characterClasses as $characterClass) {
            $check = $this->meetsRequirements($character, $characterClass->characterClass);
            if (! $check->met) {
                $errors[] = "Does not meet {$check->className} multiclass requirements: ".
                    implode(', ', $check->failedRequirements);
            }
        }

        // Check requirements for the new class
        $newClassCheck = $this->meetsRequirements($character, $newClass);
        if (! $newClassCheck->met) {
            $errors[] = "Does not meet {$newClassCheck->className} multiclass requirements: ".
                implode(', ', $newClassCheck->failedRequirements);
        }

        if (! empty($errors)) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success();
    }

    /**
     * Check if character meets a specific class's multiclass requirements.
     */
    public function meetsRequirements(
        Character $character,
        CharacterClass $class
    ): RequirementCheck {
        $requirements = $class->multiclassRequirements;

        if ($requirements->isEmpty()) {
            return new RequirementCheck(met: true, className: $class->name);
        }

        // Separate OR requirements (is_choice = true) from AND requirements (is_choice = false)
        $orRequirements = $requirements->where('is_choice', true);
        $andRequirements = $requirements->where('is_choice', false);

        $failedRequirements = [];

        // For AND requirements, ALL must be met
        foreach ($andRequirements as $req) {
            if (! $this->checkRequirement($character, $req)) {
                $failedRequirements[] = $req->proficiency_name;
            }
        }

        // For OR requirements, at least ONE must be met
        if ($orRequirements->isNotEmpty()) {
            $anyOrMet = false;
            $orNames = [];
            foreach ($orRequirements as $req) {
                $orNames[] = $req->proficiency_name;
                if ($this->checkRequirement($character, $req)) {
                    $anyOrMet = true;
                    break;
                }
            }
            if (! $anyOrMet) {
                $failedRequirements[] = implode(' or ', $orNames);
            }
        }

        return new RequirementCheck(
            met: empty($failedRequirements),
            className: $class->name,
            failedRequirements: $failedRequirements,
        );
    }

    /**
     * Check if character meets a single requirement.
     */
    private function checkRequirement(Character $character, $requirement): bool
    {
        $abilityScoreId = $requirement->ability_score_id;
        $minimumValue = $requirement->quantity;

        $abilityName = self::ABILITY_MAP[$abilityScoreId] ?? null;
        if (! $abilityName) {
            return true; // Unknown ability, skip
        }

        $characterValue = $character->{$abilityName} ?? 0;

        return $characterValue >= $minimumValue;
    }
}
