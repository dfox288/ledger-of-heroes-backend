<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterTrait;

/**
 * Trait for importing random tables embedded in trait descriptions.
 *
 * Handles detection and parsing of pipe-delimited tables like:
 * "d8|1|Result One|2|Result Two|"
 *
 * Used by: RaceImporter, BackgroundImporter, ClassImporter (future)
 *
 * This trait now delegates to ImportsRandomTablesFromText for the actual implementation.
 */
trait ImportsRandomTables
{
    use ImportsRandomTablesFromText;

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
        // Delegate to the generalized trait method
        $this->importRandomTablesFromText($trait, $description, clearExisting: false);
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
