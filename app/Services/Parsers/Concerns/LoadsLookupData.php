<?php

namespace App\Services\Parsers\Concerns;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\Condition;

/**
 * Trait for loading lookup table data into parsers.
 *
 * Provides lazy-loaded access to common lookup tables with graceful
 * fallback for unit tests running without database access.
 *
 * Usage:
 *   $abilities = $this->getAbilityScoreNames(); // ['Strength', 'Dexterity', ...]
 *   $classes = $this->getBaseClassNames();      // ['Wizard', 'Fighter', ...]
 *   $conditions = $this->getConditionSlugs();   // ['charmed', 'frightened', ...]
 */
trait LoadsLookupData
{
    private ?array $abilityScoreNamesCache = null;

    private ?array $abilityScoreMapCache = null;

    private ?array $baseClassNamesCache = null;

    private ?array $conditionSlugsCache = null;

    /**
     * Get all ability score names (e.g., ['Strength', 'Dexterity', ...]).
     *
     * @return array<string>
     */
    protected function getAbilityScoreNames(): array
    {
        if ($this->abilityScoreNamesCache === null) {
            $this->abilityScoreNamesCache = $this->loadAbilityScoreNames();
        }

        return $this->abilityScoreNamesCache;
    }

    /**
     * Get ability score name to code mapping.
     *
     * Returns a map like:
     *   ['strength' => 'STR', 'str' => 'STR', 'dexterity' => 'DEX', ...]
     *
     * @return array<string, string>
     */
    protected function getAbilityScoreMap(): array
    {
        if ($this->abilityScoreMapCache === null) {
            $this->abilityScoreMapCache = $this->loadAbilityScoreMap();
        }

        return $this->abilityScoreMapCache;
    }

    /**
     * Get all base class names (e.g., ['Wizard', 'Fighter', ...]).
     *
     * Only includes base classes (parent_class_id IS NULL), not subclasses.
     *
     * @return array<string>
     */
    protected function getBaseClassNames(): array
    {
        if ($this->baseClassNamesCache === null) {
            $this->baseClassNamesCache = $this->loadBaseClassNames();
        }

        return $this->baseClassNamesCache;
    }

    /**
     * Get all condition slugs (e.g., ['charmed', 'frightened', ...]).
     *
     * @return array<string>
     */
    protected function getConditionSlugs(): array
    {
        if ($this->conditionSlugsCache === null) {
            $this->conditionSlugsCache = $this->loadConditionSlugs();
        }

        return $this->conditionSlugsCache;
    }

    /**
     * Load ability score names from database with fallback.
     */
    private function loadAbilityScoreNames(): array
    {
        try {
            return AbilityScore::pluck('name')->toArray();
        } catch (\Exception $e) {
            // Fallback for unit tests without database
            return ['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'];
        }
    }

    /**
     * Load ability score map from database with fallback.
     *
     * Creates a map with both full names and abbreviations as keys.
     */
    private function loadAbilityScoreMap(): array
    {
        try {
            $abilities = AbilityScore::all();
            $map = [];

            foreach ($abilities as $ability) {
                // Map full name (lowercase) to code
                $map[strtolower($ability->name)] = $ability->code;
                // Map code (lowercase) to code
                $map[strtolower($ability->code)] = $ability->code;
            }

            return $map;
        } catch (\Exception $e) {
            // Fallback for unit tests without database
            return [
                'strength' => 'STR',
                'dexterity' => 'DEX',
                'constitution' => 'CON',
                'intelligence' => 'INT',
                'wisdom' => 'WIS',
                'charisma' => 'CHA',
                'str' => 'STR',
                'dex' => 'DEX',
                'con' => 'CON',
                'int' => 'INT',
                'wis' => 'WIS',
                'cha' => 'CHA',
            ];
        }
    }

    /**
     * Load base class names from database with fallback.
     */
    private function loadBaseClassNames(): array
    {
        try {
            return CharacterClass::whereNull('parent_class_id')
                ->pluck('name')
                ->unique()
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            // Fallback for unit tests without database
            return ['Wizard', 'Sorcerer', 'Warlock', 'Bard', 'Cleric', 'Druid', 'Paladin', 'Ranger', 'Fighter', 'Rogue', 'Barbarian', 'Monk', 'Artificer'];
        }
    }

    /**
     * Load condition slugs from database with fallback.
     */
    private function loadConditionSlugs(): array
    {
        try {
            return Condition::pluck('slug')->toArray();
        } catch (\Exception $e) {
            // Fallback for unit tests without database
            return ['charmed', 'frightened', 'paralyzed', 'stunned', 'poisoned', 'restrained', 'blinded', 'deafened', 'petrified', 'incapacitated'];
        }
    }
}
