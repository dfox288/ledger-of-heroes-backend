<?php

namespace App\Services\Parsers\Concerns;

/**
 * Parses natural weapons (claws, fangs, bite) from trait descriptions.
 *
 * Handles patterns like:
 * - "deal 1d4 slashing damage" (simple)
 * - "deal slashing damage equal to 1d4 + your Strength modifier"
 * - "deals 1d4 piercing damage on a hit"
 * - "add your Constitution modifier" (different ability)
 */
trait ParsesNaturalWeapons
{
    /**
     * Parse natural weapons from traits.
     *
     * @param  array<int, array<string, mixed>>  $traits  Parsed trait arrays with 'name' and 'description' keys
     * @return array<int, array{name: string, damage_dice: string, damage_type: string, ability: string|null}>
     */
    protected function parseNaturalWeaponsFromTraits(array $traits): array
    {
        $weapons = [];

        foreach ($traits as $trait) {
            $text = $trait['description'] ?? '';
            $traitName = $trait['name'] ?? '';

            // Skip if this doesn't look like a natural weapon trait
            if (! $this->looksLikeNaturalWeaponTrait($text)) {
                continue;
            }

            $weapon = $this->parseNaturalWeaponFromText($text, $traitName);
            if ($weapon !== null) {
                $weapons[] = $weapon;
            }
        }

        return $weapons;
    }

    /**
     * Check if text contains natural weapon indicators.
     */
    private function looksLikeNaturalWeaponTrait(string $text): bool
    {
        // Must have damage dice AND (natural weapon keywords OR unarmed strike mention)
        $hasDamageDice = preg_match('/\d+d\d+/', $text);
        $hasKeywords = preg_match('/natural weapon|unarmed strike|claws|fangs|bite|talons/i', $text);

        return $hasDamageDice && $hasKeywords;
    }

    /**
     * Parse natural weapon details from text.
     *
     * @return array{name: string, damage_dice: string, damage_type: string, ability: string|null}|null
     */
    private function parseNaturalWeaponFromText(string $text, string $traitName): ?array
    {
        $damageDice = null;
        $damageType = null;
        $ability = null;

        // Pattern 1: "deal TYPE damage equal to DICE + your ABILITY modifier"
        // e.g., "deal slashing damage equal to 1d4 + your Strength modifier"
        if (preg_match('/deal\s+(\w+)\s+damage\s+equal\s+to\s+(\d+d\d+)\s*\+\s*your\s+(Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma)\s+modifier/i', $text, $match)) {
            $damageType = strtolower($match[1]);
            $damageDice = $match[2];
            $ability = $this->abilityToCode($match[3]);
        }
        // Pattern 2: "deals DICE TYPE damage on a hit"
        // e.g., "It deals 1d4 piercing damage on a hit"
        elseif (preg_match('/deals?\s+(\d+d\d+)\s+(\w+)\s+damage/i', $text, $match)) {
            $damageDice = $match[1];
            $damageType = strtolower($match[2]);
        }
        // Pattern 3: "deal DICE TYPE damage"
        // e.g., "deal 1d4 slashing damage"
        elseif (preg_match('/deal\s+(\d+d\d+)\s+(\w+)\s+damage/i', $text, $match)) {
            $damageDice = $match[1];
            $damageType = strtolower($match[2]);
        }

        // Check for alternative ability modifier
        // Pattern: "add your ABILITY modifier, instead of"
        if (preg_match('/add\s+your\s+(Constitution|Dexterity|Intelligence|Wisdom|Charisma)\s+modifier,?\s+instead\s+of/i', $text, $match)) {
            $ability = $this->abilityToCode($match[1]);
        }

        if ($damageDice === null || $damageType === null) {
            return null;
        }

        return [
            'name' => $traitName,
            'damage_dice' => $damageDice,
            'damage_type' => $damageType,
            'ability' => $ability,
        ];
    }

    /**
     * Convert ability name to three-letter code.
     */
    private function abilityToCode(string $abilityName): string
    {
        return match (strtolower($abilityName)) {
            'strength' => 'STR',
            'dexterity' => 'DEX',
            'constitution' => 'CON',
            'intelligence' => 'INT',
            'wisdom' => 'WIS',
            'charisma' => 'CHA',
            default => 'STR',
        };
    }
}
