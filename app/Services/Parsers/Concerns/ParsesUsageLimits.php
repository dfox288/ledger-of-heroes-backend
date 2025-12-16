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
 * - "you can't use it again until you finish a rest" → base_uses: 1
 * - "you regain the ability when you finish a rest" → base_uses: 1
 * - "a number of times equal to your proficiency bonus" → uses_formula: 'proficiency'
 *
 * Used by: FeatXmlParser, ParsesTraits (for racial traits)
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

        // Pattern 6: "Once you [verb]...must finish a rest" implies 1 use
        // Tightened to require "must" or "before" to connect the clauses
        if (preg_match('/once you (cast|use).*?(must|before).*?finish a (short|long) rest/is', $text)) {
            return 1;
        }

        // Pattern 7: "you can't/cannot use it/this again until you finish/complete a rest" implies 1 use
        // Common in racial traits like Breath Weapon, Relentless Endurance, Hidden Step
        if (preg_match('/(?:can\'t|cannot) use (?:it|this|this feature|this trait) again until you (?:finish|complete) a/i', $text)) {
            return 1;
        }

        // Pattern 8: "you regain the ability to do so when you finish a rest" implies 1 use
        // Common in racial traits like Fey Step
        if (preg_match('/you regain the ability (?:to do so )?when you finish a/i', $text)) {
            return 1;
        }

        // Pattern 9: "you can't cast it again with this trait until" implies 1 use
        // Common in racial spellcasting like Firbolg Magic
        if (preg_match('/can\'t cast it again (?:with this trait )?until you finish a/i', $text)) {
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

    /**
     * Parse counter name from feature description.
     *
     * Extracts names like "luck points" or "superiority die" from text.
     * Falls back to null if no specific name found.
     *
     * @param  string  $text  Feature description
     * @return string|null Extracted counter name (e.g., "Luck Points")
     */
    protected function parseCounterName(string $text): ?string
    {
        // Pattern: "You have N [word] points" → extract "word points"
        if (preg_match('/you have \d+ (\w+ points?)/i', $text, $match)) {
            return ucwords($match[1]);
        }

        // Pattern: "You gain one superiority die/dice"
        if (preg_match('/you gain (?:one|two|\d+) (superiority di(?:ce|e))/i', $text, $match)) {
            return ucwords($match[1]);
        }

        // Pattern: "You gain N sorcery points"
        if (preg_match('/you gain (?:one|two|\d+) (sorcery points?)/i', $text, $match)) {
            return ucwords($match[1]);
        }

        return null;
    }
}
