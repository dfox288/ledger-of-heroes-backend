<?php

namespace App\Services\Importers\Concerns;

use App\Enums\DataTableType;
use App\Models\EntityDataTable;
use App\Models\EntityDataTableEntry;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing data tables detected in text descriptions.
 *
 * Handles detection and parsing of pipe-delimited tables like:
 * "d8|1|Result One|2|Result Two|"
 *
 * Used by: ItemImporter, SpellImporter, ImportsDataTables (for traits)
 *
 * This is a generalized version that works with any polymorphic entity,
 * not just CharacterTrait models.
 */
trait ImportsDataTablesFromText
{
    /**
     * Import data tables detected in text description.
     *
     * Detects pipe-delimited tables, parses them, and creates
     * EntityDataTable + EntityDataTableEntry records linked to the entity.
     *
     * @param  Model  $entity  The polymorphic entity (Item, Spell, CharacterTrait, etc.)
     * @param  string  $text  Description text to parse for tables
     * @param  bool  $clearExisting  Whether to delete existing data tables before importing
     */
    protected function importDataTablesFromText(Model $entity, string $text, bool $clearExisting = true): void
    {
        // Detect tables in description text
        $detector = new ItemTableDetector;
        $tables = $detector->detectTables($text);

        if (empty($tables)) {
            return;
        }

        // Clear existing data tables if requested
        if ($clearExisting) {
            $entity->dataTables()->delete();
        }

        foreach ($tables as $tableData) {
            $parser = new ItemTableParser;
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type'] ?? null);

            if (empty($parsed['rows'])) {
                continue; // Skip tables with no valid rows
            }

            // Determine table type based on content
            $tableType = $this->determineTableType($parsed);

            // Create data table linked to entity
            $table = EntityDataTable::create([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'table_name' => $parsed['table_name'],
                'dice_type' => $parsed['dice_type'],
                'table_type' => $tableType,
            ]);

            // Create table entries
            foreach ($parsed['rows'] as $index => $row) {
                EntityDataTableEntry::create([
                    'entity_data_table_id' => $table->id,
                    'roll_min' => $row['roll_min'],
                    'roll_max' => $row['roll_max'],
                    'result_text' => $row['result_text'],
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /**
     * Determine the table type based on parsed content.
     */
    protected function determineTableType(array $parsed): DataTableType
    {
        $tableName = $parsed['table_name'] ?? '';
        $hasDice = ! empty($parsed['dice_type']);

        // Check for damage tables
        if (stripos($tableName, 'Damage') !== false) {
            return DataTableType::DAMAGE;
        }

        // Check for modifier tables
        if (stripos($tableName, 'Modifier') !== false) {
            return DataTableType::MODIFIER;
        }

        // Check for progression tables
        if (
            stripos($tableName, 'Spells Known') !== false ||
            stripos($tableName, 'Cantrips Known') !== false ||
            stripos($tableName, 'Exhaustion') !== false
        ) {
            return DataTableType::PROGRESSION;
        }

        // If no dice, it's a lookup table
        if (! $hasDice) {
            return DataTableType::LOOKUP;
        }

        // Default to random table
        return DataTableType::RANDOM;
    }
}
