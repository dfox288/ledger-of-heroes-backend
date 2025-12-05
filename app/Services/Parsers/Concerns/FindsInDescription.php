<?php

namespace App\Services\Parsers\Concerns;

/**
 * Provides utilities for extracting keywords from item descriptions.
 *
 * Used by item parser strategies to find specific keywords or patterns
 * in description text without repeating the same iteration logic.
 */
trait FindsInDescription
{
    /**
     * Find first matching keyword in text.
     *
     * @param  string  $text  Text to search in
     * @param  array<string>  $keywords  Keywords to search for
     * @return string|null First matching keyword, or null
     */
    protected function findFirstKeyword(string $text, array $keywords): ?string
    {
        $textLower = strtolower($text);

        foreach ($keywords as $keyword) {
            if (str_contains($textLower, strtolower($keyword))) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Find all matching keywords in text.
     *
     * @param  string  $text  Text to search in
     * @param  array<string>  $keywords  Keywords to search for
     * @return array<string> All matching keywords
     */
    protected function findAllKeywords(string $text, array $keywords): array
    {
        $matches = [];
        $textLower = strtolower($text);

        foreach ($keywords as $keyword) {
            if (str_contains($textLower, strtolower($keyword))) {
                $matches[] = $keyword;
            }
        }

        return $matches;
    }

    /**
     * Check if any keyword exists in text.
     *
     * @param  string  $text  Text to search in
     * @param  array<string>  $keywords  Keywords to check
     */
    protected function hasAnyKeyword(string $text, array $keywords): bool
    {
        return $this->findFirstKeyword($text, $keywords) !== null;
    }
}
