<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class SpellXmlParser
{
    public function parseSpellElement(SimpleXMLElement $spellElement): array
    {
        $data = [
            'name' => (string) $spellElement->name,
            'level' => (int) $spellElement->level,
            'school_code' => (string) $spellElement->school,
            'is_ritual' => strtoupper((string) $spellElement->ritual) === 'YES',
            'casting_time' => (string) $spellElement->time,
            'range' => (string) $spellElement->range,
            'duration' => (string) $spellElement->duration,
            'description' => $this->parseDescription($spellElement),
        ];

        // Parse components
        $components = $this->parseComponents((string) $spellElement->components);
        $data = array_merge($data, $components);

        // Parse classes
        $data['classes'] = $this->parseClasses((string) $spellElement->classes);

        // Extract source info
        $sourceInfo = $this->extractSourceInfo($data['description']);
        $data['source_code'] = $sourceInfo['code'];
        $data['source_page'] = $sourceInfo['page'];

        return $data;
    }

    private function parseDescription(SimpleXMLElement $spellElement): string
    {
        $parts = [];

        foreach ($spellElement->text as $text) {
            $parts[] = trim((string) $text);
        }

        return implode("\n\n", $parts);
    }

    private function parseComponents(string $componentsString): array
    {
        $result = [
            'has_verbal_component' => false,
            'has_somatic_component' => false,
            'has_material_component' => false,
            'material_description' => null,
            'material_cost_gp' => null,
            'material_consumed' => false,
        ];

        if (empty($componentsString)) {
            return $result;
        }

        $result['has_verbal_component'] = str_contains($componentsString, 'V');
        $result['has_somatic_component'] = str_contains($componentsString, 'S');
        $result['has_material_component'] = str_contains($componentsString, 'M');

        // Extract material description
        if (preg_match('/M \(([^)]+)\)/', $componentsString, $matches)) {
            $result['material_description'] = $matches[1];

            // Extract cost
            if (preg_match('/worth (?:at least )?(\d+(?:,\d+)*) gp/', $matches[1], $costMatches)) {
                $result['material_cost_gp'] = (int) str_replace(',', '', $costMatches[1]);
            }

            // Check if consumed
            if (str_contains(strtolower($matches[1]), 'consume')) {
                $result['material_consumed'] = true;
            }
        }

        return $result;
    }

    private function parseClasses(string $classesString): array
    {
        if (empty($classesString)) {
            return [];
        }

        // Remove "School: X, " prefix if present
        $classesString = preg_replace('/^School:\s*[^,]+,\s*/', '', $classesString);

        $classes = [];
        $parts = array_map('trim', explode(',', $classesString));

        foreach ($parts as $part) {
            if (preg_match('/^(.+?)\s*\((.+?)\)$/', $part, $matches)) {
                // Class with subclass: "Fighter (Eldritch Knight)"
                $classes[] = [
                    'class_name' => trim($matches[1]),
                    'subclass_name' => trim($matches[2]),
                ];
            } else {
                // Just class name
                $classes[] = [
                    'class_name' => trim($part),
                    'subclass_name' => null,
                ];
            }
        }

        return $classes;
    }

    private function extractSourceInfo(string $description): array
    {
        $result = [
            'code' => null,
            'page' => null,
        ];

        // Match "Source: Player's Handbook (2014) p. 211"
        if (preg_match('/Source:\s*(.+?)\s*\(?\d{4}\)?\s*p\.\s*(\d+)/i', $description, $matches)) {
            // Map common book names to codes
            $bookMap = [
                "Player's Handbook" => 'PHB',
                "Dungeon Master's Guide" => 'DMG',
                'Monster Manual' => 'MM',
                "Xanathar's Guide to Everything" => 'XGE',
                "Tasha's Cauldron of Everything" => 'TCE',
            ];

            $bookName = trim($matches[1]);
            $result['code'] = $bookMap[$bookName] ?? 'PHB';
            $result['page'] = (int) $matches[2];
        }

        return $result;
    }
}
