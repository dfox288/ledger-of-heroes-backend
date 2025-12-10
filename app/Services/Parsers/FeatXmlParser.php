<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\ConvertsWordNumbers;
use App\Services\Parsers\Concerns\MapsAbilityCodes;
use App\Services\Parsers\Concerns\ParsesModifiers;
use App\Services\Parsers\Concerns\ParsesMovementModifiers;
use App\Services\Parsers\Concerns\ParsesRestTiming;
use App\Services\Parsers\Concerns\ParsesSkillAdvantages;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\ParsesUsageLimits;
use App\Services\Parsers\Concerns\StripsSourceCitations;
use SimpleXMLElement;

class FeatXmlParser
{
    use ConvertsWordNumbers, MapsAbilityCodes, ParsesModifiers, ParsesMovementModifiers, ParsesRestTiming, ParsesSkillAdvantages, ParsesSourceCitations, ParsesUsageLimits, StripsSourceCitations;

    /**
     * Parse feats from XML string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $compendium = XmlLoader::tryFromString($xml);

        if ($compendium === null || ! isset($compendium->feat)) {
            return [];
        }

        $feats = [];

        foreach ($compendium->feat as $element) {
            $feats[] = $this->parseFeat($element);
        }

        return $feats;
    }

    /**
     * Parse a single feat element.
     *
     * @return array<string, mixed>
     */
    private function parseFeat(SimpleXMLElement $element): array
    {
        // Get raw text
        $text = (string) $element->text;

        // Extract source citations from text
        $sources = $this->parseSourceCitations($text);

        // Remove source citations from description
        $description = $this->stripSourceCitations($text);

        // Parse proficiencies from both XML elements and description text
        $proficienciesFromText = $this->parseProficiencies($description);
        $proficienciesFromXml = $this->parseProficiencyElements($element);

        // Parse modifiers from XML elements
        $modifiersFromXml = $this->parseModifiers($element);

        // Parse passive score modifiers from description text
        // Pass XML modifiers to determine which ability variant was chosen
        $passiveScoreModifiers = $this->parsePassiveScoreModifiers($description, $modifiersFromXml);

        // Filter out ambiguous "Passive" bonus modifiers - we replace them with specific passive_score modifiers
        $modifiersFromXml = array_filter($modifiersFromXml, function ($mod) {
            return $mod['modifier_category'] !== 'passive';
        });

        // Parse skill-based advantages from description text
        $skillAdvantageModifiers = $this->parseSkillAdvantages($description);

        // Parse HP per level modifiers from description text (e.g., Tough feat)
        $hpPerLevelModifiers = $this->parseHitPointPerLevelModifiers($description);

        return [
            'name' => (string) $element->name,
            'prerequisites' => isset($element->prerequisite) ? (string) $element->prerequisite : null,
            'description' => trim($description),
            'sources' => $sources,
            'modifiers' => array_merge(array_values($modifiersFromXml), $passiveScoreModifiers, $skillAdvantageModifiers, $hpPerLevelModifiers),
            'proficiencies' => array_merge($proficienciesFromXml, $proficienciesFromText),
            'conditions' => $this->parseConditions($description),
            'spells' => $this->parseSpells($description),
            'languages' => $this->parseLanguages($description),
            'resets_on' => $this->parseResetTiming($description),
            'base_uses' => $this->parseBaseUses($description),
            'uses_formula' => $this->parseUsesFormula($description),
            'movement_modifiers' => $this->parseMovementModifiers($description),
            'resistances' => $this->parseDamageResistances($description),
        ];
    }

    /**
     * Parse proficiency XML elements.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseProficiencyElements(SimpleXMLElement $element): array
    {
        $proficiencies = [];

        // Parse <proficiency> XML elements
        foreach ($element->proficiency as $proficiencyElement) {
            $proficiencyName = trim((string) $proficiencyElement);

            if (! empty($proficiencyName)) {
                $proficiencies[] = [
                    'description' => $proficiencyName,
                    'is_choice' => false,
                    'quantity' => null,
                ];
            }
        }

        return $proficiencies;
    }

    /**
     * Parse modifier elements from feat.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseModifiers(SimpleXMLElement $element): array
    {
        $modifiers = [];

        foreach ($element->modifier as $modifierElement) {
            $category = (string) $modifierElement['category'];
            $text = trim((string) $modifierElement);

            $parsed = $this->parseModifierText($text, $category);

            if ($parsed !== null) {
                $modifiers[] = $parsed;
            }
        }

        return $modifiers;
    }

    // parseModifierText() and determineBonusCategory() provided by ParsesModifiers trait

    /**
     * Parse proficiencies from feat description text.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseProficiencies(string $text): array
    {
        $proficiencies = [];

        // Pattern for choice-based proficiencies with quantity
        // Examples:
        // - "You gain proficiency with four weapons of your choice"
        // - "You gain proficiency in any combination of three skills or tools of your choice"
        if (preg_match('/gain proficiency (?:with|in)(?: any combination of)?\s+(one|two|three|four|five|six)\s+(.+?)\s+of your choice/i', $text, $matches)) {
            $quantityText = $matches[1];
            $typeText = $matches[2];

            $quantity = $this->wordToNumber($quantityText);

            $proficiencies[] = [
                'description' => trim($typeText),
                'is_choice' => true,
                'quantity' => $quantity,
            ];
        }
        // Pattern for specific proficiencies
        // Examples:
        // - "You gain proficiency with heavy armor"
        // - "You gain proficiency with medium armor and shields"
        elseif (preg_match('/gain proficiency (?:with|in)\s+([^.]+?)\.?$/mi', $text, $matches)) {
            $proficiencyText = trim($matches[1]);

            // Split by "and" to handle multiple proficiencies
            $items = preg_split('/\s+and\s+/i', $proficiencyText);

            foreach ($items as $item) {
                $item = trim($item);
                if (! empty($item)) {
                    $proficiencies[] = [
                        'description' => $item,
                        'is_choice' => false,
                        'quantity' => null,
                    ];
                }
            }
        }

        return $proficiencies;
    }

    /**
     * Parse advantage/disadvantage conditions from feat description text.
     *
     * NOTE: Skill-based advantages like "advantage on Charisma (Deception) checks"
     * are handled by parseSkillAdvantages() and routed to modifiers instead.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseConditions(string $text): array
    {
        $conditions = [];

        // Pattern for "You have advantage on..."
        if (preg_match_all('/you have advantage on ([^.]+)/i', $text, $matches)) {
            foreach ($matches[1] as $match) {
                // Skip skill-based advantages - handled separately by parseSkillAdvantages()
                // Pattern: "Ability (Skill) checks" or "Ability (Skill) and Ability (Skill) checks"
                if (preg_match('/^[A-Z][a-z]+\s*\([^)]+\)(?:\s+and\s+[A-Z][a-z]+\s*\([^)]+\))?\s+checks?\s/i', $match)) {
                    continue;
                }

                $conditions[] = [
                    'effect_type' => 'advantage',
                    'description' => trim($match),
                ];
            }
        }

        // Pattern for "doesn't impose disadvantage on..."
        if (preg_match_all('/(?:doesn\'t|does not) impose disadvantage on ([^.]+)/i', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $conditions[] = [
                    'effect_type' => 'negates_disadvantage',
                    'description' => trim($match),
                ];
            }
        }

        // Pattern for "you have disadvantage on..." (less common but possible)
        if (preg_match_all('/you have disadvantage on ([^.]+)/i', $text, $matches)) {
            foreach ($matches[1] as $match) {
                // Skip skill-based disadvantages
                if (preg_match('/^[A-Z][a-z]+\s*\([^)]+\)(?:\s+and\s+[A-Z][a-z]+\s*\([^)]+\))?\s+checks?\s/i', $match)) {
                    continue;
                }

                $conditions[] = [
                    'effect_type' => 'disadvantage',
                    'description' => trim($match),
                ];
            }
        }

        return $conditions;
    }

    /**
     * Parse damage resistance/immunity from feat description text.
     *
     * Patterns supported:
     * - "You have resistance to [type] and [type] damage." (Infernal Constitution)
     * - "You have resistance to [type] damage." (single type)
     * - "You have resistance to the damage dealt by [source]." (conditional, like traps)
     *
     * @return array<int, array{damage_type: string, condition: string|null}>
     */
    private function parseDamageResistances(string $text): array
    {
        $resistances = [];

        // Pattern 1: "You have resistance to the damage dealt by [source]" (conditional)
        // Matches: "resistance to the damage dealt by traps"
        // Check this FIRST because it's more specific
        if (preg_match('/you have resistance to (the damage dealt by [^.]+)/i', $text, $match)) {
            $resistances[] = [
                'damage_type' => 'all',
                'condition' => trim($match[1]),
            ];

            return $resistances; // Don't continue parsing - this is the specific pattern
        }

        // Pattern 2: "You have resistance to [types] damage"
        // Matches: "resistance to cold and poison damage", "resistance to fire damage"
        if (preg_match('/you have resistance to ([^.]+) damage/i', $text, $match)) {
            $typesString = trim($match[1]);

            // Split on " and " to handle multiple types
            $types = preg_split('/\s+and\s+/i', $typesString);

            foreach ($types as $type) {
                $type = trim($type);
                if (! empty($type)) {
                    $resistances[] = [
                        'damage_type' => strtolower($type),
                        'condition' => null,
                    ];
                }
            }
        }

        return $resistances;
    }

    /**
     * Parse prerequisite text into structured prerequisite data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parsePrerequisites(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $prerequisites = [];
        $currentGroup = 1;

        // Pattern 1: Ability score prerequisites
        // "Dexterity 13 or higher"
        // "Intelligence or Wisdom 13 or higher"
        if ($this->matchesAbilityScorePattern($text)) {
            $prerequisites = $this->parseAbilityScorePrerequisites($text, $currentGroup);

            return $prerequisites;
        }

        // Pattern 2: Proficiency prerequisites
        // "Proficiency with medium armor"
        // "Proficiency with heavy armor"
        if ($this->matchesProficiencyPattern($text)) {
            $prerequisites = $this->parseProficiencyPrerequisites($text, $currentGroup);

            return $prerequisites;
        }

        // Pattern 3: Race prerequisites (with optional proficiency at end)
        // "Elf"
        // "Dwarf, Gnome, Halfling"
        // "Dwarf, Gnome, Halfling, Small Race, Proficiency in Acrobatics"
        if ($this->matchesRacePattern($text)) {
            $prerequisites = $this->parseRacePrerequisites($text, $currentGroup);

            return $prerequisites;
        }

        // Pattern 4: Free-form features (fallback)
        // "The ability to cast at least one spell"
        // "Spellcasting or Pact Magic feature"
        $prerequisites[] = [
            'prerequisite_type' => null,
            'prerequisite_id' => null,
            'minimum_value' => null,
            'description' => $text,
            'group_id' => $currentGroup,
        ];

        return $prerequisites;
    }

    /**
     * Check if text matches ability score pattern.
     */
    private function matchesAbilityScorePattern(string $text): bool
    {
        // Match "Dexterity 13 or higher" or "Intelligence or Wisdom 13 or higher"
        return preg_match('/^(Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma)(\s+or\s+(Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma))?\s+\d+\s+or\s+higher$/i', $text) === 1;
    }

    /**
     * Parse ability score prerequisites.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseAbilityScorePrerequisites(string $text, int $groupId): array
    {
        $prerequisites = [];

        // Extract ability names and minimum value
        // Pattern: "Dexterity 13 or higher" or "Intelligence or Wisdom 13 or higher"
        if (preg_match('/^(.+?)\s+(\d+)\s+or\s+higher$/i', $text, $matches)) {
            $abilitiesText = $matches[1];
            $minimumValue = (int) $matches[2];

            // Split by " or " to handle multiple abilities
            $abilities = preg_split('/\s+or\s+/i', $abilitiesText);

            foreach ($abilities as $abilityName) {
                $abilityName = trim($abilityName);
                $abilityCode = $this->mapAbilityNameToCode($abilityName);

                // Look up ability score ID
                $abilityScore = \App\Models\AbilityScore::where('code', $abilityCode)->first();

                if ($abilityScore) {
                    $prerequisites[] = [
                        'prerequisite_type' => \App\Models\AbilityScore::class,
                        'prerequisite_id' => $abilityScore->id,
                        'minimum_value' => $minimumValue,
                        'description' => null,
                        'group_id' => $groupId,
                    ];
                }
            }
        }

        return $prerequisites;
    }

    /**
     * Check if text matches proficiency pattern.
     */
    private function matchesProficiencyPattern(string $text): bool
    {
        return preg_match('/^Proficiency (with|in)\s+(.+)$/i', $text) === 1;
    }

    /**
     * Parse proficiency prerequisites.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseProficiencyPrerequisites(string $text, int $groupId): array
    {
        $prerequisites = [];

        // Extract proficiency name
        if (preg_match('/^Proficiency (with|in)\s+(.+)$/i', $text, $matches)) {
            $preposition = $matches[1]; // "with" or "in"
            $proficiencyName = trim($matches[2]);

            // Strategy: "in" suggests skill, "with" suggests armor/weapon/tool
            // But try skill first for "in", then fall back to proficiency type

            if (strtolower($preposition) === 'in') {
                // Try to find as Skill first
                $skill = $this->findSkill($proficiencyName);

                if ($skill) {
                    $prerequisites[] = [
                        'prerequisite_type' => \App\Models\Skill::class,
                        'prerequisite_id' => $skill->id,
                        'minimum_value' => null,
                        'description' => null,
                        'group_id' => $groupId,
                    ];

                    return $prerequisites;
                }
            }

            // Try to find matching proficiency type (armor, weapon, tool, etc.)
            $profType = $this->findProficiencyType($proficiencyName);

            if ($profType) {
                // Add the category proficiency
                $prerequisites[] = [
                    'prerequisite_type' => \App\Models\ProficiencyType::class,
                    'prerequisite_id' => $profType->id,
                    'minimum_value' => null,
                    'description' => null,
                    'group_id' => $groupId,
                ];

                // Check if this is a weapon category that should be expanded
                // "Proficiency with a martial weapon" or "Proficiency with a simple weapon"
                if ($this->shouldExpandWeaponCategory($profType)) {
                    $individualWeapons = $this->getIndividualWeapons($profType);

                    foreach ($individualWeapons as $weapon) {
                        $prerequisites[] = [
                            'prerequisite_type' => \App\Models\ProficiencyType::class,
                            'prerequisite_id' => $weapon->id,
                            'minimum_value' => null,
                            'description' => null,
                            'group_id' => $groupId, // Same group = OR logic
                        ];
                    }
                }
            } else {
                // Fallback to free-form if not found
                $prerequisites[] = [
                    'prerequisite_type' => null,
                    'prerequisite_id' => null,
                    'minimum_value' => null,
                    'description' => $text,
                    'group_id' => $groupId,
                ];
            }
        }

        return $prerequisites;
    }

    /**
     * Check if a proficiency type is a weapon category that should be expanded.
     */
    private function shouldExpandWeaponCategory(\App\Models\ProficiencyType $profType): bool
    {
        $weaponCategories = ['Martial Weapons', 'Simple Weapons'];

        return in_array($profType->name, $weaponCategories);
    }

    /**
     * Get individual weapons for a weapon category based on subcategory.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProficiencyType>
     */
    private function getIndividualWeapons(\App\Models\ProficiencyType $categoryProfType): \Illuminate\Database\Eloquent\Collection
    {
        // Map category names to subcategory prefixes
        $subcategoryPrefix = match ($categoryProfType->name) {
            'Martial Weapons' => 'martial',
            'Simple Weapons' => 'simple',
            default => null,
        };

        if ($subcategoryPrefix === null) {
            // Fallback: get all individual weapons
            return \App\Models\ProficiencyType::where('category', 'weapon')
                ->whereNotIn('name', ['Simple Weapons', 'Martial Weapons'])
                ->get();
        }

        // Get weapons with matching subcategory (e.g., 'martial_melee', 'martial_ranged')
        return \App\Models\ProficiencyType::where('category', 'weapon')
            ->where('subcategory', 'LIKE', "{$subcategoryPrefix}%")
            ->whereNotIn('name', ['Simple Weapons', 'Martial Weapons'])
            ->get();
    }

    /**
     * Find skill by name.
     */
    private function findSkill(string $name): ?\App\Models\Skill
    {
        $normalized = strtolower(trim($name));

        // Try exact match first
        $skill = \App\Models\Skill::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($skill) {
            return $skill;
        }

        // Try LIKE match
        return \App\Models\Skill::where('name', 'LIKE', "%{$name}%")->first();
    }

    /**
     * Find proficiency type by name (with fuzzy matching).
     */
    private function findProficiencyType(string $name): ?\App\Models\ProficiencyType
    {
        $normalized = strtolower(trim($name));

        // Strip articles "a" or "an" from beginning
        $normalized = preg_replace('/^(a|an)\s+/', '', $normalized);

        // Direct match
        $profType = \App\Models\ProficiencyType::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($profType) {
            return $profType;
        }

        // Fuzzy match for armor types
        if (str_contains($normalized, 'light') && str_contains($normalized, 'armor')) {
            return \App\Models\ProficiencyType::where('name', 'LIKE', '%Light%Armor%')->first();
        }

        if (str_contains($normalized, 'medium') && str_contains($normalized, 'armor')) {
            return \App\Models\ProficiencyType::where('name', 'LIKE', '%Medium%Armor%')->first();
        }

        if (str_contains($normalized, 'heavy') && str_contains($normalized, 'armor')) {
            return \App\Models\ProficiencyType::where('name', 'LIKE', '%Heavy%Armor%')->first();
        }

        // Fuzzy match for weapon categories
        if (str_contains($normalized, 'martial') && str_contains($normalized, 'weapon')) {
            return \App\Models\ProficiencyType::where('name', 'LIKE', '%Martial%Weapon%')->first();
        }

        if (str_contains($normalized, 'simple') && str_contains($normalized, 'weapon')) {
            return \App\Models\ProficiencyType::where('name', 'LIKE', '%Simple%Weapon%')->first();
        }

        // Try LIKE match
        return \App\Models\ProficiencyType::where('name', 'LIKE', "%{$name}%")->first();
    }

    /**
     * Check if text matches race pattern.
     */
    private function matchesRacePattern(string $text): bool
    {
        // Match single word capitalized names or comma-separated list
        // Can optionally end with "Proficiency in X" or "Proficiency in the X skill"
        // "Elf", "Dwarf, Gnome, Halfling" - YES
        // "Dwarf, Gnome, Halfling, Proficiency in Acrobatics" - YES
        // "Dwarf, Gnome, Halfling, Small Race, Proficiency in the Acrobatics skill" - YES
        // "Elf or Half-Elf" - YES (OR syntax)
        // "Elf (High)", "Elf (Drow)", "Elf (Wood)" - YES (parenthetical subrace)
        // "The ability to cast..." - NO (too many lowercase words)
        // "Spellcasting or Pact Magic feature" - NO (has "feature" at end)

        // Reject if it looks like a sentence or has common non-race words
        $lowerText = strtolower($text);
        if (str_contains($lowerText, 'ability to') ||
            str_contains($lowerText, 'feature')) {
            return false;
        }

        // Allow "Proficiency" at end, but reject "the " in middle (except in "the X skill" at end)
        $withoutProficiency = preg_replace('/,\s+Proficiency (with|in)\s+(the\s+)?.+$/i', '', $text);
        if (str_contains(strtolower($withoutProficiency), 'the ')) {
            return false;
        }

        // Check for parenthetical subrace pattern: "Elf (High)", "Elf (Drow)"
        if (preg_match('/^[A-Z][a-z]+(-[A-Z][a-z]+)?\s*\([A-Z][a-z]+\)$/', $text)) {
            return true;
        }

        // Check for OR syntax: "Elf or Half-Elf"
        // Pattern: Race or Race (with optional hyphenated names like Half-Elf)
        if (preg_match('/^[A-Z][a-z]+(-[A-Z][a-z]+)?\s+or\s+[A-Z][a-z]+(-[A-Z][a-z]+)?$/', $text)) {
            return true;
        }

        // Check for race pattern: capitalized words, comma-separated (with optional hyphens)
        return preg_match('/^[A-Z][a-z]+(-[A-Z][a-z]+)?(\s+[A-Z][a-z]+)?(,\s+[A-Z][a-z]+(-[A-Z][a-z]+)?(\s+[A-Z][a-z]+)*)*/', $text) === 1;
    }

    /**
     * Parse race prerequisites (with optional proficiency/skill at end).
     *
     * Handles:
     * - Simple race: "Elf"
     * - Comma-separated: "Dwarf, Gnome, Halfling"
     * - OR syntax: "Elf or Half-Elf"
     * - Parenthetical subrace: "Elf (High)", "Elf (Drow)"
     * - With proficiency: "Dwarf, Gnome, Halfling, Proficiency in Acrobatics"
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseRacePrerequisites(string $text, int $groupId): array
    {
        $prerequisites = [];

        // Check if there's a proficiency/skill at the end (indicates AND logic)
        // Patterns: "Proficiency in Acrobatics" or "Proficiency in the Acrobatics skill"
        $hasProficiency = preg_match('/,\s+Proficiency (with|in)\s+(the\s+)?(.+?)(\s+skill)?$/i', $text, $profMatch);

        if ($hasProficiency) {
            // Split into races and proficiency
            $racesPart = preg_replace('/,\s+Proficiency (with|in)\s+.+$/i', '', $text);

            // Extract skill/proficiency name (remove "the" and "skill" if present)
            $proficiencyName = trim($profMatch[3]);
            $preposition = $profMatch[1]; // "with" or "in"

            $proficiencyPart = "Proficiency {$preposition} {$proficiencyName}";

            // Parse races (group 1) - split on comma OR " or "
            $raceNames = $this->splitRaceList($racesPart);
            foreach ($raceNames as $raceName) {
                // Skip "Small Race" - it's a size descriptor, redundant when actual small races are listed
                if (stripos($raceName, 'Small Race') !== false) {
                    continue;
                }

                $race = $this->findRace($raceName);
                if ($race) {
                    $prerequisites[] = [
                        'prerequisite_type' => \App\Models\Race::class,
                        'prerequisite_id' => $race->id,
                        'minimum_value' => null,
                        'description' => null,
                        'group_id' => $groupId,
                    ];
                } else {
                    // Free-form for unmatched races
                    $prerequisites[] = [
                        'prerequisite_type' => null,
                        'prerequisite_id' => null,
                        'minimum_value' => null,
                        'description' => $raceName,
                        'group_id' => $groupId,
                    ];
                }
            }

            // Parse proficiency/skill (group 2 - AND with races)
            $profPrereqs = $this->parseProficiencyPrerequisites($proficiencyPart, $groupId + 1);
            $prerequisites = array_merge($prerequisites, $profPrereqs);
        } else {
            // Simple race list (all OR within same group)
            // Split on comma OR " or " to handle both "A, B, C" and "A or B"
            $raceNames = $this->splitRaceList($text);
            foreach ($raceNames as $raceName) {
                $race = $this->findRace($raceName);
                if ($race) {
                    $prerequisites[] = [
                        'prerequisite_type' => \App\Models\Race::class,
                        'prerequisite_id' => $race->id,
                        'minimum_value' => null,
                        'description' => null,
                        'group_id' => $groupId,
                    ];
                }
            }
        }

        return $prerequisites;
    }

    /**
     * Split a race list on both comma and " or " separators.
     *
     * @return array<int, string>
     */
    private function splitRaceList(string $text): array
    {
        // Replace " or " with comma, then split on comma
        $normalized = preg_replace('/\s+or\s+/i', ', ', $text);

        return array_map('trim', explode(',', $normalized));
    }

    /**
     * Find race by name.
     *
     * Handles:
     * - Simple race: "Elf", "Dwarf"
     * - Parenthetical subrace: "Elf (High)", "Elf (Drow)", "Elf (Wood)"
     */
    private function findRace(string $name): ?\App\Models\Race
    {
        $name = trim($name);

        // Check for parenthetical subrace pattern: "Elf (High)", "Elf (Drow)"
        if (preg_match('/^(.+?)\s*\((.+?)\)$/', $name, $matches)) {
            $parentRaceName = trim($matches[1]);
            $subraceName = trim($matches[2]);

            return $this->findSubrace($parentRaceName, $subraceName);
        }

        $normalized = strtolower($name);

        // Try exact match first
        $race = \App\Models\Race::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($race) {
            return $race;
        }

        // Try LIKE match
        return \App\Models\Race::where('name', 'LIKE', "%{$name}%")->first();
    }

    /**
     * Find a subrace by parent race name and subrace name.
     *
     * Examples:
     * - findSubrace("Elf", "High") -> Race with name "High" and parent_race_id pointing to "Elf"
     * - findSubrace("Elf", "Drow") -> Race with name containing "Drow" (e.g., "Drow / Dark")
     */
    private function findSubrace(string $parentRaceName, string $subraceName): ?\App\Models\Race
    {
        // First find the parent race
        $parentRace = \App\Models\Race::whereRaw('LOWER(name) = ?', [strtolower($parentRaceName)])
            ->whereNull('parent_race_id')
            ->first();

        if (! $parentRace) {
            return null;
        }

        $normalizedSubrace = strtolower($subraceName);

        // Try exact match on subrace name
        $subrace = \App\Models\Race::where('parent_race_id', $parentRace->id)
            ->whereRaw('LOWER(name) = ?', [$normalizedSubrace])
            ->first();

        if ($subrace) {
            return $subrace;
        }

        // Try LIKE match (for names like "Drow / Dark" matching "Drow")
        return \App\Models\Race::where('parent_race_id', $parentRace->id)
            ->where('name', 'LIKE', "%{$subraceName}%")
            ->first();
    }

    /**
     * Parse spells granted by a feat from description text.
     *
     * Handles both fixed spells ("You learn the misty step spell") and
     * spell choices ("one 1st-level spell of your choice from illusion or necromancy").
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseSpells(string $text): array
    {
        $spells = [];
        $choiceGroupCounter = 1;

        // 1. Parse fixed spells: "You learn the {spell name} spell"
        if (preg_match_all('/you learn the ([a-z][a-z\s\']+?) spell/i', $text, $matches)) {
            foreach ($matches[1] as $spellName) {
                $spellName = trim($spellName);
                $spellName = ucwords(strtolower($spellName));

                // Skip generic patterns
                if (preg_match('/^\d+(st|nd|rd|th)-level/i', $spellName) ||
                    stripos($spellName, 'cantrip') !== false ||
                    stripos($spellName, 'of your choice') !== false) {
                    continue;
                }

                $spells[] = [
                    'spell_name' => $spellName,
                    'pivot_data' => [
                        'is_cantrip' => false,
                        'usage_limit' => $this->detectUsageLimit($text),
                    ],
                ];
            }
        }

        // 2. Parse school-constrained spell choices
        // Pattern: "one 1st-level spell of your choice" + "must be from the X or Y school"
        if (preg_match('/(?:one|two|three)\s+(\d+)(?:st|nd|rd|th)-level spell(?:s)? of your choice/i', $text, $levelMatch)) {
            if (preg_match('/must be from the ([a-z]+)(?: or ([a-z]+))? school/i', $text, $schoolMatch)) {
                $count = $this->wordToNumber(strtolower(preg_match('/^(one|two|three)/i', $text, $countMatch) ? $countMatch[1] : 'one'));
                $schools = array_filter([strtolower($schoolMatch[1]), isset($schoolMatch[2]) ? strtolower($schoolMatch[2]) : null]);

                $spells[] = [
                    'is_choice' => true,
                    'choice_count' => $count,
                    'choice_group' => 'spell_choice_'.$choiceGroupCounter++,
                    'max_level' => (int) $levelMatch[1],
                    'schools' => $schools,
                    'class_name' => null,
                    'is_ritual_only' => false,
                ];
            }
        }

        // 3. Parse class-constrained cantrip choices
        // Pattern: "You learn two bard cantrips of your choice"
        if (preg_match('/(one|two|three|four)\s+([a-z]+)\s+cantrips?\s+of your choice/i', $text, $cantripMatch)) {
            $count = $this->wordToNumber(strtolower($cantripMatch[1]));
            $className = strtolower($cantripMatch[2]);

            $spells[] = [
                'is_choice' => true,
                'choice_count' => $count,
                'choice_group' => 'spell_choice_'.$choiceGroupCounter++,
                'max_level' => 0, // 0 = cantrip
                'schools' => [],
                'class_name' => $className,
                'is_ritual_only' => false,
            ];
        }

        // 4. Parse class-constrained spell choices
        // Pattern: "choose one 1st-level bard spell" or "one 1st-level bard spell"
        if (preg_match('/(?:choose\s+)?(one|two|three)\s+(\d+)(?:st|nd|rd|th)-level\s+([a-z]+)\s+spell/i', $text, $classSpellMatch)) {
            // Don't duplicate if already captured by school pattern
            $hasSchoolConstraint = preg_match('/must be from the [a-z]+ school/i', $text);
            if (! $hasSchoolConstraint) {
                $count = $this->wordToNumber(strtolower($classSpellMatch[1]));
                $level = (int) $classSpellMatch[2];
                $className = strtolower($classSpellMatch[3]);

                // Check for ritual constraint
                $isRitualOnly = (bool) preg_match('/must have the ritual tag/i', $text);

                $spells[] = [
                    'is_choice' => true,
                    'choice_count' => $count,
                    'choice_group' => 'spell_choice_'.$choiceGroupCounter++,
                    'max_level' => $level,
                    'schools' => [],
                    'class_name' => $className,
                    'is_ritual_only' => $isRitualOnly,
                ];
            }
        }

        return $spells;
    }

    /**
     * Detect the usage limit from description text.
     */
    private function detectUsageLimit(string $text): ?string
    {
        if (stripos($text, 'finish a long rest') !== false) {
            return 'long_rest';
        }

        if (stripos($text, 'finish a short or long rest') !== false ||
            stripos($text, 'finish a short rest') !== false) {
            return 'short_rest';
        }

        return null;
    }

    /**
     * Parse passive score bonuses from description text.
     *
     * For feats like Observant that grant passive bonuses:
     * - Detects "+N bonus to your passive Ability (Skill)" patterns
     * - Uses the ability_score modifier to determine which variant was chosen
     * - Returns only the passive_score modifier for the matching ability's skill
     *
     * Example: Observant (Intelligence) has ability_score modifier for INT,
     * so we return passive_score for Investigation (INT-based skill), not Perception.
     *
     * @param  string  $text  The feat description text
     * @param  array  $xmlModifiers  Modifiers parsed from XML (to determine chosen ability)
     * @return array<int, array<string, mixed>>
     */
    private function parsePassiveScoreModifiers(string $text, array $xmlModifiers): array
    {
        // Extract the bonus value: "+N bonus to your passive" or "+N bonus to passive"
        $bonusValue = null;
        if (preg_match('/\+(\d+)\s+bonus\s+to\s+(?:your\s+)?passive/i', $text, $valueMatch)) {
            $bonusValue = (int) $valueMatch[1];
        }

        if ($bonusValue === null) {
            return [];
        }

        // Find which ability was chosen from the ability_score modifier
        $chosenAbility = null;
        foreach ($xmlModifiers as $mod) {
            if (($mod['modifier_category'] ?? '') === 'ability_score' && isset($mod['ability_code'])) {
                $chosenAbility = strtoupper($mod['ability_code']);
                break;
            }
        }

        if ($chosenAbility === null) {
            return [];
        }

        // Pattern: "passive Ability (Skill)" - extract both ability and skill
        // Examples: "passive Wisdom (Perception)", "passive Intelligence (Investigation)"
        if (preg_match_all('/passive\s+(Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma)\s*\(([^)]+)\)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $abilityName = $match[1];
                $skillName = trim($match[2]);

                // Map ability name to code
                $abilityCode = $this->mapAbilityNameToCode($abilityName);

                // Only return the modifier for the chosen ability
                if ($abilityCode === $chosenAbility) {
                    return [
                        [
                            'modifier_category' => 'passive_score',
                            'value' => $bonusValue,
                            'skill_name' => $skillName,
                        ],
                    ];
                }
            }
        }

        return [];
    }

    /**
     * Parse language grants from feat description text.
     *
     * Handles patterns like "You learn three languages of your choice"
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseLanguages(string $text): array
    {
        // Pattern: "You learn X languages of your choice"
        if (preg_match('/you learn (one|two|three|four|five|six) languages? of your choice/i', $text, $match)) {
            return [[
                'language_id' => null,
                'is_choice' => true,
                'quantity' => $this->wordToNumber($match[1]),
            ]];
        }

        return [];
    }

    /**
     * Parse hit point per level modifiers from feat description text.
     *
     * Handles patterns like "hit point maximum increases by an additional N hit points"
     * which indicates a per-level HP bonus (e.g., Tough feat).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseHitPointPerLevelModifiers(string $text): array
    {
        // Pattern: "hit point maximum increases by an additional N hit points"
        // This pattern appears in the Tough feat
        if (preg_match('/hit point maximum increases by an additional (\d+) hit points?/i', $text, $match)) {
            return [[
                'modifier_category' => 'hit_points_per_level',
                'value' => (int) $match[1],
            ]];
        }

        return [];
    }
}
