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

        // Parse description from traits with category="description" only
        $description = '';
        $sourceCode = '';
        $sourcePages = '';

        foreach ($element->trait as $trait) {
            // Only include traits with category="description" (lore/flavor text)
            // Skip mechanical traits (Age, Alignment, Size, Languages, species abilities)
            $category = isset($trait['category']) ? (string) $trait['category'] : '';

            if ($category !== 'description') {
                continue;
            }

            $traitName = (string) $trait->name;
            $traitText = (string) $trait->text;

            // Check if this trait contains a source citation
            if (preg_match('/Source:\s*([^p]+)\s*p\.\s*([\d,\s]+)/', $traitText, $matches)) {
                $sourceName = trim($matches[1]);
                $sourcePages = trim($matches[2]);
                $sourceCode = $this->getSourceCode($sourceName);

                // Remove the source line from the text
                $traitText = preg_replace('/\n*Source:\s*[^\n]+/', '', $traitText);
            }

            // Add trait to description (omit the trait name if it's just "Description")
            if (trim($traitText)) {
                if ($traitName === 'Description') {
                    $description .= "{$traitText}\n\n";
                } else {
                    $description .= "**{$traitName}**\n\n{$traitText}\n\n";
                }
            }
        }

        return [
            'name' => $raceName,
            'base_race_name' => $baseRaceName,
            'size_code' => (string) $element->size,
            'speed' => (int) $element->speed,
            'description' => trim($description),
            'source_code' => $sourceCode ?: 'PHB',
            'source_pages' => $sourcePages,
        ];
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
