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

        // Check if already resolved
        $existingSelection = $character->equipment_mode;
        $selected = $existingSelection ? [$existingSelection] : [];
        $remaining = $existingSelection ? 0 : 1;

        // Build metadata
        $metadata = [
            'starting_wealth' => $startingWealth,
        ];

        // If gold mode was selected, include the stored gold amount for re-entry
        if ($existingSelection === 'gold') {
            $storedGold = $character->equipment()
                ->where('item_slug', self::GOLD_ITEM_SLUG)
                ->whereJsonContains('custom_description->source', 'starting_wealth')
                ->first();

            if ($storedGold) {
                $metadata['gold_amount'] = $storedGold->quantity;
            }
        }

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
            metadata: $metadata,
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

        // Clear any existing selection first (handles switching from gold to equipment)
        $this->clearExistingSelection($character);

        if ($selectedMode === 'gold') {
            // Get gold amount from selection or use average from metadata
            $goldAmount = (int) ($selection['gold_amount'] ?? ($choice->metadata['starting_wealth']['average'] ?? 0));

            // Guard against invalid gold amount
            if ($goldAmount <= 0) {
                throw new InvalidSelectionException(
                    $choice->id,
                    'gold',
                    'Gold amount must be greater than zero'
                );
            }

            // Add gold to inventory
            $this->addGoldToInventory($character, $goldAmount);
        }

        // Update the equipment_mode column
        $character->update(['equipment_mode' => $selectedMode]);
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Can only undo at level 1
        $totalLevel = $character->characterClasses->sum('level');

        return $totalLevel === 1;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $this->clearExistingSelection($character);
        $character->update(['equipment_mode' => null]);
    }

    /**
     * Clear existing equipment mode selection effects.
     *
     * If gold mode was selected, removes all gold and re-adds background gold.
     */
    private function clearExistingSelection(Character $character): void
    {
        if ($character->equipment_mode === 'gold') {
            $this->resetGoldToBackgroundOnly($character);
        }
    }

    /**
     * Reset gold to background amount only.
     *
     * Removes all gold entries and re-creates the background gold entry if one existed.
     */
    private function resetGoldToBackgroundOnly(Character $character): void
    {
        // Find the background gold amount (if any)
        $backgroundGold = $character->equipment()
            ->where('item_slug', self::GOLD_ITEM_SLUG)
            ->whereJsonContains('custom_description->source', 'background')
            ->first();

        $backgroundGoldAmount = $backgroundGold?->quantity ?? 0;

        // Delete ALL gold entries
        $character->equipment()
            ->where('item_slug', self::GOLD_ITEM_SLUG)
            ->delete();

        // Re-add background gold if there was any
        if ($backgroundGoldAmount > 0) {
            CharacterEquipment::create([
                'character_id' => $character->id,
                'item_slug' => self::GOLD_ITEM_SLUG,
                'quantity' => $backgroundGoldAmount,
                'equipped' => false,
                'custom_description' => json_encode(['source' => 'background']),
            ]);
        }
    }

    /**
     * Add starting wealth gold to the character's inventory.
     *
     * Creates a separate entry with source='starting_wealth' to keep it
     * distinct from background gold for proper tracking during re-selection.
     */
    private function addGoldToInventory(Character $character, int $amount): void
    {
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => self::GOLD_ITEM_SLUG,
            'quantity' => $amount,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'starting_wealth']),
        ]);
    }
}
