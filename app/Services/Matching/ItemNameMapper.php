<?php

namespace App\Services\Matching;

class ItemNameMapper
{
    /**
     * Hardcoded mappings from common background equipment names
     * to canonical item names in the database.
     *
     * Use this for:
     * - Abbreviations (gp -> Gold (gp))
     * - Common variants (belt pouch -> Pouch)
     * - Narrative descriptions -> actual items
     */
    private const MAPPINGS = [
        // Currency
        'gp' => 'Gold (gp)',
        'sp' => 'Silver (sp)',
        'cp' => 'Copper (cp)',
        'ep' => 'Electrum (ep)',
        'pp' => 'Platinum (pp)',

        // Common variants
        'belt pouch' => 'Pouch',
        'holy symbol' => 'Holy Symbol',
        'prayer book' => 'Prayer Book',
        'prayer wheel' => 'Prayer Wheel',
    ];

    /**
     * Map a raw item name to its canonical form if a mapping exists.
     * Returns the mapped name, or the original name if no mapping found.
     */
    public function map(string $itemName): string
    {
        $normalized = strtolower(trim($itemName));

        return self::MAPPINGS[$normalized] ?? $itemName;
    }

    /**
     * Check if a mapping exists for the given item name.
     */
    public function hasMapping(string $itemName): bool
    {
        $normalized = strtolower(trim($itemName));

        return isset(self::MAPPINGS[$normalized]);
    }

    /**
     * Get all mappings (useful for testing/debugging).
     */
    public function getAllMappings(): array
    {
        return self::MAPPINGS;
    }
}
