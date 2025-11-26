<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait StripsSourceCitations
 *
 * Provides a standardized method for removing source citation text from descriptions.
 * Source citations typically appear at the end of text blocks in the format:
 * "Source: Player's Handbook p. 123"
 *
 * Used by: FeatXmlParser, OptionalFeatureXmlParser, BackgroundXmlParser
 */
trait StripsSourceCitations
{
    /**
     * Remove source citations from text.
     *
     * Removes text starting from "Source:" (case-insensitive) including:
     * - Leading newlines before "Source:"
     * - The entire "Source: Book Name p. 123" line
     * - Any content after the Source line
     *
     * @param  string  $text  The text containing source citations
     * @return string The cleaned text without source citations
     */
    protected function stripSourceCitations(string $text): string
    {
        // Remove everything after "Source:" (including the Source: line)
        // Pattern explanation:
        //   \n*      - Match zero or more newlines before Source
        //   Source:  - Literal "Source:" text
        //   \s*      - Optional whitespace after colon
        //   .+       - Match everything after (greedy)
        //   $        - Until end of string
        //   /ims     - Case-insensitive, multiline, dotall (. matches newlines)
        $cleaned = preg_replace('/\n*Source:\s*.+$/ims', '', $text);

        return trim($cleaned ?? $text);
    }
}
