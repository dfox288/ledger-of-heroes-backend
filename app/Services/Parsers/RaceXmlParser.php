<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class RaceXmlParser
{
    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $races = [];

        foreach ($xml->race as $raceElement) {
            $races[] = $this->parseRace($raceElement);
        }

        return $races;
    }

    private function parseRace(SimpleXMLElement $element): array
    {
        // Parse race name and extract base race / subrace
        $fullName = (string) $element->name;
        $baseRaceName = null;
        $raceName = $fullName;

        // Check if name contains comma (indicates subrace)
        if (str_contains($fullName, ',')) {
            [$baseRaceName, $raceName] = array_map('trim', explode(',', $fullName, 2));
        }

        // Parse traits
        $traits = $this->parseTraits($element);

        // Extract source from first description trait
        $sourceCode = 'PHB';
        $sourcePages = '';
        foreach ($traits as &$trait) {
            if (preg_match('/Source:\s*([^p]+)\s*p\.\s*([\d,\s]+)/', $trait['description'], $matches)) {
                $sourceName = trim($matches[1]);
                $sourcePages = trim($matches[2]);
                $sourceCode = $this->getSourceCode($sourceName);

                // Remove source line from trait description
                $trait['description'] = trim(preg_replace('/\n*Source:\s*[^\n]+/', '', $trait['description']));
                break;
            }
        }

        // Parse ability bonuses
        $abilityBonuses = $this->parseAbilityBonuses($element);

        // Parse proficiencies
        $proficiencies = $this->parseProficiencies($element);

        return [
            'name' => $raceName,
            'base_race_name' => $baseRaceName,
            'size_code' => (string) $element->size,
            'speed' => (int) $element->speed,
            'traits' => $traits,
            'ability_bonuses' => $abilityBonuses,
            'source_code' => $sourceCode,
            'source_pages' => $sourcePages,
            'proficiencies' => $proficiencies,
        ];
    }

    private function parseTraits(SimpleXMLElement $element): array
    {
        $traits = [];
        $sortOrder = 0;

        foreach ($element->trait as $traitElement) {
            $category = isset($traitElement['category']) ? (string) $traitElement['category'] : null;
            $name = (string) $traitElement->name;
            $text = (string) $traitElement->text;

            $traits[] = [
                'name' => $name,
                'category' => $category,
                'description' => trim($text),
                'sort_order' => $sortOrder++,
            ];
        }

        return $traits;
    }

    private function parseAbilityBonuses(SimpleXMLElement $element): array
    {
        $bonuses = [];

        if (!isset($element->ability)) {
            return $bonuses;
        }

        $abilityText = (string) $element->ability;

        // Parse format: "Str +2, Cha +1"
        $parts = array_map('trim', explode(',', $abilityText));

        foreach ($parts as $part) {
            // Match "Str +2" or "Dex +1"
            if (preg_match('/^([A-Za-z]{3})\s*([+-]\d+)$/', $part, $matches)) {
                $bonuses[] = [
                    'ability' => $matches[1],
                    'value' => $matches[2],
                ];
            }
        }

        return $bonuses;
    }

    private function parseProficiencies(SimpleXMLElement $element): array
    {
        $proficiencies = [];

        // Parse skill proficiencies
        if (isset($element->proficiency)) {
            $skills = array_map('trim', explode(',', (string) $element->proficiency));
            foreach ($skills as $skill) {
                $proficiencies[] = [
                    'type' => 'skill',
                    'name' => $skill,
                ];
            }
        }

        // Parse weapon proficiencies
        if (isset($element->weapons)) {
            $weapons = array_map('trim', explode(',', (string) $element->weapons));
            foreach ($weapons as $weapon) {
                $proficiencies[] = [
                    'type' => 'weapon',
                    'name' => $weapon,
                ];
            }
        }

        // Parse armor proficiencies
        if (isset($element->armor)) {
            $armors = array_map('trim', explode(',', (string) $element->armor));
            foreach ($armors as $armor) {
                $proficiencies[] = [
                    'type' => 'armor',
                    'name' => $armor,
                ];
            }
        }

        return $proficiencies;
    }

    private function getSourceCode(string $sourceName): string
    {
        $mapping = [
            "Player's Handbook" => 'PHB',
            "Player's Handbook (2014)" => 'PHB',
            'Dungeon Master\'s Guide' => 'DMG',
            'Monster Manual' => 'MM',
            'Xanathar\'s Guide to Everything' => 'XGE',
            'Tasha\'s Cauldron of Everything' => 'TCE',
            'Volo\'s Guide to Monsters' => 'VGTM',
        ];

        return $mapping[$sourceName] ?? 'PHB';
    }
}
