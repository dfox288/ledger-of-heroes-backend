<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

use App\Models\Background;
use App\Models\CharacterClass;

/**
 * Validates equipment state after wizard flow actions.
 *
 * Checks:
 * 1. Background equipment is always present (regardless of equipment mode)
 * 2. In "equipment" mode: class fixed equipment + selected choices are present
 * 3. In "gold" mode: only starting wealth gold + background equipment present
 * 4. After switching modes: proper cleanup/repopulation occurred
 * 5. After switching to "equipment" mode: proper equipment choices are available
 */
class EquipmentValidator
{
    /**
     * Validate equipment state after equipment mode selection or choice resolution.
     *
     * @param  array  $snapshot  Current character state snapshot
     * @param  string  $equipmentMode  The selected mode ('equipment' or 'gold')
     * @param  array  $expectedSelections  Equipment choices that were made (choice_group => item_slugs)
     * @param  string|null  $classSlug  The primary class slug
     * @param  string|null  $backgroundSlug  The background slug
     * @param  bool  $allChoicesResolved  Whether all equipment choices have been resolved
     */
    public function validateEquipmentState(
        array $snapshot,
        string $equipmentMode,
        array $expectedSelections,
        ?string $classSlug,
        ?string $backgroundSlug,
        bool $allChoicesResolved = false
    ): ValidationResult {
        $errors = [];
        $warnings = [];

        $equipment = $snapshot['equipment']['data'] ?? [];
        // Use item_slug which contains the full slug (e.g., "phb:gold-gp")
        $equipmentSlugs = collect($equipment)->pluck('item_slug')->filter()->toArray();

        // 1. Validate background equipment is present
        if ($backgroundSlug) {
            $backgroundErrors = $this->validateBackgroundEquipment($equipmentSlugs, $equipment, $backgroundSlug);
            $errors = array_merge($errors, $backgroundErrors);
        }

        // 2. Validate based on equipment mode
        if ($equipmentMode === 'gold') {
            $goldErrors = $this->validateGoldMode($equipment, $classSlug);
            $errors = array_merge($errors, $goldErrors);
        } elseif ($equipmentMode === 'equipment') {
            $equipmentErrors = $this->validateEquipmentMode(
                $equipment,
                $equipmentSlugs,
                $expectedSelections,
                $classSlug,
                $allChoicesResolved
            );
            $errors = array_merge($errors, $equipmentErrors);
        }

        return new ValidationResult(
            passed: empty($errors),
            errors: $errors,
            warnings: $warnings
        );
    }

    /**
     * Validate that switching to equipment mode provides proper choices.
     *
     * @param  array  $pendingChoices  Current pending choices from snapshot
     * @param  string|null  $classSlug  The primary class slug
     */
    public function validateEquipmentChoicesAvailable(
        array $pendingChoices,
        ?string $classSlug
    ): ValidationResult {
        $errors = [];
        $warnings = [];

        if (! $classSlug) {
            return new ValidationResult(true, [], []);
        }

        $class = CharacterClass::where('slug', $classSlug)->first();
        if (! $class) {
            $warnings[] = "Could not find class '{$classSlug}' to validate equipment choices";

            return new ValidationResult(true, [], $warnings);
        }

        // Get expected equipment choice groups from class (from entity_choices table)
        $expectedChoiceGroups = $class->equipmentChoices()
            ->pluck('choice_group')
            ->unique()
            ->values()
            ->toArray();

        if (empty($expectedChoiceGroups)) {
            // Class has no equipment choices - nothing to validate
            return new ValidationResult(true, [], []);
        }

        // Find equipment choices in pending choices
        $equipmentChoices = array_filter(
            $pendingChoices,
            fn ($c) => ($c['type'] ?? '') === 'equipment'
        );

        // Extract choice groups from pending choices
        $foundChoiceGroups = [];
        foreach ($equipmentChoices as $choice) {
            $choiceGroup = $choice['metadata']['choice_group'] ?? null;
            if ($choiceGroup !== null) {
                $foundChoiceGroups[] = $choiceGroup;
            }
        }

        // Check that all expected choice groups have pending choices
        $missingGroups = array_diff($expectedChoiceGroups, $foundChoiceGroups);
        if (! empty($missingGroups)) {
            $errors[] = sprintf(
                'Missing equipment choices for choice groups: %s (expected: %s, found: %s)',
                implode(', ', $missingGroups),
                implode(', ', $expectedChoiceGroups),
                implode(', ', $foundChoiceGroups) ?: 'none'
            );
        }

        return new ValidationResult(
            passed: empty($errors),
            errors: $errors,
            warnings: $warnings
        );
    }

    /**
     * Validate that all background equipment is present.
     */
    private function validateBackgroundEquipment(array $equipmentSlugs, array $equipment, string $backgroundSlug): array
    {
        $errors = [];

        $background = Background::where('slug', $backgroundSlug)->first();
        if (! $background) {
            return ["Could not find background '{$backgroundSlug}' to validate equipment"];
        }

        // Get fixed equipment from background
        // Note: Since choice data moved to entity_choices, all entity_items are fixed.
        $expectedItems = $background->equipment()
            ->with('item')
            ->get();

        foreach ($expectedItems as $entityItem) {
            if ($entityItem->item) {
                $itemSlug = $entityItem->item->slug;
                if (! in_array($itemSlug, $equipmentSlugs, true)) {
                    // Check if it's a description-only item (custom_description with source=background)
                    $hasDescriptionItem = collect($equipment)->contains(function ($eq) {
                        $customDesc = json_decode($eq['custom_description'] ?? '{}', true);

                        return ($customDesc['source'] ?? '') === 'background'
                            && empty($eq['item']);
                    });

                    if (! $hasDescriptionItem) {
                        $errors[] = "Missing background equipment: {$entityItem->item->name} ({$itemSlug})";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate gold mode: only starting wealth + background equipment.
     */
    private function validateGoldMode(array $equipment, ?string $classSlug): array
    {
        $errors = [];

        // Check for presence of starting wealth gold
        // Note: item_slug contains the full slug (e.g., "phb:gold-gp"), item.slug is just the slug part
        $hasStartingGold = collect($equipment)->contains(function ($eq) {
            $customDesc = json_decode($eq['custom_description'] ?? '{}', true);

            return ($eq['item_slug'] ?? '') === 'phb:gold-gp'
                && ($customDesc['source'] ?? '') === 'starting_wealth';
        });

        if (! $hasStartingGold) {
            $errors[] = 'Gold mode selected but no starting wealth gold found in equipment';
        }

        // Check that no class equipment choices are present
        $classEquipment = collect($equipment)->filter(function ($eq) {
            $customDesc = json_decode($eq['custom_description'] ?? '{}', true);
            $source = $customDesc['source'] ?? '';

            // Class equipment has source='class' or has choice_group
            return $source === 'class' || isset($customDesc['choice_group']);
        });

        if ($classEquipment->isNotEmpty()) {
            $classItems = $classEquipment->pluck('item_slug')->filter()->implode(', ');
            $errors[] = "Gold mode selected but found class equipment that should be cleared: {$classItems}";
        }

        return $errors;
    }

    /**
     * Validate equipment mode: class fixed + selected choices present.
     *
     * Fixed class equipment is only validated when allChoicesResolved=true,
     * because the backend only populates fixed equipment after all choices are made.
     */
    private function validateEquipmentMode(
        array $equipment,
        array $equipmentSlugs,
        array $expectedSelections,
        ?string $classSlug,
        bool $allChoicesResolved
    ): array {
        $errors = [];

        if (! $classSlug) {
            return $errors;
        }

        $class = CharacterClass::where('slug', $classSlug)->first();
        if (! $class) {
            return ["Could not find class '{$classSlug}' to validate equipment"];
        }

        // Only validate fixed class equipment if all choices are resolved
        // (backend only populates fixed equipment after all choices are made)
        // Note: Since choice data moved to entity_choices, all entity_items are fixed.
        if ($allChoicesResolved) {
            $fixedEquipment = $class->equipment()
                ->with('item')
                ->get();

            foreach ($fixedEquipment as $entityItem) {
                if ($entityItem->item) {
                    $itemSlug = $entityItem->item->slug;
                    if (! in_array($itemSlug, $equipmentSlugs, true)) {
                        $errors[] = "Missing fixed class equipment: {$entityItem->item->name} ({$itemSlug})";
                    }
                }
            }
        }

        // Always validate selected equipment choices are present
        foreach ($expectedSelections as $choiceGroup => $selectedSlugs) {
            foreach ((array) $selectedSlugs as $slug) {
                if (! in_array($slug, $equipmentSlugs, true)) {
                    $errors[] = "Missing selected equipment from choice group {$choiceGroup}: {$slug}";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate equipment state after mode switch.
     *
     * @param  array  $beforeSnapshot  State before mode switch
     * @param  array  $afterSnapshot  State after mode switch
     * @param  string  $previousMode  The previous equipment mode
     * @param  string  $newMode  The new equipment mode
     */
    public function validateModeSwitch(
        array $beforeSnapshot,
        array $afterSnapshot,
        string $previousMode,
        string $newMode
    ): ValidationResult {
        $errors = [];
        $warnings = [];

        $beforeEquipment = $beforeSnapshot['equipment']['data'] ?? [];
        $afterEquipment = $afterSnapshot['equipment']['data'] ?? [];

        if ($previousMode === 'equipment' && $newMode === 'gold') {
            // Switching to gold: class equipment should be cleared
            $afterClassEquipment = collect($afterEquipment)->filter(function ($eq) {
                $customDesc = json_decode($eq['custom_description'] ?? '{}', true);

                return ($customDesc['source'] ?? '') === 'class'
                    || isset($customDesc['choice_group']);
            });

            if ($afterClassEquipment->isNotEmpty()) {
                $errors[] = 'Class equipment not cleared after switching to gold mode';
            }

            // Background equipment should remain
            $beforeBackgroundCount = collect($beforeEquipment)->filter(function ($eq) {
                $customDesc = json_decode($eq['custom_description'] ?? '{}', true);

                return ($customDesc['source'] ?? '') === 'background';
            })->count();

            $afterBackgroundCount = collect($afterEquipment)->filter(function ($eq) {
                $customDesc = json_decode($eq['custom_description'] ?? '{}', true);

                return ($customDesc['source'] ?? '') === 'background';
            })->count();

            if ($afterBackgroundCount < $beforeBackgroundCount) {
                $warnings[] = 'Background equipment count decreased after switching to gold mode';
            }
        } elseif ($previousMode === 'gold' && $newMode === 'equipment') {
            // Switching to equipment: starting wealth gold should be cleared
            $afterStartingGold = collect($afterEquipment)->filter(function ($eq) {
                $customDesc = json_decode($eq['custom_description'] ?? '{}', true);

                return ($eq['item_slug'] ?? '') === 'phb:gold-gp'
                    && ($customDesc['source'] ?? '') === 'starting_wealth';
            });

            if ($afterStartingGold->isNotEmpty()) {
                $errors[] = 'Starting wealth gold not cleared after switching to equipment mode';
            }

            // Equipment choices should now be available (validated separately)
        }

        return new ValidationResult(
            passed: empty($errors),
            errors: $errors,
            warnings: $warnings
        );
    }
}
