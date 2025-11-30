<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing subclass spell tables from feature descriptions.
 *
 * Handles three formats:
 * - Cleric domain spells: "Cleric Level | Spells"
 * - Druid circle spells: "Druid Level | Circle Spells"
 * - Warlock expanded spells: "Spell Level | Spells"
 */
trait ParsesSubclassSpellTables
{
    /**
     * Parse a subclass spell table from feature description text.
     *
     * @param  string  $text  The feature description text
     * @return array<int, array{level: int, spells: array<string>}>|null Parsed spell data or null if no table found
     */
    protected function parseSubclassSpellTable(string $text): ?array
    {
        // Pattern matches:
        // "Cleric Level | Spells"
        // "Druid Level | Circle Spells"
        // "Spell Level | Spells"
        // Followed by rows like "1st | bless, cure wounds"
        $tablePattern = '/(?:Cleric Level|Druid Level|Spell Level)\s*\|\s*(?:Circle )?Spells\s*\n((?:\d+(?:st|nd|rd|th)\s*\|[^\n]+\n?)+)/i';

        if (! preg_match($tablePattern, $text, $matches)) {
            return null;
        }

        $tableRows = $matches[1];
        $result = [];

        // Parse each row: "1st | bless, cure wounds"
        $rowPattern = '/(\d+)(?:st|nd|rd|th)\s*\|\s*(.+)/i';

        if (preg_match_all($rowPattern, $tableRows, $rowMatches, PREG_SET_ORDER)) {
            foreach ($rowMatches as $row) {
                $level = (int) $row[1];
                $spellsText = trim($row[2]);

                // Split spells by comma, handling potential "and" before last spell
                $spells = array_map(
                    fn ($s) => trim(preg_replace('/^and\s+/i', '', $s)),
                    preg_split('/\s*,\s*/', $spellsText)
                );

                // Filter empty strings
                $spells = array_values(array_filter($spells, fn ($s) => ! empty($s)));

                $result[] = [
                    'level' => $level,
                    'spells' => $spells,
                ];
            }
        }

        return ! empty($result) ? $result : null;
    }

    /**
     * Check if text contains a subclass spell table.
     */
    protected function hasSubclassSpellTable(string $text): bool
    {
        return preg_match('/(?:Cleric Level|Druid Level|Spell Level)\s*\|\s*(?:Circle )?Spells/i', $text) === 1;
    }
}
