<?php

namespace App\Services\Parsers;

/**
 * Parser for "additive" spell XML files that only contain name + class mappings.
 *
 * These files (e.g., spells-phb+dmg.xml, spells-phb+scag.xml) provide additional
 * class/subclass associations for spells already defined in main XML files.
 *
 * Example XML structure:
 * <spell>
 *   <name>Animate Dead</name>
 *   <classes>Cleric (Death Domain), Paladin (Oathbreaker)</classes>
 * </spell>
 */
class SpellClassMappingParser
{
    /**
     * Parse an additive spell XML file and return array of [spell_name => [classes]].
     *
     * @param  string  $xmlFilePath  Path to the XML file
     * @return array Array keyed by spell name, values are arrays of class names
     *
     * @throws \RuntimeException If file cannot be read or parsed
     */
    public function parse(string $xmlFilePath): array
    {
        // XmlLoader handles file existence, reading, and parsing errors
        $xml = XmlLoader::fromFile($xmlFilePath);

        $mappings = [];

        foreach ($xml->spell as $spellNode) {
            $name = (string) $spellNode->name;
            $classesString = (string) $spellNode->classes;

            if (empty($name) || empty($classesString)) {
                continue; // Skip entries without name or classes
            }

            // Parse comma-separated class list: "Cleric (Death Domain), Paladin (Oathbreaker)"
            $classes = array_map('trim', explode(',', $classesString));

            $mappings[$name] = $classes;
        }

        return $mappings;
    }
}
