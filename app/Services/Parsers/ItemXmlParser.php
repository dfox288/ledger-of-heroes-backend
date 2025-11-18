<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class ItemXmlParser
{
    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $items = [];

        foreach ($xml->item as $itemElement) {
            $items[] = $this->parseItem($itemElement);
        }

        return $items;
    }

    private function parseItem(SimpleXMLElement $element): array
    {
        $text = (string) $element->text;

        return [
            'name' => (string) $element->name,
            'type_code' => (string) $element->type,
            'rarity' => (string) $element->detail ?: 'common',
            'requires_attunement' => $this->parseAttunement($text),
            'cost_cp' => $this->parseCost((string) $element->value),
            'weight' => isset($element->weight) ? (float) $element->weight : null,
            'damage_dice' => (string) $element->dmg1 ?: null,
            'versatile_damage' => (string) $element->dmg2 ?: null,
            'damage_type_code' => (string) $element->dmgType ?: null,
            'armor_class' => isset($element->ac) ? (int) $element->ac : null,
            'strength_requirement' => isset($element->strength) ? (int) $element->strength : null,
            'stealth_disadvantage' => strtoupper((string) $element->stealth) === 'YES',
            'weapon_range' => (string) $element->range ?: null,
            'description' => $text,
            'properties' => $this->parseProperties((string) $element->property),
            'sources' => $this->parseSourceCitations($text),
            'proficiencies' => $this->extractProficiencies($text),
        ];
    }

    private function parseCost(string $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        // Convert gold pieces to copper pieces (1 GP = 100 CP)
        return (int) round((float) $value * 100);
    }

    private function parseAttunement(string $text): bool
    {
        return stripos($text, 'requires attunement') !== false;
    }

    private function parseProperties(string $propertyString): array
    {
        if (empty($propertyString)) {
            return [];
        }

        return array_map('trim', explode(',', $propertyString));
    }

    private function parseSourceCitations(string $text): array
    {
        $sources = [];

        // Pattern 1: "Book Name (Year) p. PageNumbers" - with year
        // Pattern 2: "Book Name p. PageNumbers" - without year
        // Handles: "p. 150" or "p. 150, 152" or "p. 150-152"

        // Extract "Source: ..." text
        $pattern = '/Source:\s*(.+?)(?:\n|$)/i';
        if (!preg_match($pattern, $text, $sourceMatch)) {
            // Fallback if no source found
            return [
                [
                    'code' => 'PHB',
                    'pages' => '',
                ],
            ];
        }

        $sourcesText = $sourceMatch[1];

        // Try pattern with year first
        $patternWithYear = '/([^(]+)\s*\((\d{4})\)\s*p\.\s*([\d,\s\-]+)/';
        preg_match_all($patternWithYear, $sourcesText, $matches, PREG_SET_ORDER);

        if (!empty($matches)) {
            foreach ($matches as $match) {
                $sourceName = trim($match[1]);
                $pages = trim($match[3]);
                // Remove trailing comma (from multi-source citations)
                $pages = rtrim($pages, ',');

                $sourceCode = $this->getSourceCode($sourceName);

                $sources[] = [
                    'code' => $sourceCode,
                    'pages' => $pages,
                ];
            }
        } else {
            // Try pattern without year
            $patternWithoutYear = '/([^\s]+(?:\s+[^\s]+)*?)\s+p\.\s*([\d,\s\-]+)/';
            preg_match_all($patternWithoutYear, $sourcesText, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $sourceName = trim($match[1]);
                $pages = trim($match[2]);
                // Remove trailing comma (from multi-source citations)
                $pages = rtrim($pages, ',');

                $sourceCode = $this->getSourceCode($sourceName);

                $sources[] = [
                    'code' => $sourceCode,
                    'pages' => $pages,
                ];
            }
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

    private function extractProficiencies(string $text): array
    {
        $proficiencies = [];
        $pattern = '/Proficienc(?:y|ies):\s*([^\n]+)/i';

        if (preg_match($pattern, $text, $matches)) {
            $profList = array_map('trim', explode(',', $matches[1]));
            foreach ($profList as $profName) {
                $proficiencies[] = [
                    'name' => $profName,
                    'type' => $this->inferProficiencyType($profName),
                ];
            }
        }

        return $proficiencies;
    }

    private function inferProficiencyType(string $name): string
    {
        $name = strtolower($name);

        // Armor types
        if (in_array($name, ['light armor', 'medium armor', 'heavy armor', 'shields'])) {
            return 'armor';
        }

        // Weapon types
        if (in_array($name, ['simple', 'martial', 'simple weapons', 'martial weapons']) ||
            str_contains($name, 'weapon')) {
            return 'weapon';
        }

        // Tool types
        if (str_contains($name, 'tools') || str_contains($name, 'kit')) {
            return 'tool';
        }

        // Default to weapon for specific weapon names
        return 'weapon';
    }
}
