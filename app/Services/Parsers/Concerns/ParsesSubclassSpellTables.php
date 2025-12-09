<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing subclass spell tables from feature descriptions.
 *
 * Handles multiple class-specific formats:
 * - Artificer: "Artificer Level | Spell"
 * - Cleric domain: "Cleric Level | Spells"
 * - Druid circle: "Druid Level | Circle Spells"
 * - Paladin oath: "Paladin Level | Spells"
 * - Ranger: "Ranger Level | Spells"
 * - Sorcerer: "Sorcerer Level | Spells"
 * - Warlock patron: "Spell Level | Spells"
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
        // Pattern matches all class-specific spell table formats:
        // "Artificer Level | Spell", "Cleric Level | Spells", "Paladin Level | Spells"
        // "Druid Level | Circle Spells", "Ranger Level | Spells", "Sorcerer Level | Spells"
        // "Spell Level | Spells" (Warlock)
        // Followed by rows like "1st | bless, cure wounds"
        $tablePattern = '/(?:Artificer Level|Cleric Level|Druid Level|Paladin Level|Ranger Level|Sorcerer Level|Spell Level)\s*\|\s*(?:Circle )?Spells?\s*\n((?:\d+(?:st|nd|rd|th)\s*\|[^\n]+\n?)+)/i';

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
    public function hasSubclassSpellTable(string $text): bool
    {
        return preg_match('/(?:Artificer Level|Cleric Level|Druid Level|Paladin Level|Ranger Level|Sorcerer Level|Spell Level)\s*\|\s*(?:Circle )?Spells?/i', $text) === 1;
    }
}
