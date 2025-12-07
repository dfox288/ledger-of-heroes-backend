<?php

namespace App\Services\Importers\Concerns;

use Illuminate\Support\Str;

/**
 * Trait for generating URL-friendly slugs for entities.
 *
 * Handles:
 * - Simple slugs: "Hill Dwarf" -> "hill-dwarf"
 * - Hierarchical slugs: "Battle Master" (fighter) -> "fighter-battle-master"
 * - Full slugs with source prefix: "phb:high-elf"
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

    /**
     * Generate a full slug with source prefix for an entity.
     *
     * Format: {source_code}:{slug} (e.g., "phb:high-elf")
     *
     * @param  string  $slug  The entity's slug
     * @param  array  $sources  Array of source data with 'code' key
     * @return string|null Full slug with source prefix, or null if no sources
     */
    protected function generateFullSlug(string $slug, array $sources): ?string
    {
        if (empty($sources)) {
            return null;
        }

        // Use the first source's code as the prefix
        $primarySourceCode = $sources[0]['code'] ?? null;

        if (! $primarySourceCode) {
            return null;
        }

        return strtolower($primarySourceCode).':'.$slug;
    }

    /**
     * Generate a full slug for universal lookup entities without sources.
     *
     * Uses 'core:' prefix for entities like languages, skills, conditions.
     *
     * @param  string  $slug  The entity's slug
     * @return string Full slug with 'core:' prefix
     */
    protected function generateCoreFullSlug(string $slug): string
    {
        return 'core:'.$slug;
    }
}
