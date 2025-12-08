<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing scaling increment from spell "At Higher Levels" text.
 *
 * Handles patterns like:
 * - "the damage increases by 1d6 for each slot level above 3rd" → "1d6"
 * - "both effects increase by 5 for each slot level above 1st" → "5"
 *
 * Used by: SpellXmlParser
 *
 * @see GitHub Issue #198
 */
trait ParsesScalingIncrement
{
    /**
     * Parse scaling increment from "At Higher Levels" text.
     *
     * @param  string|null  $higherLevels  The "At Higher Levels" text
     * @return string|null Dice notation (e.g., "1d6") or flat value (e.g., "5")
     */
    protected function parseScalingIncrement(?string $higherLevels): ?string
    {
        if (empty($higherLevels)) {
            return null;
        }

        // Pattern 1: Dice notation - "increases by 1d6 for each"
        if (preg_match('/increases?\s+by\s+(\d+d\d+)\s+for\s+each/i', $higherLevels, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Flat value - "increase by 5 for each"
        if (preg_match('/increases?\s+by\s+(\d+)\s+for\s+each/i', $higherLevels, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
