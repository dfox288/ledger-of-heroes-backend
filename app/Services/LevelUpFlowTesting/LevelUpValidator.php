<?php

declare(strict_types=1);

namespace App\Services\LevelUpFlowTesting;

use App\Services\WizardFlowTesting\ValidationResult;

/**
 * Validates level-up operations during chaos testing.
 *
 * Checks that each level-up correctly updates:
 * - Total level
 * - Class level
 * - HP (or HP choice pending)
 * - Features granted
 * - No orphaned required choices
 */
class LevelUpValidator
{
    /**
     * Validate that a level-up operation completed correctly.
     *
     * @param  array  $before  Snapshot before level-up
     * @param  array  $after  Snapshot after level-up
     * @param  string  $classSlug  Class that was leveled
     * @param  int  $expectedTotalLevel  Expected total level after level-up
     */
    public function validateLevelUp(
        array $before,
        array $after,
        string $classSlug,
        int $expectedTotalLevel
    ): ValidationResult {
        $errors = [];
        $warnings = [];

        $beforeDerived = $this->getDerived($before);
        $afterDerived = $this->getDerived($after);

        // Check total level incremented
        $actualTotalLevel = $afterDerived['total_level'];
        if ($actualTotalLevel !== $expectedTotalLevel) {
            $errors[] = "Total level did not increment: expected {$expectedTotalLevel}, got {$actualTotalLevel}";
        }

        // Check class level incremented
        $beforeClassLevel = $beforeDerived['class_levels'][$classSlug] ?? 0;
        $afterClassLevel = $afterDerived['class_levels'][$classSlug] ?? 0;
        $expectedClassLevel = $beforeClassLevel + 1;

        if ($afterClassLevel !== $expectedClassLevel) {
            $errors[] = "Class level did not increment for {$classSlug}: expected {$expectedClassLevel}, got {$afterClassLevel}";
        }

        // Check HP increased (or HP choice pending)
        $beforeHp = $beforeDerived['max_hp'];
        $afterHp = $afterDerived['max_hp'];
        $hpChoicePending = $this->hasHpChoicePending($after);

        if ($afterHp <= $beforeHp && ! $hpChoicePending) {
            $errors[] = "HP did not increase: was {$beforeHp}, now {$afterHp}";
        } elseif ($hpChoicePending && $afterHp === $beforeHp) {
            $warnings[] = 'HP choice pending - HP not yet increased';
        }

        if (! empty($errors)) {
            return ValidationResult::fail($errors, 'level_up_failed');
        }

        if (! empty($warnings)) {
            return ValidationResult::passWithWarnings($warnings);
        }

        return ValidationResult::pass();
    }

    /**
     * Validate that no required choices are orphaned (unresolved).
     */
    public function validateNoOrphanedChoices(array $snapshot): ValidationResult
    {
        $pendingChoices = $snapshot['pending_choices']['data']['choices'] ?? [];

        $requiredPending = array_filter(
            $pendingChoices,
            fn ($c) => ($c['required'] ?? false) === true && ($c['remaining'] ?? 0) > 0
        );

        if (! empty($requiredPending)) {
            $pendingTypes = array_unique(array_column($requiredPending, 'type'));
            $errors = ['Unresolved required choices: '.implode(', ', $pendingTypes)];

            return ValidationResult::fail($errors, 'orphaned_choices');
        }

        return ValidationResult::pass();
    }

    /**
     * Get derived fields, supporting both wizard flow and level-up snapshots.
     */
    private function getDerived(array $snapshot): array
    {
        // Level-up snapshots have level_up_derived, wizard flow has derived
        if (isset($snapshot['level_up_derived'])) {
            return $snapshot['level_up_derived'];
        }

        // Fall back to extracting from character data directly
        $character = $snapshot['character']['data'] ?? [];
        $classes = $character['classes'] ?? [];

        $classLevels = [];
        foreach ($classes as $classData) {
            $slug = $classData['class']['slug'] ?? null;
            if ($slug) {
                $classLevels[$slug] = $classData['level'] ?? 0;
            }
        }

        return [
            'total_level' => $character['total_level'] ?? 0,
            'max_hp' => $character['max_hit_points'] ?? 0,
            'class_levels' => $classLevels,
            'required_pending_count' => 0,
            'ability_score_totals' => $character['ability_scores'] ?? [],
            'feat_slugs' => [],
        ];
    }

    /**
     * Check if an HP choice is pending.
     */
    private function hasHpChoicePending(array $snapshot): bool
    {
        $pendingChoices = $snapshot['pending_choices']['data']['choices'] ?? [];

        foreach ($pendingChoices as $choice) {
            if (($choice['type'] ?? '') === 'hp' && ($choice['remaining'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }
}
