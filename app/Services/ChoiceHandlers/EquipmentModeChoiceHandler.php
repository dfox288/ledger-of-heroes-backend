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
        $existingMetadata = $this->getEquipmentModeMetadata($character);
        $existingSelection = $existingMetadata['equipment_mode'] ?? null;
        $selected = $existingSelection ? [$existingSelection] : [];
        $remaining = $existingSelection ? 0 : 1;

        // Build metadata - include gold_amount if choice was resolved with gold mode
        $metadata = [
            'starting_wealth' => $startingWealth,
        ];
        if ($existingSelection === 'gold' && isset($existingMetadata['gold_amount'])) {
            $metadata['gold_amount'] = $existingMetadata['gold_amount'];
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

        $parsed = $this->parseChoiceId($choice->id);
        $source = $parsed['source'];

        // Clear any existing equipment mode selection and refresh collection
        $this->clearExistingSelection($character, $source);
        $character->load('equipment');

        $goldAmount = null;
        if ($selectedMode === 'gold') {
            // Get gold amount from selection or use average from metadata
            $goldAmount = (int) ($selection['gold_amount'] ?? ($choice->metadata['starting_wealth']['average'] ?? 0));

            // Guard against invalid gold amount (shouldn't happen with validation, but be defensive)
            if ($goldAmount <= 0) {
                throw new InvalidSelectionException(
                    $choice->id,
                    'gold',
                    'Gold amount must be greater than zero'
                );
            }

            // Add gold to existing inventory entry (merge with background gold, etc.)
            $this->addGoldToInventory($character, $goldAmount);
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
                'gold_amount' => $goldAmount,
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
        // Get existing marker to find gold_amount to subtract
        $marker = $character->equipment()
            ->where('item_slug', self::EQUIPMENT_MODE_MARKER)
            ->whereJsonContains('custom_description->source', $source)
            ->first();

        if ($marker) {
            $metadata = json_decode($marker->custom_description, true);

            // If gold mode was selected, subtract that amount from inventory
            if (($metadata['equipment_mode'] ?? null) === 'gold' && isset($metadata['gold_amount'])) {
                $this->subtractGoldFromInventory($character, (int) $metadata['gold_amount']);
            }

            // Remove marker
            $marker->delete();
        }
    }

    /**
     * Add gold to the character's inventory (merges with existing gold entry).
     */
    private function addGoldToInventory(Character $character, int $amount): void
    {
        $existing = $character->equipment()
            ->where('item_slug', self::GOLD_ITEM_SLUG)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $amount);
        } else {
            CharacterEquipment::create([
                'character_id' => $character->id,
                'item_slug' => self::GOLD_ITEM_SLUG,
                'quantity' => $amount,
                'equipped' => false,
            ]);
        }
    }

    /**
     * Subtract gold from the character's inventory.
     * Deletes the entry if quantity reaches zero or below.
     */
    private function subtractGoldFromInventory(Character $character, int $amount): void
    {
        $existing = $character->equipment()
            ->where('item_slug', self::GOLD_ITEM_SLUG)
            ->first();

        if (! $existing) {
            return;
        }

        $newQuantity = $existing->quantity - $amount;

        if ($newQuantity <= 0) {
            $existing->delete();
        } else {
            $existing->update(['quantity' => $newQuantity]);
        }
    }
}
