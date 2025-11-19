<?php

namespace App\Services\Parsers;

use App\Models\Language;
use App\Models\Skill;
use App\Services\Parsers\Concerns\MatchesLanguages;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

class BackgroundXmlParser
{
    use MatchesLanguages, MatchesProficiencyTypes, ParsesSourceCitations;

    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $backgrounds = [];

        foreach ($xml->background as $bg) {
            $descriptionText = (string) ($bg->trait[0]->text ?? '');

            // Merge XML proficiencies with trait-text tool proficiencies
            $xmlProfs = $this->parseProficiencies((string) $bg->proficiency);
            $toolProfs = $this->parseToolProficienciesFromTraitText($descriptionText);

            // Parse traits
            $parsedTraits = $this->parseTraits($bg->trait);

            // Parse random tables from ALL traits
            $randomTables = $this->parseAllEmbeddedTables($parsedTraits);

            $backgrounds[] = [
                'name' => (string) $bg->name,
                'proficiencies' => array_merge($xmlProfs, $toolProfs),
                'traits' => $parsedTraits,
                'sources' => $this->extractSources($descriptionText),
                'languages' => $this->parseLanguagesFromTraitText($descriptionText),
                'equipment' => $this->parseEquipmentFromTraitText($descriptionText),
                'random_tables' => $randomTables,
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

    /**
     * Parse languages from trait Description text.
     * Pattern: "• Languages: One of your choice" or "• Languages: Common"
     */
    private function parseLanguagesFromTraitText(string $text): array
    {
        if (! preg_match('/• Languages:\s*(.+?)(?:\n|$)/m', $text, $matches)) {
            return [];
        }

        $languageText = trim($matches[1]);

        // Check for "one of your choice" or similar choice patterns
        if (preg_match('/one.*?choice/i', $languageText)) {
            return [[
                'language_id' => null,
                'is_choice' => true,
                'quantity' => 1,
            ]];
        }

        // Try to match specific language by name directly
        try {
            // Initialize languages cache if not already done
            $this->initializeLanguages();

            $language = $this->matchLanguage($languageText);
            if ($language) {
                return [[
                    'language_id' => $language->id,
                    'is_choice' => false,
                    'quantity' => 1,
                ]];
            }

            // Fallback: Parse using extractLanguagesFromText for complex cases
            $languageResults = $this->extractLanguagesFromText($languageText);
            $languages = [];

            foreach ($languageResults as $langData) {
                if ($langData['is_choice']) {
                    $languages[] = [
                        'language_id' => null,
                        'is_choice' => true,
                        'quantity' => 1,
                    ];
                } else {
                    // Map slug to language_id
                    $language = Language::where('slug', $langData['slug'])->first();
                    $languages[] = [
                        'language_id' => $language?->id,
                        'is_choice' => false,
                        'quantity' => 1,
                    ];
                }
            }

            return $languages;
        } catch (\Exception $e) {
            // Database not available in unit tests - return empty
            return [];
        }
    }

    /**
     * Parse tool proficiencies from trait Description text.
     * Pattern: "• Tool Proficiencies: One type of artisan's tools" or "• Tool Proficiencies: Navigator's tools"
     */
    private function parseToolProficienciesFromTraitText(string $text): array
    {
        if (! preg_match('/• Tool Proficiencies:\s*(.+?)(?:\n|$)/m', $text, $matches)) {
            return [];
        }

        $toolText = trim($matches[1]);
        $proficiencies = [];

        // Check for "one type of" or choice pattern
        if (preg_match('/one\s+type\s+of\s+(.+?)$/i', $toolText, $choiceMatch)) {
            $toolName = trim($choiceMatch[1]);
            $proficiencyType = $this->matchProficiencyType($toolName);

            return [[
                'proficiency_name' => $toolName,
                'proficiency_type' => 'tool',
                'proficiency_type_id' => $proficiencyType?->id,
                'skill_id' => null,
                'is_choice' => true,
                'quantity' => 1,
                'grants' => true,
            ]];
        }

        // Parse comma-separated tool list (e.g., "Navigator's tools, vehicles (water)")
        $tools = array_map('trim', explode(',', $toolText));

        foreach ($tools as $toolName) {
            if (empty($toolName)) {
                continue;
            }

            $proficiencyType = $this->matchProficiencyType($toolName);

            $proficiencies[] = [
                'proficiency_name' => $toolName,
                'proficiency_type' => 'tool',
                'proficiency_type_id' => $proficiencyType?->id,
                'skill_id' => null,
                'is_choice' => false,
                'quantity' => 1,
                'grants' => true,
            ];
        }

        return $proficiencies;
    }

    /**
     * Parse equipment from trait Description text.
     * Pattern: "• Equipment: A set of artisan's tools (one of your choice), a letter..."
     */
    private function parseEquipmentFromTraitText(string $text): array
    {
        if (! preg_match('/• Equipment:\s*(.+?)(?:\n\n|\n[A-Z•]|$)/ms', $text, $matches)) {
            return [];
        }

        $equipmentText = trim($matches[1]);
        $items = [];

        // Split by commas, but preserve parenthetical content
        // This regex splits on ", " but not if inside parentheses
        $parts = preg_split('/,\s*(?![^()]*\))/', $equipmentText);

        foreach ($parts as $part) {
            $part = trim($part);

            // Skip empty parts and "and" connectors
            if (empty($part) || strtolower($part) === 'and') {
                continue;
            }

            // Remove leading "and"
            $part = preg_replace('/^and\s+/i', '', $part);

            if (empty($part)) {
                continue;
            }

            // Check for choice pattern
            $isChoice = false;
            $choiceDescription = null;
            if (preg_match('/\(([^)]*choice[^)]*)\)/i', $part, $choiceMatch)) {
                $isChoice = true;
                $choiceDescription = trim($choiceMatch[1]);
                $part = trim(preg_replace('/\([^)]*choice[^)]*\)/i', '', $part));
            }

            // Extract quantity (e.g., "15 gp", "10 torches")
            $quantity = 1;
            if (preg_match('/^(\d+)\s+/', $part, $qtyMatch)) {
                $quantity = (int) $qtyMatch[1];
                $part = trim(substr($part, strlen($qtyMatch[0])));
            }

            // Clean up article prefixes
            $itemName = preg_replace('/^(a|an|the)\s+/i', '', $part);
            $itemName = preg_replace('/\s*set\s+of\s+/i', '', $itemName);
            $itemName = trim($itemName);

            // Remove "containing X gp" suffix (e.g., "a belt pouch containing 15 gp")
            if (preg_match('/^(.+?)\s+containing\s+(\d+)\s+gp$/i', $itemName, $containingMatch)) {
                // Add the container
                $items[] = [
                    'item_id' => null,
                    'quantity' => $quantity,
                    'is_choice' => $isChoice,
                    'choice_description' => $choiceDescription,
                    'item_name' => trim($containingMatch[1]),
                ];

                // Add the gp separately
                $items[] = [
                    'item_id' => null,
                    'quantity' => (int) $containingMatch[2],
                    'is_choice' => false,
                    'choice_description' => null,
                    'item_name' => 'gp',
                ];

                continue;
            }

            $items[] = [
                'item_id' => null,
                'quantity' => $quantity,
                'is_choice' => $isChoice,
                'choice_description' => $choiceDescription,
                'item_name' => $itemName,
            ];
        }

        return $items;
    }

    /**
     * Parse ALL embedded random tables from ALL traits.
     * Uses ItemTableDetector and ItemTableParser to extract tables from trait text.
     */
    private function parseAllEmbeddedTables(array $traits): array
    {
        $detector = new ItemTableDetector;
        $parser = new ItemTableParser;
        $allTables = [];

        foreach ($traits as $trait) {
            $text = $trait['description'];
            $detectedTables = $detector->detectTables($text);

            foreach ($detectedTables as $tableInfo) {
                $parsedTable = $parser->parse($tableInfo['text'], $tableInfo['dice_type']);

                $allTables[] = [
                    'name' => $tableInfo['name'],
                    'dice_type' => $tableInfo['dice_type'],
                    'trait_name' => $trait['name'], // For linking back to trait
                    'entries' => $parsedTable['rows'],
                ];
            }
        }

        return $allTables;
    }
}
