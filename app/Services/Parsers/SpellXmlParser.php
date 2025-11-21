<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

class SpellXmlParser
{
    use ParsesSourceCitations;

    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $spells = [];

        foreach ($xml->spell as $spellElement) {
            $spells[] = $this->parseSpell($spellElement);
        }

        return $spells;
    }

    private function parseSpell(SimpleXMLElement $element): array
    {
        $components = (string) $element->components;
        $materialComponents = null;

        // Extract material components from "V, S, M (materials)"
        if (preg_match('/M \(([^)]+)\)/', $components, $matches)) {
            $materialComponents = $matches[1];
            $components = preg_replace('/\s*\([^)]+\)/', '', $components);
        }

        $duration = (string) $element->duration;
        $needsConcentration = stripos($duration, 'concentration') !== false;

        $isRitual = isset($element->ritual) && strtoupper((string) $element->ritual) === 'YES';

        // Parse classes (strip "School: X, " prefix if present)
        $classesString = (string) $element->classes;
        $classesString = preg_replace('/^School:\s*[^,]+,\s*/', '', $classesString);
        $classes = array_map('trim', explode(',', $classesString));

        // Parse description and sources from text elements
        $description = '';
        $sources = [];

        foreach ($element->text as $text) {
            $textContent = (string) $text;

            // Check if this text contains source citation(s)
            if (preg_match('/Source:\s*(.+)/s', $textContent, $matches)) {
                // Extract all sources from the citation (may be multi-line)
                $sourcesText = $matches[1];
                $sources = $this->parseSourceCitations($sourcesText);

                // Remove the source lines from the description
                $textContent = preg_replace('/\n*Source:\s*.+/s', '', $textContent);
            }

            // Add the remaining text to description (even if we extracted source)
            if (trim($textContent)) {
                $description .= $textContent."\n\n";
            }
        }

        // Parse roll elements for spell effects
        $effects = $this->parseRollElements($element);

        return [
            'name' => (string) $element->name,
            'level' => (int) $element->level,
            'school' => (string) $element->school,
            'casting_time' => (string) $element->time,
            'range' => (string) $element->range,
            'components' => $components,
            'material_components' => $materialComponents,
            'duration' => $duration,
            'needs_concentration' => $needsConcentration,
            'is_ritual' => $isRitual,
            'description' => trim($description),
            'higher_levels' => null, // TODO: Parse from description if present
            'classes' => $classes,
            'sources' => $sources, // NEW: Array of sources instead of single source
            'effects' => $effects,
        ];
    }

    private function parseRollElements(SimpleXMLElement $element): array
    {
        $effects = [];
        $spellLevel = (int) $element->level;

        foreach ($element->roll as $roll) {
            $description = (string) $roll['description'];
            $diceFormula = (string) $roll;
            $rollLevel = isset($roll['level']) ? (int) $roll['level'] : null;

            // Determine effect type based on description
            $effectType = $this->determineEffectType($description);

            // Extract damage type name from description (e.g., "Acid Damage" -> "Acid")
            $damageTypeName = $this->extractDamageTypeName($description);

            // Determine scaling type and levels
            if ($rollLevel !== null) {
                if ($spellLevel === 0 && in_array($rollLevel, [0, 5, 11, 17])) {
                    // Cantrip scaling by character level
                    $scalingType = 'character_level';
                    $minCharacterLevel = $rollLevel;
                    $minSpellSlot = null;
                } else {
                    // Spell slot level scaling
                    $scalingType = 'spell_slot_level';
                    $minCharacterLevel = null;
                    $minSpellSlot = $rollLevel;
                }
            } else {
                // No scaling
                $scalingType = 'none';
                $minCharacterLevel = null;
                $minSpellSlot = null;
            }

            $effects[] = [
                'effect_type' => $effectType,
                'description' => $description,
                'damage_type_name' => $damageTypeName, // NEW: Pass damage type name for lookup
                'dice_formula' => $diceFormula,
                'base_value' => null,
                'scaling_type' => $scalingType,
                'min_character_level' => $minCharacterLevel,
                'min_spell_slot' => $minSpellSlot,
                'scaling_increment' => null, // TODO: Parse from "At Higher Levels" text
            ];
        }

        return $effects;
    }

    private function determineEffectType(string $description): string
    {
        $lowercaseDesc = strtolower($description);

        if (str_contains($lowercaseDesc, 'damage')) {
            return 'damage';
        }

        if (str_contains($lowercaseDesc, 'heal') || str_contains($lowercaseDesc, 'regain')) {
            return 'healing';
        }

        return 'other';
    }

    /**
     * Extract damage type name from effect description.
     *
     * Examples:
     * - "Acid Damage" -> "Acid"
     * - "Fire damage" -> "Fire"
     * - "Temporary Hit Points" -> null
     *
     * @param  string  $description  Effect description from roll element
     * @return string|null Damage type name or null if not found
     */
    private function extractDamageTypeName(string $description): ?string
    {
        // Match pattern: "{DamageType} Damage" (case-insensitive)
        if (preg_match('/^(\w+)\s+damage$/i', trim($description), $matches)) {
            // Capitalize first letter to match damage_types table format
            return ucfirst(strtolower($matches[1]));
        }

        return null;
    }
}
