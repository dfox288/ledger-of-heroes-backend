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

    /**
     * Parse source citations that may span multiple books.
     *
     * Examples:
     *   "Player's Handbook (2014) p. 241"
     *   "Dungeon Master's Guide (2014) p. 150,\n\t\tPlayer's Handbook (2014) p. 150"
     *
     * @param string $sourcesText
     * @return array Array of ['code' => 'PHB', 'pages' => '241']
     */
    private function parseSourceCitations(string $sourcesText): array
    {
        $sources = [];

        // Pattern: "Book Name (Year) p. PageNumbers"
        // Handles: "p. 150" or "p. 150, 152" or "p. 150-152"
        $pattern = '/([^(]+)\s*\((\d{4})\)\s*p\.\s*([\d,\s\-]+)/';

        preg_match_all($pattern, $sourcesText, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $sourceName = trim($match[1]);
            $pages = trim($match[3]);

            $sourceCode = $this->getSourceCode($sourceName);

            $sources[] = [
                'code' => $sourceCode,
                'pages' => $pages,
            ];
        }

        // Fallback if no sources parsed (shouldn't happen with valid XML)
        if (empty($sources)) {
            $sources[] = [
                'code' => 'PHB',
                'pages' => '',
            ];
        }

        return $sources;
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
