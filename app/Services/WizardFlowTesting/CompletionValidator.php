<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

/**
 * Validates that a character is complete after the wizard flow.
 *
 * This is a critical check - level-up and other gameplay features
 * require is_complete = true.
 */
class CompletionValidator
{
    /**
     * Validate that the character is complete.
     */
    public function validate(array $snapshot): ValidationResult
    {
        $errors = [];
        $warnings = [];

        $character = $snapshot['character']['data'] ?? [];
        $isComplete = $character['is_complete'] ?? false;

        if (! $isComplete) {
            $errors[] = 'Character is not complete after wizard flow';

            // Check validation status for specific missing fields
            $validationStatus = $character['validation_status'] ?? [];
            foreach ($validationStatus as $field => $isValid) {
                if (! $isValid) {
                    $errors[] = "Missing required field: {$field}";
                }
            }

            // Check for pending required choices with remaining > 0
            $pendingChoices = $snapshot['pending_choices']['data']['choices'] ?? [];
            $requiredPending = array_filter(
                $pendingChoices,
                fn ($c) => ($c['required'] ?? false) === true && ($c['remaining'] ?? 0) > 0
            );

            if (! empty($requiredPending)) {
                $pendingTypes = array_unique(array_column($requiredPending, 'type'));
                $errors[] = 'Unresolved required choices: '.implode(', ', $pendingTypes);
            }

            return ValidationResult::fail($errors, 'character_incomplete');
        }

        // Character is complete - check for optional warnings
        $pendingChoices = $snapshot['pending_choices']['data']['choices'] ?? [];
        $optionalPending = array_filter(
            $pendingChoices,
            fn ($c) => ($c['required'] ?? false) === false && ($c['remaining'] ?? 0) > 0
        );

        if (! empty($optionalPending)) {
            $optionalTypes = array_unique(array_column($optionalPending, 'type'));
            $warnings[] = 'Optional choices remaining: '.implode(', ', $optionalTypes);
        }

        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }
}
