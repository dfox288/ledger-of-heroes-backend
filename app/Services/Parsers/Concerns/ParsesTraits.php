<?php

namespace App\Services\Parsers\Concerns;

use SimpleXMLElement;

/**
 * Trait for parsing character traits from XML.
 *
 * Handles <trait> elements with:
 * - name: trait name
 * - text: trait description
 * - category: optional trait category
 * - roll: optional dice rolls (parsed via ParsesRolls)
 *
 * Used by: RaceXmlParser, ClassXmlParser, BackgroundXmlParser
 */
trait ParsesTraits
{
    use ParsesRolls;

    /**
     * Parse trait elements from XML.
     *
     * Extracts trait name, category, description, and embedded rolls.
     * Automatically assigns sort_order based on XML document order.
     *
     * @param  SimpleXMLElement  $element  Parent element containing <trait> children
     * @return array<int, array<string, mixed>> Array of trait data
     */
    protected function parseTraitElements(SimpleXMLElement $element): array
    {
        $traits = [];
        $sortOrder = 0;

        foreach ($element->trait as $traitElement) {
            $traits[] = [
                'name' => (string) $traitElement->name,
                'category' => isset($traitElement['category'])
                    ? (string) $traitElement['category']
                    : null,
                'description' => trim((string) $traitElement->text),
                'rolls' => $this->parseRollElements($traitElement),
                'sort_order' => $sortOrder++,
            ];
        }

        return $traits;
    }
}
