<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Language;
use Illuminate\Support\Collection;

/**
 * Trait MatchesLanguages
 *
 * Provides language matching with static caching.
 * Uses the same pattern as LookupsGameEntities for consistency.
 *
 * Used by: RaceXmlParser, BackgroundXmlParser
 */
trait MatchesLanguages
{
    use ConvertsWordNumbers;

    private static ?Collection $languagesCache = null;

    /**
     * Initialize the languages cache (lazy initialization).
     */
    private function initializeLanguagesCache(): void
    {
        if (self::$languagesCache === null) {
            try {
                self::$languagesCache = Language::all()
                    ->keyBy(fn ($language) => $language->slug);
            } catch (\Exception $e) {
                // Graceful fallback for unit tests without database
                self::$languagesCache = collect();
            }
        }
    }

    /**
     * Extract languages from text, handling both fixed languages and choice slots.
     *
     * Example inputs:
     * - "Common and Dwarvish" → 2 fixed languages
     * - "Common, Elvish, and one extra language" → 2 fixed + 1 choice
     * - "one extra language of your choice" → 1 choice slot
     * - "two other languages" → 2 choice slots
     * - "Common, plus any three languages of your choice" → 1 fixed + 3 choices
     *
     * @param  string  $text  The language description from XML
     * @return array Array of language data: [['language_id' => X, 'is_choice' => false], ...]
     */
    protected function extractLanguagesFromText(string $text): array
    {
        $this->initializeLanguagesCache();

        $results = [];

        // Only parse the first sentence - language mechanics, not flavor text
        // Split on period or on pattern like "Humans typically" / "The language" / etc
        $sentences = preg_split('/\.(?:\s|$)/', $text, 2);
        $remainingText = $sentences[0] ?? $text;

        // Pattern 1a: Handle "X of your choice" format (e.g., "Two of your choice")
        // This is common in backgrounds and must be checked first
        if (preg_match('/\b(one|two|three|four|any|a|an)\s+(?:of\s+your\s+choice)\b/i', $remainingText, $choiceMatch)) {
            $quantity = $this->wordToNumber($choiceMatch[1]);

            // Add choice slots
            for ($i = 0; $i < $quantity; $i++) {
                $results[] = [
                    'slug' => null,
                    'is_choice' => true,
                ];
            }

            // Remove this match from remaining text
            $remainingText = str_replace($choiceMatch[0], '', $remainingText);
        }

        // Pattern 1b: Extract choice slots with "languages" keyword
        // Matches: "one extra language", "two other languages", "any three languages"
        $choicePattern = '/\b(one|two|three|four|any|a|an)\s+(extra|other|additional)?\s*languages?\b/i';
        if (preg_match_all($choicePattern, $remainingText, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $quantityWord = $matches[1][array_search($match, $matches[0])][0];
                $quantity = $this->wordToNumber($quantityWord);

                // Add choice slots
                for ($i = 0; $i < $quantity; $i++) {
                    $results[] = [
                        'slug' => null,
                        'is_choice' => true,
                    ];
                }

                // Remove this match from remaining text to avoid re-matching
                $remainingText = str_replace($match[0], '', $remainingText);
            }
        }

        // Pattern 2: Extract specific language names
        // Try to match each known language in the cache
        foreach (self::$languagesCache as $slug => $language) {
            // Match the language name (case-insensitive, word boundary)
            $pattern = '/\b'.preg_quote($language->name, '/').'\b/i';
            if (preg_match($pattern, $remainingText)) {
                $results[] = [
                    'slug' => $slug,
                    'is_choice' => false,
                ];

                // Remove this language from remaining text to avoid duplicates
                $remainingText = preg_replace($pattern, '', $remainingText, 1);
            }
        }

        // Pattern 3: Handle "plus one of the following" or "choose one from" patterns
        // This is more complex and might reference specific languages
        // Example: "Common, plus one of the following: Dwarvish, Elvish, or Giant"
        if (preg_match('/\bplus\s+one\s+of\s+the\s+following[:\s]+(.*?)(?:\.|$)/i', $remainingText, $followingMatch)) {
            // This would be a choice from specific languages
            // For now, we treat it as a single choice slot
            $results[] = [
                'slug' => null,
                'is_choice' => true,
            ];
        }

        return $results;
    }

    /**
     * Match a language name to a Language model.
     *
     * @param  string  $name  The language name from XML
     * @return Language|null The matched language, or null if no match
     */
    protected function matchLanguage(string $name): ?Language
    {
        $this->initializeLanguagesCache();

        $normalized = $this->normalizeLanguageName($name);

        // Exact slug match first
        $match = self::$languagesCache->get($normalized);
        if ($match) {
            return $match;
        }

        // Try partial matching (e.g., "thieves cant" → "thieves' cant")
        $match = self::$languagesCache->first(function ($language) use ($normalized) {
            $langSlug = $this->normalizeLanguageName($language->name);

            return $langSlug === $normalized || str_contains($langSlug, $normalized) || str_contains($normalized, $langSlug);
        });

        return $match;
    }

    /**
     * Normalize a language name for matching.
     * Handles case differences, apostrophes, and special characters.
     *
     * @param  string  $name  The name to normalize
     * @return string The normalized name (slug format)
     */
    protected function normalizeLanguageName(string $name): string
    {
        // Remove various apostrophe types
        $name = str_replace("'", '', $name); // Straight apostrophe
        $name = str_replace("'", '', $name); // Right single quotation mark (curly)
        $name = str_replace("'", '', $name); // Left single quotation mark

        // Convert to slug format (lowercase, spaces to hyphens)
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        $name = trim($name, '-');

        return $name;
    }

    /**
     * Initialize languages (legacy method for backward compatibility).
     *
     * @deprecated Use initializeLanguagesCache() instead (called automatically)
     */
    protected function initializeLanguages(): void
    {
        $this->initializeLanguagesCache();
    }

    /**
     * Clear all static caches held by this trait.
     *
     * Implements ClearsCaches interface pattern for test isolation.
     */
    public static function clearLanguagesCache(): void
    {
        self::$languagesCache = null;
    }
}
