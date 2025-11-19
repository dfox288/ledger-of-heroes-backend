<?php

namespace App\Services\Parsers\Concerns;

use App\Models\ProficiencyType;
use Illuminate\Support\Collection;

trait MatchesProficiencyTypes
{
    private Collection $proficiencyTypesCache;

    /**
     * Initialize the proficiency types cache.
     * Call this in the parser's constructor.
     */
    protected function initializeProficiencyTypes(): void
    {
        if (! isset($this->proficiencyTypesCache)) {
            try {
                $this->proficiencyTypesCache = ProficiencyType::all()
                    ->keyBy(fn ($type) => $this->normalizeName($type->name));
            } catch (\Exception $e) {
                // Graceful fallback for unit tests without database
                $this->proficiencyTypesCache = collect();
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
        // Lazy initialization for backward compatibility with unit tests
        if (! isset($this->proficiencyTypesCache)) {
            $this->initializeProficiencyTypes();
        }

        $normalized = $this->normalizeName($name);

        // Exact match first
        $match = $this->proficiencyTypesCache->get($normalized);
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
            $match = $this->proficiencyTypesCache->get($fuzzyMatches[$normalized]);
            if ($match) {
                return $match;
            }
        }

        // Partial string matching - find proficiency type that contains the search term
        // e.g., "staff" should match "Quarterstaff"
        $match = $this->proficiencyTypesCache->first(function ($type, $key) use ($normalized) {
            return str_contains($key, $normalized) || str_contains($normalized, $key);
        });

        return $match;
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
}
