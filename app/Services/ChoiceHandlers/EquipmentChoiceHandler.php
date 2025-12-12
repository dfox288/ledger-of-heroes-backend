<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\EntityChoice;
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

        // Get equipment choices from unified entity_choices table
        $equipmentChoices = $primaryClass->equipmentChoices()
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

                // Build items for this option from EntityChoice records
                $optionData = $this->buildOptionItemsFromEntityChoice($optionItems);

                // Get label from EntityChoice description field (e.g., "a rapier")
                $label = $optionItems->first()?->description ?? '';

                $builtOptions[] = [
                    'option' => $optionLetter,
                    'label' => $label,
                    'items' => $optionData['items'],
                    'is_category' => $optionData['is_category'],
                    'category_item_count' => $optionData['category_item_count'],
                    'select_count' => $optionData['select_count'],
                ];
            }

            // Quantity is always 1 for equipment choices (pick one option from a, b, c, etc.)
            $quantity = 1;

            // Calculate remaining and selected from existing equipment
            $selection = $existingSelections[$choiceGroup] ?? null;
            $remaining = $selection ? 0 : $quantity;
            $selected = $selection ? [$selection['option']] : [];

            $choice = new PendingChoice(
                id: $this->generateChoiceId('equipment', 'class', $primaryClass->slug, 1, $choiceGroup),
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
            $validSlugs = array_column($nonFixedItems, 'slug');

            // Grant: selected items from category + all fixed items
            $itemsToGrant = array_filter($foundOption['items'], function ($item) use ($specificItems) {
                // Always include fixed items (e.g., shield in "martial weapon and shield")
                if ($item['is_fixed'] ?? false) {
                    return true;
                }

                // Include non-fixed items only if they're in item_selections
                return in_array($item['slug'], $specificItems, true);
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
                    'from_pack' => $item['slug'],
                ]);

                foreach ($item['contents'] as $contentItem) {
                    CharacterEquipment::create([
                        'character_id' => $character->id,
                        'item_slug' => $contentItem['slug'],
                        'quantity' => $contentItem['quantity'],
                        'equipped' => false,
                        'custom_description' => $packMetadata,
                    ]);
                }
            } else {
                // Regular item
                CharacterEquipment::create([
                    'character_id' => $character->id,
                    'item_slug' => $item['slug'],
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
     * Build the items array for an equipment option from EntityChoice records.
     *
     * Handles two types of EntityChoice:
     * - Category (target_type='proficiency_type'): "any simple weapon" - user picks one, is_fixed=false
     * - Specific item (target_type='item'): "a shield" - always granted, is_fixed=true
     *
     * The is_fixed flag determines behavior during resolution:
     * - is_fixed=false: User must select via item_selections
     * - is_fixed=true: Always granted when option is selected
     *
     * @return array{items: array, is_category: bool, category_item_count: int, select_count: int}
     */
    private function buildOptionItemsFromEntityChoice(Collection $optionChoices): array
    {
        $items = [];
        $isCategory = false;
        $categoryItemCount = 0;
        $selectCount = 1; // Default: select 1 item from category

        foreach ($optionChoices as $entityChoice) {
            $quantity = $entityChoice->constraints['quantity'] ?? 1;

            // Category choice (e.g., "any simple weapon") - user picks from list
            if ($entityChoice->target_type === 'proficiency_type' && $entityChoice->target_slug) {
                $profType = ProficiencyType::where('slug', $entityChoice->target_slug)->first();
                if ($profType) {
                    $categoryItems = $this->getItemsForProficiencyType($profType);
                    $isCategory = true;
                    $categoryItemCount = $categoryItems->count();
                    $selectCount = $quantity;

                    foreach ($categoryItems as $item) {
                        $items[] = [
                            'slug' => $item->slug,
                            'name' => $item->name,
                            'quantity' => 1, // Each selection grants 1 of the chosen item
                            'is_fixed' => false, // User must select from category
                        ];
                    }
                }
            } elseif ($entityChoice->target_type === 'item' && $entityChoice->target_slug) {
                // Specific item
                $item = Item::where('slug', $entityChoice->target_slug)->with('contents.item')->first();
                if ($item) {
                    // Pack item - keep pack structure with nested contents for UI
                    if ($item->contents->isNotEmpty()) {
                        $contents = [];
                        foreach ($item->contents as $content) {
                            if ($content->item) {
                                $contents[] = [
                                    'slug' => $content->item->slug,
                                    'name' => $content->item->name,
                                    'quantity' => $content->quantity ?? 1,
                                ];
                            }
                        }
                        $items[] = [
                            'slug' => $item->slug,
                            'name' => $item->name,
                            'quantity' => $quantity,
                            'is_fixed' => true,
                            'is_pack' => true,
                            'contents' => $contents,
                        ];
                    } else {
                        // Regular item - always granted when option selected
                        $items[] = [
                            'slug' => $item->slug,
                            'name' => $item->name,
                            'quantity' => $quantity,
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
            'select_count' => $selectCount,
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
     *   - Find Items with a proficiency linking to "Musical Instruments"
     *
     * Items are linked to proficiency types via the entity_proficiencies table.
     * For example, "Club" item has proficiencies: "Simple Weapons" and "Club".
     */
    private function getItemsForProficiencyType(ProficiencyType $proficiencyType): Collection
    {
        // Find items that have a proficiency relationship to this proficiency type
        // This works for weapon categories (Simple Weapons, Martial Weapons)
        // and instrument categories (Musical Instruments)
        return Item::whereHas('proficiencies', function ($query) use ($proficiencyType) {
            $query->where('proficiency_type_id', $proficiencyType->id);
        })
            ->where('is_magic', false)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
}
