<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntityItem;
use App\Models\Item;
use Illuminate\Support\Facades\Log;

/**
 * Provides functionality to match equipment descriptions to Item records.
 *
 * Used by importers that reference equipment/items by name (ClassImporter, RaceImporter, etc.).
 */
trait ImportsEntityItems
{
    /**
     * Import structured choice_items for an EntityItem.
     *
     * @param  EntityItem  $entityItem  The parent entity item
     * @param  array  $choiceItems  Array of choice items from parser
     */
    protected function importChoiceItems(EntityItem $entityItem, array $choiceItems): void
    {
        foreach ($choiceItems as $index => $choiceItem) {
            $data = [
                'quantity' => $choiceItem['quantity'] ?? 1,
                'sort_order' => $index,
            ];

            if ($choiceItem['type'] === 'category') {
                // Use MatchesProficiencyCategories trait (must be used by importer class)
                $profType = $this->matchProficiencyCategory($choiceItem['value']);
                $data['proficiency_type_id'] = $profType?->id;

                if ($profType === null) {
                    Log::warning("Could not match proficiency category: '{$choiceItem['value']}'");
                }
            } else {
                // type === 'item'
                $item = $this->matchItemByDescription($choiceItem['value']);
                $data['item_id'] = $item?->id;

                if ($item === null) {
                    Log::warning("Could not match item: '{$choiceItem['value']}'");
                }
            }

            $entityItem->choiceItems()->create($data);
        }
    }

    /**
     * Match an equipment description to an Item record.
     *
     * Handles common patterns like:
     * - Articles: "a rapier" → "Rapier"
     * - Compound items: "shortbow and quiver of arrows (20)" → "Shortbow"
     * - Quantities: "two dagger" → "Dagger"
     * - Plurals: "javelins" → "Javelin"
     *
     * @param  string  $description  Equipment description from XML
     * @return Item|null Matched item or null if no match found
     */
    protected function matchItemByDescription(string $description): ?Item
    {
        // Remove leading articles (a, an, the)
        $cleanName = preg_replace('/^(a|an|the)\s+/i', '', trim($description));

        // Remove quantity words (two, three, etc.)
        $cleanName = preg_replace('/^(two|three|four|five|six|seven|eight|nine|ten|twenty)\s+/i', '', $cleanName);

        // Extract first item name before "and" or "or" (handles compound items)
        // "shortbow and quiver of arrows (20)" → "shortbow"
        if (preg_match('/^([^,\(]+?)(?:\s+(?:and|or)\s+|\(|,|$)/', $cleanName, $match)) {
            $itemName = trim($match[1]);
        } else {
            $itemName = $cleanName;
        }

        // Try exact match first (case-insensitive)
        $item = Item::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();

        if ($item) {
            return $item;
        }

        // Try removing trailing 's' for plurals: "javelins" → "javelin"
        if (str_ends_with(strtolower($itemName), 's')) {
            $singular = substr($itemName, 0, -1);
            $item = Item::whereRaw('LOWER(name) = ?', [strtolower($singular)])->first();

            if ($item) {
                Log::info("Matched plural item '{$description}' to '{$item->name}' (singular form)");

                return $item;
            }
        }

        // Try fuzzy match - prefer non-magic items for base equipment
        // First try: starts with item name, non-magic items first
        $item = Item::where('name', 'LIKE', $itemName.'%')
            ->orderByRaw('is_magic ASC')  // Non-magic first (0 before 1)
            ->orderBy('name')  // Then alphabetically for consistency
            ->first();

        if ($item) {
            Log::info("Fuzzy matched item '{$description}' to '{$item->name}'");

            return $item;
        }

        // Fallback: contains item name anywhere
        $item = Item::where('name', 'LIKE', '%'.$itemName.'%')
            ->orderByRaw('is_magic ASC')
            ->orderBy('name')
            ->first();

        if ($item) {
            Log::info("Fuzzy matched item (contains) '{$description}' to '{$item->name}'");

            return $item;
        }

        // No match found - log for analysis
        Log::warning("Could not match equipment description to item: '{$description}' (cleaned: '{$itemName}')");

        return null;
    }
}
