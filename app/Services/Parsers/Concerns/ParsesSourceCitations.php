<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Source;
use Illuminate\Support\Collection;

/**
 * Trait for parsing D&D source citations from XML text.
 *
 * Handles patterns like:
 * - "Player's Handbook (2014) p. 123"
 * - "Xanathar's Guide to Everything p. 45, 47"
 * - "Source: Player's Handbook p. 123"
 */
trait ParsesSourceCitations
{
    private ?Collection $sourcesCache = null;

    /**
     * Parse source citations from text.
     *
     * Extracts book names and page numbers from various citation formats.
     *
     * @param  string  $text  The text containing source citations (with or without "Source:" prefix)
     * @return array Array of ['code' => 'PHB', 'pages' => '123, 125']
     */
    protected function parseSourceCitations(string $text): array
    {
        $sources = [];

        // Extract the source section (everything after "Source:")
        // If text already has "Source:" prefix removed, use it directly
        if (preg_match('/Source:\s*(.+)$/ims', $text, $sourceMatch)) {
            $sourcesText = $sourceMatch[1];
        } else {
            // Text is already extracted (no "Source:" prefix)
            $sourcesText = $text;
        }

        // Pattern 1: Try to match sources with year first
        // Example: "Player's Handbook (2014) p. 123"
        // Updated regex to not capture trailing commas
        $patternWithYear = '/([^,\n]+?)\s*\((\d{4})\)\s*p\.\s*([\d,\s\-]+?)(?:,|\s|$)/i';

        if (preg_match_all($patternWithYear, $sourcesText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sourceName = trim($match[1]);
                $pages = trim($match[3]);
                // Clean up any remaining trailing commas or whitespace
                $pages = rtrim($pages, ", \t\n\r\0\x0B");

                $sourceCode = $this->mapSourceNameToCode($sourceName);

                $sources[] = [
                    'code' => $sourceCode,
                    'pages' => $pages,
                ];
            }
        } else {
            // Pattern 2: Try pattern without year
            // Example: "Player's Handbook p. 123"
            // Updated regex to not capture trailing commas
            $patternWithoutYear = '/([^\s]+(?:\s+[^\s]+)*?)\s+p\.\s*([\d,\s\-]+?)(?:,|\s|$)/i';
            preg_match_all($patternWithoutYear, $sourcesText, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $sourceName = trim($match[1]);
                $pages = trim($match[2]);
                // Clean up any remaining trailing commas or whitespace
                $pages = rtrim($pages, ", \t\n\r\0\x0B");

                $sourceCode = $this->mapSourceNameToCode($sourceName);

                $sources[] = [
                    'code' => $sourceCode,
                    'pages' => $pages,
                ];
            }
        }

        // Fallback if no sources parsed
        if (empty($sources)) {
            $sources[] = [
                'code' => $this->getDefaultSourceCode(),
                'pages' => '',
            ];
        }

        return $sources;
    }

    /**
     * Initialize the sources cache from the database.
     * Lazy-loaded on first use for performance.
     */
    protected function initializeSources(): void
    {
        if ($this->sourcesCache === null) {
            try {
                // Load all sources and key by name for quick lookup
                $this->sourcesCache = Source::all()->keyBy('name');
            } catch (\Exception $e) {
                // Graceful fallback for unit tests without database
                $this->sourcesCache = collect();
            }
        }
    }

    /**
     * Map a source book name to its official code.
     *
     * Queries the database for the source and returns its code.
     * Falls back to 'PHB' if source not found.
     *
     * @param  string  $sourceName  The full name of the source book
     * @return string The source code (e.g., 'PHB', 'XGE')
     */
    protected function mapSourceNameToCode(string $sourceName): string
    {
        $this->initializeSources();

        // Normalize the source name (remove year suffixes like "(2014)")
        $normalizedName = preg_replace('/\s*\(\d{4}\)\s*$/', '', $sourceName);
        $normalizedName = trim($normalizedName);

        // Try exact match first
        $source = $this->sourcesCache->get($normalizedName);

        // If not found, try the original name with year
        if (! $source) {
            $source = $this->sourcesCache->get($sourceName);
        }

        // If still not found, try fuzzy matching (case-insensitive)
        if (! $source) {
            $source = $this->sourcesCache->first(function ($src) use ($normalizedName) {
                return strcasecmp($src->name, $normalizedName) === 0;
            });
        }

        // Return code or fallback to configured default
        return $source?->code ?? $this->getDefaultSourceCode();
    }

    /**
     * Get the default source code from config, with fallback for unit tests.
     */
    protected function getDefaultSourceCode(): string
    {
        if (function_exists('config') && app()->bound('config')) {
            return config('import.default_source_code', 'PHB');
        }

        return 'PHB';
    }
}
