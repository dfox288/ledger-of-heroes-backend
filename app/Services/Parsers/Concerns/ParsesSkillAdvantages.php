<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing skill check advantages from description text.
 *
 * Used by: FeatXmlParser, RaceXmlParser
 *
 * Detects patterns like "advantage on Ability (Skill) checks" and extracts
 * structured modifier data for storage in entity_modifiers table.
 */
trait ParsesSkillAdvantages
{
    /**
     * Parse skill-based advantages from description text.
     *
     * Detects patterns like:
     * - "advantage on Charisma (Deception) and Charisma (Performance) checks when..."
     * - "advantage on Wisdom (Perception) checks while..."
     * - "advantage on Intelligence (History) checks related to..."
     *
     * These are routed to modifiers (not conditions) because they're skill check
     * modifiers, not D&D Condition interactions.
     *
     * @return array<int, array{modifier_category: string, skill_name: string, value: string, condition: string|null}>
     */
    protected function parseSkillAdvantages(string $text): array
    {
        $modifiers = [];

        // Pattern: "advantage on Ability (Skill) checks" with optional second skill and condition
        // Captures: skill names in parentheses, and the conditional text after "when/while/related to/made to"
        // Uses [^.]+ instead of .+? to capture full condition text until sentence end
        $pattern = '/advantage on\s+(?:[A-Z][a-z]+)\s*\(([^)]+)\)(?:\s+and\s+(?:[A-Z][a-z]+)\s*\(([^)]+)\))?\s+checks?\s*(?:(when|while|related to|made to)\s+([^.]+))?(?:\.|$)/i';

        if (preg_match($pattern, $text, $match)) {
            $skills = [];

            // First skill
            if (! empty($match[1])) {
                $skills[] = trim($match[1]);
            }

            // Second skill (if "and" pattern)
            if (! empty($match[2])) {
                $skills[] = trim($match[2]);
            }

            // Condition text (after "when", "while", "related to", "made to")
            $conditionText = ! empty($match[4]) ? trim($match[4]) : null;

            foreach ($skills as $skillName) {
                $modifiers[] = [
                    'modifier_category' => 'skill_advantage',
                    'skill_name' => $skillName,
                    'value' => 'advantage',
                    'condition' => $conditionText,
                ];
            }
        }

        return $modifiers;
    }
}
