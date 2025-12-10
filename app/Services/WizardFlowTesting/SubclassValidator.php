<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

use App\Models\CharacterClass;

/**
 * Validates that subclass features are properly assigned after subclass selection.
 * This catches the bug where subclass features marked as is_optional are not granted.
 */
class SubclassValidator
{
    /**
     * Validate that subclass features were assigned after subclass selection.
     *
     * @param  array  $snapshotAfter  State snapshot after subclass selection
     * @param  CharacterClass  $subclass  The selected subclass
     * @param  int  $characterLevel  The character's level in the parent class
     */
    public function validateSubclassFeatures(
        array $snapshotAfter,
        CharacterClass $subclass,
        int $characterLevel = 1
    ): ValidationResult {
        $errors = [];
        $warnings = [];
        $pattern = null;

        // Get expected subclass features at or below character level
        $expectedFeatures = $subclass->features()
            ->where('level', '<=', $characterLevel)
            ->whereNull('parent_feature_id') // Exclude child features (choice options)
            ->get();

        if ($expectedFeatures->isEmpty()) {
            // No features expected at this level - this is a warning since most subclasses have level 1 features
            $warnings[] = "No subclass features found for {$subclass->name} at level {$characterLevel}";

            return ValidationResult::passWithWarnings($warnings);
        }

        // Get character's current features
        $characterFeatures = $snapshotAfter['features']['data'] ?? [];

        // Get subclass features specifically
        $subclassFeatures = array_filter(
            $characterFeatures,
            fn ($f) => ($f['source'] ?? '') === 'subclass'
        );

        // Check if any subclass features were assigned
        if (empty($subclassFeatures)) {
            $errors[] = "No subclass features assigned for {$subclass->name}. Expected ".count($expectedFeatures).' features at level '.$characterLevel.'.';
            $errors[] = 'Expected features: '.implode(', ', $expectedFeatures->pluck('feature_name')->toArray());
            $pattern = 'subclass_features_not_assigned';

            return ValidationResult::fail($errors, $pattern);
        }

        // Check if the correct number of features were assigned
        $expectedCount = $expectedFeatures->count();
        $actualCount = count($subclassFeatures);

        if ($actualCount < $expectedCount) {
            $errors[] = "Only {$actualCount} of {$expectedCount} expected subclass features assigned for {$subclass->name}.";
            $pattern = 'subclass_features_incomplete';

            // List what's missing
            $assignedSlugs = array_column($subclassFeatures, 'slug');
            $expectedSlugs = $expectedFeatures->pluck('slug')->toArray();
            $missingSlugs = array_diff($expectedSlugs, $assignedSlugs);

            if (! empty($missingSlugs)) {
                $errors[] = 'Missing features: '.implode(', ', $missingSlugs);
            }

            return ValidationResult::fail($errors, $pattern);
        }

        // Optional: Verify specific expected features are present
        $assignedSlugs = array_column($subclassFeatures, 'slug');
        $expectedSlugs = $expectedFeatures->pluck('slug')->toArray();
        $missingSlugs = array_diff($expectedSlugs, $assignedSlugs);

        if (! empty($missingSlugs)) {
            $warnings[] = 'Some expected subclass features not found: '.implode(', ', $missingSlugs);
        }

        // Success with optional warnings
        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }
}
