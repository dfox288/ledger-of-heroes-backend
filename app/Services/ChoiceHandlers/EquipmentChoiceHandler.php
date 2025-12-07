<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ProficiencyType;
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
        // Eager load: proficiencyType for category filters, item.contents.item for pack contents
        $equipmentChoices = $primaryClass->equipment()
            ->with([
                'choiceItems.item.contents.item',
                'choiceItems.proficiencyType',
            ])
            ->where('is_choice', true)
            ->orderBy('choice_group')
            ->orderBy('choice_option')
            ->get();

        // Group by choice_group
        $grouped = $equipmentChoices->groupBy('choice_group');

        // Pre-fetch existing equipment selections for this character from class
        $existingSelections = $this->getExistingSelections($character, 'class');

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
                            // Build item data with optional contents for packs
                            $itemData = [
                                'id' => $choiceItem->item->id,
                                'name' => $choiceItem->item->name,
                                'slug' => $choiceItem->item->slug,
                                'full_slug' => $choiceItem->item->full_slug,
                                'quantity' => $choiceItem->quantity,
                            ];

                            // Include pack contents if available (filter out orphaned records)
                            if ($choiceItem->item->contents && $choiceItem->item->contents->isNotEmpty()) {
                                $contents = $choiceItem->item->contents
                                    ->map(fn ($content) => [
                                        'quantity' => $content->quantity,
                                        'item' => $content->item ? [
                                            'id' => $content->item->id,
                                            'name' => $content->item->name,
                                            'slug' => $content->item->slug,
                                            'full_slug' => $content->item->full_slug,
                                        ] : null,
                                    ])
                                    ->filter(fn ($c) => $c['item'] !== null)
                                    ->values();

                                if ($contents->isNotEmpty()) {
                                    $itemData['contents'] = $contents->all();
                                }
                            }

                            $items[] = $itemData;
                        } elseif ($choiceItem->proficiencyType) {
                            // Category-based choice (e.g., "any simple weapon")
                            // Fetch all matching items and add them to the items array
                            $categoryItems = $this->getItemsForProficiencyType($choiceItem->proficiencyType);
                            foreach ($categoryItems as $categoryItem) {
                                $items[] = [
                                    'id' => $categoryItem->id,
                                    'name' => $categoryItem->name,
                                    'slug' => $categoryItem->slug,
                                    'full_slug' => $categoryItem->full_slug,
                                    'quantity' => $choiceItem->quantity,
                                ];
                            }
                        }
                    }
                }

                // Get label from EntityItem description field (e.g., "a rapier")
                $label = $optionItems->first()?->description ?? '';

                $builtOptions[] = [
                    'option' => $optionLetter,
                    'label' => $label,
                    'items' => $items,
                ];
            }

            // Quantity is always 1 for equipment choices (pick one option)
            $quantity = $options->first()->quantity ?? 1;

            // Calculate remaining and selected from existing equipment
            $selection = $existingSelections[$choiceGroup] ?? null;
            $remaining = $selection ? 0 : $quantity;
            $selected = $selection ? [$selection['option']] : [];

            $choice = new PendingChoice(
                id: $this->generateChoiceId('equipment', 'class', $primaryClass->full_slug, 1, $choiceGroup),
                type: 'equipment',
                subtype: null,
                source: 'class',
                sourceName: $primaryClass->name,
                levelGranted: 1,
                required: true,
                quantity: $quantity,
                remaining: $remaining,
                selected: $selected,
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

    /**
     * Get existing equipment selections for a character from a specific source.
     *
     * Returns an array keyed by choice_group with the selected option and item slugs.
     */
    private function getExistingSelections(Character $character, string $source): array
    {
        $equipment = $character->equipment()
            ->whereJsonContains('custom_description->source', $source)
            ->whereNotNull('custom_description')
            ->get();

        $selections = [];
        foreach ($equipment as $item) {
            $metadata = json_decode($item->custom_description, true);
            if (! $metadata || ! isset($metadata['choice_group'])) {
                continue;
            }

            $choiceGroup = $metadata['choice_group'];
            $selectedOption = $metadata['selected_option'] ?? null;

            if (! isset($selections[$choiceGroup])) {
                $selections[$choiceGroup] = [
                    'option' => $selectedOption,
                    'items' => [],
                ];
            }

            $selections[$choiceGroup]['items'][] = $item->item_slug;
        }

        return $selections;
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

        // Handle array format: selected is an array like ["a"], extract first element
        $selectedOption = is_array($selected) ? ($selected[0] ?? null) : $selected;
        if (empty($selectedOption)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Find the selected option in the choice options
        $foundOption = null;
        foreach ($choice->options as $option) {
            if ($option['option'] === $selectedOption) {
                $foundOption = $option;
                break;
            }
        }

        if (! $foundOption) {
            throw new InvalidSelectionException(
                $choice->id,
                $selectedOption,
                "Invalid option '{$selectedOption}' for equipment choice"
            );
        }

        // Create metadata JSON to track the source, choice_group, and selected option
        $metadata = json_encode([
            'source' => $source,
            'choice_group' => $choiceGroup,
            'selected_option' => $selectedOption,
        ]);

        // Clear existing equipment from this choice_group before adding new ones
        // This ensures re-submitting replaces rather than duplicates
        $character->equipment()
            ->whereJsonContains('custom_description->source', $source)
            ->whereJsonContains('custom_description->choice_group', $choiceGroup)
            ->delete();

        // Determine which items to grant
        $itemSelections = $selection['item_selections'] ?? [];
        $specificItems = $itemSelections[$selectedOption] ?? null;

        // If item_selections is provided for this option, only grant those specific items
        // Otherwise grant all items from the option (for fixed options like "a rapier")
        $itemsToGrant = $foundOption['items'];
        if ($specificItems !== null && is_array($specificItems)) {
            $itemsToGrant = array_filter($foundOption['items'], function ($item) use ($specificItems) {
                return in_array($item['full_slug'], $specificItems, true);
            });
        }

        // Grant each selected item
        foreach ($itemsToGrant as $item) {
            CharacterEquipment::create([
                'character_id' => $character->id,
                'item_slug' => $item['full_slug'],
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

        // Delete equipment records that match this choice (use whereJsonContains for robustness)
        $character->equipment()
            ->whereJsonContains('custom_description->source', $source)
            ->whereJsonContains('custom_description->choice_group', $choiceGroup)
            ->delete();

        $character->load('equipment');
    }

    /**
     * Get all items matching a proficiency type category.
     *
     * For "Simple Weapons" (category=weapon, subcategory=simple):
     *   - Find ProficiencyTypes where subcategory starts with "simple_" (simple_melee, simple_ranged)
     *   - Match items by slug
     *
     * For "Martial Weapons" (category=weapon, subcategory=martial):
     *   - Find ProficiencyTypes where subcategory starts with "martial_"
     *   - Match items by slug
     *
     * For "Musical Instruments" (category=musical_instrument):
     *   - Find ProficiencyTypes where subcategory = "musical_instrument"
     *   - Match items by slug
     */
    private function getItemsForProficiencyType(ProficiencyType $proficiencyType): Collection
    {
        $category = $proficiencyType->category;
        $subcategory = $proficiencyType->subcategory;

        // Build query for matching proficiency types
        $query = ProficiencyType::query();

        if ($category === 'weapon' && $subcategory === 'simple') {
            // Simple Weapons: match simple_melee and simple_ranged
            $query->where('category', 'weapon')
                ->where('subcategory', 'like', 'simple_%');
        } elseif ($category === 'weapon' && $subcategory === 'martial') {
            // Martial Weapons: match martial_melee and martial_ranged
            $query->where('category', 'weapon')
                ->where('subcategory', 'like', 'martial_%');
        } elseif ($category === 'musical_instrument') {
            // Musical Instruments: match tool/musical_instrument
            $query->where('subcategory', 'musical_instrument');
        } else {
            // Unknown category - return empty collection
            return collect();
        }

        // Get slugs of matching proficiency types
        $slugs = $query->pluck('slug');

        // Find items matching these slugs (non-magic base items only)
        return Item::whereIn('slug', $slugs)
            ->where('is_magic', false)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'full_slug']);
    }
}
