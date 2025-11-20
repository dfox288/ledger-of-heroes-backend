<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for mapping ability score names to standardized codes.
 *
 * Converts full names or abbreviations to uppercase 3-letter codes:
 * - "Strength" -> "STR"
 * - "dexterity" -> "DEX"
 * - "int" -> "INT"
 *
 * Used by: Parsers that handle ability scores
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
        $map = [
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

        $normalized = strtolower(trim($abilityName));

        // Check the map first
        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        // Fallback: return first 3 letters uppercase
        return strtoupper(substr($normalized, 0, 3));
    }
}
