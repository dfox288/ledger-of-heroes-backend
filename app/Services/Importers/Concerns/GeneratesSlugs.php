<?php

namespace App\Services\Importers\Concerns;

use Illuminate\Support\Str;

/**
 * Trait for generating source-prefixed slugs for entities.
 *
 * All slugs are prefixed with their source code:
 * - Content entities: "phb:fireball", "xge:eldritch-invocations"
 * - Lookup entities: "core:common", "core:acrobatics"
 * - Hierarchical: "phb:fighter-battle-master", "phb:elf-high-elf"
 *
 * Used by: All importers
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/506
 */
trait GeneratesSlugs
{
    /**
     * Generate a source-prefixed slug for an entity.
     *
     * @param  string  $name  Entity name
     * @param  array  $sources  Array of source data with 'code' key (empty = 'core' prefix)
     * @param  string|null  $parentSlug  Parent entity slug for hierarchical slugs
     * @return string Generated slug (e.g., "phb:fireball", "phb:fighter-battle-master")
     */
    protected function generateSlug(string $name, array $sources = [], ?string $parentSlug = null): string
    {
        $nameSlug = Str::slug($name);

        // If parent provided, create hierarchical slug
        if ($parentSlug !== null) {
            // Extract name part from parent slug (after the colon)
            $parentNamePart = Str::after($parentSlug, ':');
            $nameSlug = "{$parentNamePart}-{$nameSlug}";
        }

        // Determine source prefix
        $sourceCode = 'core';
        if (! empty($sources) && ! empty($sources[0]['code'])) {
            $sourceCode = strtolower($sources[0]['code']);
        }

        return "{$sourceCode}:{$nameSlug}";
    }
}
