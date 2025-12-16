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
 * - Warlock patron: "Spell Level | Spells" or "Spell Level | Genie Spells | ..."
 * - Warlock (The Undead): No header, just "1st | bane, false life" rows
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
        // Try standard format first (most common)
        $result = $this->parseStandardSpellTable($text);
        if ($result !== null) {
            return $result;
        }

        // Try The Genie format (multi-column: "Spell Level | Genie Spells | Dao Spells | ...")
        $result = $this->parseGenieSpellTable($text);
        if ($result !== null) {
            return $result;
        }

        // Try headerless format (The Undead: rows like "1st | bane, false life" after "Expanded Spells:")
        return $this->parseHeaderlessSpellTable($text);
    }

    /**
     * Parse standard spell table format.
     *
     * Matches: "Artificer Level | Spell", "Cleric Level | Spells", "Paladin Level | Spells",
     * "Druid Level | Circle Spells", "Spell Level | Spells"
     */
    private function parseStandardSpellTable(string $text): ?array
    {
        $tablePattern = '/(?:Artificer Level|Cleric Level|Druid Level|Paladin Level|Ranger Level|Sorcerer Level|Spell Level)\s*\|\s*(?:Circle )?Spells?\s*\n((?:\d+(?:st|nd|rd|th)\s*\|[^\n]+\n?)+)/i';

        if (! preg_match($tablePattern, $text, $matches)) {
            return null;
        }

        return $this->parseSpellRows($matches[1]);
    }

    /**
     * Parse The Genie format: "Spell Level | Genie Spells | Dao Spells | ..."
     *
     * Only extracts the first column of spells (Genie Spells) as those are common to all.
     */
    private function parseGenieSpellTable(string $text): ?array
    {
        // Match "Spell Level | Genie Spells | ..." header followed by data rows
        $tablePattern = '/Spell Level\s*\|\s*Genie Spells\s*\|[^\n]+\n((?:\d+(?:st|nd|rd|th)\s*\|[^\n]+\n?)+)/i';

        if (! preg_match($tablePattern, $text, $matches)) {
            return null;
        }

        $tableRows = $matches[1];
        $result = [];

        // Parse each row, extracting only the first spell column
        // Format: "1st | detect evil and good | sanctuary | thunderwave | ..."
        $rowPattern = '/(\d+)(?:st|nd|rd|th)\s*\|\s*([^|]+)/i';

        if (preg_match_all($rowPattern, $tableRows, $rowMatches, PREG_SET_ORDER)) {
            foreach ($rowMatches as $row) {
                $level = (int) $row[1];
                $spellsText = trim($row[2]);

                // First column may have single spell or comma-separated
                $spells = array_map('trim', preg_split('/\s*,\s*/', $spellsText));
                $spells = array_values(array_filter($spells, fn ($s) => ! empty($s)));

                if (! empty($spells)) {
                    $result[] = [
                        'level' => $level,
                        'spells' => $spells,
                    ];
                }
            }
        }

        return ! empty($result) ? $result : null;
    }

    /**
     * Parse headerless spell table format (The Undead).
     *
     * Matches: "Expanded Spells:\n1st | bane, false life\n2nd | ..."
     */
    private function parseHeaderlessSpellTable(string $text): ?array
    {
        // Look for "Expanded Spells:" followed by rows without a column header
        $tablePattern = '/Expanded Spells:\s*\n((?:\d+(?:st|nd|rd|th)\s*\|[^\n]+\n?)+)/i';

        if (! preg_match($tablePattern, $text, $matches)) {
            return null;
        }

        return $this->parseSpellRows($matches[1]);
    }

    /**
     * Parse spell rows from captured table text.
     *
     * Handles rows like "1st | bless, cure wounds" or "5th | cloudkill, cone of cold"
     */
    private function parseSpellRows(string $tableRows): ?array
    {
        $result = [];
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

                if (! empty($spells)) {
                    $result[] = [
                        'level' => $level,
                        'spells' => $spells,
                    ];
                }
            }
        }

        return ! empty($result) ? $result : null;
    }

    /**
     * Check if text contains a subclass spell table.
     */
    public function hasSubclassSpellTable(string $text): bool
    {
        // Standard format: "Class Level | Spells"
        if (preg_match('/(?:Artificer Level|Cleric Level|Druid Level|Paladin Level|Ranger Level|Sorcerer Level|Spell Level)\s*\|\s*(?:Circle )?Spells?/i', $text) === 1) {
            return true;
        }

        // The Genie format: "Spell Level | Genie Spells |"
        if (preg_match('/Spell Level\s*\|\s*Genie Spells\s*\|/i', $text) === 1) {
            return true;
        }

        // Headerless format: "Expanded Spells:\n1st |"
        if (preg_match('/Expanded Spells:\s*\n\d+(?:st|nd|rd|th)\s*\|/i', $text) === 1) {
            return true;
        }

        return false;
    }
}
