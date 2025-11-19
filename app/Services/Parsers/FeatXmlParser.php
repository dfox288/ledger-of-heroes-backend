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
}
