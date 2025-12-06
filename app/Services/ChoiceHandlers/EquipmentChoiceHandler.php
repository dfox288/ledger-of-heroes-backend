<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use Illuminate\Support\Collection;

class EquipmentChoiceHandler extends AbstractChoiceHandler
{
    public function getType(): string
    {
        return 'equipment';
    }

    public function getChoices(Character $character): Collection
    {
        // Equipment choices only available at level 1
        $totalLevel = $character->characterClasses->sum('level');
        if ($totalLevel !== 1) {
            return collect();
        }

        // Get primary class
        $primaryClass = $character->characterClasses
            ->where('is_primary', true)
            ->first()
            ?->characterClass;

        if (! $primaryClass) {
            return collect();
        }

        // Get equipment choices from class
        $equipmentChoices = $primaryClass->equipment()
            ->with('choiceItems.item')
            ->where('is_choice', true)
            ->orderBy('choice_group')
            ->orderBy('choice_option')
            ->get();

        // Group by choice_group
        $grouped = $equipmentChoices->groupBy('choice_group');

        $choices = collect();

        foreach ($grouped as $choiceGroup => $options) {
            // Build options array - convert choice_option number to letters (a, b, c, etc.)
            $builtOptions = [];
            $optionsByNumber = $options->groupBy('choice_option');

            foreach ($optionsByNumber as $optionNumber => $optionItems) {
                $optionLetter = chr(96 + (int) $optionNumber); // 1 => 'a', 2 => 'b', etc.

                // Get items for this option from choiceItems relationship
                $items = [];
                foreach ($optionItems as $optionItem) {
                    foreach ($optionItem->choiceItems as $choiceItem) {
                        if ($choiceItem->item) {
                            $items[] = [
                                'id' => $choiceItem->item->id,
                                'name' => $choiceItem->item->name,
                                'slug' => $choiceItem->item->slug,
                                'quantity' => $choiceItem->quantity,
                            ];
                        }
                    }
                }

                $builtOptions[] = [
                    'option' => $optionLetter,
                    'items' => $items,
                ];
            }

            // Quantity is always 1 for equipment choices (pick one option)
            $quantity = $options->first()->quantity ?? 1;

            $choice = new PendingChoice(
                id: $this->generateChoiceId('equipment', 'class', $primaryClass->id, 1, $choiceGroup),
                type: 'equipment',
                subtype: null,
                source: 'class',
                sourceName: $primaryClass->name,
                levelGranted: 1,
                required: true,
                quantity: $quantity,
                remaining: $quantity, // For equipment, always show as pending since we can't easily check without DB columns
                selected: [],
                options: $builtOptions,
                optionsEndpoint: null,
                metadata: [
                    'choice_group' => $choiceGroup,
                ],
            );

            $choices->push($choice);
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $choiceGroup = $parsed['group'];
        $source = $parsed['source'];

        $selected = $selection['selected'] ?? null;
        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Find the selected option in the choice options
        $selectedOption = null;
        foreach ($choice->options as $option) {
            if ($option['option'] === $selected) {
                $selectedOption = $option;
                break;
            }
        }

        if (! $selectedOption) {
            throw new InvalidSelectionException(
                $choice->id,
                $selected,
                "Invalid option '{$selected}' for equipment choice"
            );
        }

        // Create metadata JSON to track the source and choice_group
        $metadata = json_encode([
            'source' => $source,
            'choice_group' => $choiceGroup,
        ]);

        // Grant each item in the selected option
        foreach ($selectedOption['items'] as $item) {
            CharacterEquipment::create([
                'character_id' => $character->id,
                'item_id' => $item['id'],
                'quantity' => $item['quantity'],
                'equipped' => false,
                'custom_description' => $metadata,
            ]);
        }
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Can only undo equipment choices at level 1
        $totalLevel = $character->characterClasses->sum('level');

        return $totalLevel === 1;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $choiceGroup = $parsed['group'];
        $source = $parsed['source'];

        // Build metadata JSON to find items from this choice
        $metadata = json_encode([
            'source' => $source,
            'choice_group' => $choiceGroup,
        ]);

        // Delete equipment records that match this metadata
        $character->equipment()
            ->where('custom_description', $metadata)
            ->delete();

        $character->load('equipment');
    }
}
