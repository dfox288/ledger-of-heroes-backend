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
     * @throws \Exception If file cannot be read or parsed
     */
    public function parse(string $xmlFilePath): array
    {
        if (! file_exists($xmlFilePath)) {
            throw new \Exception("XML file not found: {$xmlFilePath}");
        }

        $xmlContent = file_get_contents($xmlFilePath);
        if ($xmlContent === false) {
            throw new \Exception("Failed to read XML file: {$xmlFilePath}");
        }

        // Suppress XML parsing errors and handle them manually
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $errorMessage = $errors[0]->message ?? 'Unknown XML parsing error';
            throw new \Exception("Failed to parse XML: {$errorMessage}");
        }

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
