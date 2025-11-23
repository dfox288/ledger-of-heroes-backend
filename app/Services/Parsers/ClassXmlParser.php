<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesRolls;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\ParsesTraits;
use SimpleXMLElement;

class ClassXmlParser
{
    use MatchesProficiencyTypes, ParsesRolls, ParsesSourceCitations, ParsesTraits;

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

        // Parse spellcasting ability - but only if class has non-optional spell slots
        // Classes like Rogue have spellAbility defined but only for subclasses (Arcane Trickster)
        if (isset($element->spellAbility) && $this->hasNonOptionalSpellSlots($element)) {
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
        $data['traits'] = $this->parseTraitElements($element);

        // Extract source citations from each trait description
        foreach ($data['traits'] as &$trait) {
            $trait['sources'] = $this->parseSourceCitations($trait['description']);
        }
        unset($trait);

        // Parse features from autolevel elements
        $data['features'] = $this->parseFeatures($element);

        // Parse spell progression from autolevel elements
        $data['spell_progression'] = $this->parseSpellSlots($element);

        // Parse counters from autolevel elements
        $data['counters'] = $this->parseCounters($element);

        // Parse optional spell slots (for subclasses like Arcane Trickster, Eldritch Knight)
        $optionalSpellData = $this->parseOptionalSpellSlots($element);

        // Detect and group subclasses, and filter base class features
        $subclassData = $this->detectSubclasses(
            $data['features'],
            $data['counters'],
            $optionalSpellData
        );
        $data['subclasses'] = $subclassData['subclasses'];
        $data['features'] = $subclassData['filtered_base_features']; // Use filtered list

        // Parse starting equipment
        $data['equipment'] = $this->parseEquipment($element);

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
                    'rolls' => $this->parseRollElements($featureElement),
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
                // Check if slots are marked as optional (subclass-only)
                $isOptional = isset($autolevel->slots['optional'])
                    && (string) $autolevel->slots['optional'] === 'YES';

                // Skip optional slots for base class - they belong to subclasses
                // Example: Rogue has optional="YES" for Arcane Trickster only
                if ($isOptional) {
                    continue;
                }

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
            // NOTE: Only process these if there are non-optional spell slots
            // (Rogue has "Spells Known" counters for Arcane Trickster, but slots are optional)
            $spellsKnown = null;
            foreach ($autolevel->counter as $counterElement) {
                if ((string) $counterElement->name === 'Spells Known') {
                    $spellsKnown = (int) $counterElement->value;
                    break;
                }
            }

            // If we found spells_known, merge it into existing progression
            // BUT: Only if this level has non-optional spell slots
            if ($spellsKnown !== null && isset($autolevel->slots)) {
                $isOptional = isset($autolevel->slots['optional'])
                    && (string) $autolevel->slots['optional'] === 'YES';

                // Skip if slots are optional (subclass-only spellcasting)
                if ($isOptional) {
                    continue;
                }

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
     * Parse optional spell slots and match them to spellcasting subclasses.
     * Returns array keyed by subclass name containing spell progression data.
     *
     * @return array<string, array{spellcasting_ability: string, spell_progression: array}>
     */
    private function parseOptionalSpellSlots(SimpleXMLElement $element): array
    {
        $optionalSlots = [];
        $spellsKnownByLevel = [];
        $spellcastingAbility = null;

        // First pass: Collect all optional spell slots and "Spells Known" counters
        foreach ($element->autolevel as $autolevel) {
            $level = (int) $autolevel['level'];

            // Check if this autolevel has optional spell slots
            if (isset($autolevel->slots)) {
                $isOptional = isset($autolevel->slots['optional'])
                    && (string) $autolevel->slots['optional'] === 'YES';

                if ($isOptional) {
                    $slotsString = (string) $autolevel->slots;
                    $slots = array_map('intval', explode(',', $slotsString));

                    $optionalSlots[] = [
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
                }
            }

            // Collect ALL "Spells Known" counters (might be in separate autolevel blocks)
            foreach ($autolevel->counter as $counterElement) {
                if ((string) $counterElement->name === 'Spells Known') {
                    $spellsKnownByLevel[$level] = (int) $counterElement->value;
                    break;
                }
            }
        }

        // If no optional slots, return empty
        if (empty($optionalSlots)) {
            return [];
        }

        // Get spellcasting ability if defined (for optional spellcasters)
        if (isset($element->spellAbility)) {
            $spellcastingAbility = (string) $element->spellAbility;
        }

        // Second pass: Find "Spellcasting (SubclassName)" features to match slots to subclass
        $spellcastingSubclass = null;
        foreach ($element->autolevel as $autolevel) {
            foreach ($autolevel->feature as $featureElement) {
                $featureName = (string) $featureElement->name;

                // Pattern: "Spellcasting (Arcane Trickster)" or "Spellcasting (Eldritch Knight)"
                if (preg_match('/^Spellcasting\s*\((.+)\)$/', $featureName, $matches)) {
                    $spellcastingSubclass = trim($matches[1]);
                    break 2; // Break both loops
                }
            }
        }

        // If we found a spellcasting subclass, assign the optional slots to it
        if ($spellcastingSubclass !== null) {
            // Merge spells_known counters into progression
            foreach ($optionalSlots as &$progression) {
                if (isset($spellsKnownByLevel[$progression['level']])) {
                    $progression['spells_known'] = $spellsKnownByLevel[$progression['level']];
                }
            }
            unset($progression);

            return [
                $spellcastingSubclass => [
                    'spellcasting_ability' => $spellcastingAbility,
                    'spell_progression' => $optionalSlots,
                ],
            ];
        }

        // No spellcasting subclass found - slots remain unassigned
        return [];
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
     * Returns both the detected subclasses AND filtered base class features.
     * Base class features will have subclass-specific features removed.
     *
     * @param  array<int, array<string, mixed>>  $features
     * @param  array<int, array<string, mixed>>  $counters
     * @return array{subclasses: array, filtered_base_features: array}
     */
    private function detectSubclasses(array $features, array $counters, array $optionalSpellData = []): array
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

        // Track which feature indices belong to subclasses
        $subclassFeatureIndices = [];

        // Group features and counters by subclass
        foreach ($subclassNames as $subclassName) {
            $subclassFeatures = [];
            $subclassCounters = [];

            // Find features belonging to this subclass
            foreach ($features as $index => $feature) {
                $name = $feature['name'];

                // Check if feature name contains subclass name
                if ($this->featureBelongsToSubclass($name, $subclassName)) {
                    $subclassFeatures[] = $feature;
                    $subclassFeatureIndices[] = $index; // Track this index for removal from base
                }
            }

            // Find counters belonging to this subclass
            foreach ($counters as $counter) {
                if (! empty($counter['subclass']) && $counter['subclass'] === $subclassName) {
                    $subclassCounters[] = $counter;
                }
            }

            $subclass = [
                'name' => $subclassName,
                'features' => $subclassFeatures,
                'counters' => $subclassCounters,
            ];

            // Add spell progression if this subclass has optional spellcasting
            if (isset($optionalSpellData[$subclassName])) {
                $spellData = $optionalSpellData[$subclassName];
                $subclass['spell_progression'] = $spellData['spell_progression'];
                if ($spellData['spellcasting_ability']) {
                    $subclass['spellcasting_ability'] = $spellData['spellcasting_ability'];
                }
            }

            $subclasses[] = $subclass;
        }

        // Filter base class features - remove any that belong to subclasses
        $baseFeatures = [];
        foreach ($features as $index => $feature) {
            if (! in_array($index, $subclassFeatureIndices)) {
                $baseFeatures[] = $feature;
            }
        }

        return [
            'subclasses' => $subclasses,
            'filtered_base_features' => $baseFeatures,
        ];
    }

    /**
     * Check if a feature belongs to a specific subclass based on naming patterns.
     *
     * @param  string  $featureName  The feature name to check
     * @param  string  $subclassName  The subclass name to match against
     */
    private function featureBelongsToSubclass(string $featureName, string $subclassName): bool
    {
        // Pattern 1: "Archetype: Subclass Name" (intro feature)
        if (preg_match('/^(?:Martial Archetype|Primal Path|Monastic Tradition|Otherworldly Patron|Divine Domain|Arcane Tradition|Sacred Oath|Ranger Archetype|Roguish Archetype|Sorcerous Origin|Bard College|Druid Circle|College of|Artificer Specialist):\s*'.preg_quote($subclassName, '/').'$/i', $featureName)) {
            return true;
        }

        // Pattern 2: "Feature Name (Subclass Name)" (subsequent features)
        if (preg_match('/\('.preg_quote($subclassName, '/').'\)$/i', $featureName)) {
            return true;
        }

        // Pattern 3: Feature name contains subclass name without parentheses
        // (less common, but some XML files use this)
        if (str_contains($featureName, $subclassName)) {
            return true;
        }

        return false;
    }

    /**
     * Parse starting equipment from class XML.
     *
     * Extracts:
     * - Wealth formula (<wealth> tag)
     * - Starting equipment from "Starting [Class]" feature text
     *
     * @return array{wealth: string|null, items: array}
     */
    private function parseEquipment(SimpleXMLElement $element): array
    {
        $equipment = [
            'wealth' => null,
            'items' => [],
        ];

        // Parse wealth formula (e.g., "2d4x10")
        if (isset($element->wealth)) {
            $equipment['wealth'] = (string) $element->wealth;
        }

        // Parse starting equipment from level 1 "Starting [Class]" feature
        foreach ($element->autolevel as $autolevel) {
            if ((int) $autolevel['level'] !== 1) {
                continue;
            }

            foreach ($autolevel->feature as $feature) {
                $featureName = (string) $feature->name;

                // Match "Starting Barbarian", "Starting Fighter", etc.
                if (preg_match('/^Starting\s+\w+$/i', $featureName)) {
                    $text = (string) $feature->text;
                    // Fix UTF-8 encoding issues from SimpleXML (bullet points often get corrupted)
                    // Remove non-ASCII characters to avoid database encoding errors
                    $text = preg_replace('/[^\x20-\x7E\n\r\t]/', '-', $text);
                    $equipment['items'] = $this->parseEquipmentChoices($text);
                    break 2; // Found it, exit both loops
                }
            }
        }

        return $equipment;
    }

    /**
     * Parse equipment choice text into structured items.
     *
     * Handles patterns like:
     * - "(a) a greataxe or (b) any martial melee weapon"
     * - "An explorer's pack, and four javelins"
     *
     * @return array<int, array{description: string, is_choice: bool, quantity: int}>
     */
    private function parseEquipmentChoices(string $text): array
    {
        $items = [];

        // Extract bullet points (• or - prefix)
        preg_match_all('/[•\-]\s*(.+?)(?=\n[•\-]|\n\n|$)/s', $text, $bullets);

        foreach ($bullets[1] as $bulletText) {
            $bulletText = trim($bulletText);

            // Check if this is a choice: "(a) X or (b) Y"
            if (preg_match_all('/\(([a-z])\)\s*([^()]+?)(?=\s+or\s+\(|\s*$)/i', $bulletText, $choices)) {
                // Multiple choice options
                foreach ($choices[2] as $choiceText) {
                    $items[] = [
                        'description' => trim($choiceText),
                        'is_choice' => true,
                        'quantity' => 1,
                    ];
                }
            } else {
                // Simple item (no choice) - may have multiple items separated by "and" or ","
                // Split by "and" or ","
                $parts = preg_split('/\s+and\s+|,\s+and\s+/i', $bulletText);

                foreach ($parts as $part) {
                    $part = trim($part);
                    if (empty($part)) {
                        continue;
                    }

                    // Extract quantity if present: "four javelins" → quantity=4
                    $quantity = 1;
                    if (preg_match('/^(two|three|four|five|six|seven|eight|nine|ten)\s+/i', $part, $qtyMatch)) {
                        $quantity = $this->convertWordToNumber(strtolower($qtyMatch[1]));
                        $part = preg_replace('/^(two|three|four|five|six|seven|eight|nine|ten)\s+/i', '', $part);
                    }

                    $items[] = [
                        'description' => trim($part),
                        'is_choice' => false,
                        'quantity' => $quantity,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Convert word numbers to integers.
     *
     * @param  string  $word  Number word (e.g., "two")
     */
    private function convertWordToNumber(string $word): int
    {
        return match (strtolower($word)) {
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10,
            default => 1,
        };
    }

    /**
     * Check if class has non-optional spell slots (i.e., base class spellcasting).
     *
     * Returns false if all spell slots are marked optional="YES" (subclass-only).
     * Example: Rogue has only optional slots (Arcane Trickster), Wizard has non-optional.
     */
    private function hasNonOptionalSpellSlots(SimpleXMLElement $element): bool
    {
        foreach ($element->autolevel as $autolevel) {
            if (isset($autolevel->slots)) {
                $isOptional = isset($autolevel->slots['optional'])
                    && (string) $autolevel->slots['optional'] === 'YES';

                if (! $isOptional) {
                    return true; // Found at least one non-optional slot progression
                }
            }
        }

        return false; // No spell slots, or all are optional (subclass-only)
    }
}
