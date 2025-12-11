<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

/**
 * Generates wizard flow step sequences for testing.
 * Supports linear flows, chaos mode (random switches), and parameterized patterns.
 */
class FlowGenerator
{
    /**
     * Generate a linear wizard flow (no switches).
     * Mirrors the frontend wizard order.
     */
    public function linear(): array
    {
        return [
            ['action' => 'create', 'description' => 'Create character shell'],
            ['action' => 'set_race', 'description' => 'Select race'],
            ['action' => 'set_subrace', 'description' => 'Select subrace (if applicable)', 'conditional' => true],
            ['action' => 'set_class', 'description' => 'Select class'],
            ['action' => 'set_subclass', 'description' => 'Select subclass (if applicable)', 'conditional' => true],
            ['action' => 'set_background', 'description' => 'Select background'],
            ['action' => 'set_ability_scores', 'description' => 'Assign ability scores'],
            ['action' => 'resolve_proficiency_choices', 'description' => 'Resolve proficiency choices', 'conditional' => true],
            ['action' => 'resolve_language_choices', 'description' => 'Resolve language choices', 'conditional' => true],
            ['action' => 'set_equipment_mode', 'description' => 'Choose equipment mode'],
            ['action' => 'resolve_equipment_choices', 'description' => 'Resolve equipment choices', 'conditional' => true],
            ['action' => 'resolve_spell_choices', 'description' => 'Resolve spell choices (if spellcaster)', 'conditional' => true],
            ['action' => 'set_details', 'description' => 'Set name and alignment'],
            ['action' => 'validate', 'description' => 'Validate character completion'],
        ];
    }

    /**
     * Generate a chaos flow with random switches inserted.
     */
    public function chaos(CharacterRandomizer $randomizer, int $minSwitches = 1, int $maxSwitches = 3): array
    {
        $flow = $this->linear();
        $switchCount = $randomizer->randomInt($minSwitches, $maxSwitches);

        // Possible switch types and where they can be inserted
        $switchTypes = ['switch_race', 'switch_background', 'switch_class'];

        for ($i = 0; $i < $switchCount; $i++) {
            $switchType = $switchTypes[$randomizer->randomInt(0, count($switchTypes) - 1)];
            $insertPosition = $this->findValidInsertPosition($flow, $switchType, $randomizer);

            if ($insertPosition !== null) {
                $switchStep = [
                    'action' => $switchType,
                    'description' => $this->getSwitchDescription($switchType),
                    'is_switch' => true,
                ];

                array_splice($flow, $insertPosition, 0, [$switchStep]);
            }
        }

        return $flow;
    }

    /**
     * Generate a flow with specific switch pattern.
     * Example: ['race', 'background', 'race'] inserts those switches at appropriate points.
     */
    public function withSwitches(array $switchSequence): array
    {
        $flow = $this->linear();

        foreach ($switchSequence as $switchType) {
            $fullSwitchType = str_starts_with($switchType, 'switch_') ? $switchType : "switch_{$switchType}";
            $insertPosition = $this->findInsertPositionForSwitch($flow, $fullSwitchType);

            if ($insertPosition !== null) {
                $switchStep = [
                    'action' => $fullSwitchType,
                    'description' => $this->getSwitchDescription($fullSwitchType),
                    'is_switch' => true,
                ];

                array_splice($flow, $insertPosition, 0, [$switchStep]);
            }
        }

        return $flow;
    }

    /**
     * Generate a flow that tests equipment mode switching.
     */
    public function equipmentModeChaos(CharacterRandomizer $randomizer): array
    {
        $flow = $this->linear();

        // Add a background or class switch after equipment is set
        $equipmentIndex = $this->findActionIndex($flow, 'resolve_equipment_choices');
        if ($equipmentIndex !== null) {
            $switchType = $randomizer->randomInt(0, 1) === 0 ? 'switch_background' : 'switch_class';
            $switchStep = [
                'action' => $switchType,
                'description' => $this->getSwitchDescription($switchType).' (after equipment)',
                'is_switch' => true,
                'equipment_mode_test' => true,
            ];

            array_splice($flow, $equipmentIndex + 1, 0, [$switchStep]);
        }

        return $flow;
    }

    /**
     * Generate a flow that tests spellcaster to non-spellcaster (or vice versa) class switch.
     */
    public function classTypeSwitchFlow(string $fromType, string $toType): array
    {
        $flow = $this->linear();

        // Insert class switch after spell choices
        $spellIndex = $this->findActionIndex($flow, 'resolve_spell_choices');
        $insertIndex = $spellIndex !== null ? $spellIndex + 1 : $this->findActionIndex($flow, 'set_class') + 1;

        $switchStep = [
            'action' => 'switch_class',
            'description' => "Switch class from {$fromType} to {$toType}",
            'is_switch' => true,
            'class_type_from' => $fromType,
            'class_type_to' => $toType,
        ];

        array_splice($flow, $insertIndex, 0, [$switchStep]);

        return $flow;
    }

    /**
     * Generate flows for testing every race with chaos.
     *
     * @return array Array of [race_slug => flow]
     */
    public function allRacesWithChaos(CharacterRandomizer $randomizer): array
    {
        $races = \App\Models\Race::whereNull('parent_race_id')->get();
        $flows = [];

        foreach ($races as $race) {
            $flow = $this->chaos($randomizer);
            // Mark the first set_race step to use this specific race
            foreach ($flow as &$step) {
                if ($step['action'] === 'set_race') {
                    $step['force_race'] = $race->slug;
                    break;
                }
            }
            $flows[$race->slug] = $flow;
        }

        return $flows;
    }

    /**
     * Find a valid position to insert a switch in the flow.
     */
    private function findValidInsertPosition(array $flow, string $switchType, CharacterRandomizer $randomizer): ?int
    {
        $validRanges = $this->getValidSwitchRange($flow, $switchType);

        if (empty($validRanges)) {
            return null;
        }

        $range = $validRanges[$randomizer->randomInt(0, count($validRanges) - 1)];

        return $randomizer->randomInt($range['min'], $range['max']);
    }

    /**
     * Find a deterministic position to insert a switch.
     */
    private function findInsertPositionForSwitch(array $flow, string $switchType): ?int
    {
        $validRanges = $this->getValidSwitchRange($flow, $switchType);

        if (empty($validRanges)) {
            return null;
        }

        // Use the middle of the last valid range
        $range = end($validRanges);

        return (int) (($range['min'] + $range['max']) / 2);
    }

    /**
     * Get valid index ranges where a switch can be inserted.
     */
    private function getValidSwitchRange(array $flow, string $switchType): array
    {
        $ranges = [];

        $minIndex = match ($switchType) {
            'switch_race' => $this->findActionIndex($flow, 'set_race'),
            'switch_background' => $this->findActionIndex($flow, 'set_background'),
            'switch_class' => $this->findActionIndex($flow, 'set_class'),
            default => null,
        };

        if ($minIndex === null) {
            return [];
        }

        // Can insert after the initial set and before validate
        $validateIndex = $this->findActionIndex($flow, 'validate');
        $maxIndex = $validateIndex !== null ? $validateIndex - 1 : count($flow) - 1;

        if ($minIndex + 1 <= $maxIndex) {
            $ranges[] = ['min' => $minIndex + 1, 'max' => $maxIndex];
        }

        return $ranges;
    }

    /**
     * Find the index of an action in the flow.
     */
    private function findActionIndex(array $flow, string $action): ?int
    {
        foreach ($flow as $index => $step) {
            if ($step['action'] === $action) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Get description for a switch action.
     */
    private function getSwitchDescription(string $switchType): string
    {
        return match ($switchType) {
            'switch_race' => 'SWITCH: Change race (should cascade reset)',
            'switch_background' => 'SWITCH: Change background (should cascade reset)',
            'switch_class' => 'SWITCH: Change class (should cascade reset)',
            default => 'SWITCH: Unknown',
        };
    }

    /**
     * Get the list of available flow types.
     */
    public static function availableFlowTypes(): array
    {
        return [
            'linear' => 'Standard wizard flow with no switches',
            'chaos' => 'Random switches inserted at random points',
            'equipment_chaos' => 'Tests equipment mode with switches after equipment selection',
            'class_type_switch' => 'Tests switching between spellcaster and martial classes',
            'parameterized' => 'Specific switch sequence (use --switches)',
        ];
    }
}
