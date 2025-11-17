<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class SpellXmlParser
{
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

        // Parse description and source from text elements
        $description = '';
        $sourceCode = '';
        $sourcePages = '';

        foreach ($element->text as $text) {
            $textContent = (string) $text;

            // Check if this text contains a source citation
            if (preg_match('/Source:\s*([^p]+)\s*p\.\s*([\d,\s]+)/', $textContent, $matches)) {
                // Extract source book name and pages
                $sourceName = trim($matches[1]);
                $sourcePages = trim($matches[2]);

                // Map source name to code
                $sourceCode = $this->getSourceCode($sourceName);

                // Remove the source line from the description, but keep the rest
                $textContent = preg_replace('/\n*Source:\s*[^\n]+/', '', $textContent);
            }

            // Add the remaining text to description (even if we extracted source)
            if (trim($textContent)) {
                $description .= $textContent . "\n\n";
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
            'source_code' => $sourceCode,
            'source_pages' => $sourcePages,
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

    private function getSourceCode(string $sourceName): string
    {
        $mapping = [
            "Player's Handbook" => 'PHB',
            'Dungeon Master\'s Guide' => 'DMG',
            'Monster Manual' => 'MM',
            'Xanathar\'s Guide to Everything' => 'XGE',
            'Tasha\'s Cauldron of Everything' => 'TCE',
            'Volo\'s Guide to Monsters' => 'VGTM',
        ];

        return $mapping[$sourceName] ?? 'PHB';
    }
}
