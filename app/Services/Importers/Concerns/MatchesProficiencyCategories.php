<?php

namespace App\Services\Importers\Concerns;

use App\Models\ProficiencyType;

/**
 * Provides functionality to match equipment category values to ProficiencyType records.
 *
 * Used by importers that reference equipment categories by name (ClassImporter, BackgroundImporter, etc.).
 */
trait MatchesProficiencyCategories
{
    /**
     * Map of category values to proficiency_type slugs.
     *
     * @var array<string, string>
     */
    private static array $categorySlugMap = [
        // Weapons - parent categories (note: core: prefix required)
        'martial' => 'core:martial-weapons',
        'simple' => 'core:simple-weapons',

        // Weapons - specific subcategories (map to parent for now)
        'martial_melee' => 'core:martial-weapons',
        'martial_ranged' => 'core:martial-weapons',
        'simple_melee' => 'core:simple-weapons',
        'simple_ranged' => 'core:simple-weapons',

        // Armor categories
        'light_armor' => 'core:light-armor',
        'medium_armor' => 'core:medium-armor',
        'heavy_armor' => 'core:heavy-armor',
        'shields' => 'core:shields',

        // Tools/instruments categories
        'musical_instrument' => 'core:musical-instruments',
    ];

    /**
     * Match a category value to a ProficiencyType record.
     *
     * @param  string  $category  Category value (e.g., 'martial', 'simple_melee', 'light_armor')
     * @return ProficiencyType|null Matched proficiency type or null if no match
     */
    protected function matchProficiencyCategory(string $category): ?ProficiencyType
    {
        $slug = self::$categorySlugMap[$category] ?? $category;

        return ProficiencyType::where('slug', $slug)->first();
    }
}
