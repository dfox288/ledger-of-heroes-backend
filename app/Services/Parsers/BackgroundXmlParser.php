<?php

namespace App\Services\Parsers;

use App\Models\Skill;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

class BackgroundXmlParser
{
    use MatchesProficiencyTypes, ParsesSourceCitations;

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

    /**
     * Extract sources from background trait text.
     * Delegates to ParsesSourceCitations trait.
     */
    private function extractSources(string $text): array
    {
        return $this->parseSourceCitations($text);
    }
}
