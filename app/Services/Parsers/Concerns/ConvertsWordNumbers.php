<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for converting English number words to integers.
 *
 * Handles common patterns like:
 * - "one skill proficiency" -> 1
 * - "three ability scores" -> 3
 * - "five weapons of your choice" -> 5
 *
 * Used by: Parsers that handle player choices
 */
trait ConvertsWordNumbers
{
    /**
     * Convert an English number word to an integer.
     *
     * @param  string  $word  Number word (e.g., "three", "five")
     * @param  int  $default  Default value if word not recognized
     * @return int The numeric value
     */
    protected function wordToNumber(string $word, int $default = 1): int
    {
        $word = strtolower(trim($word));

        $map = [
            'a' => 1,
            'an' => 1,
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10,
            'any' => 1, // "any languages" typically means "one language"
            'several' => 2, // Approximation
        ];

        return $map[$word] ?? $default;
    }
}
