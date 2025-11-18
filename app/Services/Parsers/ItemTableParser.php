<?php

namespace App\Services\Parsers;

class ItemTableParser
{
    public function parse(string $tableText): array
    {
        $lines = explode("\n", $tableText);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines); // Remove empty lines

        if (count($lines) < 3) {
            // Need at least: name, header, 1 data row
            return ['table_name' => '', 'rows' => []];
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
            'rows' => $rows,
        ];
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
