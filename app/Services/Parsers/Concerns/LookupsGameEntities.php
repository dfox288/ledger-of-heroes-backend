<?php

namespace App\Services\Parsers\Concerns;

use App\Models\AbilityScore;
use App\Models\Skill;
use Illuminate\Support\Collection;

/**
 * Trait for looking up game entities with caching.
 *
 * Provides efficient lookups for:
 * - Skills (by name)
 * - Ability Scores (by name or code)
 *
 * Uses static caching to avoid repeated database queries.
 *
 * Used by: All parsers that need to reference game entities
 */
trait LookupsGameEntities
{
    private static ?Collection $skillsCache = null;

    private static ?Collection $abilityScoresCache = null;

    /**
     * Look up a skill ID by name.
     *
     * @param  string  $name  Skill name (e.g., "Acrobatics")
     * @return int|null Skill ID or null if not found
     */
    protected function lookupSkillId(string $name): ?int
    {
        $this->initializeSkillsCache();

        $normalized = strtolower(trim($name));

        return self::$skillsCache->get($normalized);
    }

    /**
     * Look up an ability score ID by name or code.
     *
     * @param  string  $nameOrCode  Name (e.g., "Strength") or code (e.g., "STR")
     * @return int|null Ability score ID or null if not found
     */
    protected function lookupAbilityScoreId(string $nameOrCode): ?int
    {
        $this->initializeAbilityScoresCache();

        $normalized = strtolower(trim($nameOrCode));

        return self::$abilityScoresCache->get($normalized);
    }

    /**
     * Initialize skills cache.
     */
    private function initializeSkillsCache(): void
    {
        if (self::$skillsCache === null) {
            try {
                self::$skillsCache = Skill::all()
                    ->mapWithKeys(fn ($skill) => [strtolower($skill->name) => $skill->id]);
            } catch (\Exception $e) {
                // Graceful fallback for unit tests without database
                self::$skillsCache = collect();
            }
        }
    }

    /**
     * Initialize ability scores cache.
     */
    private function initializeAbilityScoresCache(): void
    {
        if (self::$abilityScoresCache === null) {
            try {
                self::$abilityScoresCache = AbilityScore::all()
                    ->flatMap(fn ($ability) => [
                        strtolower($ability->name) => $ability->id,
                        strtolower($ability->code) => $ability->id,
                    ]);
            } catch (\Exception $e) {
                // Graceful fallback for unit tests without database
                self::$abilityScoresCache = collect();
            }
        }
    }
}
