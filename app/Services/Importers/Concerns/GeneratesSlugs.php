<?php

namespace App\Services\Importers\Concerns;

use Illuminate\Support\Str;

/**
 * Trait for generating URL-friendly slugs for entities.
 *
 * Handles:
 * - Simple slugs: "Hill Dwarf" -> "hill-dwarf"
 * - Hierarchical slugs: "Battle Master" (fighter) -> "fighter-battle-master"
 * - Special characters and parentheses
 *
 * Used by: All importers
 */
trait GeneratesSlugs
{
    /**
     * Generate a URL-friendly slug for an entity.
     *
     * @param  string  $name  Entity name
     * @param  string|null  $parentSlug  Parent entity slug for hierarchical slugs
     * @return string Generated slug
     */
    protected function generateSlug(string $name, ?string $parentSlug = null): string
    {
        // Generate base slug from name
        $slug = Str::slug($name);

        // If parent slug provided, create hierarchical slug
        if ($parentSlug !== null) {
            return "{$parentSlug}-{$slug}";
        }

        return $slug;
    }
}
