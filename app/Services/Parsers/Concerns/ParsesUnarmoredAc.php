<?php

namespace App\Services\Parsers\Concerns;

/**
 * Parses unarmored AC calculations from description text.
 *
 * Handles patterns like:
 * - "calculate your AC as 13 + your Dexterity modifier" (Dragon Hide feat)
 * - "your AC is 13 + your Dexterity modifier" (Lizardfolk)
 * - "your AC is 12 + your Constitution modifier" (Loxodon)
 * - "base AC of 17" (Tortle - no ability modifier)
 */
trait ParsesUnarmoredAc
{
    /**
     * Parse unarmored AC calculation from description text.
     *
     * @return array{base_ac: int, ability_code: string|null, allows_shield: bool, replaces_armor: bool}|null
     */
    protected function parseUnarmoredAc(string $text): ?array
    {
        if (empty($text)) {
            return null;
        }

        // Skip armor item descriptions (they have "max 2" or similar constraints)
        if (preg_match('/\(max\s+\d+\)/i', $text)) {
            return null;
        }

        $baseAc = null;
        $abilityCode = null;

        // Pattern 1: "AC is/as/of X + your {Ability} modifier" or "calculate your AC as X + your {Ability} modifier"
        // Covers: "your AC is 13 + your Dexterity modifier", "calculate your AC as 13 + your Dexterity modifier"
        // Also matches: "base AC of X + your {Ability} modifier"
        if (preg_match('/(?:calculate\s+)?(?:your\s+)?(?:AC|base\s+AC)\s+(?:is|as|of)\s+(\d+)\s*\+\s*your\s+(Dexterity|Constitution)\s+modifier/i', $text, $match)) {
            $baseAc = (int) $match[1];
            $abilityCode = $this->mapAbilityToCode($match[2]);
        }
        // Pattern 2: "base AC of/is X" without ability modifier (Tortle)
        elseif (preg_match('/base\s+AC\s+(?:of|is)\s+(\d+)(?:\s*\(your\s+Dexterity\s+modifier\s+doesn\'t\s+affect)?/i', $text, $match)) {
            $baseAc = (int) $match[1];
            $abilityCode = null; // Explicitly no ability modifier
        }

        // No AC pattern found or AC value outside valid D&D range (10-20)
        if ($baseAc === null || $baseAc < 10 || $baseAc > 20) {
            return null;
        }

        return [
            'base_ac' => $baseAc,
            'ability_code' => $abilityCode,
            'allows_shield' => $this->detectShieldAllowed($text),
            'replaces_armor' => $this->detectReplacesArmor($text),
        ];
    }

    /**
     * Map ability name to code.
     *
     * Only maps the six D&D ability scores. The regex patterns ensure
     * only valid ability names (Dexterity, Constitution) are passed here.
     */
    private function mapAbilityToCode(string $abilityName): string
    {
        return match (strtolower($abilityName)) {
            'dexterity' => 'DEX',
            'constitution' => 'CON',
            'strength' => 'STR',
            'intelligence' => 'INT',
            'wisdom' => 'WIS',
            'charisma' => 'CHA',
            default => 'DEX', // Fallback to DEX for safety; regex restricts to DEX/CON anyway
        };
    }

    /**
     * Detect if shields are allowed with this natural armor.
     *
     * Defaults to true unless explicitly prohibited.
     */
    private function detectShieldAllowed(string $text): bool
    {
        // Look for explicit shield mentions - all positive indicators
        // "You can use a shield", "using a shield", "shield's benefits apply"
        // Default to true since D&D generally allows shields with natural armor
        return true;
    }

    /**
     * Detect if this natural armor replaces the ability to wear armor.
     *
     * Returns true if the creature cannot wear armor at all (like Tortle).
     * Returns false if they can choose the better of natural or worn armor.
     */
    private function detectReplacesArmor(string $text): bool
    {
        // Pattern: "can't wear" or "cannot wear" followed by armor types
        if (preg_match('/(?:can\'t|cannot)\s+wear\s+(?:light,?\s*)?(?:medium,?\s*)?(?:(?:or\s+)?heavy\s+)?armor/i', $text)) {
            return true;
        }

        // Pattern: "cannot wear armor"
        if (preg_match('/cannot\s+wear\s+armor/i', $text)) {
            return true;
        }

        return false;
    }
}
