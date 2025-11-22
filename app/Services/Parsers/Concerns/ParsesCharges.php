<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing magic item charge mechanics from D&D item descriptions.
 *
 * Handles patterns like:
 * - "This wand has 3 charges"
 * - "It regains 1d6+1 expended charges daily at dawn"
 * - "The wand regains all expended charges daily at dawn"
 * - "regains 1d3 expended charges after a long rest"
 *
 * Extracts:
 * - charges_max: Total charge capacity
 * - recharge_formula: How many charges regenerate ("1d6+1", "all", "1d3", "1d20")
 * - recharge_timing: When charges regenerate ("dawn", "dusk", "short rest", "long rest")
 */
trait ParsesCharges
{
    /**
     * Parse charge mechanics from item description text.
     *
     * @param  string  $text  Item description containing charge information
     * @return array{charges_max: ?int, recharge_formula: ?string, recharge_timing: ?string}
     */
    protected function parseCharges(string $text): array
    {
        $charges = [
            'charges_max' => null,
            'recharge_formula' => null,
            'recharge_timing' => null,
        ];

        // Pattern 1a: "has XdY+Z charges" (dice formula for variable charges)
        // Examples: "has 1d4-1 charges", "has 1d6+2 charges"
        // Note: Store as string formula, not int
        if (preg_match('/(has|starts with|contains)\s+([\dd\s\+\-]+)\s+charges?/i', $text, $matches)) {
            $formula = strtolower(str_replace(' ', '', $matches[2]));

            // If it's a dice formula, store as string; otherwise parse as int
            if (preg_match('/\d+d\d+/', $formula)) {
                $charges['charges_max'] = $formula;
            } else {
                $charges['charges_max'] = (int) $matches[2];
            }
        }

        // Pattern 2: "regains XdY+Z expended charges" (dice-based recharge)
        // Examples: "regains 1d6+1 expended charges", "regains 1d3 expended charges"
        // Note: Handles spaces in formula like "1d6 + 1" → "1d6+1"
        if (preg_match('/regains?\s+([\dd\s\+\-]+)\s+expended\s+charges?/i', $text, $matches)) {
            // Remove spaces from formula: "1d6 + 1" → "1d6+1"
            $charges['recharge_formula'] = strtolower(str_replace(' ', '', $matches[1]));
        }

        // Pattern 3: "regains all expended charges" (full recharge)
        // Examples: "The wand regains all expended charges daily at dawn"
        if (preg_match('/regains?\s+all\s+expended\s+charges?/i', $text)) {
            $charges['recharge_formula'] = 'all';
        }

        // Pattern 4: "daily at dawn|dusk" or "until the next dawn|dusk"
        // Examples: "daily at dawn", "daily at dusk", "until the next dawn"
        if (preg_match('/(?:daily\s+at|until\s+the\s+next)\s+(dawn|dusk)/i', $text, $matches)) {
            $charges['recharge_timing'] = strtolower($matches[1]);
        }

        // Pattern 5: "after a short|long rest" (rest-based recharge)
        // Examples: "after a long rest", "after a short rest"
        if (preg_match('/after\s+a\s+(short|long)\s+rest/i', $text, $matches)) {
            $charges['recharge_timing'] = strtolower($matches[1]).' rest';
        }

        // Pattern 6: "regain X charges after" (alternative phrasing)
        // Examples: "regain 1d3 charges after a long rest"
        if ($charges['recharge_formula'] === null && preg_match('/regains?\s+([\dd\+\-]+)\s+charges?/i', $text, $matches)) {
            $charges['recharge_formula'] = strtolower($matches[1]);
        }

        return $charges;
    }
}
