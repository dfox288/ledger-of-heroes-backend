<?php

namespace App\Services\Importers\Concerns;

use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing random tables detected in text descriptions.
 *
 * Handles detection and parsing of pipe-delimited tables like:
 * "d8|1|Result One|2|Result Two|"
 *
 * Used by: ItemImporter, SpellImporter, ImportsRandomTables (for traits)
 *
 * This is a generalized version that works with any polymorphic entity,
 * not just CharacterTrait models.
 */
trait ImportsRandomTablesFromText
{
    /**
     * Import random tables detected in text description.
     *
     * Detects pipe-delimited tables, parses them, and creates
     * RandomTable + RandomTableEntry records linked to the entity.
     *
     * @param  Model  $entity  The polymorphic entity (Item, Spell, CharacterTrait, etc.)
     * @param  string  $text  Description text to parse for tables
     * @param  bool  $clearExisting  Whether to delete existing random tables before importing
     */
    protected function importRandomTablesFromText(Model $entity, string $text, bool $clearExisting = true): void
    {
        // Detect tables in description text
        $detector = new ItemTableDetector;
        $tables = $detector->detectTables($text);

        if (empty($tables)) {
            return;
        }

        // Clear existing random tables if requested
        if ($clearExisting) {
            $entity->randomTables()->delete();
        }

        foreach ($tables as $tableData) {
            $parser = new ItemTableParser;
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type'] ?? null);

            if (empty($parsed['rows'])) {
                continue; // Skip tables with no valid rows
            }

            // Create random table linked to entity
            $table = RandomTable::create([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'table_name' => $parsed['table_name'],
                'dice_type' => $parsed['dice_type'],
            ]);

            // Create table entries
            foreach ($parsed['rows'] as $index => $row) {
                RandomTableEntry::create([
                    'random_table_id' => $table->id,
                    'roll_min' => $row['roll_min'],
                    'roll_max' => $row['roll_max'],
                    'result_text' => $row['result_text'],
                    'sort_order' => $index,
                ]);
            }
        }
    }
}
