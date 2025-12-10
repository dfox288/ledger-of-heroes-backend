<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing feature usage limits from D&D text descriptions.
 *
 * Handles patterns like:
 * - "You have 3 luck points" → base_uses: 3
 * - "You gain one superiority die" → base_uses: 1
 * - "Once you cast it, you must finish a long rest" → base_uses: 1
 * - "use this feature 2 times" → base_uses: 2
 * - "twice before" → base_uses: 2
 * - "a number of times equal to your proficiency bonus" → uses_formula: 'proficiency'
 *
 * Used by: FeatXmlParser
 */
trait ParsesUsageLimits
{
    use ConvertsWordNumbers;

    /**
     * Parse base uses count from feature description text.
     *
     * @param  string  $text  Feature description containing usage info
     */
    protected function parseBaseUses(string $text): ?int
    {
        $lowerText = strtolower($text);

        // Pattern 1: "You have N [word] points" (e.g., "You have 3 luck points")
        if (preg_match('/you have (\d+) \w+ points?/i', $text, $match)) {
            return (int) $match[1];
        }

        // Pattern 2: "You gain one/two/etc [noun]" (e.g., "You gain one superiority die")
        if (preg_match('/you gain (one|two|three|four|five|\d+) /i', $text, $match)) {
            if (is_numeric($match[1])) {
                return (int) $match[1];
            }

            return $this->wordToNumber($match[1]);
        }

        // Pattern 3: "N times" with number (e.g., "2 times", "three times")
        if (preg_match('/(\d+) times?\b/i', $text, $match)) {
            return (int) $match[1];
        }

        // Pattern 4: Word number times (e.g., "twice", "three times")
        if (preg_match('/\b(once|twice|thrice)\b/i', $text, $match)) {
            $wordMap = ['once' => 1, 'twice' => 2, 'thrice' => 3];

            return $wordMap[strtolower($match[1])];
        }

        // Pattern 5: Word number + "times" (e.g., "two times", "three times")
        if (preg_match('/\b(one|two|three|four|five) times?\b/i', $text, $match)) {
            return $this->wordToNumber($match[1]);
        }

        // Pattern 6: "Once you [verb]...finish a rest" implies 1 use
        // This is a weaker pattern, so check it after explicit counts
        if (preg_match('/once you (cast|use)/i', $lowerText) &&
            preg_match('/finish a (short|long) rest/i', $lowerText)) {
            return 1;
        }

        return null;
    }

    /**
     * Parse uses formula for dynamic use counts.
     *
     * @param  string  $text  Feature description
     * @return string|null Formula identifier ('proficiency', 'ability_modifier', etc.)
     */
    protected function parseUsesFormula(string $text): ?string
    {
        // Pattern: "number of times equal to your proficiency bonus"
        if (preg_match('/number of times equal to your proficiency bonus/i', $text)) {
            return 'proficiency';
        }

        // Pattern: "times equal to [ability] modifier"
        if (preg_match('/times equal to (?:your )?(\w+) modifier/i', $text, $match)) {
            return 'ability_modifier';
        }

        return null;
    }
}
