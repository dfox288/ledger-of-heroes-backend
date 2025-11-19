<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

class FeatXmlParser
{
    use ParsesSourceCitations;

    /**
     * Parse feats from XML string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $compendium = simplexml_load_string($xml);

        if ($compendium === false || ! isset($compendium->feat)) {
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

        return [
            'name' => (string) $element->name,
            'prerequisites' => isset($element->prerequisite) ? (string) $element->prerequisite : null,
            'description' => trim($description),
            'sources' => $sources,
            'modifiers' => $this->parseModifiers($element),
            'proficiencies' => $this->parseProficiencies($description),
            'conditions' => $this->parseConditions($description),
        ];
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

    /**
     * Parse modifier text to extract structured data.
     *
     * @return array<string, mixed>|null
     */
    private function parseModifierText(string $text, string $xmlCategory): ?array
    {
        $text = strtolower($text);

        // Pattern: "target +/-value"
        if (! preg_match('/([\w\s]+)\s*([+\-]\d+)/', $text, $matches)) {
            return null;
        }

        $target = trim($matches[1]);
        $value = (int) $matches[2];

        // Determine category based on XML category and target
        $category = match ($xmlCategory) {
            'ability score' => 'ability_score',
            'skill' => 'skill',
            'bonus' => $this->determineBonusCategory($target),
            default => 'bonus',
        };

        $result = [
            'category' => $category,
            'value' => $value,
        ];

        // For ability score modifiers, extract the ability code
        if ($category === 'ability_score') {
            $result['ability_code'] = $this->mapAbilityCode($target);
        }

        return $result;
    }

    /**
     * Determine the specific category for bonus modifiers.
     */
    private function determineBonusCategory(string $target): string
    {
        return match (true) {
            str_contains($target, 'initiative') => 'initiative',
            str_contains($target, 'ac') || str_contains($target, 'armor class') => 'ac',
            default => 'bonus',
        };
    }

    /**
     * Map ability name to ability code.
     */
    private function mapAbilityCode(string $abilityName): string
    {
        $map = [
            'strength' => 'STR',
            'dexterity' => 'DEX',
            'constitution' => 'CON',
            'intelligence' => 'INT',
            'wisdom' => 'WIS',
            'charisma' => 'CHA',
        ];

        $normalized = strtolower(trim($abilityName));

        return $map[$normalized] ?? strtoupper(substr($normalized, 0, 3));
    }

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
     * Convert number words to integers.
     */
    private function wordToNumber(string $word): int
    {
        $map = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
        ];

        return $map[strtolower($word)] ?? 1;
    }

    /**
     * Parse advantage/disadvantage conditions from feat description text.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseConditions(string $text): array
    {
        $conditions = [];

        // Pattern for "You have advantage on..."
        if (preg_match_all('/you have advantage on ([^.]+)/i', $text, $matches)) {
            foreach ($matches[1] as $match) {
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
                $conditions[] = [
                    'effect_type' => 'disadvantage',
                    'description' => trim($match),
                ];
            }
        }

        return $conditions;
    }

    /**
     * Remove source citations from text.
     */
    private function stripSourceCitations(string $text): string
    {
        // Remove everything after "Source:" (including the Source: line)
        $cleaned = preg_replace('/\n*Source:\s*.+$/ims', '', $text);

        return trim($cleaned ?? $text);
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
                $abilityCode = $this->mapAbilityCode($abilityName);

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
                $prerequisites[] = [
                    'prerequisite_type' => \App\Models\ProficiencyType::class,
                    'prerequisite_id' => $profType->id,
                    'minimum_value' => null,
                    'description' => null,
                    'group_id' => $groupId,
                ];
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

        // Try LIKE match
        return \App\Models\ProficiencyType::where('name', 'LIKE', "%{$name}%")->first();
    }

    /**
     * Check if text matches race pattern.
     */
    private function matchesRacePattern(string $text): bool
    {
        // Match single word capitalized names or comma-separated list
        // But NOT sentences or long phrases with many words
        // "Elf", "Dwarf, Gnome, Halfling" - YES
        // "The ability to cast..." - NO (too many lowercase words)
        // "Spellcasting or Pact Magic feature" - NO (has "feature" at end)

        // Reject if it looks like a sentence or has common non-race words
        $lowerText = strtolower($text);
        if (str_contains($lowerText, 'ability to') ||
            str_contains($lowerText, 'feature') ||
            str_contains($lowerText, 'the ') ||
            str_word_count($text) > 8) { // Long phrases are not races
            return false;
        }

        return preg_match('/^[A-Z][a-z]+(\s+[A-Z][a-z]+)?(,\s+[A-Z][a-z]+(\s+[A-Z][a-z]+)*)*/', $text) === 1;
    }

    /**
     * Parse race prerequisites (with optional proficiency at end).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseRacePrerequisites(string $text, int $groupId): array
    {
        $prerequisites = [];

        // Check if there's a proficiency at the end (indicates AND logic)
        $hasProficiency = preg_match('/,\s+Proficiency (with|in)\s+(.+)$/i', $text, $profMatch);

        if ($hasProficiency) {
            // Split into races and proficiency
            $racesPart = preg_replace('/,\s+Proficiency (with|in)\s+.+$/i', '', $text);
            $proficiencyPart = 'Proficiency '.$profMatch[1].' '.$profMatch[2];

            // Parse races (group 1)
            $raceNames = array_map('trim', explode(',', $racesPart));
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
                } else {
                    // Free-form for unmatched races (e.g., "Small Race")
                    $prerequisites[] = [
                        'prerequisite_type' => null,
                        'prerequisite_id' => null,
                        'minimum_value' => null,
                        'description' => $raceName,
                        'group_id' => $groupId,
                    ];
                }
            }

            // Parse proficiency (group 2 - AND with races)
            $profPrereqs = $this->parseProficiencyPrerequisites($proficiencyPart, $groupId + 1);
            $prerequisites = array_merge($prerequisites, $profPrereqs);
        } else {
            // Simple race list (all OR within same group)
            $raceNames = array_map('trim', explode(',', $text));
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
     * Find race by name.
     */
    private function findRace(string $name): ?\App\Models\Race
    {
        $normalized = strtolower(trim($name));

        // Try exact match first
        $race = \App\Models\Race::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($race) {
            return $race;
        }

        // Try LIKE match
        return \App\Models\Race::where('name', 'LIKE', "%{$name}%")->first();
    }
}
