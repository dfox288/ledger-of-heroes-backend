<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

class ClassXmlParser
{
    use MatchesProficiencyTypes, ParsesSourceCitations;

    /**
     * Parse classes from XML string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $element = new SimpleXMLElement($xml);
        $classes = [];

        // Parse each <class> element
        foreach ($element->class as $classElement) {
            $classes[] = $this->parseClass($classElement);
        }

        return $classes;
    }

    /**
     * Parse a single class element.
     *
     * @return array<string, mixed>
     */
    private function parseClass(SimpleXMLElement $element): array
    {
        $data = [
            'name' => (string) $element->name,
            'hit_die' => (int) $element->hd,
        ];

        // Parse description from first text element if exists
        if (isset($element->text)) {
            $description = [];
            foreach ($element->text as $text) {
                $description[] = trim((string) $text);
            }
            $data['description'] = implode("\n\n", $description);
        }

        // Parse proficiencies
        $data['proficiencies'] = $this->parseProficiencies($element);

        // Parse skill choices
        if (isset($element->numSkills)) {
            $data['skill_choices'] = (int) $element->numSkills;
        }

        return $data;
    }

    /**
     * Parse proficiencies from class XML.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseProficiencies(SimpleXMLElement $element): array
    {
        $proficiencies = [];

        // Parse armor proficiencies
        if (isset($element->armor)) {
            $armors = array_map('trim', explode(',', (string) $element->armor));
            foreach ($armors as $armor) {
                if (strtolower($armor) === 'none') {
                    continue;
                }
                $proficiencyType = $this->matchProficiencyType($armor);
                $proficiencies[] = [
                    'type' => 'armor',
                    'name' => $armor,
                    'proficiency_type_id' => $proficiencyType?->id,
                ];
            }
        }

        // Parse weapon proficiencies
        if (isset($element->weapons)) {
            $weapons = array_map('trim', explode(',', (string) $element->weapons));
            foreach ($weapons as $weapon) {
                if (strtolower($weapon) === 'none') {
                    continue;
                }
                $proficiencyType = $this->matchProficiencyType($weapon);
                $proficiencies[] = [
                    'type' => 'weapon',
                    'name' => $weapon,
                    'proficiency_type_id' => $proficiencyType?->id,
                ];
            }
        }

        // Parse tool proficiencies
        if (isset($element->tools)) {
            $tools = array_map('trim', explode(',', (string) $element->tools));
            foreach ($tools as $tool) {
                if (strtolower($tool) === 'none') {
                    continue;
                }
                $proficiencyType = $this->matchProficiencyType($tool);
                $proficiencies[] = [
                    'type' => 'tool',
                    'name' => $tool,
                    'proficiency_type_id' => $proficiencyType?->id,
                ];
            }
        }

        // Parse saving throws and skills from <proficiency> element
        // Format: "Strength, Constitution, Acrobatics, Animal Handling, ..."
        // First two are saving throws, rest are available skills
        if (isset($element->proficiency)) {
            $items = array_map('trim', explode(',', (string) $element->proficiency));

            // Classes typically list saving throws first (2), then skills
            // We'll need to detect which are abilities vs skills
            $abilityScores = ['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'];

            foreach ($items as $item) {
                if (in_array($item, $abilityScores)) {
                    // This is a saving throw
                    $proficiencies[] = [
                        'type' => 'saving_throw',
                        'name' => $item,
                        'proficiency_type_id' => null, // Saving throws don't need FK
                    ];
                } else {
                    // This is a skill available for selection
                    $proficiencyType = $this->matchProficiencyType($item);
                    $proficiencies[] = [
                        'type' => 'skill',
                        'name' => $item,
                        'proficiency_type_id' => $proficiencyType?->id,
                    ];
                }
            }
        }

        return $proficiencies;
    }

    /**
     * Parse traits (features) from class XML.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseTraits(SimpleXMLElement $element): array
    {
        // TODO: Implement parseTraits logic
        return [];
    }

    /**
     * Parse features from autolevel elements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseFeatures(SimpleXMLElement $element): array
    {
        // TODO: Implement parseFeatures logic
        return [];
    }

    /**
     * Parse spell slots from autolevel elements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseSpellSlots(SimpleXMLElement $element): array
    {
        // TODO: Implement parseSpellSlots logic
        return [];
    }

    /**
     * Parse counters (Ki, Rage, etc.) from autolevel elements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCounters(SimpleXMLElement $element): array
    {
        // TODO: Implement parseCounters logic
        return [];
    }

    /**
     * Detect subclasses from features and counters.
     *
     * @param  array<int, array<string, mixed>>  $features
     * @param  array<int, array<string, mixed>>  $counters
     * @return array<int, array<string, mixed>>
     */
    private function detectSubclasses(array $features, array $counters): array
    {
        // TODO: Implement detectSubclasses logic
        return [];
    }
}
