<?php

namespace App\Services\Parsers;

class ItemTableDetector
{
    public function detectTables(string $text): array
    {
        $tables = [];

        // Pattern: Table with numeric rows and pipe delimiters
        // Matches:
        //   Table Name:
        //   Header | Header  (or: d8 | Header)
        //   1 | Data | Data
        //   2 | Data | Data

        $pattern = '/^(.+?):\s*\n([^\n]+\|[^\n]+)\s*\n((?:^\d+(?:-\d+)?\s*\|[^\n]+\s*\n?)+)/m';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
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
                $tableText = $tableName . ":\n" . $header . "\n" . $rowsText;

                $tables[] = [
                    'name' => $tableName,
                    'text' => $tableText,
                    'dice_type' => $diceType,
                    'start_pos' => $match[0][1],
                    'end_pos' => $match[0][1] + strlen($match[0][0]),
                ];
            }
        }

        return $tables;
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
