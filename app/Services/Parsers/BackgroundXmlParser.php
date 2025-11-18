<?php

namespace App\Services\Parsers;

use App\Models\Skill;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use SimpleXMLElement;

class BackgroundXmlParser
{
    use MatchesProficiencyTypes;

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
            // NEW: Match to proficiency_types table
            $proficiencyType = $this->matchProficiencyType($name);

            $profs[] = [
                'proficiency_name' => $name,
                'proficiency_type' => $this->inferProficiencyType($name),
                'skill_id' => $this->lookupSkillId($name),
                'proficiency_type_id' => $proficiencyType?->id, // NEW
                'grants' => true, // Backgrounds GRANT proficiency
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
        $sources = [];

        // Extract the entire source section (everything after "Source:")
        if (preg_match('/Source:\s*(.+)$/ims', $text, $sourceSection)) {
            $sourceText = $sourceSection[1];

            // Try to match sources with year: "Book Name (Year) p. Pages"
            if (preg_match_all('/([^,\n]+?)\s*\((\d{4})\)\s*p\.\s*([\d,\s-]+)/i', $sourceText, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $sourceName = trim($match[1]);
                    $pages = trim($match[3]);
                    // Remove trailing comma
                    $pages = rtrim($pages, ',');

                    $sourceCode = $this->mapSourceNameToCode($sourceName);

                    $sources[] = [
                        'code' => $sourceCode,
                        'pages' => $pages,
                    ];
                }
            } else {
                // Try to match sources without year: "Book Name p. Pages"
                if (preg_match_all('/([^,\n]+?)\s+p\.\s*([\d,\s-]+)/i', $sourceText, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $sourceName = trim($match[1]);
                        // Remove "Source:" prefix if present (from first match)
                        $sourceName = preg_replace('/^Source:\s*/i', '', $sourceName);
                        $pages = trim($match[2]);
                        // Remove trailing comma
                        $pages = rtrim($pages, ',');

                        $sourceCode = $this->mapSourceNameToCode($sourceName);

                        $sources[] = [
                            'code' => $sourceCode,
                            'pages' => $pages,
                        ];
                    }
                }
            }
        }

        // Fallback to PHB if no sources found
        if (empty($sources)) {
            $sources[] = ['code' => 'PHB', 'pages' => ''];
        }

        return $sources;
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
            'Eberron: Rising from the Last War' => 'ERLW',
            'Wayfinder\'s Guide to Eberron' => 'WGTE',
        ];

        return $mappings[$name] ?? 'PHB';
    }
}
