<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ProficiencyType;
use App\Services\EquipmentManagerService;
use Illuminate\Support\Collection;

class EquipmentChoiceHandler extends AbstractChoiceHandler
{
    use ChecksEquipmentMode;

    public function __construct(
        private EquipmentManagerService $equipmentService
    ) {}

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

        // Check if gold mode was selected - skip equipment choices if so
        if ($this->isGoldModeSelected($character)) {
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

                // Build items for this option using helper method
                $optionData = $this->buildOptionItems($optionItems);

                // Get label from EntityItem description field (e.g., "a rapier")
                $label = $optionItems->first()?->description ?? '';

                $builtOptions[] = [
                    'option' => $optionLetter,
                    'label' => $label,
                    'items' => $optionData['items'],
                    'is_category' => $optionData['is_category'],
                    'category_item_count' => $optionData['category_item_count'],
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

        // Check if item_selections is required for this option
        $itemSelections = $selection['item_selections'] ?? [];
        $specificItems = $itemSelections[$selectedOption] ?? null;
        $isCategory = $foundOption['is_category'] ?? false;
        $optionItemCount = count($foundOption['items'] ?? []);

        // Category options (e.g., "any simple weapon") require item_selections
        // Bundle options (e.g., "a longbow and arrows") grant all items without item_selections
        if ($isCategory && $specificItems === null) {
            $itemWord = $optionItemCount === 1 ? 'item' : 'items';
            throw new InvalidSelectionException(
                $choice->id,
                $selectedOption,
                "Option '{$selectedOption}' has {$optionItemCount} {$itemWord} to choose from. Use item_selections to specify which item(s) you want."
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
        // ($itemSelections and $specificItems already defined above for validation)

        // Build list of items to grant based on selection type:
        // - If item_selections provided: grant selected items + all fixed items
        // - Otherwise: grant all items (for pure fixed options like "a rapier")
        $itemsToGrant = $foundOption['items'];
        if ($specificItems !== null && is_array($specificItems)) {
            // Get valid non-fixed item slugs from the option (for validation)
            $nonFixedItems = array_filter($foundOption['items'], fn ($item) => ! ($item['is_fixed'] ?? false));
            $validSlugs = array_column($nonFixedItems, 'full_slug');

            // Grant: selected items from category + all fixed items
            $itemsToGrant = array_filter($foundOption['items'], function ($item) use ($specificItems) {
                // Always include fixed items (e.g., shield in "martial weapon and shield")
                if ($item['is_fixed'] ?? false) {
                    return true;
                }

                // Include non-fixed items only if they're in item_selections
                return in_array($item['full_slug'], $specificItems, true);
            });

            // Validate that at least one specified item exists in the non-fixed options
            $selectedNonFixed = array_filter($itemsToGrant, fn ($item) => ! ($item['is_fixed'] ?? false));
            if (empty($selectedNonFixed)) {
                $invalidSlugs = array_diff($specificItems, $validSlugs);
                throw new InvalidSelectionException(
                    $choice->id,
                    implode(', ', $invalidSlugs),
                    "None of the specified items exist in option '{$selectedOption}'"
                );
            }
        }

        // Grant each selected item
        foreach ($itemsToGrant as $item) {
            // Pack items: grant the pack contents, not the pack itself
            if (($item['is_pack'] ?? false) && ! empty($item['contents'])) {
                // Add pack info to metadata for items from this pack
                $packMetadata = json_encode([
                    'source' => $source,
                    'choice_group' => $choiceGroup,
                    'selected_option' => $selectedOption,
                    'from_pack' => $item['full_slug'],
                ]);

                foreach ($item['contents'] as $contentItem) {
                    CharacterEquipment::create([
                        'character_id' => $character->id,
                        'item_slug' => $contentItem['full_slug'],
                        'quantity' => $contentItem['quantity'],
                        'equipped' => false,
                        'custom_description' => $packMetadata,
                    ]);
                }
            } else {
                // Regular item
                CharacterEquipment::create([
                    'character_id' => $character->id,
                    'item_slug' => $item['full_slug'],
                    'quantity' => $item['quantity'],
                    'equipped' => false,
                    'custom_description' => $metadata,
                ]);
            }
        }

        // After resolving this equipment choice, try to populate fixed equipment.
        // This will only succeed if all equipment choices have been resolved.
        $this->equipmentService->populateFromClass($character);
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
     * Build the items array for an equipment option.
     *
     * Handles three types of items via EquipmentChoiceItem:
     * - Category items (proficiency_type_id set): "any simple weapon" - user picks one, is_fixed=false
     * - Pack items (item with contents): "explorer's pack" - expands to contents, is_fixed=true
     * - Regular items (item_id set): "a shield" - always granted with option, is_fixed=true
     *
     * The is_fixed flag determines behavior during resolution:
     * - is_fixed=false: User must select via item_selections
     * - is_fixed=true: Always granted when option is selected
     *
     * @return array{items: array, is_category: bool, category_item_count: int}
     */
    private function buildOptionItems(Collection $optionItems): array
    {
        $items = [];
        $isCategory = false;
        $categoryItemCount = 0;

        foreach ($optionItems as $entityItem) {
            // Process choice items (EquipmentChoiceItem records)
            foreach ($entityItem->choiceItems as $choiceItem) {
                // Category choice (e.g., "any simple weapon") - user picks one
                if ($choiceItem->proficiency_type_id && $choiceItem->proficiencyType) {
                    $categoryItems = $this->getItemsForProficiencyType($choiceItem->proficiencyType);
                    $isCategory = true;
                    $categoryItemCount = $categoryItems->count();

                    foreach ($categoryItems as $item) {
                        $items[] = [
                            'full_slug' => $item->full_slug,
                            'name' => $item->name,
                            'quantity' => $choiceItem->quantity ?? 1,
                            'is_fixed' => false, // User must select from category
                        ];
                    }
                } elseif ($choiceItem->item) {
                    // Pack item - keep pack structure with nested contents for UI
                    if ($choiceItem->item->contents->isNotEmpty()) {
                        $contents = [];
                        foreach ($choiceItem->item->contents as $content) {
                            if ($content->item) {
                                $contents[] = [
                                    'full_slug' => $content->item->full_slug,
                                    'name' => $content->item->name,
                                    'quantity' => $content->quantity ?? 1,
                                ];
                            }
                        }
                        $items[] = [
                            'full_slug' => $choiceItem->item->full_slug,
                            'name' => $choiceItem->item->name,
                            'quantity' => $choiceItem->quantity ?? 1,
                            'is_fixed' => true,
                            'is_pack' => true,
                            'contents' => $contents,
                        ];
                    } else {
                        // Regular item - always granted when option selected
                        $items[] = [
                            'full_slug' => $choiceItem->item->full_slug,
                            'name' => $choiceItem->item->name,
                            'quantity' => $choiceItem->quantity ?? 1,
                            'is_fixed' => true,
                        ];
                    }
                }
            }
        }

        return [
            'items' => $items,
            'is_category' => $isCategory,
            'category_item_count' => $categoryItemCount,
        ];
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
