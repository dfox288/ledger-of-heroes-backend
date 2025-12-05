<?php

namespace App\Services\Parsers;

use App\Models\Language;
use App\Services\Parsers\Concerns\LookupsGameEntities;
use App\Services\Parsers\Concerns\MatchesLanguages;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\StripsSourceCitations;

class BackgroundXmlParser
{
    use LookupsGameEntities, MatchesLanguages, MatchesProficiencyTypes, ParsesSourceCitations, StripsSourceCitations;

    public function parse(string $xmlContent): array
    {
        $xml = XmlLoader::fromString($xmlContent);
        $backgrounds = [];

        foreach ($xml->background as $bg) {
            $descriptionText = (string) ($bg->trait[0]->text ?? '');

            // Merge XML proficiencies with trait-text tool proficiencies
            $xmlProfs = $this->parseProficiencies((string) $bg->proficiency);
            $toolProfs = $this->parseToolProficienciesFromTraitText($descriptionText);

            // Parse traits
            $parsedTraits = $this->parseTraits($bg->trait);

            // Parse random tables from ALL traits and strip them from descriptions
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
                'proficiency_type' => $this->inferProficiencyTypeFromName($name),
                'skill_id' => $this->lookupSkillId($name),
                'proficiency_type_id' => $proficiencyType?->id, // NEW
                'grants' => true, // Backgrounds GRANT proficiency
            ];
        }

        return $profs;
    }

    // Removed lookupSkillId() - now using LookupsGameEntities trait

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
        // Use the shared StripsSourceCitations trait
        return $this->stripSourceCitations($text);
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
     *
     * Handles patterns like:
     * - "Two of your choice" → 2 unrestricted choice slots
     * - "One of your choice" → 1 unrestricted choice slot
     * - "Common" → specific fixed language
     * - "Dwarvish, or one other of your choice if you already speak Dwarvish" → Dwarvish + 1 conditional choice
     * - "One of your choice of Elvish, Gnomish, Goblin, or Sylvan" → restricted choice from list
     */
    private function parseLanguagesFromTraitText(string $text): array
    {
        if (! preg_match('/• Languages:\s*(.+?)(?:\n|$)/m', $text, $matches)) {
            return [];
        }

        $languageText = trim($matches[1]);

        // Pattern 1: Conditional language with fallback choice
        // "Dwarvish, or one other of your choice if you already speak Dwarvish"
        // Returns: fixed language + 1 conditional choice (only if character already knows the language)
        if (preg_match('/^(\w+),\s*or\s+one\s+other\s+of\s+your\s+choice\s+if\s+you\s+already\s+speak\s+\1$/i', $languageText, $conditionalMatch)) {
            $languageName = $conditionalMatch[1];
            $results = [];

            try {
                $language = $this->matchLanguage($languageName);
                $slug = $this->normalizeLanguageName($languageName);

                // Fixed language grant (for those who don't already know it)
                $results[] = [
                    'language_id' => $language?->id,
                    'language_slug' => $slug,
                    'is_choice' => false,
                    'quantity' => 1,
                ];

                // Conditional choice (only applies if character already knows the language)
                $results[] = [
                    'language_id' => null,
                    'language_slug' => null,
                    'is_choice' => true,
                    'quantity' => 1,
                    'choice_group' => null,
                    'choice_option' => null,
                    'condition_type' => 'already_knows',
                    'condition_language_id' => $language?->id,
                    'condition_language_slug' => $slug,
                ];

                return $results;
            } catch (\Exception $e) {
                // Database not available - return structure without IDs (for unit tests)
                $slug = $this->normalizeLanguageName($languageName);

                return [
                    [
                        'language_id' => null,
                        'language_slug' => $slug,
                        'is_choice' => false,
                        'quantity' => 1,
                    ],
                    [
                        'language_id' => null,
                        'language_slug' => null,
                        'is_choice' => true,
                        'quantity' => 1,
                        'choice_group' => null,
                        'choice_option' => null,
                        'condition_type' => 'already_knows',
                        'condition_language_id' => null,
                        'condition_language_slug' => $slug,
                    ],
                ];
            }
        }

        // Pattern 2: Restricted choice list
        // "One of your choice of Elvish, Gnomish, Goblin, or Sylvan"
        // Returns: multiple rows with choice_group linking them
        if (preg_match('/^one\s+of\s+your\s+choice\s+of\s+(.+)$/i', $languageText, $restrictedMatch)) {
            $languageList = $restrictedMatch[1];

            // Parse the language names from "Elvish, Gnomish, Goblin, or Sylvan"
            $languageNames = $this->parseLanguageList($languageList);
            $results = [];
            $choiceGroup = 'lang_choice_1';
            $optionNum = 1;

            try {
                foreach ($languageNames as $langName) {
                    $language = $this->matchLanguage($langName);

                    $results[] = [
                        'language_id' => $language?->id,
                        'language_slug' => $language ? $language->slug : $this->normalizeLanguageName($langName),
                        'is_choice' => true,
                        'quantity' => $optionNum === 1 ? 1 : null, // Only first row has quantity
                        'choice_group' => $choiceGroup,
                        'choice_option' => $optionNum,
                    ];
                    $optionNum++;
                }

                return $results;
            } catch (\Exception $e) {
                // Database not available - return structure without IDs (for unit tests)
                foreach ($languageNames as $langName) {
                    $results[] = [
                        'language_id' => null,
                        'language_slug' => $this->normalizeLanguageName($langName),
                        'is_choice' => true,
                        'quantity' => $optionNum === 1 ? 1 : null,
                        'choice_group' => $choiceGroup,
                        'choice_option' => $optionNum,
                    ];
                    $optionNum++;
                }

                return $results;
            }
        }

        // Pattern 3: Simple "X of your choice" patterns (e.g., "One of your choice", "Two of your choice")
        // Uses wordToNumber() from ConvertsWordNumbers trait (via MatchesLanguages)
        if (preg_match('/^(one|two|three|four|any)\s+of\s+your\s+choice$/i', $languageText, $choiceMatch)) {
            $quantity = $this->wordToNumber($choiceMatch[1]);

            return [[
                'language_id' => null,
                'is_choice' => true,
                'quantity' => $quantity,
            ]];
        }

        // Pattern 4: Try to match specific language by name directly
        try {
            // Note: languagesCache uses lazy initialization via trait
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
     * Parse a comma/and/or separated list of language names.
     * "Elvish, Gnomish, Goblin, or Sylvan" → ["Elvish", "Gnomish", "Goblin", "Sylvan"]
     */
    private function parseLanguageList(string $text): array
    {
        // Remove "or" and "and" connectors, then split by comma
        $text = preg_replace('/\s+(or|and)\s+/i', ', ', $text);
        $parts = array_map('trim', explode(',', $text));

        return array_filter($parts, fn ($p) => ! empty($p));
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

            // Extract subcategory from tool name (e.g., "artisan's tools" -> "artisan")
            $subcategory = $this->extractToolSubcategory($toolName);

            return [[
                'proficiency_name' => $toolName,
                'proficiency_type' => 'tool',
                'proficiency_subcategory' => $subcategory,
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
            $proficiencySubcategory = null;

            // Check for "with which you are proficient" pattern
            if (preg_match('/^(.+?)\s+with which you (?:are|\'re) proficient$/i', $part, $profMatch)) {
                $isChoice = true;
                $choiceDescription = 'with which you are proficient';
                // Update part to just the base item name for subcategory extraction
                $part = trim($profMatch[1]);
            }

            // Then check for parenthetical choice pattern
            if (! $isChoice && preg_match('/\(([^)]*choice[^)]*)\)/i', $part, $choiceMatch)) {
                $isChoice = true;
                $choiceDescription = trim($choiceMatch[1]);
                // DON'T remove the choice text yet - we need it to extract subcategory
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

            // For choices, extract the subcategory BEFORE removing choice text
            if ($isChoice) {
                // Get the base item name without choice parentheses
                $baseItemName = trim(preg_replace('/\([^)]*choice[^)]*\)/i', '', $itemName));
                $proficiencySubcategory = $this->extractToolSubcategory($baseItemName);
            }

            // Now remove the choice text
            $itemName = trim(preg_replace('/\([^)]*choice[^)]*\)/i', '', $itemName));
            $itemName = trim($itemName);

            // Remove "containing X gp" suffix (e.g., "a belt pouch containing 15 gp")
            if (preg_match('/^(.+?)\s+containing\s+(\d+)\s+gp$/i', $itemName, $containingMatch)) {
                // Add the container
                $items[] = [
                    'item_id' => null,
                    'quantity' => $quantity,
                    'is_choice' => $isChoice,
                    'choice_description' => $choiceDescription,
                    'proficiency_subcategory' => $proficiencySubcategory,
                    'item_name' => trim($containingMatch[1]),
                ];

                // Add the gp separately
                $items[] = [
                    'item_id' => null,
                    'quantity' => (int) $containingMatch[2],
                    'is_choice' => false,
                    'choice_description' => null,
                    'proficiency_subcategory' => null,
                    'item_name' => 'gp',
                ];

                continue;
            }

            $items[] = [
                'item_id' => null,
                'quantity' => $quantity,
                'is_choice' => $isChoice,
                'choice_description' => $choiceDescription,
                'proficiency_subcategory' => $proficiencySubcategory,
                'item_name' => $itemName,
            ];
        }

        return $items;
    }

    /**
     * Extract tool subcategory from tool name.
     * Examples: "artisan's tools" -> "artisan", "gaming set" -> "gaming", "musical instrument" -> "musical"
     */
    private function extractToolSubcategory(string $toolName): ?string
    {
        $normalized = strtolower($toolName);

        // Check for common patterns
        if (str_contains($normalized, 'artisan')) {
            return 'artisan';
        }

        if (str_contains($normalized, 'gaming')) {
            return 'gaming';
        }

        if (str_contains($normalized, 'musical')) {
            return 'musical';
        }

        // Match pattern: "word's tools/instruments/set"
        if (preg_match('/^(\w+)[\'\s]s\s+(tools|instrument|set)/i', $toolName, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    /**
     * Parse ALL embedded random tables from ALL traits and strip table text from descriptions.
     * Uses ItemTableDetector and ItemTableParser to extract tables from trait text.
     *
     * @param  array  $traits  Passed by reference - descriptions will be modified to remove table text
     */
    private function parseAllEmbeddedTables(array &$traits): array
    {
        $detector = new ItemTableDetector;
        $parser = new ItemTableParser;
        $allTables = [];

        foreach ($traits as $index => $trait) {
            $text = $trait['description'];
            $detectedTables = $detector->detectTables($text);

            if (! empty($detectedTables)) {
                // Strip tables from description (process in reverse order to preserve positions)
                $strippedText = $this->stripTablesFromText($text, $detectedTables);
                $traits[$index]['description'] = $strippedText;
            }

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

    /**
     * Strip detected table text from a string using start/end positions.
     * Processes tables in reverse order to preserve character positions.
     */
    private function stripTablesFromText(string $text, array $tables): string
    {
        // Sort tables by start_pos in descending order to strip from end first
        usort($tables, fn ($a, $b) => $b['start_pos'] <=> $a['start_pos']);

        foreach ($tables as $table) {
            $before = substr($text, 0, $table['start_pos']);
            $after = substr($text, $table['end_pos']);
            $text = $before.$after;
        }

        // Clean up multiple consecutive newlines left by removed tables
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
