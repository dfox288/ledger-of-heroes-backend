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
        $this->proficiencyTypesCache = ProficiencyType::all()
            ->keyBy(fn($type) => $this->normalizeName($type->name));
    }

    /**
     * Match a proficiency name to a ProficiencyType.
     *
     * @param string $name The proficiency name from XML
     * @return ProficiencyType|null The matched type, or null if no match
     */
    protected function matchProficiencyType(string $name): ?ProficiencyType
    {
        $normalized = $this->normalizeName($name);
        return $this->proficiencyTypesCache->get($normalized);
    }

    /**
     * Normalize a proficiency name for matching.
     * Handles case differences and apostrophe variants.
     *
     * @param string $name The name to normalize
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
