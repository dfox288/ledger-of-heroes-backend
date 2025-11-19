<?php

namespace App\Services\Matching;

use App\Models\Item;
use Illuminate\Support\Str;

class ItemMatchingService
{
    private static ?array $itemsCache = null;

    /**
     * Attempt to match an item name to an existing Item in the database.
     * Returns Item model if matched, null otherwise.
     */
    public function matchItem(string $itemName): ?Item
    {
        if (empty(trim($itemName))) {
            return null;
        }

        // Initialize cache on first use
        if (self::$itemsCache === null) {
            $this->initializeCache();
        }

        $normalized = $this->normalizeItemName($itemName);

        // 1. Try exact match on normalized name
        if (isset(self::$itemsCache['by_normalized'][$normalized])) {
            return self::$itemsCache['by_normalized'][$normalized];
        }

        // 2. Try slug match (e.g., "traveler's clothes" -> "travelers-clothes")
        $slug = Str::slug($itemName);
        if (isset(self::$itemsCache['by_slug'][$slug])) {
            return self::$itemsCache['by_slug'][$slug];
        }

        // 3. Try partial match (item name contains or is contained by database name)
        foreach (self::$itemsCache['items'] as $item) {
            // Check if normalized names overlap significantly
            if ($this->isPartialMatch($normalized, $this->normalizeItemName($item->name))) {
                return $item;
            }
        }

        // No match found
        return null;
    }

    /**
     * Initialize the items cache from database.
     */
    private function initializeCache(): void
    {
        try {
            $items = Item::all();

            self::$itemsCache = [
                'items' => $items,
                'by_normalized' => [],
                'by_slug' => [],
            ];

            foreach ($items as $item) {
                $normalized = $this->normalizeItemName($item->name);
                $slug = Str::slug($item->name);

                self::$itemsCache['by_normalized'][$normalized] = $item;
                self::$itemsCache['by_slug'][$slug] = $item;
            }
        } catch (\Exception $e) {
            // Database not available (unit tests) - use empty cache
            self::$itemsCache = [
                'items' => collect(),
                'by_normalized' => [],
                'by_slug' => [],
            ];
        }
    }

    /**
     * Normalize item name for matching.
     * Removes articles, possessives, and standardizes casing.
     */
    private function normalizeItemName(string $name): string
    {
        $normalized = strtolower(trim($name));

        // Remove possessives ('s)
        $normalized = str_replace("'s", 's', $normalized);

        // Remove articles at start
        $normalized = preg_replace('/^(a|an|the)\s+/', '', $normalized);

        // Remove "set of" prefix
        $normalized = preg_replace('/^set\s+of\s+/', '', $normalized);

        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Check if two normalized names are partial matches.
     * Returns true if one contains the other and the overlap is significant.
     */
    private function isPartialMatch(string $name1, string $name2): bool
    {
        // Require at least 5 characters for partial matching
        if (strlen($name1) < 5 || strlen($name2) < 5) {
            return false;
        }

        // Check if one contains the other
        if (str_contains($name1, $name2) || str_contains($name2, $name1)) {
            // Ensure the match is at least 70% of the shorter name
            $shorterLength = min(strlen($name1), strlen($name2));
            $overlap = strlen($name1) === strlen($name2) ? $shorterLength : min(
                strlen($name1),
                strlen($name2)
            );

            return ($overlap / $shorterLength) >= 0.7;
        }

        return false;
    }

    /**
     * Clear the items cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$itemsCache = null;
    }
}
