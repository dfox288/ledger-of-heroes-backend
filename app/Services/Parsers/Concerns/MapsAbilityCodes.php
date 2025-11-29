<?php

namespace App\Services\Parsers\Concerns;

use App\Models\AbilityScore;

/**
 * Trait for mapping ability score names to standardized codes and resolving IDs.
 *
 * Converts full names or abbreviations to uppercase 3-letter codes:
 * - "Strength" -> "STR"
 * - "dexterity" -> "DEX"
 * - "int" -> "INT"
 *
 * Also provides methods to resolve ability codes to database IDs with optional caching.
 *
 * Used by: Parsers and Importers that handle ability scores
 */
trait MapsAbilityCodes
{
    /**
     * Map an ability score name to its standard 3-letter code.
     *
     * @param  string  $abilityName  Full name or abbreviation
     * @return string Uppercase 3-letter code (e.g., "STR", "DEX")
     */
    protected function mapAbilityNameToCode(string $abilityName): string
    {
        $map = $this->getAbilityCodeMap();

        $normalized = strtolower(trim($abilityName));

        // Check the map first
        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        // Fallback: return first 3 letters uppercase
        return strtoupper(substr($normalized, 0, 3));
    }

    /**
     * Resolve an ability code or name to its database ID.
     *
     * Uses cachedFindId() if available (from CachesLookupTables trait),
     * otherwise performs a direct query.
     *
     * @param  string  $codeOrName  Ability code ("STR") or name ("Strength")
     * @return int|null Database ID or null if not found
     */
    protected function resolveAbilityScoreId(string $codeOrName): ?int
    {
        // Normalize to code first
        $code = $this->mapAbilityNameToCode($codeOrName);

        // Use cached lookup if available (from CachesLookupTables trait)
        if (method_exists($this, 'cachedFindId')) {
            return $this->cachedFindId(AbilityScore::class, 'code', $code, useFail: false);
        }

        // Fallback to direct query
        return AbilityScore::where('code', $code)->value('id');
    }

    /**
     * Get the ability score code mapping.
     *
     * Uses getAbilityScoreMap() from LoadsLookupData trait if available,
     * otherwise falls back to hardcoded values for unit tests.
     *
     * @return array Map of ability names to codes
     */
    private function getAbilityCodeMap(): array
    {
        // Use lookup table if available (from LoadsLookupData trait)
        if (method_exists($this, 'getAbilityScoreMap')) {
            return $this->getAbilityScoreMap();
        }

        // Fallback for classes without LoadsLookupData trait
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
