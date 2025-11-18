<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use SimpleXMLElement;

class RaceXmlParser
{
    use MatchesProficiencyTypes;

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

        // Check if name contains parentheses (indicates subrace: "Dwarf (Hill)")
        if (preg_match('/^(.+?)\s*\((.+)\)$/', $fullName, $matches)) {
            $baseRaceName = trim($matches[1]);
            $raceName = $fullName; // Keep full name as-is
        }
        // Also check for comma format (alternative subrace notation)
        elseif (str_contains($fullName, ',')) {
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

        // Convert single source to sources array for consistency
        $sources = [[
            'code' => $sourceCode,
            'pages' => $sourcePages,
        ]];

        return [
            'name' => $raceName,
            'base_race_name' => $baseRaceName,
            'size_code' => (string) $element->size,
            'speed' => (int) $element->speed,
            'traits' => $traits,
            'ability_bonuses' => $abilityBonuses,
            'sources' => $sources,
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

            // Parse rolls within this trait
            $rolls = [];
            foreach ($traitElement->roll as $rollElement) {
                $description = isset($rollElement['description']) ? (string) $rollElement['description'] : null;
                $level = isset($rollElement['level']) ? (int) $rollElement['level'] : null;
                $formula = (string) $rollElement;

                $rolls[] = [
                    'description' => $description,
                    'formula' => $formula,
                    'level' => $level,
                ];
            }

            $traits[] = [
                'name' => $name,
                'category' => $category,
                'description' => trim($text),
                'rolls' => $rolls,
                'sort_order' => $sortOrder++,
            ];
        }

        return $traits;
    }

    private function parseAbilityBonuses(SimpleXMLElement $element): array
    {
        $bonuses = [];

        if (! isset($element->ability)) {
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

        // Parse skill/general proficiencies (multiple <proficiency> elements)
        foreach ($element->proficiency as $profElement) {
            $profName = trim((string) $profElement);
            // Determine type based on common patterns
            $type = $this->determineProficiencyType($profName);

            // NEW: Match to proficiency_types table
            $proficiencyType = $this->matchProficiencyType($profName);

            $proficiencies[] = [
                'type' => $type,
                'name' => $profName,
                'proficiency_type_id' => $proficiencyType?->id,
                'grants' => true, // Races GRANT proficiency
            ];
        }

        // Parse weapon proficiencies
        if (isset($element->weapons)) {
            $weapons = array_map('trim', explode(',', (string) $element->weapons));
            foreach ($weapons as $weapon) {
                $proficiencyType = $this->matchProficiencyType($weapon);
                $proficiencies[] = [
                    'type' => 'weapon',
                    'name' => $weapon,
                    'proficiency_type_id' => $proficiencyType?->id,
                    'grants' => true, // Races GRANT proficiency
                ];
            }
        }

        // Parse armor proficiencies
        if (isset($element->armor)) {
            $armors = array_map('trim', explode(',', (string) $element->armor));
            foreach ($armors as $armor) {
                $proficiencyType = $this->matchProficiencyType($armor);
                $proficiencies[] = [
                    'type' => 'armor',
                    'name' => $armor,
                    'proficiency_type_id' => $proficiencyType?->id,
                    'grants' => true, // Races GRANT proficiency
                ];
            }
        }

        return $proficiencies;
    }

    /**
     * Determine proficiency type based on name
     */
    private function determineProficiencyType(string $name): string
    {
        $lowerName = strtolower($name);

        // Check for armor
        if (str_contains($lowerName, 'armor')) {
            return 'armor';
        }

        // Check for weapons
        if (str_contains($lowerName, 'weapon') ||
            in_array($lowerName, ['battleaxe', 'handaxe', 'light hammer', 'warhammer', 'longsword', 'shortsword', 'rapier'])) {
            return 'weapon';
        }

        // Check for tools
        if (str_contains($lowerName, 'tools') || str_contains($lowerName, 'kit')) {
            return 'tool';
        }

        // Default to skill
        return 'skill';
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
            'Eberron: Rising from the Last War' => 'ERLW',
            'Wayfinder\'s Guide to Eberron' => 'WGTE',
        ];

        return $mapping[$sourceName] ?? 'PHB';
    }
}
