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
            if (preg_match('/Source:\s*([^p]+)\s*p\.\s*([\d,\s]+)/', $textContent, $matches)) {
                // Extract source book name and pages
                $sourceName = trim($matches[1]);
                $sourcePages = trim($matches[2]);

                // Map source name to code
                $sourceCode = $this->getSourceCode($sourceName);
            } else {
                $description .= $textContent . "\n\n";
            }
        }

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
        ];
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
