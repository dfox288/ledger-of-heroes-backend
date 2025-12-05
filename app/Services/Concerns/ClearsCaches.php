<?php

namespace App\Services\Concerns;

use App\Services\Importers\Concerns\ImportsSenses;
use App\Services\Parsers\Concerns\LookupsGameEntities;
use App\Services\Parsers\Concerns\MatchesLanguages;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;

/**
 * Cache clearing helper for all concerns with static caching.
 *
 * Provides a central place to clear all static caches used by parser
 * and importer traits. This is useful for:
 * - Test isolation (clear between tests)
 * - Memory management (clear after large imports)
 * - Refreshing cached data after database changes
 *
 * Each trait has its own clearXxxCache() method to avoid trait collisions.
 * This class provides clearAll() to clear all caches at once.
 */
class ClearsCaches
{
    /**
     * Clear all static caches held by all caching concerns.
     *
     * Safe to call even if some traits haven't been loaded.
     */
    public static function clearAll(): void
    {
        // Anonymous class to access trait static methods
        $sensesClearer = new class
        {
            use ImportsSenses;
        };
        $sensesClearer::clearSenseCache();

        $gameEntitiesClearer = new class
        {
            use LookupsGameEntities;
        };
        $gameEntitiesClearer::clearGameEntitiesCache();

        $proficiencyTypesClearer = new class
        {
            use MatchesProficiencyTypes;
        };
        $proficiencyTypesClearer::clearProficiencyTypesCache();

        $languagesClearer = new class
        {
            use MatchesLanguages;
        };
        $languagesClearer::clearLanguagesCache();
    }

    /**
     * Get list of all caching concerns and their clear methods.
     *
     * Useful for documentation and debugging.
     *
     * @return array<string, string> Map of trait class to clear method name
     */
    public static function getCachingConcerns(): array
    {
        return [
            ImportsSenses::class => 'clearSenseCache',
            LookupsGameEntities::class => 'clearGameEntitiesCache',
            MatchesProficiencyTypes::class => 'clearProficiencyTypesCache',
            MatchesLanguages::class => 'clearLanguagesCache',
        ];
    }
}
