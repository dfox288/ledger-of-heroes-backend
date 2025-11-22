<?php

namespace App\Services\Parsers\Concerns;

trait ParsesItemSpells
{
    /**
     * Parse spell charge costs from spell entry text
     *
     * Examples:
     * - "cure wounds (1 charge per spell level, up to 4th)"
     *   -> min:1, max:4, formula:"1 per spell level"
     *
     * - "lesser restoration (2 charges)"
     *   -> min:2, max:2, formula:null
     *
     * - "detect magic (no charges)"
     *   -> min:0, max:0, formula:null
     *
     * @param  string  $spellText  The spell entry from description
     * @return array ['min' => int|null, 'max' => int|null, 'formula' => string|null]
     */
    protected function parseSpellChargeCost(string $spellText): array
    {
        $result = [
            'min' => null,
            'max' => null,
            'formula' => null,
        ];

        // Pattern 1: "X charge(s) per spell level, up to Yth"
        if (preg_match('/(\d+)\s+charges?\s+per\s+spell\s+level.*?up\s+to\s+(\d+)(?:st|nd|rd|th)/i', $spellText, $matches)) {
            $result['min'] = (int) $matches[1];
            $result['max'] = (int) $matches[1] * (int) $matches[2];
            $result['formula'] = "{$matches[1]} per spell level";

            return $result;
        }

        // Pattern 2: "no charges" or "0 charges"
        if (preg_match('/\b(?:no|0)\s+charges?\b/i', $spellText)) {
            $result['min'] = 0;
            $result['max'] = 0;

            return $result;
        }

        // Pattern 3: Fixed cost "X charge(s)" in parentheses
        if (preg_match('/\((\d+)\s+charges?\)/i', $spellText, $matches)) {
            $cost = (int) $matches[1];
            $result['min'] = $cost;
            $result['max'] = $cost;

            return $result;
        }

        // Pattern 4: "expends X charge(s)"
        if (preg_match('/expends?\s+(\d+)\s+charges?/i', $spellText, $matches)) {
            $cost = (int) $matches[1];
            $result['min'] = $cost;
            $result['max'] = $cost;

            return $result;
        }

        return $result; // All null if no pattern matched
    }

    /**
     * Extract spell names and their charge costs from item description
     *
     * @param  string  $description  Full item description
     * @return array [['spell_name' => 'Cure Wounds', 'charges_cost_min' => 1, ...], ...]
     */
    protected function parseItemSpells(string $description): array
    {
        $spells = [];

        // Pattern 1: "cast one of the following spells: spell1 (cost), spell2 (cost)"
        // Updated regex: Look for "The <word> regains" instead of any period
        // This handles cases where spell lists contain periods (e.g., "restoration (2 charges). or mass cure wounds")
        if (preg_match('/cast\s+(?:one\s+of\s+)?the\s+following\s+spells[^:]*:\s*(.+?)(?:The\s+\w+\s+regains|$)/is', $description, $matches)) {
            $spellList = $matches[1];

            // Extract all "spell name (cost)" patterns
            // This handles commas inside parentheses correctly
            preg_match_all('/([a-z\s\']+?)\s*\(([^)]+)\)/i', $spellList, $spellMatches, PREG_SET_ORDER);

            foreach ($spellMatches as $spellMatch) {
                // Clean up spell name (remove leading "or", commas, periods, etc.)
                $spellName = trim($spellMatch[1]);
                $spellName = preg_replace('/^(?:or|,|\.)\s+/i', '', $spellName);
                $spellName = trim($spellName);

                $costText = '('.$spellMatch[2].')'; // Wrap in parens for parsing

                $costData = $this->parseSpellChargeCost($costText);

                if ($costData['min'] !== null) {
                    $spells[] = [
                        'spell_name' => $spellName,
                        'charges_cost_min' => $costData['min'],
                        'charges_cost_max' => $costData['max'],
                        'charges_cost_formula' => $costData['formula'],
                    ];
                }
            }
        }

        // Pattern 2: "cast the X spell" (single spell pattern)
        // Example: "cast the lightning bolt spell (save DC 15)"
        // Only check if we haven't found spells yet
        if (empty($spells) && preg_match('/cast\s+the\s+([a-z\s\']+?)\s+spell/i', $description, $matches)) {
            $spellName = trim($matches[1]);

            // Try to find charge cost near the spell mention
            // Look for patterns like "For 1 charge" or "expend 1 or more"
            $costData = ['min' => null, 'max' => null, 'formula' => null];

            // Pattern: "For 1 charge, you cast"
            if (preg_match('/For\s+(\d+)\s+charges?,\s+you\s+cast/i', $description, $costMatches)) {
                $costData['min'] = (int) $costMatches[1];
                $costData['max'] = (int) $costMatches[1];
            }
            // Pattern: "expend 1 or more"
            elseif (preg_match('/expend\s+(\d+)\s+or\s+more/i', $description)) {
                $costData['min'] = 1;
                $costData['max'] = null; // Variable cost
            }

            // Only add if we found a cost or if it's worth noting the spell exists
            $spells[] = [
                'spell_name' => $spellName,
                'charges_cost_min' => $costData['min'],
                'charges_cost_max' => $costData['max'],
                'charges_cost_formula' => $costData['formula'],
            ];
        }

        return $spells;
    }
}
