<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing equipment pack contents from D&D item descriptions.
 *
 * Handles patterns like:
 * - "• a backpack" → ['name' => 'backpack', 'quantity' => 1]
 * - "• 10 torches" → ['name' => 'torch', 'quantity' => 10]
 * - "• 10 days of rations" → ['name' => 'rations (1 day)', 'quantity' => 10]
 * - "• 50 feet of hempen rope" → ['name' => 'hempen rope (50 feet)', 'quantity' => 1]
 *
 * Returns normalized item names that can be matched against the items table.
 */
trait ParsesPackContents
{
    /**
     * Parse pack contents from item description text.
     *
     * @param  string  $description  Item description containing "Includes:" section
     * @return array<int, array{name: string, quantity: int}>
     */
    protected function parsePackContents(string $description): array
    {
        // Only parse descriptions that contain "Includes:"
        if (! str_contains($description, 'Includes:')) {
            return [];
        }

        $contents = [];

        // Extract lines after "Includes:" and before "Source:"
        if (preg_match('/Includes:(.*?)(?:Source:|$)/s', $description, $match)) {
            $contentSection = $match[1];

            // Match bullet points: "• item text"
            if (preg_match_all('/•\s*(.+?)(?=\n|$)/m', $contentSection, $matches)) {
                foreach ($matches[1] as $line) {
                    $parsed = $this->parseContentLine(trim($line));
                    if ($parsed !== null) {
                        $contents[] = $parsed;
                    }
                }
            }
        }

        return $contents;
    }

    /**
     * Parse a single content line into name and quantity.
     *
     * @return array{name: string, quantity: int}|null
     */
    private function parseContentLine(string $line): ?array
    {
        // Remove trailing period if present
        $line = rtrim($line, '.');

        // Special case: "a bag of 1,000 ball bearings"
        if (preg_match('/^an?\s+bag\s+of\s+[\d,]+\s+ball\s+bearings/i', $line)) {
            return ['name' => 'ball bearings (bag of 1,000)', 'quantity' => 1];
        }

        // Special case: "X feet of string" (10 feet of string → string (10 feet))
        if (preg_match('/^(\d+)\s+feet\s+of\s+string/i', $line, $matches)) {
            return ['name' => 'string ('.$matches[1].' feet)', 'quantity' => 1];
        }

        // Special case: "X feet of hempen rope" → hempen rope (50 feet), qty 1
        if (preg_match('/^(\d+)\s+feet(?:\s+of)?\s+hempen\s+rope/i', $line, $matches)) {
            return ['name' => 'hempen rope (50 feet)', 'quantity' => 1];
        }

        // Special case: "X days of rations" or "X days Rations"
        if (preg_match('/^(\d+)\s+days?\s+(?:of\s+)?rations/i', $line, $matches)) {
            return ['name' => 'rations (1 day)', 'quantity' => (int) $matches[1]];
        }

        // Special case: "X flasks of oil"
        if (preg_match('/^(\d+)\s+flasks?\s+of\s+oil/i', $line, $matches)) {
            return ['name' => 'oil (flask)', 'quantity' => (int) $matches[1]];
        }

        // Special case: "X sheets of paper"
        if (preg_match('/^(\d+)\s+sheets?\s+of\s+paper/i', $line, $matches)) {
            return ['name' => 'paper (one sheet)', 'quantity' => (int) $matches[1]];
        }

        // Special case: "X sheets of parchment"
        if (preg_match('/^(\d+)\s+sheets?\s+of\s+parchment/i', $line, $matches)) {
            return ['name' => 'parchment (one sheet)', 'quantity' => (int) $matches[1]];
        }

        // Special case: "a bottle of ink (1-ounce bottle)"
        if (preg_match('/^an?\s+bottle\s+of\s+ink\s*\([^)]+\)/i', $line)) {
            return ['name' => 'ink (1-ounce bottle)', 'quantity' => 1];
        }

        // Special case: "a bottle of ink" (without specification)
        if (preg_match('/^an?\s+bottle\s+of\s+ink/i', $line)) {
            return ['name' => 'ink (1-ounce bottle)', 'quantity' => 1];
        }

        // Special case: "X cases for maps and scrolls"
        if (preg_match('/^(\d+)\s+cases?\s+for\s+maps\s+and\s+scrolls/i', $line, $matches)) {
            return ['name' => 'map or scroll case', 'quantity' => (int) $matches[1]];
        }

        // Special case: "a vial of perfume"
        if (preg_match('/^an?\s+vial\s+of\s+perfume/i', $line)) {
            return ['name' => 'perfume (vial)', 'quantity' => 1];
        }

        // Special case: "X costumes" → costume clothes
        if (preg_match('/^(\d+)\s+costumes?/i', $line, $matches)) {
            return ['name' => 'costume clothes', 'quantity' => (int) $matches[1]];
        }

        // Special case: "a book of lore" → book
        if (preg_match('/^an?\s+book\s+of\s+lore/i', $line)) {
            return ['name' => 'book', 'quantity' => 1];
        }

        // Special case: "a set of fine clothes"
        if (preg_match('/^an?\s+set\s+of\s+fine\s+clothes/i', $line)) {
            return ['name' => 'fine clothes', 'quantity' => 1];
        }

        // Special case: "X blocks of incense"
        if (preg_match('/^(\d+)\s+blocks?\s+of\s+incense/i', $line, $matches)) {
            return ['name' => 'incense', 'quantity' => (int) $matches[1]];
        }

        // Pattern: "X item(s)" where X is a number
        if (preg_match('/^(\d+)\s+(.+)$/i', $line, $matches)) {
            $quantity = (int) $matches[1];
            $name = $this->normalizeItemName($matches[2]);

            return ['name' => $name, 'quantity' => $quantity];
        }

        // Pattern: "a/an item" - quantity of 1
        if (preg_match('/^an?\s+(.+)$/i', $line, $matches)) {
            $name = $this->normalizeItemName($matches[1]);

            return ['name' => $name, 'quantity' => 1];
        }

        // Fallback: just the item name
        $name = $this->normalizeItemName($line);

        return ['name' => $name, 'quantity' => 1];
    }

    /**
     * Normalize item name to match database entries.
     */
    private function normalizeItemName(string $name): string
    {
        // Lowercase for consistent matching
        $name = strtolower(trim($name));

        // Remove trailing 's' for pluralization (torches → torch, candles → candle)
        // But be careful with special cases
        $pluralExceptions = ['clothes', 'mess', 'rations', 'ball bearings'];
        $shouldDepluralize = true;
        foreach ($pluralExceptions as $exception) {
            if (str_contains($name, $exception)) {
                $shouldDepluralize = false;
                break;
            }
        }

        if ($shouldDepluralize && str_ends_with($name, 's') && ! str_ends_with($name, 'ss')) {
            // Handle specific plural patterns
            if (str_ends_with($name, 'ches')) {
                // torches → torch (remove "es")
                $name = substr($name, 0, -2);
            } elseif (str_ends_with($name, 'shes')) {
                // Not common in D&D items, but handle "dishes" → "dish"
                $name = substr($name, 0, -2);
            } else {
                // Regular plural: candles → candle, pitons → piton (remove "s")
                $name = substr($name, 0, -1);
            }
        }

        // Handle "pitons" → "piton" (already handled by above)
        // Handle typo "piton" (singular when should be plural) - keep as is

        return $name;
    }
}
