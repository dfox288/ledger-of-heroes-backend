<?php

namespace App\Services\Parsers\Concerns;

use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;

/**
 * Trait for parsing random tables embedded in entity descriptions.
 *
 * Handles pipe-delimited tables like:
 * - d8 | Effect
 * - 1 | Red: Fire damage
 * - 2-6 | Orange: Acid damage
 *
 * Used by: Spells (Prismatic Spray, Confusion), Items, Backgrounds, etc.
 */
trait ParsesRandomTables
{
    /**
     * Parse random tables embedded in spell description.
     *
     * Uses ItemTableDetector and ItemTableParser to find pipe-delimited tables
     * like those in Prismatic Spray (d8 roll tables).
     *
     * @param  string  $description  Spell description text
     * @return array<int, array{table_name: string, dice_type: string|null, entries: array}>
     */
    protected function parseRandomTables(string $description): array
    {
        $detector = new ItemTableDetector;
        $detectedTables = $detector->detectTables($description);

        if (empty($detectedTables)) {
            return [];
        }

        $tables = [];
        $parser = new ItemTableParser;

        foreach ($detectedTables as $tableData) {
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type'] ?? null);

            if (empty($parsed['rows'])) {
                continue; // Skip tables with no valid rows
            }

            $tables[] = [
                'table_name' => $parsed['table_name'],
                'dice_type' => $parsed['dice_type'],
                'entries' => $parsed['rows'],
            ];
        }

        return $tables;
    }
}
