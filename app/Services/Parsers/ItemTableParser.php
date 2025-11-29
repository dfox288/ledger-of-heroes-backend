<?php

namespace App\Services\Parsers;

class ItemTableParser
{
    public function parse(string $tableText, ?string $diceType = null): array
    {
        $lines = explode("\n", $tableText);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines); // Remove empty lines

        if (count($lines) < 3) {
            // Need at least: name, header, 1 data row
            return ['table_name' => '', 'dice_type' => null, 'rows' => []];
        }

        // First line is table name (may end with colon)
        $tableName = trim(array_shift($lines), ': ');

        // Second line is header (skip it for now)
        array_shift($lines);

        // Remaining lines are data rows
        $rows = [];
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $cells = array_map('trim', explode('|', $line));

            if (count($cells) < 2) {
                // Not a valid table row
                continue;
            }

            // First cell might be a roll number or range
            $rollCell = array_shift($cells);
            [$rollMin, $rollMax] = $this->parseRollRange($rollCell);

            // If first cell wasn't a roll number, include it in the result
            if ($rollMin === null && $rollMax === null) {
                array_unshift($cells, $rollCell); // Put it back
            }

            // Remaining cells are the result text
            $resultText = implode(' | ', $cells);

            $rows[] = [
                'roll_min' => $rollMin,
                'roll_max' => $rollMax,
                'result_text' => $resultText,
            ];
        }

        return [
            'table_name' => $tableName,
            'dice_type' => $diceType,
            'rows' => $rows,
        ];
    }

    /**
     * Parse level-ordinal progression table.
     *
     * @param  string  $tableText  Table text with format "Name:\nHeader\n1st | value\n..."
     * @return array{table_name: string, column_name: string, rows: array<array{level: int, value: string}>}
     */
    public function parseLevelProgression(string $tableText): array
    {
        $lines = explode("\n", trim($tableText));
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines);

        if (count($lines) < 2) {
            return ['table_name' => '', 'column_name' => '', 'rows' => []];
        }

        // First line is table name (with colon)
        $tableName = trim(array_shift($lines), ': ');

        // Second line is header
        $header = array_shift($lines);
        $headerParts = array_map('trim', explode('|', $header));
        $columnName = $headerParts[1] ?? $tableName;

        // Remaining lines are data rows
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2) {
                continue;
            }

            // Parse ordinal to integer: "5th" → 5
            $level = $this->parseOrdinalLevel($parts[0]);
            if ($level === null) {
                continue;
            }

            $rows[] = [
                'level' => $level,
                'value' => $parts[1],
            ];
        }

        return [
            'table_name' => $tableName,
            'column_name' => $columnName,
            'rows' => $rows,
        ];
    }

    /**
     * Parse ordinal level string to integer.
     *
     * @param  string  $ordinal  "1st", "2nd", "3rd", "5th", etc.
     */
    private function parseOrdinalLevel(string $ordinal): ?int
    {
        if (preg_match('/^(\d+)(?:st|nd|rd|th)$/i', trim($ordinal), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parseRollRange(string $cell): array
    {
        // Handle formats:
        // "1" => [1, 1]
        // "01" => [1, 1]
        // "2-3" => [2, 3]
        // "01-02" => [1, 2]
        // "Lever" => [null, null]

        $cell = trim($cell);

        // Check for range (e.g., "2-3" or "01-02")
        if (preg_match('/^(\d+)-(\d+)$/', $cell, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        // Check for single number (e.g., "1" or "01")
        if (is_numeric($cell)) {
            $num = (int) $cell;

            return [$num, $num];
        }

        // Not a number (e.g., "Lever")
        return [null, null];
    }
}
