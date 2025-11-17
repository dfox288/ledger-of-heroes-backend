<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class RaceXmlParser
{
    public function parseRaceElement(SimpleXMLElement $raceElement): array
    {
        $data = [
            'name' => (string) $raceElement->name,
            'size_code' => (string) $raceElement->size,
            'speed' => (int) $raceElement->speed,
        ];

        // Parse ability score modifiers
        $data['modifiers'] = [];
        if (!empty($raceElement->ability)) {
            $data['modifiers'] = $this->parseAbilityModifiers((string) $raceElement->ability);
        }

        // Parse traits
        $data['traits'] = [];
        foreach ($raceElement->trait as $traitElement) {
            $trait = [
                'name' => (string) $traitElement->name,
                'category' => !empty($traitElement['category']) ? (string) $traitElement['category'] : null,
                'description' => trim((string) $traitElement->text),
            ];
            $data['traits'][] = $trait;
        }

        // Parse proficiencies
        $data['proficiencies'] = [];
        if (!empty($raceElement->proficiency)) {
            $data['proficiencies'] = $this->parseProficiencies((string) $raceElement->proficiency);
        }

        // Extract source info from first trait
        $sourceInfo = ['code' => 'PHB', 'page' => null];
        if (!empty($data['traits']) && !empty($data['traits'][0]['description'])) {
            $sourceInfo = $this->extractSourceInfo($data['traits'][0]['description']);
        }
        $data['source_code'] = $sourceInfo['code'];
        $data['source_page'] = $sourceInfo['page'];

        return $data;
    }

    private function parseAbilityModifiers(string $abilityString): array
    {
        $modifiers = [];

        // Map of ability abbreviations to full names
        $abilityMap = [
            'Str' => 'strength',
            'Dex' => 'dexterity',
            'Con' => 'constitution',
            'Int' => 'intelligence',
            'Wis' => 'wisdom',
            'Cha' => 'charisma',
        ];

        // Parse "Str +2, Cha +1" format
        $parts = array_map('trim', explode(',', $abilityString));

        foreach ($parts as $part) {
            // Match pattern like "Str +2" or "Dex +1"
            if (preg_match('/(\w+)\s*([+\-]\d+)/', $part, $matches)) {
                $ability = $matches[1];
                $value = $matches[2];

                if (isset($abilityMap[$ability])) {
                    $modifiers[] = [
                        'modifier_type' => 'ability_score',
                        'target' => $abilityMap[$ability],
                        'value' => $value,
                    ];
                }
            }
        }

        return $modifiers;
    }

    private function parseProficiencies(string $proficiencyString): array
    {
        $proficiencies = [];

        // Split by comma
        $items = array_map('trim', explode(',', $proficiencyString));

        foreach ($items as $item) {
            if (!empty($item)) {
                $proficiencies[] = [
                    'proficiency_type' => 'weapon', // Default to weapon for now
                    'name' => $item,
                ];
            }
        }

        return $proficiencies;
    }

    private function extractSourceInfo(string $description): array
    {
        $result = ['code' => 'PHB', 'page' => null];

        // Match "Source: Player's Handbook (2014) p. 32"
        if (preg_match('/Source:\s*(.+?)\s*\(?\d{4}\)?\s*p\.\s*(\d+)/i', $description, $matches)) {
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
