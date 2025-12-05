<?php

namespace App\Services\Parsers\Concerns;

use App\Models\ProficiencyType;
use Illuminate\Support\Collection;

/**
 * Trait MatchesProficiencyTypes
 *
 * Provides proficiency type matching with static caching.
 * Uses the same pattern as LookupsGameEntities for consistency.
 *
 * Used by: ItemXmlParser, ClassXmlParser, RaceXmlParser, BackgroundXmlParser
 */
trait MatchesProficiencyTypes
{
    private static ?Collection $proficiencyTypesCache = null;

    /**
     * Initialize the proficiency types cache (lazy initialization).
     */
    private function initializeProficiencyTypesCache(): void
    {
        if (self::$proficiencyTypesCache === null) {
            try {
                self::$proficiencyTypesCache = ProficiencyType::all()
                    ->keyBy(fn ($type) => $this->normalizeName($type->name));
            } catch (\Exception $e) {
                // Graceful fallback for unit tests without database
                self::$proficiencyTypesCache = collect();
            }
        }
    }

    /**
     * Match a proficiency name to a ProficiencyType.
     *
     * @param  string  $name  The proficiency name from XML
     * @return ProficiencyType|null The matched type, or null if no match
     */
    protected function matchProficiencyType(string $name): ?ProficiencyType
    {
        $this->initializeProficiencyTypesCache();

        // Skip matching for choice-based proficiencies (e.g., "Any one type of Artisan's Tools")
        // These should NOT be linked to a specific proficiency type
        if ($this->isChoiceBasedProficiency($name)) {
            return null;
        }

        $normalized = $this->normalizeName($name);

        // Exact match first
        $match = self::$proficiencyTypesCache->get($normalized);
        if ($match) {
            return $match;
        }

        // Partial/fuzzy matching for common patterns
        // "martial" → "Martial Weapons", "simple" → "Simple Weapons"
        $fuzzyMatches = [
            'martial' => 'martialweapons',
            'simple' => 'simpleweapons',
            'light' => 'lightarmor',
            'medium' => 'mediumarmor',
            'heavy' => 'heavyarmor',
            'shield' => 'shields',
        ];

        if (isset($fuzzyMatches[$normalized])) {
            $match = self::$proficiencyTypesCache->get($fuzzyMatches[$normalized]);
            if ($match) {
                return $match;
            }
        }

        // Partial string matching - find proficiency type that contains the search term
        // e.g., "staff" should match "Quarterstaff"
        $match = self::$proficiencyTypesCache->first(function ($type, $key) use ($normalized) {
            return str_contains($key, $normalized) || str_contains($normalized, $key);
        });

        return $match;
    }

    /**
     * Check if a proficiency name represents a choice-based proficiency.
     *
     * Choice-based proficiencies like "Any one type of Artisan's Tools" should
     * not be linked to a specific ProficiencyType since they represent player choices.
     *
     * @param  string  $name  The proficiency name
     * @return bool True if this is a choice-based proficiency
     */
    protected function isChoiceBasedProficiency(string $name): bool
    {
        $lowerName = strtolower($name);

        // Common patterns for choice-based proficiencies
        $choicePatterns = [
            'any one',
            'one type of',
            'choose one',
            'your choice',
            'of your choice',
            'any musical instrument',
            'any artisan',
        ];

        foreach ($choicePatterns as $pattern) {
            if (str_contains($lowerName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a proficiency name for matching.
     * Handles case differences and apostrophe variants.
     *
     * @param  string  $name  The name to normalize
     * @return string The normalized name
     */
    protected function normalizeName(string $name): string
    {
        // Convert to lowercase and remove all apostrophe variants and spaces
        $name = str_replace("'", '', $name); // Straight apostrophe
        $name = str_replace("'", '', $name); // Right single quotation mark (curly)
        $name = str_replace("'", '', $name); // Left single quotation mark
        $name = str_replace(' ', '', $name); // Spaces

        return strtolower($name);
    }

    /**
     * Infer proficiency type from proficiency name.
     *
     * Uses keyword detection to categorize as armor, weapon, tool, or skill.
     *
     * @param  string  $name  Proficiency name
     * @return string Proficiency type (armor, weapon, tool, skill)
     */
    protected function inferProficiencyTypeFromName(string $name): string
    {
        $lowerName = strtolower($name);

        // Check for armor
        if (str_contains($lowerName, 'armor') || str_contains($lowerName, 'shield')) {
            return 'armor';
        }

        // Check for weapons
        if (str_contains($lowerName, 'weapon') ||
            in_array($lowerName, [
                'battleaxe', 'handaxe', 'light hammer', 'warhammer',
                'longsword', 'shortsword', 'rapier', 'greatsword',
                'dagger', 'mace', 'quarterstaff', 'crossbow', 'bow',
            ])) {
            return 'weapon';
        }

        // Check for tools
        if (str_contains($lowerName, 'tools') ||
            str_contains($lowerName, 'kit') ||
            str_contains($lowerName, 'gaming set') ||
            str_contains($lowerName, 'instrument')) {
            return 'tool';
        }

        // Default to skill
        return 'skill';
    }

    /**
     * Initialize proficiency types (legacy method for backward compatibility).
     *
     * @deprecated Use initializeProficiencyTypesCache() instead (called automatically)
     */
    protected function initializeProficiencyTypes(): void
    {
        $this->initializeProficiencyTypesCache();
    }

    /**
     * Check if a tool name represents an artisan tool choice.
     *
     * @param  string  $name  Tool name from XML
     * @return bool True if this is an artisan tool choice
     */
    protected function isArtisanToolChoice(string $name): bool
    {
        $lowerName = strtolower($name);

        return str_contains($lowerName, 'artisan') && $this->isChoiceBasedProficiency($name);
    }

    /**
     * Check if a tool name represents a musical instrument choice.
     *
     * Matches patterns like:
     * - "Three musical instruments of your choice"
     * - "one musical instrument of your choice"
     * - "any musical instrument"
     *
     * @param  string  $name  Tool name from XML
     * @return bool True if this is a musical instrument choice
     */
    protected function isMusicalInstrumentChoice(string $name): bool
    {
        $lowerName = strtolower($name);

        // Must contain "musical instrument" (singular or plural)
        if (! preg_match('/musical\s+instruments?/', $lowerName)) {
            return false;
        }

        // Check for choice patterns: "of your choice", "any", or word numbers (one, two, three, etc.)
        $choiceIndicators = [
            'of your choice',
            'your choice',
            'any ',
        ];

        foreach ($choiceIndicators as $indicator) {
            if (str_contains($lowerName, $indicator)) {
                return true;
            }
        }

        // Also check for word numbers at the start (e.g., "Three musical instruments")
        $wordNumbers = ['one', 'two', 'three', 'four', 'five'];
        foreach ($wordNumbers as $word) {
            if (preg_match('/\b'.$word.'\b/', $lowerName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract quantity from a tool choice description.
     *
     * Examples:
     * - "one type of Artisan's Tools" → 1
     * - "two types of Artisan's Tools" → 2
     *
     * @param  string  $name  Tool name from XML
     * @return int Quantity to choose (default 1)
     */
    protected function extractToolChoiceQuantity(string $name): int
    {
        $lowerName = strtolower($name);

        $wordNumbers = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
        ];

        foreach ($wordNumbers as $word => $number) {
            if (str_contains($lowerName, $word)) {
                return $number;
            }
        }

        return 1;
    }

    /**
     * Clear all static caches held by this trait.
     *
     * Implements ClearsCaches interface pattern for test isolation.
     */
    public static function clearProficiencyTypesCache(): void
    {
        self::$proficiencyTypesCache = null;
    }
}
