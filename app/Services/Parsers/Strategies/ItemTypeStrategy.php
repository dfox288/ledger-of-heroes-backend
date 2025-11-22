<?php

namespace App\Services\Parsers\Strategies;

use SimpleXMLElement;

interface ItemTypeStrategy
{
    /**
     * Determine if this strategy applies to the given item.
     *
     * @param  array  $baseData  The base item data parsed by ItemXmlParser
     * @param  SimpleXMLElement  $xml  The raw XML element
     * @return bool True if this strategy should enhance the item data
     */
    public function appliesTo(array $baseData, SimpleXMLElement $xml): bool;

    /**
     * Enhance modifiers array with type-specific modifiers.
     *
     * @param  array  $modifiers  Current modifiers array
     * @param  array  $baseData  The base item data
     * @param  SimpleXMLElement  $xml  The raw XML element
     * @return array Enhanced modifiers array
     */
    public function enhanceModifiers(array $modifiers, array $baseData, SimpleXMLElement $xml): array;

    /**
     * Enhance abilities array with type-specific abilities.
     *
     * @param  array  $abilities  Current abilities array
     * @param  array  $baseData  The base item data
     * @param  SimpleXMLElement  $xml  The raw XML element
     * @return array Enhanced abilities array
     */
    public function enhanceAbilities(array $abilities, array $baseData, SimpleXMLElement $xml): array;

    /**
     * Extract type-specific relationship data (e.g., spell references).
     *
     * Returns an associative array that will be merged with base data.
     * Example: ['spell_references' => [...]]
     *
     * @param  array  $baseData  The base item data
     * @param  SimpleXMLElement  $xml  The raw XML element
     * @return array Relationship data to merge with base data
     */
    public function enhanceRelationships(array $baseData, SimpleXMLElement $xml): array;

    /**
     * Extract metadata about the parsing process (warnings, metrics).
     *
     * @return array Metadata array with 'warnings' and 'metrics' keys
     */
    public function extractMetadata(): array;
}
