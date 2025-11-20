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

        // Parse spellcasting ability
        if (isset($element->spellAbility)) {
            $data['spellcasting_ability'] = (string) $element->spellAbility;
        }

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

        // Parse traits (flavor text)
        $data['traits'] = $this->parseTraits($element);

        // Parse features from autolevel elements
        $data['features'] = $this->parseFeatures($element);

        // Parse spell progression from autolevel elements
        $data['spell_progression'] = $this->parseSpellSlots($element);

        // Parse counters from autolevel elements
        $data['counters'] = $this->parseCounters($element);

        // Detect and group subclasses
        $data['subclasses'] = $this->detectSubclasses($data['features'], $data['counters']);

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

        // Check if this class has skill choices
        $numSkills = isset($element->numSkills) ? (int) $element->numSkills : null;

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
                    // This is a saving throw - never a choice
                    $proficiencies[] = [
                        'type' => 'saving_throw',
                        'name' => $item,
                        'proficiency_type_id' => null, // Saving throws don't need FK
                        'is_choice' => false,
                    ];
                } else {
                    // This is a skill available for selection
                    $proficiencyType = $this->matchProficiencyType($item);
                    $skillProf = [
                        'type' => 'skill',
                        'name' => $item,
                        'proficiency_type_id' => $proficiencyType?->id,
                    ];

                    // If numSkills exists, mark skills as choices
                    if ($numSkills !== null) {
                        $skillProf['is_choice'] = true;
                        $skillProf['quantity'] = $numSkills;
                    } else {
                        $skillProf['is_choice'] = false;
                    }

                    $proficiencies[] = $skillProf;
                }
            }
        }

        return $proficiencies;
    }

    /**
     * Parse traits (flavor text) from class XML.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseTraits(SimpleXMLElement $element): array
    {
        $traits = [];
        $sortOrder = 0;

        foreach ($element->trait as $traitElement) {
            $category = isset($traitElement['category']) ? (string) $traitElement['category'] : null;
            $name = (string) $traitElement->name;
            $text = (string) $traitElement->text;

            // Parse rolls within this trait (if any)
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

            // Extract source citations
            $sources = $this->parseSourceCitations($text);

            $traits[] = [
                'name' => $name,
                'category' => $category,
                'description' => trim($text),
                'rolls' => $rolls,
                'sources' => $sources,
                'sort_order' => $sortOrder++,
            ];
        }

        return $traits;
    }

    /**
     * Parse features from autolevel elements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseFeatures(SimpleXMLElement $element): array
    {
        $features = [];
        $sortOrder = 0;

        // Iterate through all autolevel elements
        foreach ($element->autolevel as $autolevel) {
            $level = (int) $autolevel['level'];

            // Parse each feature within this autolevel
            foreach ($autolevel->feature as $featureElement) {
                $isOptional = isset($featureElement['optional']) && (string) $featureElement['optional'] === 'YES';
                $name = (string) $featureElement->name;
                $text = (string) $featureElement->text;

                // Extract source citation if present
                $sources = $this->parseSourceCitations($text);

                $features[] = [
                    'level' => $level,
                    'name' => $name,
                    'description' => trim($text),
                    'is_optional' => $isOptional,
                    'sources' => $sources,
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        return $features;
    }

    /**
     * Parse spell slots from autolevel elements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseSpellSlots(SimpleXMLElement $element): array
    {
        $spellProgression = [];

        // Iterate through all autolevel elements
        foreach ($element->autolevel as $autolevel) {
            $level = (int) $autolevel['level'];

            // Check if this autolevel has spell slots
            if (isset($autolevel->slots)) {
                $slotsString = (string) $autolevel->slots;
                $slots = array_map('intval', explode(',', $slotsString));

                // Format: cantrips, 1st, 2nd, 3rd, ..., 9th
                $progression = [
                    'level' => $level,
                    'cantrips_known' => $slots[0] ?? 0,
                    'spell_slots_1st' => $slots[1] ?? 0,
                    'spell_slots_2nd' => $slots[2] ?? 0,
                    'spell_slots_3rd' => $slots[3] ?? 0,
                    'spell_slots_4th' => $slots[4] ?? 0,
                    'spell_slots_5th' => $slots[5] ?? 0,
                    'spell_slots_6th' => $slots[6] ?? 0,
                    'spell_slots_7th' => $slots[7] ?? 0,
                    'spell_slots_8th' => $slots[8] ?? 0,
                    'spell_slots_9th' => $slots[9] ?? 0,
                ];

                $spellProgression[] = $progression;
            }

            // Check for "Spells Known" counter
            $spellsKnown = null;
            foreach ($autolevel->counter as $counterElement) {
                if ((string) $counterElement->name === 'Spells Known') {
                    $spellsKnown = (int) $counterElement->value;
                    break;
                }
            }

            // If we found spells_known, merge it into existing progression or create new entry
            if ($spellsKnown !== null) {
                // Find existing progression entry for this level
                $found = false;
                foreach ($spellProgression as &$prog) {
                    if ($prog['level'] === $level) {
                        $prog['spells_known'] = $spellsKnown;
                        $found = true;
                        break;
                    }
                }
                unset($prog);

                // If no existing entry, create one with just spells_known
                if (! $found) {
                    $spellProgression[] = [
                        'level' => $level,
                        'cantrips_known' => 0,
                        'spell_slots_1st' => 0,
                        'spell_slots_2nd' => 0,
                        'spell_slots_3rd' => 0,
                        'spell_slots_4th' => 0,
                        'spell_slots_5th' => 0,
                        'spell_slots_6th' => 0,
                        'spell_slots_7th' => 0,
                        'spell_slots_8th' => 0,
                        'spell_slots_9th' => 0,
                        'spells_known' => $spellsKnown,
                    ];
                }
            }
        }

        return $spellProgression;
    }

    /**
     * Parse counters (Ki, Rage, etc.) from autolevel elements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCounters(SimpleXMLElement $element): array
    {
        $counters = [];

        // Iterate through all autolevel elements
        foreach ($element->autolevel as $autolevel) {
            $level = (int) $autolevel['level'];

            // Parse each counter within this autolevel
            foreach ($autolevel->counter as $counterElement) {
                $name = (string) $counterElement->name;

                // Skip "Spells Known" counters - they're handled in spell_progression
                if ($name === 'Spells Known') {
                    continue;
                }

                $value = (int) $counterElement->value;

                // Parse reset timing
                $resetTiming = null;
                if (isset($counterElement->reset)) {
                    $reset = (string) $counterElement->reset;
                    $resetTiming = match ($reset) {
                        'S' => 'short_rest',
                        'L' => 'long_rest',
                        default => null,
                    };
                }

                // Parse subclass if present
                $subclass = null;
                if (isset($counterElement->subclass)) {
                    $subclass = (string) $counterElement->subclass;
                }

                $counters[] = [
                    'level' => $level,
                    'name' => $name,
                    'value' => $value,
                    'reset_timing' => $resetTiming,
                    'subclass' => $subclass,
                ];
            }
        }

        return $counters;
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
        $subclasses = [];
        $subclassNames = [];

        // Pattern 1: "Martial Archetype: Battle Master" or "Otherworldly Patron: The Fiend"
        // Pattern 2: "Combat Superiority (Battle Master)" - name with parentheses
        foreach ($features as $feature) {
            $name = $feature['name'];

            // Pattern 1: "Martial Archetype: Subclass Name" or "Primal Path: Subclass Name"
            if (preg_match('/^(?:Martial Archetype|Primal Path|Monastic Tradition|Otherworldly Patron|Divine Domain|Arcane Tradition|Sacred Oath|Ranger Archetype|Roguish Archetype|Sorcerous Origin|Bard College|Druid Circle|College of|Artificer Specialist):\s*(.+)$/i', $name, $matches)) {
                $subclassNames[] = trim($matches[1]);
            }

            // Pattern 2: "Feature Name (Subclass Name)"
            if (preg_match('/\(([^)]+)\)$/', $name, $matches)) {
                $possibleSubclass = trim($matches[1]);
                // Only consider it a subclass if it:
                // 1. Not a common qualifier like "Revised" or "Alternative"
                // 2. Not a number (like "Action Surge (2)")
                // 3. Not a lowercase phrase (like "two uses")
                // 4. Starts with a capital letter (subclass names are proper nouns)
                if (! in_array(strtolower($possibleSubclass), ['revised', 'alternative', 'optional', 'variant'])
                    && ! is_numeric($possibleSubclass)
                    && preg_match('/^[A-Z]/', $possibleSubclass)
                    && ! preg_match('/^\d+/', $possibleSubclass)) {
                    $subclassNames[] = $possibleSubclass;
                }
            }
        }

        // Pattern 3: Direct <subclass> tag in counters
        foreach ($counters as $counter) {
            if (! empty($counter['subclass'])) {
                $subclassNames[] = $counter['subclass'];
            }
        }

        // Remove duplicates and sort
        $subclassNames = array_unique($subclassNames);
        sort($subclassNames);

        // Group features and counters by subclass
        foreach ($subclassNames as $subclassName) {
            $subclassFeatures = [];
            $subclassCounters = [];

            // Find features belonging to this subclass
            foreach ($features as $feature) {
                $name = $feature['name'];

                // Check if feature name contains subclass name
                if (str_contains($name, $subclassName) || str_contains($name, "($subclassName)")) {
                    $subclassFeatures[] = $feature;
                }
            }

            // Find counters belonging to this subclass
            foreach ($counters as $counter) {
                if (! empty($counter['subclass']) && $counter['subclass'] === $subclassName) {
                    $subclassCounters[] = $counter;
                }
            }

            $subclasses[] = [
                'name' => $subclassName,
                'features' => $subclassFeatures,
                'counters' => $subclassCounters,
            ];
        }

        return $subclasses;
    }
}
