<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterTrait;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;

/**
 * Trait for importing random tables embedded in trait descriptions.
 *
 * Handles detection and parsing of pipe-delimited tables like:
 * "d8|1|Result One|2|Result Two|"
 *
 * Used by: RaceImporter, BackgroundImporter, ClassImporter (future)
 */
trait ImportsRandomTables
{
    /**
     * Import random tables embedded in a trait's description.
     *
     * Detects pipe-delimited tables, parses them, and creates
     * RandomTable + RandomTableEntry records linked to the trait.
     *
     * @param  CharacterTrait  $trait  The trait containing the table
     * @param  string  $description  Trait description text
     */
    protected function importTraitTables(CharacterTrait $trait, string $description): void
    {
        // Detect tables in trait description
        $detector = new ItemTableDetector;
        $tables = $detector->detectTables($description);

        if (empty($tables)) {
            return;
        }

        foreach ($tables as $tableData) {
            $parser = new ItemTableParser;
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type'] ?? null);

            if (empty($parsed['rows'])) {
                continue; // Skip tables with no valid rows
            }

            // Create random table linked to trait
            $table = RandomTable::create([
                'reference_type' => CharacterTrait::class,
                'reference_id' => $trait->id,
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

    /**
     * Import random tables from all traits of an entity.
     *
     * Convenience method to import tables from multiple traits at once.
     *
     * @param  array  $createdTraits  Array of CharacterTrait models
     * @param  array  $traitsData  Original trait data with descriptions
     */
    protected function importRandomTablesFromTraits(array $createdTraits, array $traitsData): void
    {
        foreach ($createdTraits as $index => $trait) {
            if (isset($traitsData[$index]['description'])) {
                $this->importTraitTables($trait, $traitsData[$index]['description']);
            }
        }
    }
}
