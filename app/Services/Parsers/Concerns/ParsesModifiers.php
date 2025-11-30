<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait ParsesModifiers
 *
 * Provides standardized modifier parsing for XML elements like:
 * - <modifier category="bonus">HP +1</modifier>
 * - <modifier category="ability score">Strength +2</modifier>
 *
 * Replaces duplicate implementations in:
 * - ItemXmlParser::parseModifierText()
 * - ClassXmlParser::parseModifierText()
 * - RaceXmlParser::parseModifierText()
 * - FeatXmlParser::parseModifierText()
 *
 * The trait depends on MapsAbilityCodes for ability name → code conversion.
 */
trait ParsesModifiers
{
    /**
     * Parse modifier text to extract structured data.
     *
     * Parses patterns like:
     * - "HP +1", "Speed +10", "Strength +2"
     * - "AC +1", "Initiative +5"
     * - "Melee Attack +1", "Spell DC +2"
     *
     * @param  string  $text  The modifier text (e.g., "Strength +2")
     * @param  string  $xmlCategory  The category attribute from XML (e.g., "ability score", "bonus", "skill")
     * @return array<string, mixed>|null Parsed modifier data or null if unparseable
     */
    protected function parseModifierText(string $text, string $xmlCategory): ?array
    {
        $text = strtolower($text);

        // Pattern: "target +/-value" (e.g., "Strength +2", "HP +1")
        if (! preg_match('/([\w\s]+)\s*([+\-]\d+)/', $text, $matches)) {
            return null;
        }

        $target = trim($matches[1]);
        $value = (int) $matches[2];

        // Determine category based on XML category and target text
        $category = $this->determineModifierCategory($target, $xmlCategory);

        $result = [
            'modifier_category' => $category,
            'value' => $value,
        ];

        // For ability score modifiers, extract the ability code
        if ($category === 'ability_score' && method_exists($this, 'mapAbilityNameToCode')) {
            $result['ability_code'] = $this->mapAbilityNameToCode($target);
        }

        return $result;
    }

    /**
     * Determine the modifier category based on target text and XML category.
     *
     * Order matters - check specific patterns before general ones.
     *
     * @param  string  $target  The target text (e.g., "strength", "hp", "melee attack")
     * @param  string  $xmlCategory  The XML category attribute
     * @return string The determined category
     */
    protected function determineModifierCategory(string $target, string $xmlCategory): string
    {
        // First, check XML category for explicit types
        if ($xmlCategory === 'ability score') {
            return 'ability_score';
        }

        if ($xmlCategory === 'skill') {
            return 'skill';
        }

        // Then check target text for specific patterns (order matters!)
        return match (true) {
            // Combat modifiers (check specific before general)
            str_contains($target, 'saving throw') => 'saving_throw',
            str_contains($target, 'spell attack') => 'spell_attack',
            str_contains($target, 'spell dc') => 'spell_dc',
            str_contains($target, 'melee attack') => 'melee_attack',
            str_contains($target, 'melee damage') => 'melee_damage',
            str_contains($target, 'ranged attack') => 'ranged_attack',
            str_contains($target, 'ranged damage') => 'ranged_damage',
            str_contains($target, 'weapon attack') => 'weapon_attack',
            str_contains($target, 'weapon damage') => 'weapon_damage',
            str_contains($target, 'attack') => 'attack_bonus',
            str_contains($target, 'damage') => 'damage_bonus',

            // Stat modifiers
            str_contains($target, 'hp') || str_contains($target, 'hit point') => 'hp',
            str_contains($target, 'speed') => 'speed',
            str_contains($target, 'initiative') => 'initiative',
            str_contains($target, 'passive') => 'passive',

            // AC modifiers (exact match to avoid matching "acrobatics")
            ($target === 'ac' || $target === 'armor class') && $xmlCategory === 'bonus' => 'ac_magic',
            $target === 'ac' || $target === 'armor class' => 'ac',

            // Default fallback
            default => 'bonus',
        };
    }

    /**
     * Determine the specific category for bonus modifiers.
     *
     * This is a simpler version used by Class/Race/Feat parsers.
     * Kept for backwards compatibility.
     *
     * @param  string  $target  The target text
     * @return string The bonus category
     */
    protected function determineBonusCategory(string $target): string
    {
        return match (true) {
            str_contains($target, 'hp') || str_contains($target, 'hit point') => 'hp',
            str_contains($target, 'speed') => 'speed',
            str_contains($target, 'initiative') => 'initiative',
            str_contains($target, 'ac') || str_contains($target, 'armor class') => 'ac',
            default => 'bonus',
        };
    }
}
