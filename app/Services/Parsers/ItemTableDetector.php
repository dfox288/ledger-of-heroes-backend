<?php

namespace App\Services\Parsers;

class ItemTableDetector
{
    public function detectTables(string $text): array
    {
        $tables = [];

        // Pattern 1: Table with "Table Name:" header
        // Matches:
        //   Table Name:
        //   Header | Header  (or: d8 | Header)
        //   1 | Data | Data
        //   2 | Data | Data

        $pattern1 = '/^(.+?):\s*\n([^\n]+\|[^\n]+)\s*\n((?:^\d+(?:-\d+)?\s*\|[^\n]+\s*\n?)+)/m';

        if (preg_match_all($pattern1, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                // Extract table name (remove trailing colon)
                $tableName = trim($match[1][0], ': ');

                // Extract header row
                $header = trim($match[2][0]);

                // Extract dice type from header if present (e.g., "d8 | Result")
                $diceType = $this->parseDiceType($header);

                // Extract data rows
                $rowsText = trim($match[3][0]);

                // Build table text (name + header + rows)
                $tableText = $tableName.":\n".$header."\n".$rowsText;

                $tables[] = [
                    'name' => $tableName,
                    'text' => $tableText,
                    'dice_type' => $diceType,
                    'start_pos' => $match[0][1],
                    'end_pos' => $match[0][1] + strlen($match[0][0]),
                ];
            }
        }

        // Pattern 2: Headerless table (dice type and name in first row)
        // Matches:
        //   d8 | Personality Trait
        //   1 | Data
        //   2 | Data

        $pattern2 = '/^(\d*d\d+)\s*\|\s*([^\n]+)\s*\n((?:^\d+(?:-\d+)?\s*\|[^\n]+\s*\n?)+)/m';

        if (preg_match_all($pattern2, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $diceType = $match[1][0];
                $tableName = trim($match[2][0]);
                $header = $match[1][0].' | '.$tableName;
                $rowsText = trim($match[3][0]);

                // Check if this table was already captured by pattern 1
                // (to avoid duplicates if a table has both formats)
                // We check if any existing table's range overlaps with this one
                $alreadyExists = false;
                $matchStart = $match[0][1];
                $matchEnd = $match[0][1] + strlen($match[0][0]);

                foreach ($tables as $existing) {
                    $existingStart = $existing['start_pos'];
                    $existingEnd = $existing['end_pos'];

                    // Check for overlap
                    if (($matchStart >= $existingStart && $matchStart < $existingEnd) ||
                        ($matchEnd > $existingStart && $matchEnd <= $existingEnd) ||
                        ($matchStart <= $existingStart && $matchEnd >= $existingEnd)) {
                        $alreadyExists = true;
                        break;
                    }
                }

                if (! $alreadyExists) {
                    // Build table text
                    $tableText = $tableName.":\n".$header."\n".$rowsText;

                    $tables[] = [
                        'name' => $tableName,
                        'text' => $tableText,
                        'dice_type' => $diceType,
                        'start_pos' => $match[0][1],
                        'end_pos' => $match[0][1] + strlen($match[0][0]),
                    ];
                }
            }
        }

        // Pattern 3: Tables with text in first column (e.g., Draconic Ancestry, Staff of the Magi)
        // Matches:
        //   Table Name:
        //   Header | Header | Header
        //   TextValue | Data | Data
        //   TextValue | Data | Data
        //
        // Also matches digit-prefixed text that isn't a pure number/range/ordinal:
        //   10 ft. away | Data (matched - digit followed by non-digit text)
        //   10 | Data (NOT matched - pure number, handled by Pattern 1)
        //   2-3 | Data (NOT matched - range, handled by Pattern 1)
        //   1st | Data (NOT matched - ordinal, handled by Pattern 4)
        //
        // Regex breakdown:
        //   (?!\d+(?:-\d+)?\s*\|) - Negative lookahead: NOT a pure number or range followed by |
        //   (?!\d+(?:st|nd|rd|th)\s*\|) - Negative lookahead: NOT an ordinal followed by |
        //   [^\n]+ - Then any text (including digit-prefixed like "10 ft.")
        //   \| - Followed by pipe delimiter

        $pattern3 = '/^(.+?):\s*\n([^\n]+\|[^\n]+)\s*\n((?:^(?!\d+(?:-\d+)?\s*\|)(?!\d+(?:st|nd|rd|th)\s*\|)[^\n]+\|[^\n]+\s*\n?)+)/m';

        if (preg_match_all($pattern3, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $matchStart = $match[0][1];
                $matchEnd = $match[0][1] + strlen($match[0][0]);

                // Check if this table was already captured by previous patterns
                $alreadyExists = false;
                foreach ($tables as $existing) {
                    $existingStart = $existing['start_pos'];
                    $existingEnd = $existing['end_pos'];

                    // Check for overlap
                    if (($matchStart >= $existingStart && $matchStart < $existingEnd) ||
                        ($matchEnd > $existingStart && $matchEnd <= $existingEnd) ||
                        ($matchStart <= $existingStart && $matchEnd >= $existingEnd)) {
                        $alreadyExists = true;
                        break;
                    }
                }

                if (! $alreadyExists) {
                    // Extract table name (remove trailing colon)
                    $tableName = trim($match[1][0], ': ');

                    // Extract header row
                    $header = trim($match[2][0]);

                    // Extract dice type from header if present
                    $diceType = $this->parseDiceType($header);

                    // Extract data rows
                    $rowsText = trim($match[3][0]);

                    // Build table text (name + header + rows)
                    $tableText = $tableName.":\n".$header."\n".$rowsText;

                    $tables[] = [
                        'name' => $tableName,
                        'text' => $tableText,
                        'dice_type' => $diceType,
                        'start_pos' => $match[0][1],
                        'end_pos' => $match[0][1] + strlen($match[0][0]),
                    ];
                }
            }
        }

        // Pattern 4: Level-ordinal tables (class progression)
        // Matches:
        //   Table Name:
        //   Level | Column (or Header | Header)
        //   1st | value
        //   5th | value
        //   17th | value
        $pattern4 = '/^(.+?):\s*\n([^\n]+\|[^\n]+)\s*\n((?:^\d+(?:st|nd|rd|th)\s*\|[^\n]+\s*\n?)+)/mi';

        if (preg_match_all($pattern4, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $matchStart = $match[0][1];
                $matchEnd = $match[0][1] + strlen($match[0][0]);

                // Check for overlap with existing tables
                $alreadyExists = $this->rangesOverlap($matchStart, $matchEnd, $tables);

                if (! $alreadyExists) {
                    $tableName = trim($match[1][0], ': ');
                    $header = trim($match[2][0]);
                    $rowsText = trim($match[3][0]);
                    $tableText = $tableName.":\n".$header."\n".$rowsText;

                    $tables[] = [
                        'name' => $tableName,
                        'text' => $tableText,
                        'dice_type' => null,  // Level-ordinal tables don't have dice type
                        'start_pos' => $matchStart,
                        'end_pos' => $matchEnd,
                        'is_level_progression' => true,  // Flag for progression tables
                    ];
                }
            }
        }

        // Sort tables by position to maintain order
        usort($tables, fn ($a, $b) => $a['start_pos'] <=> $b['start_pos']);

        return $tables;
    }

    /**
     * Check if a range overlaps with any existing table ranges.
     */
    private function rangesOverlap(int $start, int $end, array $tables): bool
    {
        foreach ($tables as $existing) {
            $existingStart = $existing['start_pos'];
            $existingEnd = $existing['end_pos'];

            // Check for overlap
            if (($start >= $existingStart && $start < $existingEnd) ||
                ($end > $existingStart && $end <= $existingEnd) ||
                ($start <= $existingStart && $end >= $existingEnd)) {
                return true;
            }
        }

        return false;
    }

    private function parseDiceType(string $header): ?string
    {
        // Check if header starts with dice notation: d8, 1d22, 2d6, etc.
        // Matches:
        //   d8 | Result
        //   1d22 | Playing Card
        //   1d33 | Card
        //   2d6 | Damage
        if (preg_match('/^(\d*d\d+)\s*\|/', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
