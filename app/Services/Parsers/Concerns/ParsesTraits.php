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
 * - max_uses: optional usage limit (parsed from description)
 * - resets_on: optional reset timing (parsed from description)
 *
 * Used by: RaceXmlParser, ClassXmlParser, BackgroundXmlParser
 */
trait ParsesTraits
{
    use ParsesRestTiming;
    use ParsesRolls;
    use ParsesUsageLimits;

    /**
     * Parse trait elements from XML.
     *
     * Extracts trait name, category, description, embedded rolls,
     * and usage limits (max_uses, resets_on) from the description text.
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
            $description = trim((string) $traitElement->text);

            // Parse usage limits from the description text
            $maxUses = $this->parseBaseUses($description);
            $resetsOn = $this->parseResetTiming($description);

            $traits[] = [
                'name' => (string) $traitElement->name,
                'category' => isset($traitElement['category'])
                    ? (string) $traitElement['category']
                    : null,
                'description' => $description,
                'rolls' => $this->parseRollElements($traitElement),
                'sort_order' => $sortOrder++,
                'max_uses' => $maxUses,
                'resets_on' => $resetsOn,
            ];
        }

        return $traits;
    }
}
