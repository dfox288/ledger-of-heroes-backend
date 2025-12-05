<?php

namespace App\Services\Concerns;

use App\Models\Spell;

/**
 * Provides spell name normalization and lookup with caching.
 *
 * Used by monster spellcaster strategy and charged item parser.
 */
trait NormalizesSpellNames
{
    /** @var array<string, Spell|null> Cache of spell lookups */
    private array $spellCache = [];

    /**
     * Normalize spell name to Title Case for database matching.
     */
    protected function normalizeSpellName(string $name): string
    {
        return mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Find spell by name (case-insensitive) with caching.
     */
    protected function findSpell(string $name): ?Spell
    {
        $cacheKey = mb_strtolower($name);

        if (! isset($this->spellCache[$cacheKey])) {
            $this->spellCache[$cacheKey] = Spell::whereRaw('LOWER(name) = ?', [$cacheKey])->first();
        }

        return $this->spellCache[$cacheKey];
    }

    /**
     * Clear the spell cache (useful for testing).
     */
    protected function clearSpellCache(): void
    {
        $this->spellCache = [];
    }
}
