<?php

namespace App\Services\Parsers;

use App\Enums\OptionalFeatureType;
use App\Enums\ResourceType;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\StripsSourceCitations;
use SimpleXMLElement;

class OptionalFeatureXmlParser
{
    use ParsesSourceCitations, StripsSourceCitations;

    /**
     * Parse optional features from XML string.
     *
     * Handles both <spell> and <feat> elements for D&D 5e optional features
     * like Eldritch Invocations, Elemental Disciplines, Maneuvers, etc.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $compendium = XmlLoader::tryFromString($xml);

        if ($compendium === null) {
            return [];
        }

        $features = [];

        // Parse <spell> elements (Invocations, Elemental Disciplines)
        if (isset($compendium->spell)) {
            foreach ($compendium->spell as $element) {
                $features[] = $this->parseSpellElement($element);
            }
        }

        // Parse <feat> elements (Fighting Styles, Maneuvers, etc.)
        if (isset($compendium->feat)) {
            foreach ($compendium->feat as $element) {
                $features[] = $this->parseFeatElement($element);
            }
        }

        return $features;
    }

    /**
     * Parse a <spell> element into optional feature data.
     *
     * @return array<string, mixed>
     */
    private function parseSpellElement(SimpleXMLElement $element): array
    {
        $name = (string) $element->name;
        $text = (string) $element->text;

        // Detect feature type and clean name
        [$featureType, $cleanName] = $this->detectFeatureTypeAndCleanName($name);

        // Extract prerequisite text from beginning of description
        [$prerequisiteText, $levelRequirement, $description] = $this->extractPrerequisites($text);

        // Parse source citations
        $sources = $this->parseSourceCitations($text);

        // Strip source citations from description
        $description = $this->stripSourceCitations($description);

        // Parse resource cost from components, fallback to description
        $resourceData = $this->parseResourceCost((string) $element->components);
        if ($resourceData['type'] === null) {
            $resourceData = $this->parseResourceFromDescription($description);
        }

        // Parse class associations
        $classesData = $this->parseClassAssociations((string) $element->classes, $featureType);

        return [
            'name' => $cleanName,
            'feature_type' => $featureType,
            'level_requirement' => $levelRequirement,
            'prerequisite_text' => $prerequisiteText,
            'description' => trim($description),
            'casting_time' => isset($element->time) ? (string) $element->time : null,
            'range' => isset($element->range) ? (string) $element->range : null,
            'duration' => isset($element->duration) ? (string) $element->duration : null,
            'spell_school_code' => isset($element->school) ? (string) $element->school : null,
            'resource_type' => $resourceData['type'],
            'resource_cost' => $resourceData['cost'],
            'cost_formula' => $resourceData['formula'],
            'sources' => $sources,
            'classes' => $classesData,
        ];
    }

    /**
     * Parse a <feat> element into optional feature data.
     *
     * @return array<string, mixed>
     */
    private function parseFeatElement(SimpleXMLElement $element): array
    {
        $name = (string) $element->name;
        $text = (string) $element->text;

        // Detect feature type and clean name
        [$featureType, $cleanName] = $this->detectFeatureTypeAndCleanName($name);

        // Extract prerequisite text from <prerequisite> element or text
        $prerequisiteText = isset($element->prerequisite) ? (string) $element->prerequisite : null;
        $levelRequirement = null;

        // Parse sources
        $sources = $this->parseSourceCitations($text);

        // Strip source citations from description
        $description = $this->stripSourceCitations($text);

        // Fighting Styles don't have class associations in the XML
        // They're implied by the "Fighting Style Feature" prerequisite
        $classesData = $this->inferClassesFromFeatureType($featureType);

        // Parse resource cost from description (feat elements don't have components)
        $resourceData = $this->parseResourceFromDescription($description);

        return [
            'name' => $cleanName,
            'feature_type' => $featureType,
            'level_requirement' => $levelRequirement,
            'prerequisite_text' => $prerequisiteText,
            'description' => trim($description),
            'casting_time' => null,
            'range' => null,
            'duration' => null,
            'spell_school_code' => null,
            'resource_type' => $resourceData['type'],
            'resource_cost' => $resourceData['cost'],
            'cost_formula' => $resourceData['formula'],
            'sources' => $sources,
            'classes' => $classesData,
        ];
    }

    /**
     * Detect feature type from name prefix and return clean name.
     *
     * @return array{0: OptionalFeatureType, 1: string}
     */
    private function detectFeatureTypeAndCleanName(string $name): array
    {
        $patterns = [
            '/^Invocation:\s*(.+)$/i' => OptionalFeatureType::ELDRITCH_INVOCATION,
            '/^Elemental Discipline:\s*(.+)$/i' => OptionalFeatureType::ELEMENTAL_DISCIPLINE,
            '/^Maneuver:\s*(.+)$/i' => OptionalFeatureType::MANEUVER,
            '/^Metamagic:\s*(.+)$/i' => OptionalFeatureType::METAMAGIC,
            '/^Fighting Style:\s*(.+)$/i' => OptionalFeatureType::FIGHTING_STYLE,
            '/^Infusion:\s*(.+)$/i' => OptionalFeatureType::ARTIFICER_INFUSION,
            '/^Rune:\s*(.+)$/i' => OptionalFeatureType::RUNE,
            '/^Arcane Shot:\s*(.+)$/i' => OptionalFeatureType::ARCANE_SHOT,
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $name, $matches)) {
                return [$type, trim($matches[1])];
            }
        }

        // Default to Eldritch Invocation if no prefix found
        return [OptionalFeatureType::ELDRITCH_INVOCATION, $name];
    }

    /**
     * Extract prerequisite text and level requirement from description.
     *
     * @return array{0: string|null, 1: int|null, 2: string}
     */
    private function extractPrerequisites(string $text): array
    {
        $prerequisiteText = null;
        $levelRequirement = null;
        $description = $text;

        // Pattern: "Prerequisite: ..." at the beginning
        if (preg_match('/^Prerequisite:\s*(.+?)(?:\n\n|\z)/s', $text, $matches)) {
            $prerequisiteText = trim($matches[1]);

            // Extract level requirement (e.g., "17th level Monk" -> 17)
            if (preg_match('/(\d+)(?:st|nd|rd|th)\s+level/i', $prerequisiteText, $levelMatches)) {
                $levelRequirement = (int) $levelMatches[1];
            }

            // Remove prerequisite from description
            $description = preg_replace('/^Prerequisite:\s*.+?(?:\n\n|\n(?=[A-Z]))/s', '', $text);
        }

        return [$prerequisiteText, $levelRequirement, $description];
    }

    /**
     * Parse resource cost from components string.
     *
     * Examples:
     * - "V, S, M (6 ki points)" -> type: KI_POINTS, cost: 6
     * - "V, S, M (3 sorcery points)" -> type: SORCERY_POINTS, cost: 3
     *
     * @return array{type: ResourceType|null, cost: int|null, formula: string|null}
     */
    private function parseResourceCost(string $components): array
    {
        if (preg_match('/\((\d+)\s+ki\s+points?\)/i', $components, $matches)) {
            return ['type' => ResourceType::KI_POINTS, 'cost' => (int) $matches[1], 'formula' => null];
        }

        if (preg_match('/\((\d+)\s+sorcery\s+points?\)/i', $components, $matches)) {
            return ['type' => ResourceType::SORCERY_POINTS, 'cost' => (int) $matches[1], 'formula' => null];
        }

        if (preg_match('/\((\d+)\s+superiority\s+di(?:ce|e)\)/i', $components, $matches)) {
            return ['type' => ResourceType::SUPERIORITY_DIE, 'cost' => (int) $matches[1], 'formula' => null];
        }

        if (preg_match('/\((\d+)\s+charges?\)/i', $components, $matches)) {
            return ['type' => ResourceType::CHARGES, 'cost' => (int) $matches[1], 'formula' => null];
        }

        return ['type' => null, 'cost' => null, 'formula' => null];
    }

    /**
     * Parse resource cost from description text.
     *
     * Fallback for features that don't have structured component data.
     * Extracts costs from natural language patterns in the description.
     *
     * Examples:
     * - "expend one superiority die" -> SUPERIORITY_DIE, cost: 1
     * - "equal to the spell's level" -> SORCERY_POINTS, cost: null, formula: spell_level
     *
     * @return array{type: ResourceType|null, cost: int|null, formula: string|null}
     */
    private function parseResourceFromDescription(string $description): array
    {
        // Variable cost: "equal to the spell's level" (Twinned Spell)
        if (preg_match('/equal to the spell\'?s level/i', $description)) {
            return ['type' => ResourceType::SORCERY_POINTS, 'cost' => null, 'formula' => 'spell_level'];
        }

        // Superiority die patterns: "expend one/a superiority die"
        if (preg_match('/expend (one|a) superiority/i', $description)) {
            return ['type' => ResourceType::SUPERIORITY_DIE, 'cost' => 1, 'formula' => null];
        }

        // Numeric superiority dice: "expend 2 superiority dice"
        if (preg_match('/expend (\d+) superiority/i', $description, $matches)) {
            return ['type' => ResourceType::SUPERIORITY_DIE, 'cost' => (int) $matches[1], 'formula' => null];
        }

        return ['type' => null, 'cost' => null, 'formula' => null];
    }

    /**
     * Parse class associations from <classes> tag.
     *
     * Handles:
     * - Pseudo-class names: "Eldritch Invocations" -> Warlock
     * - Class with subclass: "Monk (Way of the Four Elements)" -> class: Monk, subclass: Way of the Four Elements
     * - Feature type suffix: "Fighter (Arcane Archer): Arcane Shot" -> class: Fighter, subclass: Arcane Archer
     *
     * @return array<int, array{class: string, subclass: string|null}>
     */
    private function parseClassAssociations(string $classesString, OptionalFeatureType $featureType): array
    {
        if (empty($classesString)) {
            return $this->inferClassesFromFeatureType($featureType);
        }

        // Map pseudo-class names to actual classes
        $classMap = [
            'Eldritch Invocations' => ['class' => 'Warlock', 'subclass' => null],
            'Maneuver Options' => ['class' => 'Fighter', 'subclass' => 'Battle Master'],
            'Metamagic Options' => ['class' => 'Sorcerer', 'subclass' => null],
            'Artificer Infusions' => ['class' => 'Artificer', 'subclass' => null],
        ];

        if (isset($classMap[$classesString])) {
            return [$classMap[$classesString]];
        }

        // Strip feature type suffix after closing paren
        // e.g., "Fighter (Arcane Archer): Arcane Shot" -> "Fighter (Arcane Archer)"
        $cleanedString = preg_replace('/\)[^)]*$/', ')', $classesString);

        // Pattern: "ClassName (Subclass Name)"
        if (preg_match('/^(.+?)\s*\((.+?)\)$/', $cleanedString, $matches)) {
            return [[
                'class' => trim($matches[1]),
                'subclass' => trim($matches[2]),
            ]];
        }

        // Simple class name
        return [[
            'class' => $classesString,
            'subclass' => null,
        ]];
    }

    /**
     * Infer class associations from feature type when not specified in XML.
     *
     * @return array<int, array{class: string, subclass: string|null}>
     */
    private function inferClassesFromFeatureType(OptionalFeatureType $featureType): array
    {
        return match ($featureType) {
            OptionalFeatureType::ELDRITCH_INVOCATION => [['class' => 'Warlock', 'subclass' => null]],
            OptionalFeatureType::ELEMENTAL_DISCIPLINE => [['class' => 'Monk', 'subclass' => 'Way of the Four Elements']],
            OptionalFeatureType::MANEUVER => [['class' => 'Fighter', 'subclass' => 'Battle Master']],
            OptionalFeatureType::METAMAGIC => [['class' => 'Sorcerer', 'subclass' => null]],
            OptionalFeatureType::ARTIFICER_INFUSION => [['class' => 'Artificer', 'subclass' => null]],
            OptionalFeatureType::FIGHTING_STYLE => [
                ['class' => 'Fighter', 'subclass' => null],
                ['class' => 'Paladin', 'subclass' => null],
                ['class' => 'Ranger', 'subclass' => null],
            ],
            OptionalFeatureType::ARCANE_SHOT => [['class' => 'Fighter', 'subclass' => 'Arcane Archer']],
            OptionalFeatureType::RUNE => [['class' => 'Fighter', 'subclass' => 'Rune Knight']],
            default => [],
        };
    }
}
