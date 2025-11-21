<?php

namespace App\Services\Importers;

use App\Models\CharacterClass;
use App\Models\Spell;
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
    /**
     * Subclass name aliases for XML → Database mapping.
     *
     * Inherited from SpellImporter for consistency.
     */
    private const SUBCLASS_ALIASES = [
        // Druid Circle of the Land variants
        'Coast' => 'Circle of the Land',
        'Desert' => 'Circle of the Land',
        'Forest' => 'Circle of the Land',
        'Grassland' => 'Circle of the Land',
        'Mountain' => 'Circle of the Land',
        'Swamp' => 'Circle of the Land',
        'Underdark' => 'Circle of the Land',
        'Arctic' => 'Circle of the Land',

        // Common abbreviations
        'Ancients' => 'Oath of the Ancients',
        'Vengeance' => 'Oath of Vengeance',
    ];

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

    /**
     * Add class associations to a spell (without removing existing ones).
     *
     * Logic:
     * - "Fighter (Eldritch Knight)" → Use SUBCLASS (Eldritch Knight)
     * - "Wizard" → Use BASE CLASS (Wizard)
     *
     * @param  Spell  $spell  The spell to add classes to
     * @param  array  $classNames  Array of class names (may include subclasses in parentheses)
     * @return int Number of new class associations added
     */
    private function addClassAssociations(Spell $spell, array $classNames): int
    {
        $newClassIds = [];

        foreach ($classNames as $className) {
            $class = null;

            // Check if subclass is specified in parentheses: "Fighter (Eldritch Knight)"
            if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $className, $matches)) {
                $baseClassName = trim($matches[1]);
                $subclassName = trim($matches[2]);

                // Check if there's an alias mapping for this subclass name
                if (isset(self::SUBCLASS_ALIASES[$subclassName])) {
                    $subclassName = self::SUBCLASS_ALIASES[$subclassName];
                }

                // Try to find the SUBCLASS - try exact match first, then fuzzy match
                $class = CharacterClass::where('name', $subclassName)->first();

                // If exact match fails, try fuzzy match (e.g., "Archfey" -> "The Archfey")
                if (! $class) {
                    $class = CharacterClass::where('name', 'LIKE', "%{$subclassName}%")->first();
                }

                // If subclass still not found, skip (don't fallback to base class)
                if (! $class) {
                    continue;
                }
            } else {
                // No parentheses = use base class
                $class = CharacterClass::where('name', $className)
                    ->whereNull('parent_class_id') // Only match base classes
                    ->first();
            }

            if ($class) {
                $newClassIds[] = $class->id;
            }
        }

        // Get existing class associations
        $existingClassIds = $spell->classes()->pluck('class_id')->toArray();

        // Merge with new class IDs (avoiding duplicates)
        $allClassIds = array_unique(array_merge($existingClassIds, $newClassIds));

        // Sync all class associations
        $spell->classes()->sync($allClassIds);

        // Return count of NEW associations added
        return count($allClassIds) - count($existingClassIds);
    }
}
