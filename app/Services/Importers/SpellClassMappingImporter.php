<?php

namespace App\Services\Importers;

use App\Models\Spell;
use App\Services\Importers\Concerns\ImportsClassAssociations;
use App\Services\Parsers\SpellClassMappingParser;
use Illuminate\Support\Str;

/**
 * Imports additional class/subclass associations for existing spells.
 *
 * Handles "additive" XML files (e.g., spells-phb+dmg.xml) that only contain
 * spell names and class lists, without full spell definitions.
 *
 * Strategy:
 * 1. Parse XML to get [spell_name => [classes]]
 * 2. Find existing spell by name (fuzzy match on slug)
 * 3. Add new class associations WITHOUT removing existing ones
 */
class SpellClassMappingImporter
{
    use ImportsClassAssociations;

    public function __construct(
        private SpellClassMappingParser $parser
    ) {}

    /**
     * Import class mappings from an additive XML file.
     *
     * @param  string  $xmlFilePath  Path to the XML file
     * @return array Statistics: ['processed' => int, 'spells_found' => int, 'classes_added' => int, 'spells_not_found' => array]
     */
    public function import(string $xmlFilePath): array
    {
        $mappings = $this->parser->parse($xmlFilePath);

        $stats = [
            'processed' => 0,
            'spells_found' => 0,
            'classes_added' => 0,
            'spells_not_found' => [],
        ];

        foreach ($mappings as $spellName => $classNames) {
            $stats['processed']++;

            // Find spell by name (try exact slug match first, then fuzzy)
            $spell = $this->findSpellByName($spellName);

            if (! $spell) {
                $stats['spells_not_found'][] = $spellName;

                continue;
            }

            $stats['spells_found']++;

            // Add class associations (without removing existing ones)
            $classesAdded = $this->addClassAssociations($spell, $classNames);
            $stats['classes_added'] += $classesAdded;
        }

        return $stats;
    }

    /**
     * Find a spell by name using slug-based matching.
     *
     * @param  string  $spellName  The spell name from XML
     * @return Spell|null The found spell, or null if not found
     */
    private function findSpellByName(string $spellName): ?Spell
    {
        $slug = Str::slug($spellName);

        // Try exact slug match first
        $spell = Spell::where('slug', $slug)->first();

        if ($spell) {
            return $spell;
        }

        // Try fuzzy match on name (handles variations like "Leomund's Secret Chest" vs "Secret Chest")
        return Spell::where('name', 'LIKE', "%{$spellName}%")->first();
    }
}
