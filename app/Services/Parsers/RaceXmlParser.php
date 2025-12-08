<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\ConvertsWordNumbers;
use App\Services\Parsers\Concerns\MapsAbilityCodes;
use App\Services\Parsers\Concerns\MatchesLanguages;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesModifiers;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\ParsesTraits;
use SimpleXMLElement;

class RaceXmlParser
{
    use ConvertsWordNumbers, MapsAbilityCodes, MatchesLanguages, MatchesProficiencyTypes, ParsesModifiers, ParsesSourceCitations, ParsesTraits;

    public function parse(string $xmlContent): array
    {
        $xml = XmlLoader::fromString($xmlContent);
        $races = [];

        foreach ($xml->race as $raceElement) {
            // Check if this is a variant bundle that needs expansion
            if ($this->isVariantBundle($raceElement)) {
                $expandedRaces = $this->expandVariantBundle($raceElement);
                foreach ($expandedRaces as $expandedRace) {
                    $races[] = $expandedRace;
                }
            } else {
                $races[] = $this->parseRace($raceElement);
            }
        }

        return $races;
    }

    private function parseRace(SimpleXMLElement $element): array
    {
        // Parse race name and extract base race / subrace
        $fullName = (string) $element->name;
        $baseRaceName = null;
        $raceName = $fullName;

        // Check for comma format first (handles "Dwarf, Mark of Warding (WGtE)")
        if (str_contains($fullName, ',')) {
            [$baseRaceName, $raceName] = array_map('trim', explode(',', $fullName, 2));
        }
        // Then check if name contains parentheses (indicates subrace: "Dwarf (Hill)")
        elseif (preg_match('/^(.+?)\s*\((.+)\)$/', $fullName, $matches)) {
            $baseRaceName = trim($matches[1]);
            $raceName = $fullName; // Keep full name as-is
        }

        // Parse traits and categorize them
        $allTraits = $this->parseTraitElements($element);

        // Separate traits into base race traits and subrace-specific traits
        // Base race traits: species category + description + general (no category)
        // Subrace traits: subspecies category only
        $baseTraits = [];
        $subraceTraits = [];

        foreach ($allTraits as $trait) {
            $category = $trait['category'] ?? null;

            if ($category === 'subspecies') {
                $subraceTraits[] = $trait;
            } else {
                // species, description, or no category = base race trait
                $baseTraits[] = $trait;
            }
        }

        // For backward compatibility, keep 'traits' as all traits
        // But also provide separated arrays for the importer
        $traits = $allTraits;

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

        // Parse modifiers from XML elements
        $modifiers = $this->parseModifiers($element);

        // Parse bonus feats from trait text and merge with modifiers
        $bonusFeatModifiers = $this->parseBonusFeatFromTraits($traits);
        $modifiers = array_merge($modifiers, $bonusFeatModifiers);

        return [
            'name' => $raceName,
            'base_race_name' => $baseRaceName,
            'size_code' => (string) $element->size,
            'speed' => (int) $element->speed,
            'traits' => $traits,
            'base_traits' => $baseTraits,           // species, description, general traits
            'subrace_traits' => $subraceTraits,     // subspecies traits only
            'ability_bonuses' => $abilityBonuses,
            'sources' => $sources,
            'proficiencies' => $proficiencies,
            'languages' => $languages,
            'conditions' => $conditions,
            'ability_choices' => $abilityChoices,
            'spellcasting' => $spellcasting,
            'resistances' => $resistances,
            'modifiers' => $modifiers,
        ];
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
            $type = $this->inferProficiencyTypeFromName($profName);

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
            // Also matches: "two other ability scores of your choice increase by 1"
            if (preg_match('/(\w+)\s+(?:different|other)\s+ability scores?\s+of your choice\s+increases?\s+by\s+(\d+)/i', $text, $m)) {
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

            // Pattern: "You know one cantrip of your choice from the CLASS spell list"
            // Handles: High Elf ("wizard"), Aereni Elf ("cleric or wizard"), etc.
            if (preg_match('/You know (?:one|a) (?:\w+ )?cantrip of your choice from the ([\w\s]+?) spell list/i', $text, $matches)) {
                $classString = strtolower(trim($matches[1]));
                // Parse class options (handles "cleric or wizard", "wizard", etc.)
                $classNames = preg_split('/\s+or\s+/', $classString);

                foreach ($classNames as $className) {
                    $spellData['spells'][] = [
                        'is_choice' => true,
                        'choice_count' => 1,
                        'class_name' => trim($className),
                        'max_level' => 0, // cantrip = level 0
                        'is_cantrip' => true,
                        'is_ritual_only' => false,
                    ];
                }
            }

            // Pattern: "You know the SPELL cantrip" (fixed spell, not a choice)
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

            // Pattern: "You can cast the SPELL spell once" (no level requirement)
            // This handles races like Eladrin (DMG) with innate spellcasting
            if (preg_match_all('/You can cast the ([\w\s\']+) spell once/i', $text, $matches)) {
                foreach ($matches[1] as $spellName) {
                    // Determine rest type from surrounding text
                    $usageLimit = '1/long rest';
                    if (preg_match('/short or long rest|short rest/i', $text)) {
                        $usageLimit = '1/short rest';
                    }

                    $spellData['spells'][] = [
                        'spell_name' => trim($spellName),
                        'is_cantrip' => false,
                        'level_requirement' => null,
                        'usage_limit' => $usageLimit,
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

            // Pattern: "You gain proficiency in one skill of your choice" (simpler variant)
            // This pattern is used by Human Variant and potentially other races
            if (preg_match('/You gain proficienc(?:y|ies) in (\w+)\s+skill(?:s)?\s+of your choice/i', $text, $m)) {
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

            // Pattern: "You gain proficiency in one tool of your choice" (simpler variant)
            if (preg_match('/You gain proficienc(?:y|ies) in (\w+)\s+tool(?:s)?\s+of your choice/i', $text, $m)) {
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

    /**
     * Parse bonus feat grants from trait text.
     *
     * Detects traits named "Feat" that grant a bonus feat.
     * Pattern examples:
     * - Variant Human: "You gain one feat of your choice."
     * - Custom Lineage: "You gain one feat of your choice for which you qualify."
     *
     * @param  array  $traits  Parsed traits array
     * @return array<int, array<string, mixed>> Array of modifier data
     */
    private function parseBonusFeatFromTraits(array $traits): array
    {
        $modifiers = [];

        foreach ($traits as $trait) {
            // Only check traits named "Feat"
            if (strtolower($trait['name']) !== 'feat') {
                continue;
            }

            $text = strtolower($trait['description']);

            // Pattern: "you gain one feat of your choice" (with optional qualifiers)
            // Matches:
            // - "You gain one feat of your choice."
            // - "You gain one feat of your choice for which you qualify."
            if (preg_match('/you gain (?:one|a) feat of your choice/i', $text)) {
                $modifiers[] = [
                    'modifier_category' => 'bonus_feat',
                    'value' => 1,
                ];
            }
        }

        return $modifiers;
    }

    /**
     * Parse modifiers from trait <modifier> elements.
     * Example: <modifier category="bonus">HP +1</modifier>
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseModifiers(SimpleXMLElement $element): array
    {
        $modifiers = [];

        // Iterate through all traits to find modifier elements
        foreach ($element->trait as $traitElement) {
            foreach ($traitElement->modifier as $modifierElement) {
                $category = (string) $modifierElement['category'] ?? 'bonus';
                $text = trim((string) $modifierElement);

                $parsed = $this->parseModifierText($text, $category);

                if ($parsed !== null) {
                    $modifiers[] = $parsed;
                }
            }
        }

        return $modifiers;
    }

    /**
     * Check if a race element is a "variant bundle" that should be expanded.
     *
     * Variant bundles are races like "Tiefling, Variants" that contain multiple
     * mutually exclusive subspecies options in a single <race> element.
     *
     * Detection criteria:
     * - Name ends with ", Variants"
     * - Has 2+ subspecies traits with "Variant:" prefix (excluding Appearance)
     */
    private function isVariantBundle(SimpleXMLElement $element): bool
    {
        $name = (string) $element->name;

        // Must be named "Something, Variants"
        if (! str_ends_with($name, ', Variants')) {
            return false;
        }

        // Count subspecies traits with "Variant:" prefix (excluding Appearance)
        $variantTraitCount = 0;
        foreach ($element->trait as $trait) {
            $category = (string) $trait['category'];
            $traitName = (string) $trait->name;

            if ($category === 'subspecies'
                && str_starts_with($traitName, 'Variant:')
                && ! str_contains($traitName, 'Appearance')) {
                $variantTraitCount++;
            }
        }

        // Need at least 2 mutually exclusive variant traits
        return $variantTraitCount >= 2;
    }

    /**
     * Expand a variant bundle into multiple separate race arrays.
     *
     * For "Tiefling, Variants", this creates:
     * - Feral (keeps Infernal Legacy)
     * - Devil's Tongue (variant trait replaces Infernal Legacy)
     * - Hellfire (variant trait replaces Infernal Legacy)
     * - Winged (variant trait replaces Infernal Legacy)
     *
     * All subraces share: Darkvision, Hellish Resistance, Appearance, and common traits.
     *
     * @return array<int, array> Array of race data arrays
     */
    private function expandVariantBundle(SimpleXMLElement $element): array
    {
        $fullName = (string) $element->name;

        // Extract base race name: "Tiefling, Variants" -> "Tiefling"
        [$baseRaceName] = array_map('trim', explode(',', $fullName, 2));

        // Parse all traits
        $allTraits = $this->parseTraitElements($element);

        // First pass: collect variant traits and build replacement pattern
        $variantTraits = [];
        $appearanceTrait = null;
        $replacedTraitNames = [];

        foreach ($allTraits as $trait) {
            $category = $trait['category'] ?? null;
            $traitName = $trait['name'];

            if ($category === 'subspecies') {
                if (str_contains($traitName, 'Appearance')) {
                    $appearanceTrait = $trait;
                } elseif (str_starts_with($traitName, 'Variant:')) {
                    $variantTraits[] = $trait;
                    // Detect which trait this variant replaces by checking description
                    // Pattern: "replaces the X trait" or "This replaces the X trait"
                    if (preg_match('/replaces\s+the\s+([A-Za-z\s]+?)\s+trait/i', $trait['description'], $matches)) {
                        $replacedTraitNames[] = trim($matches[1]);
                    }
                }
            }
        }

        // Get unique replaced trait name (typically one trait like "Infernal Legacy")
        $replacedTraitNames = array_unique($replacedTraitNames);

        // Second pass: categorize traits into shared vs replaceable
        $sharedTraits = [];
        $replaceableTrait = null;

        foreach ($allTraits as $trait) {
            $category = $trait['category'] ?? null;
            $traitName = $trait['name'];

            // Skip subspecies traits (handled separately)
            if ($category === 'subspecies') {
                continue;
            }

            // Check if this is the trait that variants replace
            if ($category === 'species' && in_array($traitName, $replacedTraitNames)) {
                $replaceableTrait = $trait;
            } else {
                $sharedTraits[] = $trait;
            }
        }

        // Parse common data that all variants share
        $commonData = [
            'base_race_name' => $baseRaceName,
            'size_code' => (string) $element->size,
            'speed' => (int) $element->speed,
            'ability_bonuses' => $this->parseAbilityBonuses($element),
            'proficiencies' => $this->parseProficiencies($element),
            'resistances' => $this->parseResistances($element),
            'modifiers' => $this->parseModifiers($element),
        ];

        // Extract sources from description trait
        $sources = [];
        foreach ($sharedTraits as &$trait) {
            if (str_contains($trait['description'], 'Source:')) {
                $sources = $this->parseSourceCitations($trait['description']);
                $trait['description'] = trim(preg_replace('/\n*Source:\s*[^\n]+/', '', $trait['description']));
                break;
            }
        }
        unset($trait); // Clear reference to avoid accidental mutation
        if (empty($sources)) {
            $sources = [['code' => 'PHB', 'pages' => '']];
        }
        $commonData['sources'] = $sources;

        // Parse languages
        $commonData['languages'] = $this->parseLanguages($element);

        // Parse conditions
        $commonData['conditions'] = $this->parseConditionsAndImmunities($allTraits);

        // Parse ability choices
        $commonData['ability_choices'] = $this->parseAbilityChoices($allTraits);

        // Parse spellcasting (will be overridden per variant as needed)
        $commonSpellcasting = $this->parseSpellcasting($element, $allTraits);

        // Build the expanded races
        $expandedRaces = [];

        // 1. Create "Feral" variant - the base variant that keeps the original replaceable trait.
        // "Feral" is the SCAG-specific name for the Tiefling variant with Dex+2/Int+1 ability scores
        // (vs PHB's Cha+2/Int+1). It uses "Infernal Legacy" spellcasting like standard Tieflings.
        // For other future variant bundles, this would be the "base" variant that doesn't swap traits.
        $feralTraits = $sharedTraits;
        if ($replaceableTrait) {
            $feralTraits[] = $replaceableTrait;
        }
        if ($appearanceTrait) {
            $feralTraits[] = $appearanceTrait;
        }

        // For expanded variants, we DON'T set subrace_traits because SubraceStrategy
        // would replace 'traits' with just subspecies traits. These are self-contained
        // subraces from SCAG that need all their traits imported directly.
        $expandedRaces[] = array_merge($commonData, [
            'name' => 'Feral',
            'traits' => $feralTraits,
            'spellcasting' => $commonSpellcasting,
        ]);

        // 2. Create variants that replace Infernal Legacy
        foreach ($variantTraits as $variantTrait) {
            // Extract variant name: "Variant: Devil's Tongue" -> "Devil's Tongue"
            $variantName = trim(str_replace('Variant:', '', $variantTrait['name']));

            // Build traits for this variant (shared + variant trait + appearance)
            $thisVariantTraits = $sharedTraits;
            $thisVariantTraits[] = $variantTrait;
            if ($appearanceTrait) {
                $thisVariantTraits[] = $appearanceTrait;
            }

            // Parse spellcasting from this variant's trait text
            $variantSpellcasting = $this->parseSpellcasting($element, [$variantTrait]);

            // Same as Feral - don't set subrace_traits to preserve all traits
            $expandedRaces[] = array_merge($commonData, [
                'name' => $variantName,
                'traits' => $thisVariantTraits,
                'spellcasting' => $variantSpellcasting,
            ]);
        }

        return $expandedRaces;
    }

    // parseModifierText() and determineBonusCategory() provided by ParsesModifiers trait
}
