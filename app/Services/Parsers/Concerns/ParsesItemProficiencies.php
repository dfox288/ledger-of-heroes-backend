<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing weapon proficiencies from item description text.
 *
 * Handles patterns like:
 * - "you have proficiency with the longbow and shortbow"
 * - "you have proficiency with the rapier"
 * - "proficiency with the longsword, shortsword, and greatsword"
 *
 * Extracts weapon names and creates proficiency records.
 */
trait ParsesItemProficiencies
{
    /**
     * Parse weapon proficiency grants from item description text.
     *
     * @param  string  $text  Item description containing proficiency information
     * @return array Array of proficiency data: [['proficiency_type' => 'weapon', 'proficiency_name' => 'longbow'], ...]
     */
    protected function parseProficienciesFromText(string $text): array
    {
        $proficiencies = [];

        // Pattern: "you have proficiency with the X" or "proficiency with the X"
        // Captures: "longbow and shortbow" or "longsword, shortsword, and greatsword"
        if (preg_match('/proficiency\s+with\s+the\s+([^.]+)/i', $text, $matches)) {
            $weaponList = $matches[1];

            // Clean up the weapon list
            // Remove trailing context like "while wearing" or "when you"
            $weaponList = preg_replace('/\s+(while|when|if|and\s+you).*$/i', '', $weaponList);

            // Split by comma and "and"
            // Handle patterns: "X and Y", "X, Y, and Z"
            $weaponList = str_replace([', and ', ' and '], ',', $weaponList);
            $weapons = array_map('trim', explode(',', $weaponList));

            foreach ($weapons as $weapon) {
                if (! empty($weapon)) {
                    $proficiencies[] = [
                        'proficiency_type' => 'weapon',
                        'proficiency_name' => strtolower($weapon),
                    ];
                }
            }
        }

        return $proficiencies;
    }
}
