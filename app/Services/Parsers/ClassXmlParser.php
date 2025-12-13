<?php

namespace App\Services\Parsers;

use App\Enums\ToolProficiencyCategory;
use App\Services\Parsers\Concerns\ConvertsWordNumbers;
use App\Services\Parsers\Concerns\LoadsLookupData;
use App\Services\Parsers\Concerns\MapsAbilityCodes;
use App\Services\Parsers\Concerns\MatchesLanguages;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesClassCounters;
use App\Services\Parsers\Concerns\ParsesClassEquipment;
use App\Services\Parsers\Concerns\ParsesFeatureChoiceProgressions;
use App\Services\Parsers\Concerns\ParsesModifiers;
use App\Services\Parsers\Concerns\ParsesRestTiming;
use App\Services\Parsers\Concerns\ParsesRolls;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\ParsesSpellProgression;
use App\Services\Parsers\Concerns\ParsesSubclassDetection;
use App\Services\Parsers\Concerns\ParsesTraits;
use SimpleXMLElement;

class ClassXmlParser
{
    use ConvertsWordNumbers, LoadsLookupData, MapsAbilityCodes, MatchesLanguages, MatchesProficiencyTypes, ParsesClassCounters, ParsesClassEquipment, ParsesFeatureChoiceProgressions, ParsesModifiers, ParsesRestTiming, ParsesRolls, ParsesSourceCitations, ParsesSpellProgression, ParsesSubclassDetection, ParsesTraits;

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

        // Parse multiclass requirements from the "Multiclass {Class}" feature
        // Must be done before detectSubclasses() filters the features
        $data['multiclass_requirements'] = $this->parseMulticlassRequirements($data['features'], $data['name']);

        // Parse spell progression from autolevel elements
        $data['spell_progression'] = $this->parseSpellSlots($element);

        // Parse counters from autolevel elements
        $data['counters'] = $this->parseCounters($element);

        // Parse feature choice progressions from feature descriptions
        // (Maneuvers, Metamagic, Infusions, etc. not covered by XML counters)
        $featureChoiceCounters = $this->parseFeatureChoiceProgressions($data['features']);
        $data['counters'] = array_merge($data['counters'], $featureChoiceCounters);

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
        $data['archetype'] = $subclassData['archetype']; // e.g., "Martial Archetype" for Fighter

        // Parse starting equipment
        $data['equipment'] = $this->parseEquipment($element);

        // Parse language grants from features (e.g., Thieves' Cant, Druidic)
        $data['languages'] = $this->parseLanguageGrants($data['features']);

        return $data;
    }

    /**
     * Parse language grants from class features.
     *
     * Detects features that grant languages by matching feature names against
     * the languages table. Currently grants: Thieves' Cant (Rogue), Druidic (Druid).
     *
     * Uses the MatchesLanguages trait to dynamically match against database records,
     * so any new languages added to the database will automatically be detected.
     *
     * @param  array<int, array<string, mixed>>  $features
     * @return array<int, array{slug: string, is_choice: bool}>
     */
    private function parseLanguageGrants(array $features): array
    {
        $languages = [];

        foreach ($features as $feature) {
            $featureName = $feature['name'] ?? '';

            // Try to match feature name against known languages in database
            $language = $this->matchLanguage($featureName);

            if ($language !== null) {
                $languages[] = [
                    'slug' => $language->slug,
                    'is_choice' => false,
                ];
            }
        }

        return $languages;
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

        // Parse tool proficiencies (including choice-based tools like "Artisan's Tools of your choice")
        if (isset($element->tools)) {
            $tools = array_map('trim', explode(',', (string) $element->tools));
            foreach ($tools as $tool) {
                if (strtolower($tool) === 'none') {
                    continue;
                }

                // Check if this is an artisan tool choice
                if ($this->isArtisanToolChoice($tool)) {
                    $quantity = $this->extractToolChoiceQuantity($tool);

                    // Store as a single choice proficiency with subcategory reference
                    // Frontend looks up options from proficiency_types where subcategory='artisan'
                    $proficiencies[] = [
                        'type' => 'tool',
                        'name' => $tool, // Keep original text for display
                        'proficiency_type_id' => null, // No specific type - it's a choice
                        'proficiency_subcategory' => ToolProficiencyCategory::ARTISAN->value,
                        'is_choice' => true,
                        'choice_group' => "tool_choice_{$choiceCounter}",
                        'quantity' => $quantity,
                    ];
                    $choiceCounter++;
                } elseif ($this->isMusicalInstrumentChoice($tool)) {
                    // Musical instrument choice (e.g., "Three musical instruments of your choice")
                    $quantity = $this->extractToolChoiceQuantity($tool);

                    $proficiencies[] = [
                        'type' => 'tool',
                        'name' => $tool,
                        'proficiency_type_id' => null,
                        'proficiency_subcategory' => ToolProficiencyCategory::MUSICAL_INSTRUMENT->value,
                        'is_choice' => true,
                        'choice_group' => "tool_choice_{$choiceCounter}",
                        'quantity' => $quantity,
                    ];
                    $choiceCounter++;
                } else {
                    // Standard tool proficiency (not a choice)
                    $proficiencyType = $this->matchProficiencyType($tool);
                    $proficiencies[] = [
                        'type' => 'tool',
                        'name' => $tool,
                        'proficiency_type_id' => $proficiencyType?->id,
                        'is_choice' => false,
                    ];
                }
            }
        }

        // Parse saving throws and skills from <proficiency> element
        // Format: "Strength, Constitution, Acrobatics, Animal Handling, ..."
        // First two are saving throws, rest are available skills
        if (isset($element->proficiency)) {
            $items = array_map('trim', explode(',', (string) $element->proficiency));

            // Classes typically list saving throws first (2), then skills
            // We'll need to detect which are abilities vs skills (using lookup table)
            $abilityScores = $this->getAbilityScoreNames();

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
                    'resets_on' => $this->parseResetTiming($text),
                ];
            }
        }

        return $features;
    }

    // parseModifierText() and determineBonusCategory() provided by ParsesModifiers trait
    // parseSpellSlots(), parseOptionalSpellSlots(), hasNonOptionalSpellSlots() provided by ParsesSpellProgression trait
    // parseCounters(), addSpecialCaseCounters() provided by ParsesClassCounters trait
    // detectSubclasses(), featureBelongsToSubclass() provided by ParsesSubclassDetection trait
    // parseEquipment(), parseEquipmentChoices(), parseCompoundItem(), parseSingleItem() provided by ParsesClassEquipment trait

    /**
     * Parse multiclass ability score requirements from the "Multiclass {Class}" feature.
     *
     * The requirements are embedded in the feature description text with patterns like:
     * - Single: "• Charisma 13"
     * - AND: "• Dexterity 13\n• Wisdom 13" (no "or")
     * - OR: "• Strength 13, or\n• Dexterity 13"
     *
     * @param  array  $features  Parsed features array
     * @param  string  $className  The class name to find "Multiclass {Class}" feature
     * @return array<int, array{ability: string, minimum: int, is_alternative: bool}>
     */
    private function parseMulticlassRequirements(array $features, string $className): array
    {
        $requirements = [];

        // Find the "Multiclass {Class}" feature
        $multiclassFeatureName = "Multiclass {$className}";
        $multiclassFeature = null;

        foreach ($features as $feature) {
            if ($feature['name'] === $multiclassFeatureName) {
                $multiclassFeature = $feature;
                break;
            }
        }

        if ($multiclassFeature === null) {
            return [];
        }

        $description = $multiclassFeature['description'];

        // Extract the ability score requirements section
        // Look for text between "Ability Score Minimum:" and "Proficiencies Gained:"
        if (! preg_match('/Ability Score Minimum:(.+?)(?:Proficiencies Gained:|$)/s', $description, $sectionMatch)) {
            return [];
        }

        $requirementsText = $sectionMatch[1];

        // Check if this is an OR condition
        // - "at least 1 of" in the preamble text means OR
        // - ", or" after an ability score bullet (e.g., "• Strength 13, or") means OR
        // Be careful: ", or to take a level" is NOT an OR condition for abilities
        $isOrCondition = preg_match('/at least 1 of/i', $requirementsText)
            || preg_match('/•\s*(?:Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma)\s+\d+\s*,\s*or\b/i', $requirementsText);

        // Extract ability requirements: "• Ability 13" or "• Ability 13, or"
        // Abilities: Strength, Dexterity, Constitution, Intelligence, Wisdom, Charisma
        $abilityPattern = '/•\s*(Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma)\s+(\d+)/i';

        if (preg_match_all($abilityPattern, $requirementsText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $requirements[] = [
                    'ability' => strtolower($match[1]),
                    'minimum' => (int) $match[2],
                    'is_alternative' => $isOrCondition,
                ];
            }
        }

        return $requirements;
    }
}
