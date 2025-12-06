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

        // Create metadata JSON to track the source and choice_group
        $metadata = json_encode([
            'source' => $source,
            'choice_group' => $choiceGroup,
        ]);

        // Grant each item in the selected option
        foreach ($foundOption['items'] as $item) {
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
            ->get(['id', 'name', 'slug']);
    }
}
