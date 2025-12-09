<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use Illuminate\Support\Collection;

/**
 * Handles the equipment vs gold alternative choice for starting equipment.
 *
 * D&D 5e allows players to choose between:
 * - Taking their class's standard starting equipment
 * - Taking starting gold instead (rolled or average)
 *
 * This choice must be made at level 1 before equipment choices.
 */
class EquipmentModeChoiceHandler extends AbstractChoiceHandler
{
    use ChecksEquipmentMode;

    private const GOLD_ITEM_SLUG = 'phb:gold-gp';

    public function getType(): string
    {
        return 'equipment_mode';
    }

    public function getChoices(Character $character): Collection
    {
        // Only available at level 1
        $totalLevel = $character->characterClasses->sum('level');
        if ($totalLevel !== 1) {
            return collect();
        }

        // Get primary class
        $primaryClassPivot = $character->characterClasses
            ->where('is_primary', true)
            ->first();

        if (! $primaryClassPivot) {
            return collect();
        }

        $primaryClass = $primaryClassPivot->characterClass;
        if (! $primaryClass) {
            return collect();
        }

        // Check if class has starting wealth data
        $startingWealth = $primaryClass->starting_wealth;
        if (! $startingWealth) {
            return collect();
        }

        // Check if class has equipment choices (no point offering gold alternative if no equipment)
        $hasEquipmentChoices = $primaryClass->equipment()
            ->where('is_choice', true)
            ->exists();

        if (! $hasEquipmentChoices) {
            return collect();
        }

        // Check if already resolved (uses trait method)
        $existingSelection = $this->getEquipmentModeSelection($character);
        $selected = $existingSelection ? [$existingSelection] : [];
        $remaining = $existingSelection ? 0 : 1;

        $choice = new PendingChoice(
            id: $this->generateChoiceId('equipment_mode', 'class', $primaryClass->full_slug, 1, 'starting_equipment'),
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: $primaryClass->name,
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: $remaining,
            selected: $selected,
            options: [
                [
                    'value' => 'equipment',
                    'label' => 'Take Starting Equipment',
                    'description' => "Receive your class's standard starting equipment",
                ],
                [
                    'value' => 'gold',
                    'label' => 'Take Starting Gold',
                    'description' => "Receive {$startingWealth['formula']} (avg. {$startingWealth['average']} gp) instead of equipment",
                ],
            ],
            optionsEndpoint: null,
            metadata: [
                'starting_wealth' => $startingWealth,
            ],
        );

        return collect([$choice]);
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $selected = $selection['selected'] ?? null;
        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Handle array format
        $selectedMode = is_array($selected) ? ($selected[0] ?? null) : $selected;
        if (empty($selectedMode)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Validate selection
        if (! in_array($selectedMode, ['equipment', 'gold'], true)) {
            throw new InvalidSelectionException(
                $choice->id,
                $selectedMode,
                "Invalid selection '{$selectedMode}'. Must be 'equipment' or 'gold'"
            );
        }

        $parsed = $this->parseChoiceId($choice->id);
        $source = $parsed['source'];

        // Clear any existing equipment mode selection and refresh collection
        $this->clearExistingSelection($character, $source);
        $character->load('equipment');

        if ($selectedMode === 'gold') {
            // Get gold amount from selection or use average from metadata
            $goldAmount = $selection['gold_amount'] ?? ($choice->metadata['starting_wealth']['average'] ?? 0);

            // Add gold to inventory
            CharacterEquipment::create([
                'character_id' => $character->id,
                'item_slug' => self::GOLD_ITEM_SLUG,
                'quantity' => (int) $goldAmount,
                'equipped' => false,
                'custom_description' => json_encode([
                    'source' => $source,
                    'equipment_mode' => 'gold',
                    'gold_amount' => (int) $goldAmount,
                ]),
            ]);
        }

        // Store marker to track the choice
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => self::EQUIPMENT_MODE_MARKER,
            'quantity' => 0,
            'equipped' => false,
            'custom_description' => json_encode([
                'source' => $source,
                'equipment_mode' => $selectedMode,
                'gold_amount' => $selectedMode === 'gold' ? ($selection['gold_amount'] ?? ($choice->metadata['starting_wealth']['average'] ?? 0)) : null,
            ]),
        ]);
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Can only undo at level 1
        $totalLevel = $character->characterClasses->sum('level');

        return $totalLevel === 1;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $source = $parsed['source'];

        $this->clearExistingSelection($character, $source);

        $character->load('equipment');
    }

    /**
     * Clear existing equipment mode selection and related items.
     */
    private function clearExistingSelection(Character $character, string $source): void
    {
        // Remove marker
        $character->equipment()
            ->where('item_slug', self::EQUIPMENT_MODE_MARKER)
            ->whereJsonContains('custom_description->source', $source)
            ->delete();

        // Remove gold from equipment_mode choice (if gold was selected)
        $character->equipment()
            ->where('item_slug', self::GOLD_ITEM_SLUG)
            ->whereJsonContains('custom_description->equipment_mode', 'gold')
            ->whereJsonContains('custom_description->source', $source)
            ->delete();
    }
}
