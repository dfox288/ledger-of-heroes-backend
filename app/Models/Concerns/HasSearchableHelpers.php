<?php

namespace App\Models\Concerns;

/**
 * Trait HasSearchableHelpers
 *
 * Provides helper methods for common searchable array patterns.
 * Used by models implementing Laravel Scout's Searchable trait.
 */
trait HasSearchableHelpers
{
    /**
     * Get source codes for indexing.
     * Ensures unique values for consistent filtering.
     *
     * @return array<string>
     */
    protected function getSearchableSourceCodes(): array
    {
        return $this->sources->pluck('source.code')->unique()->values()->all();
    }

    /**
     * Get source names for indexing.
     * Ensures unique values for consistent display.
     *
     * @return array<string>
     */
    protected function getSearchableSourceNames(): array
    {
        return $this->sources->pluck('source.name')->unique()->values()->all();
    }

    /**
     * Get tag slugs for indexing.
     *
     * @return array<string>
     */
    protected function getSearchableTagSlugs(): array
    {
        return $this->tags->pluck('slug')->all();
    }
}
