<?php

namespace App\Services\Parsers\Concerns;

use SimpleXMLElement;

/**
 * Trait for parsing dice roll elements from XML.
 *
 * Handles <roll> elements with:
 * - formula: dice notation (e.g., "2d6", "1d8+5")
 * - description: optional roll description (attribute)
 * - level: optional character/spell level requirement (attribute)
 *
 * Used by: All entity parsers that have abilities/effects
 */
trait ParsesRolls
{
    /**
     * Parse roll elements from XML.
     *
     * Extracts dice formulas, descriptions, and level requirements.
     *
     * @param  SimpleXMLElement  $element  Element containing <roll> children
     * @return array<int, array<string, mixed>> Array of roll data
     */
    protected function parseRollElements(SimpleXMLElement $element): array
    {
        $rolls = [];

        foreach ($element->roll as $rollElement) {
            $rolls[] = [
                'description' => isset($rollElement['description'])
                    ? (string) $rollElement['description']
                    : null,
                'formula' => (string) $rollElement,
                'level' => isset($rollElement['level'])
                    ? (int) $rollElement['level']
                    : null,
            ];
        }

        return $rolls;
    }
}
