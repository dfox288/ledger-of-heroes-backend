<?php

namespace App\Services\Parsers\Strategies;

use SimpleXMLElement;

/**
 * Strategy for parsing potions.
 *
 * Extracts duration and categorizes potion effects (healing, resistance, buff, etc.).
 */
class PotionStrategy extends AbstractItemStrategy
{
    /**
     * Applies to potion items (type code: P).
     */
    public function appliesTo(array $baseData, SimpleXMLElement $xml): bool
    {
        $typeCode = $baseData['type_code'] ?? '';

        return $typeCode === 'P';
    }

    /**
     * Enhance modifiers with potion-specific metadata.
     *
     * Tracks duration and effect categories.
     */
    public function enhanceModifiers(array $modifiers, array $baseData, SimpleXMLElement $xml): array
    {
        $description = $baseData['description'] ?? '';
        $name = $baseData['name'] ?? '';

        // Extract duration
        $duration = $this->extractDuration($description);
        if ($duration) {
            $this->setMetric('duration', $duration);
        }

        // Categorize potion effect
        $category = $this->categorizeEffect($name, $description, $modifiers);
        if ($category) {
            $this->setMetric('effect_category', $category);
            $this->incrementMetric("effect_{$category}");
        }

        return $modifiers;
    }

    /**
     * Extract duration from potion description.
     *
     * Patterns: "for 1 hour", "for 10 minutes", "for 8 hours"
     */
    private function extractDuration(string $description): ?string
    {
        if (preg_match('/for\s+(\d+)\s+(hour|minute)s?/i', $description, $matches)) {
            $amount = (int) $matches[1];
            $unit = strtolower($matches[2]);

            return $amount.' '.$unit.($amount > 1 ? 's' : '');
        }

        return null;
    }

    /**
     * Categorize potion effect based on name, description, and modifiers.
     *
     * Categories: healing, resistance, buff, debuff, utility
     */
    private function categorizeEffect(string $name, string $description, array $modifiers): ?string
    {
        $nameLower = strtolower($name);
        $descLower = strtolower($description);

        // Check for healing
        if (str_contains($nameLower, 'healing') ||
            str_contains($nameLower, 'health') ||
            preg_match('/regain\s+\d+d\d+.*?hit\s+points/i', $description)) {
            return 'healing';
        }

        // Check for resistance (via modifiers or description)
        foreach ($modifiers as $modifier) {
            if (($modifier['category'] ?? '') === 'damage_resistance') {
                return 'resistance';
            }
        }

        if (str_contains($nameLower, 'resistance')) {
            return 'resistance';
        }

        // Check for buffs (stat increases, advantages)
        if (preg_match('/your\s+\w+\s+(?:score\s+)?(?:is|becomes|increases)/i', $description) ||
            str_contains($descLower, 'advantage on') ||
            str_contains($nameLower, 'strength') ||
            str_contains($nameLower, 'heroism') ||
            str_contains($nameLower, 'invulnerability')) {
            return 'buff';
        }

        // Check for debuffs (poison, reduction)
        if (str_contains($nameLower, 'poison') ||
            str_contains($descLower, 'poisoned') ||
            str_contains($descLower, 'disadvantage')) {
            return 'debuff';
        }

        // Check for utility effects (invisibility, diminution, etc.)
        if (str_contains($nameLower, 'invisibility') ||
            str_contains($nameLower, 'diminution') ||
            str_contains($nameLower, 'growth') ||
            str_contains($nameLower, 'gaseous') ||
            str_contains($nameLower, 'climbing') ||
            str_contains($nameLower, 'flying') ||
            str_contains($nameLower, 'water breathing')) {
            return 'utility';
        }

        // Default to unknown if we can't categorize
        return null;
    }
}
