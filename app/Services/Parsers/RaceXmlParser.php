<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\MatchesLanguages;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\ParsesTraits;
use SimpleXMLElement;

class RaceXmlParser
{
    use MatchesLanguages, MatchesProficiencyTypes, ParsesSourceCitations, ParsesTraits;

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
        $traits = $this->parseTraitElements($element);

        // Extract sources from first description trait using shared trait
        $sources = [];
        foreach ($traits as &$trait) {
            if (str_contains($trait['description'], 'Source:')) {
                // Use the ParsesSourceCitations trait to extract ALL sources
                $sources = $this->parseSourceCitations($trait['description']);

                // Remove source line from trait description
                $trait['description'] = trim(preg_replace('/\n*Source:\s*[^\n]+/', '', $trait['description']));
                break;
            }
        }

        // Fallback if no sources found
        if (empty($sources)) {
            $sources = [['code' => 'PHB', 'pages' => '']];
        }

        // Parse ability bonuses
        $abilityBonuses = $this->parseAbilityBonuses($element);

        // Parse proficiencies
        $proficiencies = $this->parseProficiencies($element);

        // Parse choice-based proficiencies from trait text
        $proficiencyChoices = $this->parseProficiencyChoicesFromTraits($traits);
        $proficiencies = array_merge($proficiencies, $proficiencyChoices);

        // Parse languages
        $languages = $this->parseLanguages($element);

        // Parse conditions and immunities
        $conditions = $this->parseConditionsAndImmunities($traits);

        // Parse ability score choices from trait text
        $abilityChoices = $this->parseAbilityChoices($traits);

        // Parse spellcasting
        $spellcasting = $this->parseSpellcasting($element, $traits);

        // Parse resistances
        $resistances = $this->parseResistances($element);

        return [
            'name' => $raceName,
            'base_race_name' => $baseRaceName,
            'size_code' => (string) $element->size,
            'speed' => (int) $element->speed,
            'traits' => $traits,
            'ability_bonuses' => $abilityBonuses,
            'sources' => $sources,
            'proficiencies' => $proficiencies,
            'languages' => $languages,
            'conditions' => $conditions,
            'ability_choices' => $abilityChoices,
            'spellcasting' => $spellcasting,
            'resistances' => $resistances,
        ];
    }

    /**
     * Parse roll elements from XML (temporary implementation).
     * Will be replaced by ParsesRolls trait in next task.
     */
    protected function parseRollElements(SimpleXMLElement $element): array
    {
        $rolls = [];
        foreach ($element->roll as $rollElement) {
            $rolls[] = [
                'description' => isset($rollElement['description']) ? (string) $rollElement['description'] : null,
                'formula' => (string) $rollElement,
                'level' => isset($rollElement['level']) ? (int) $rollElement['level'] : null,
            ];
        }
        return $rolls;
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
                    'value' => (int) $matches[2], // Cast to int to remove '+' prefix
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

    /**
     * Parse languages from traits
     */
    private function parseLanguages(SimpleXMLElement $element): array
    {
        // Look for a trait named "Languages"
        foreach ($element->trait as $traitElement) {
            $traitName = (string) $traitElement->name;
            if ($traitName === 'Languages') {
                $text = (string) $traitElement->text;

                // Use the MatchesLanguages trait to extract language data
                return $this->extractLanguagesFromText($text);
            }
        }

        // No Languages trait found
        return [];
    }

    /**
     * Parse conditions, immunities, and advantages from trait text.
     */
    private function parseConditionsAndImmunities(array $traits): array
    {
        $conditions = [];

        foreach ($traits as $trait) {
            $text = $trait['description'];

            // Pattern: "immune to disease" or "immune to magical aging"
            if (preg_match('/immune to (disease|magical aging)/i', $text, $m)) {
                $conditions[] = [
                    'condition_name' => $m[1],
                    'effect_type' => 'immunity',
                ];
            }

            // Pattern: "advantage on saving throws against being frightened"
            if (preg_match('/advantage on saving throws against being (\w+)/i', $text, $m)) {
                $conditions[] = [
                    'condition_name' => $m[1],
                    'effect_type' => 'advantage',
                ];
            }

            // Pattern: "advantage on saving throws against poison"
            if (preg_match('/advantage on saving throws against poison/i', $text)) {
                $conditions[] = [
                    'condition_name' => 'poisoned',
                    'effect_type' => 'advantage',
                ];
            }
        }

        return $conditions;
    }

    /**
     * Convert word numbers to integers.
     */
    private function wordToNumber(string $word): int
    {
        $map = [
            'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4,
            'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8,
        ];

        return $map[strtolower($word)] ?? 1;
    }

    /**
     * Parse choice-based ability score increases from trait text.
     */
    private function parseAbilityChoices(array $traits): array
    {
        $choices = [];

        foreach ($traits as $trait) {
            // Only check "Ability Score Increase" traits
            if ($trait['name'] !== 'Ability Score Increase' &&
                $trait['name'] !== 'Ability Score Increases') {
                continue;
            }

            $text = $trait['description'];

            // Pattern: "Two different ability scores of your choice increase by 1"
            if (preg_match('/(\w+)\s+different\s+ability scores?\s+of your choice\s+increases?\s+by\s+(\d+)/i', $text, $m)) {
                $count = $this->wordToNumber($m[1]);
                $value = (int) $m[2];

                $choices[] = [
                    'is_choice' => true,
                    'choice_count' => $count,
                    'value' => $value,
                    'choice_constraint' => 'different',
                ];
            }
            // Pattern: "one other ability score of your choice increases by 1"
            elseif (preg_match('/one other ability score of your choice increases by (\d+)/i', $text, $m)) {
                $choices[] = [
                    'is_choice' => true,
                    'choice_count' => 1,
                    'value' => (int) $m[1],
                    'choice_constraint' => 'any',
                ];
            }
            // Pattern: "one ability score of your choice increases by 1"
            elseif (preg_match('/one ability score of your choice increases by (\d+)/i', $text, $m)) {
                $choices[] = [
                    'is_choice' => true,
                    'choice_count' => 1,
                    'value' => (int) $m[1],
                    'choice_constraint' => 'any',
                ];
            }
            // Pattern: "Increase either your Intelligence or Wisdom score by 1"
            elseif (preg_match('/Increase either your (\w+) or (\w+) score by (\d+)/i', $text, $m)) {
                $choices[] = [
                    'is_choice' => true,
                    'choice_count' => 1,
                    'value' => (int) $m[3],
                    'choice_constraint' => 'specific',
                ];
            }
        }

        return $choices;
    }

    /**
     * Parse spellcasting ability and spells from race.
     */
    private function parseSpellcasting(SimpleXMLElement $element, array $traits): array
    {
        $spellData = [
            'ability' => null,
            'spells' => [],
        ];

        // Extract spellcasting ability from XML element
        if (isset($element->spellAbility)) {
            $spellData['ability'] = (string) $element->spellAbility;
        }

        // Parse trait text for spells
        foreach ($traits as $trait) {
            $text = $trait['description'];

            // Skip if no spell-related keywords
            if (! str_contains(strtolower($text), 'cantrip') &&
                ! str_contains(strtolower($text), 'cast') &&
                ! str_contains(strtolower($text), 'spell')) {
                continue;
            }

            // Pattern: "You know the SPELL cantrip"
            if (preg_match_all('/You know the ([\w\s\']+) cantrip/i', $text, $matches)) {
                foreach ($matches[1] as $spellName) {
                    $spellData['spells'][] = [
                        'spell_name' => trim($spellName),
                        'is_cantrip' => true,
                        'level_requirement' => null,
                        'usage_limit' => null,
                    ];
                }
            }

            // Pattern: "Once you reach 3rd level, you can cast the SPELL spell"
            if (preg_match_all('/Once you reach (\d+)(?:st|nd|rd|th) level.*?cast the ([\w\s\']+) spell/i', $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $spellData['spells'][] = [
                        'spell_name' => trim($match[2]),
                        'is_cantrip' => false,
                        'level_requirement' => (int) $match[1],
                        'usage_limit' => '1/long rest',
                    ];
                }
            }
        }

        return $spellData;
    }

    /**
     * Parse damage resistances from <resist> elements.
     */
    private function parseResistances(SimpleXMLElement $element): array
    {
        $resistances = [];

        // Parse <resist> elements
        foreach ($element->resist as $resistElement) {
            $damageType = trim((string) $resistElement);
            if (! empty($damageType)) {
                $resistances[] = [
                    'damage_type' => $damageType,
                ];
            }
        }

        return $resistances;
    }

    /**
     * Parse choice-based proficiencies from trait text.
     * Patterns like: "one skill proficiency and one tool proficiency of your choice"
     */
    private function parseProficiencyChoicesFromTraits(array $traits): array
    {
        $choices = [];

        foreach ($traits as $trait) {
            $text = $trait['description'];

            // Pattern: "one skill proficiency and one tool proficiency of your choice"
            // This handles the compound case where both are mentioned
            if (preg_match('/(\w+)\s+skill\s+proficienc(?:y|ies)\s+and\s+(\w+)\s+tool\s+proficienc(?:y|ies)\s+of your choice/i', $text, $m)) {
                $skillQuantity = $this->wordToNumber($m[1]);
                $toolQuantity = $this->wordToNumber($m[2]);

                $choices[] = [
                    'type' => 'skill',
                    'name' => null,
                    'proficiency_type_id' => null,
                    'grants' => true,
                    'is_choice' => true,
                    'quantity' => $skillQuantity,
                ];

                $choices[] = [
                    'type' => 'tool',
                    'name' => null,
                    'proficiency_type_id' => null,
                    'grants' => true,
                    'is_choice' => true,
                    'quantity' => $toolQuantity,
                ];

                continue; // Skip other patterns if we matched this compound pattern
            }

            // Pattern: "one skill proficiency...of your choice" (standalone)
            if (preg_match('/(\w+)\s+skill\s+proficienc(?:y|ies)\s+of your choice/i', $text, $m)) {
                $quantity = $this->wordToNumber($m[1]);
                $choices[] = [
                    'type' => 'skill',
                    'name' => null,
                    'proficiency_type_id' => null,
                    'grants' => true,
                    'is_choice' => true,
                    'quantity' => $quantity,
                ];
            }

            // Pattern: "one tool proficiency...of your choice" (standalone)
            if (preg_match('/(\w+)\s+tool\s+proficienc(?:y|ies)\s+of your choice/i', $text, $m)) {
                $quantity = $this->wordToNumber($m[1]);
                $choices[] = [
                    'type' => 'tool',
                    'name' => null,
                    'proficiency_type_id' => null,
                    'grants' => true,
                    'is_choice' => true,
                    'quantity' => $quantity,
                ];
            }
        }

        return $choices;
    }
}
