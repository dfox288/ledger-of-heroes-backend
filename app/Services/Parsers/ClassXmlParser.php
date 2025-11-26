<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\ConvertsWordNumbers;
use App\Services\Parsers\Concerns\MapsAbilityCodes;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesModifiers;
use App\Services\Parsers\Concerns\ParsesRolls;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\ParsesTraits;
use SimpleXMLElement;

class ClassXmlParser
{
    use ConvertsWordNumbers, MapsAbilityCodes, MatchesProficiencyTypes, ParsesModifiers, ParsesRolls, ParsesSourceCitations, ParsesTraits;

    /**
     * Parse classes from XML string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $element = XmlLoader::fromString($xml);
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
        $choiceCounter = 1; // Track choice groups

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

            // Collect skills first to apply choice grouping
            $skills = [];
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
                    $skills[] = [
                        'type' => 'skill',
                        'name' => $item,
                        'proficiency_type_id' => $proficiencyType?->id,
                    ];
                }
            }

            // Apply choice grouping to skills if numSkills exists
            if (! empty($skills)) {
                if ($numSkills !== null) {
                    // All skills are part of one choice group
                    $choiceGroup = "skill_choice_{$choiceCounter}";
                    $choiceCounter++;

                    foreach ($skills as $index => $skill) {
                        $skill['is_choice'] = true;
                        $skill['choice_group'] = $choiceGroup;
                        $skill['choice_option'] = $index + 1;
                        // Only the first skill in the group gets the quantity
                        // This tells frontend: "pick X from this group"
                        $skill['quantity'] = ($index === 0) ? $numSkills : null;
                        $proficiencies[] = $skill;
                    }
                } else {
                    // No choices, add skills without grouping
                    foreach ($skills as $skill) {
                        $skill['is_choice'] = false;
                        $proficiencies[] = $skill;
                    }
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
            $grantsAsi = isset($autolevel['scoreImprovement']) && strtoupper((string) $autolevel['scoreImprovement']) === 'YES';

            // Parse each feature within this autolevel
            foreach ($autolevel->feature as $featureElement) {
                $isOptional = isset($featureElement['optional']) && (string) $featureElement['optional'] === 'YES';
                $name = (string) $featureElement->name;
                $text = (string) $featureElement->text;

                // Extract source citation if present
                $sources = $this->parseSourceCitations($text);

                // Parse special tags
                $specialTags = [];
                foreach ($featureElement->special as $specialElement) {
                    $tag = trim((string) $specialElement);
                    if (! empty($tag)) {
                        $specialTags[] = $tag;
                    }
                }

                // Parse modifiers
                $modifiers = [];
                foreach ($featureElement->modifier as $modifierElement) {
                    $category = (string) $modifierElement['category'] ?? 'bonus';
                    $text = trim((string) $modifierElement);

                    $parsed = $this->parseModifierText($text, $category);

                    if ($parsed !== null) {
                        $modifiers[] = $parsed;
                    }
                }

                $features[] = [
                    'level' => $level,
                    'name' => $name,
                    'description' => trim($text),
                    'is_optional' => $isOptional,
                    'sources' => $sources,
                    'sort_order' => $sortOrder++,
                    'rolls' => $this->parseRollElements($featureElement),
                    'special_tags' => $specialTags,
                    'modifiers' => $modifiers,
                    'grants_asi' => $grantsAsi,
                ];
            }
        }

        return $features;
    }

    // parseModifierText() and determineBonusCategory() provided by ParsesModifiers trait

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

                // Define false positive patterns that should NOT be treated as subclasses
                $falsePositivePatterns = [
                    '/^CR\s+\d+/',                   // CR 1, CR 2, CR 3, CR 4
                    '/^CR\s+\d+\/\d+/',              // CR 1/2, CR 3/4
                    '/^\d+\s*\/\s*(rest|day)/i',    // 2/rest, 3/day
                    '/^\d+(st|nd|rd|th)\b/i',        // 2nd, 3rd, 4th
                    '/\buses?\b/i',                  // one use, two uses
                    '/^\d+\s+slots?/i',              // 2 slots
                    '/^level\s+\d+/i',               // level 5
                    '/^\d+\s+times?/i',              // 2 times
                ];

                // Check if this matches any false positive pattern
                $isFalsePositive = false;
                foreach ($falsePositivePatterns as $pattern) {
                    if (preg_match($pattern, $possibleSubclass)) {
                        $isFalsePositive = true;
                        break;
                    }
                }

                // Only consider it a subclass if it:
                // 1. Not a false positive pattern (CR, uses, etc.)
                // 2. Not a common qualifier like "Revised" or "Alternative"
                // 3. Not a number (like "Action Surge (2)")
                // 4. Not a lowercase phrase (like "two uses")
                // 5. Starts with a capital letter (subclass names are proper nouns)
                if (! $isFalsePositive
                    && ! in_array(strtolower($possibleSubclass), ['revised', 'alternative', 'optional', 'variant'])
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

                    // Extract only the equipment section (after "You begin play with")
                    // and before "If you forgo" to avoid proficiency/hit point text
                    if (preg_match('/You begin play with the following equipment[^•\-]+(.*?)(?=\n\nIf you forgo|$)/s', $text, $match)) {
                        $equipmentText = $match[1];
                        $equipment['items'] = $this->parseEquipmentChoices($equipmentText);
                    } else {
                        // Fallback: use entire text if pattern not found
                        $equipment['items'] = $this->parseEquipmentChoices($text);
                    }
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
     * - "(a) X, (b) Y, or (c) Z" (three-way choice)
     * - "An explorer's pack, and four javelins"
     *
     * @return array<int, array{description: string, is_choice: bool, choice_group: string|null, choice_option: int|null, quantity: int}>
     */
    private function parseEquipmentChoices(string $text): array
    {
        $items = [];
        $choiceGroupNumber = 1;

        // Extract bullet points (• or - prefix, with optional leading whitespace)
        // Use 'u' flag for UTF-8 support (bullet • is 3-byte UTF-8 character)
        preg_match_all('/[•\-]\s*(.+?)(?=\n\s*[•\-]|\n\n|$)/su', $text, $bullets);

        foreach ($bullets[1] as $bulletText) {
            $bulletText = trim($bulletText);

            // Check if this is a choice: "(a) X or (b) Y" or "(a) X, (b) Y, or (c) Z"
            // Pattern matches (a) followed by text (allowing nested parentheses) until next choice or end
            if (preg_match('/\([a-z]\)/i', $bulletText)) {
                // Has choice markers, extract all options
                // Lookahead matches: " or (x)", ", (x)", or end of string
                if (preg_match_all('/\(([a-z])\)\s*(.+?)(?=\s+(?:,\s*)?or\s+\([a-z]\)|\s*,\s*\([a-z]\)|$)/i', $bulletText, $choices)) {
                    $optionNumber = 1;
                    foreach ($choices[2] as $choiceText) {
                        // Clean up trailing ", or" and whitespace
                        $choiceText = preg_replace('/\s*,?\s*or\s*$/i', '', $choiceText);
                        $choiceText = preg_replace('/\s*,\s*$/i', '', $choiceText);
                        $choiceText = trim($choiceText);

                        if (empty($choiceText)) {
                            continue;
                        }

                        // Extract quantity if present
                        $quantity = 1;
                        if (preg_match('/^(two|three|four|five|six|seven|eight|nine|ten|twenty)\s+/i', $choiceText, $qtyMatch)) {
                            $quantity = $this->wordToNumber($qtyMatch[1]);
                            $choiceText = preg_replace('/^(two|three|four|five|six|seven|eight|nine|ten|twenty)\s+/i', '', $choiceText);
                        }

                        $items[] = [
                            'description' => trim($choiceText),
                            'is_choice' => true,
                            'choice_group' => "choice_{$choiceGroupNumber}",
                            'choice_option' => $optionNumber++,
                            'quantity' => $quantity,
                        ];
                    }
                    $choiceGroupNumber++;
                }
            } else {
                // Simple item (no choice) - may have multiple items separated by comma or "and"
                // Handle cases like: "two dagger and four javelins" or "Leather armor, dagger, and rope"

                // Split by ", and " (Oxford comma) or ", " or " and "
                $parts = preg_split('/,\s+(?:and\s+)?|\s+and\s+/i', $bulletText);

                foreach ($parts as $part) {
                    $part = trim($part);
                    if (empty($part)) {
                        continue;
                    }

                    // Extract quantity if present: "four javelins" → quantity=4, "javelins"
                    // Handle: two, three, four, five, six, seven, eight, nine, ten, twenty
                    $quantity = 1;
                    if (preg_match('/^(two|three|four|five|six|seven|eight|nine|ten|twenty)\s+(.+)/i', $part, $qtyMatch)) {
                        $quantity = $this->wordToNumber($qtyMatch[1]);
                        $part = $qtyMatch[2]; // Item name after quantity
                    }

                    // Skip if part is empty
                    $part = trim($part);
                    if (empty($part)) {
                        continue;
                    }

                    $items[] = [
                        'description' => $part,
                        'is_choice' => false,
                        'choice_group' => null,
                        'choice_option' => null,
                        'quantity' => $quantity,
                    ];
                }
            }
        }

        return $items;
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
