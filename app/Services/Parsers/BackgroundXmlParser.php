<?php

namespace App\Services\Parsers;

use App\Models\Skill;
use SimpleXMLElement;

class BackgroundXmlParser
{
    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $backgrounds = [];

        foreach ($xml->background as $bg) {
            $backgrounds[] = [
                'name' => (string) $bg->name,
                'proficiencies' => $this->parseProficiencies((string) $bg->proficiency),
                'traits' => $this->parseTraits($bg->trait),
                'sources' => $this->extractSources($bg->trait[0]->text ?? ''),
            ];
        }

        return $backgrounds;
    }

    private function parseProficiencies(string $profText): array
    {
        if (empty(trim($profText))) {
            return [];
        }

        $profs = [];
        $parts = array_map('trim', explode(',', $profText));

        foreach ($parts as $name) {
            $profs[] = [
                'proficiency_name' => $name,
                'proficiency_type' => $this->inferProficiencyType($name),
                'skill_id' => $this->lookupSkillId($name),
            ];
        }

        return $profs;
    }

    private function inferProficiencyType(string $name): string
    {
        $nameLower = strtolower($name);

        // Check for tool indicators
        if (str_contains($nameLower, 'kit') ||
            str_contains($nameLower, 'tools') ||
            str_contains($nameLower, 'gaming set') ||
            str_contains($nameLower, 'instrument')) {
            return 'tool';
        }

        // Check for language indicators
        if (str_contains($nameLower, 'language')) {
            return 'language';
        }

        // Default to skill (will be validated via skill_id lookup)
        return 'skill';
    }

    private function lookupSkillId(string $name): ?int
    {
        try {
            // Query skills table for matching skill
            $skill = Skill::where('name', $name)->first();

            return $skill?->id;
        } catch (\Exception $e) {
            // If database isn't available (unit tests), return null
            return null;
        }
    }

    private function parseTraits($traits): array
    {
        $parsed = [];

        foreach ($traits as $trait) {
            $name = (string) $trait->name;
            $text = (string) $trait->text;

            $parsed[] = [
                'name' => $name,
                'description' => $this->cleanTraitText($text),
                'category' => $this->inferCategory($name),
                'rolls' => $this->parseRolls($trait->roll),
            ];
        }

        return $parsed;
    }

    private function cleanTraitText(string $text): string
    {
        // Remove source citation (will be stored separately)
        $text = preg_replace('/\n\nSource:.*$/s', '', $text);

        // Trim whitespace
        return trim($text);
    }

    private function inferCategory(string $name): ?string
    {
        if ($name === 'Description') {
            return null;
        }

        if (str_starts_with($name, 'Feature:')) {
            return 'feature';
        }

        if ($name === 'Suggested Characteristics') {
            return 'characteristics';
        }

        // Other flavor traits (Favorite Schemes, etc.)
        return 'flavor';
    }

    private function parseRolls($rolls): array
    {
        $parsed = [];

        foreach ($rolls as $roll) {
            $parsed[] = [
                'description' => (string) ($roll['description'] ?? ''),
                'formula' => (string) $roll,
            ];
        }

        return $parsed;
    }

    private function extractSources(string $text): array
    {
        // Match "Source: Player's Handbook (2014) p. 127"
        if (preg_match('/Source:\s*(.+?)\s*\((\d{4})\)\s*p\.\s*([\d,\s-]+)/i', $text, $matches)) {
            $sourceName = $matches[1];
            $pages = trim($matches[3]);

            // Map source name to code
            $sourceCode = $this->mapSourceNameToCode($sourceName);

            return [
                [
                    'code' => $sourceCode,
                    'pages' => $pages,
                ],
            ];
        }

        // Fallback to PHB if no source found
        return [['code' => 'PHB', 'pages' => '']];
    }

    private function mapSourceNameToCode(string $name): string
    {
        $mappings = [
            "Player's Handbook" => 'PHB',
            'Dungeon Master\'s Guide' => 'DMG',
            'Monster Manual' => 'MM',
            'Xanathar\'s Guide to Everything' => 'XGE',
            'Tasha\'s Cauldron of Everything' => 'TCE',
            'Volo\'s Guide to Monsters' => 'VGTM',
        ];

        return $mappings[$name] ?? 'PHB';
    }
}
