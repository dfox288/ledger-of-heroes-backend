<?php

namespace App\Services\Cache;

use App\Models\AbilityScore;
use App\Models\Condition;
use App\Models\DamageType;
use App\Models\Language;
use App\Models\ProficiencyType;
use App\Models\Size;
use App\Models\SpellSchool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Service for caching static lookup table data.
 *
 * Caches immutable reference data with 1-hour TTL to reduce database load.
 * All lookup tables are seeded once and rarely change, making them ideal
 * candidates for aggressive caching.
 */
class LookupCacheService
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    private const TTL = 3600;

    /**
     * Cache key prefix for all lookups.
     */
    private const PREFIX = 'lookups:';

    /**
     * Get all spell schools (8 schools of magic).
     */
    public function getSpellSchools(): Collection
    {
        return Cache::remember(
            self::PREFIX.'spell-schools:all',
            self::TTL,
            fn () => SpellSchool::all()
        );
    }

    /**
     * Get all damage types (13 types).
     */
    public function getDamageTypes(): Collection
    {
        return Cache::remember(
            self::PREFIX.'damage-types:all',
            self::TTL,
            fn () => DamageType::all()
        );
    }

    /**
     * Get all conditions (15 D&D conditions).
     *
     * Includes monster and spell counts showing how many entities inflict each condition.
     */
    public function getConditions(): Collection
    {
        return Cache::remember(
            self::PREFIX.'conditions:all',
            self::TTL,
            fn () => Condition::withCount(['monsters', 'spells'])->get()
        );
    }

    /**
     * Get all sizes (9 creature sizes).
     */
    public function getSizes(): Collection
    {
        return Cache::remember(
            self::PREFIX.'sizes:all',
            self::TTL,
            fn () => Size::all()
        );
    }

    /**
     * Get all ability scores (6 core abilities).
     */
    public function getAbilityScores(): Collection
    {
        return Cache::remember(
            self::PREFIX.'ability-scores:all',
            self::TTL,
            fn () => AbilityScore::all()
        );
    }

    /**
     * Get all languages (30 D&D languages).
     */
    public function getLanguages(): Collection
    {
        return Cache::remember(
            self::PREFIX.'languages:all',
            self::TTL,
            fn () => Language::all()
        );
    }

    /**
     * Get all proficiency types (82 weapon/armor/tool types).
     */
    public function getProficiencyTypes(): Collection
    {
        return Cache::remember(
            self::PREFIX.'proficiency-types:all',
            self::TTL,
            fn () => ProficiencyType::all()
        );
    }

    /**
     * Clear all lookup caches.
     *
     * Useful after data re-imports or migrations.
     */
    public function clearAll(): void
    {
        $keys = [
            'spell-schools:all',
            'damage-types:all',
            'conditions:all',
            'sizes:all',
            'ability-scores:all',
            'languages:all',
            'proficiency-types:all',
        ];

        foreach ($keys as $key) {
            Cache::forget(self::PREFIX.$key);
        }
    }
}
