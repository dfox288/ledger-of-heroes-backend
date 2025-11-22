<?php

namespace App\Services\Parsers\Strategies;

use SimpleXMLElement;

/**
 * Strategy for parsing scrolls (spell scrolls and protection scrolls).
 *
 * Extracts spell level from spell scroll names and distinguishes
 * protection scrolls from spell scrolls.
 */
class ScrollStrategy extends AbstractItemStrategy
{
    /**
     * Applies to scroll items (type code: SC).
     */
    public function appliesTo(array $baseData, SimpleXMLElement $xml): bool
    {
        $typeCode = $baseData['type_code'] ?? '';

        return $typeCode === 'SC';
    }

    /**
     * Enhance modifiers with scroll-specific data.
     *
     * For protection scrolls, we may want to add duration modifiers.
     */
    public function enhanceModifiers(array $modifiers, array $baseData, SimpleXMLElement $xml): array
    {
        $name = $baseData['name'] ?? '';
        $description = $baseData['description'] ?? '';

        // Check if this is a protection scroll
        if (stripos($name, 'protection') !== false) {
            // Extract duration if present (e.g., "for 5 minutes")
            if (preg_match('/for\s+(\d+)\s+(minute|hour)s?/i', $description, $matches)) {
                $duration = $matches[1].' '.$matches[2].($matches[1] > 1 ? 's' : '');
                $this->setMetric('protection_duration', $duration);
            }

            $this->incrementMetric('protection_scrolls');
        } else {
            $this->incrementMetric('spell_scrolls');
        }

        return $modifiers;
    }

    /**
     * Extract scroll-specific relationship data.
     *
     * For spell scrolls, extract the spell level from the name.
     */
    public function enhanceRelationships(array $baseData, SimpleXMLElement $xml): array
    {
        $name = $baseData['name'] ?? '';

        // Check if this is a spell scroll (not protection scroll)
        if (stripos($name, 'protection') !== false) {
            return []; // Protection scrolls don't have spell levels
        }

        // Extract spell level from name like "Spell Scroll (3rd Level)"
        if (preg_match('/Spell\s+Scroll\s*\((\d+)(?:st|nd|rd|th)\s+Level\)/i', $name, $matches)) {
            $spellLevel = (int) $matches[1];

            $this->setMetric('spell_level', $spellLevel);

            return ['spell_level' => $spellLevel];
        }

        // Check for cantrip scrolls: "Spell Scroll (Cantrip)"
        if (preg_match('/Spell\s+Scroll\s*\(Cantrip\)/i', $name)) {
            $this->setMetric('spell_level', 0);

            return ['spell_level' => 0];
        }

        // If we can't extract spell level, log a warning
        if (stripos($name, 'spell scroll') !== false) {
            $this->addWarning("Could not extract spell level from scroll name: {$name}");
        }

        return [];
    }
}
