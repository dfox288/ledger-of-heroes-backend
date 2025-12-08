<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\LoadsLookupData;
use App\Services\Parsers\Concerns\ParsesDataTables;
use App\Services\Parsers\Concerns\ParsesProjectileScaling;
use App\Services\Parsers\Concerns\ParsesSavingThrows;
use App\Services\Parsers\Concerns\ParsesScalingIncrement;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

class SpellXmlParser
{
    use LoadsLookupData;
    use ParsesDataTables;
    use ParsesProjectileScaling;
    use ParsesSavingThrows;
    use ParsesScalingIncrement;
    use ParsesSourceCitations;

    public function parse(string $xmlContent): array
    {
        $xml = XmlLoader::fromString($xmlContent);
        $spells = [];

        foreach ($xml->spell as $spellElement) {
            $spells[] = $this->parseSpell($spellElement);
        }

        return $spells;
    }

    private function parseSpell(SimpleXMLElement $element): array
    {
        $components = (string) $element->components;
        $materialComponents = null;

        // Extract material components from "V, S, M (materials)"
        if (preg_match('/M \(([^)]+)\)/', $components, $matches)) {
            $materialComponents = $matches[1];
            $components = preg_replace('/\s*\([^)]+\)/', '', $components);
        }

        $duration = (string) $element->duration;
        $needsConcentration = stripos($duration, 'concentration') !== false;

        $isRitual = isset($element->ritual) && strtoupper((string) $element->ritual) === 'YES';

        // Parse classes and tags (strip "School: X, " prefix if present)
        $classesString = (string) $element->classes;
        $classesString = preg_replace('/^School:\s*[^,]+,\s*/', '', $classesString);
        $parts = array_map('trim', explode(',', $classesString));

        // Separate classes from tags using lookup table
        $classes = [];
        $tags = [];
        $knownBaseClasses = $this->getBaseClassNames();

        foreach ($parts as $part) {
            // If it has parentheses, it's a class/subclass
            if (preg_match('/\(/', $part)) {
                $classes[] = $part;
            }
            // If it's a known base class, it's a class
            elseif (in_array($part, $knownBaseClasses)) {
                $classes[] = $part;
            }
            // Otherwise, it's a tag
            else {
                $tags[] = $part;
            }
        }

        // Parse description, higher_levels, and sources from text elements
        $description = '';
        $higherLevels = null;
        $sources = [];

        foreach ($element->text as $text) {
            $textContent = (string) $text;

            // Check if this text contains source citation(s)
            if (preg_match('/Source:\s*(.+)/s', $textContent, $matches)) {
                // Extract all sources from the citation (may be multi-line)
                $sourcesText = $matches[1];
                $sources = $this->parseSourceCitations($sourcesText);

                // Remove the source lines from the description
                $textContent = preg_replace('/\n*Source:\s*.+/s', '', $textContent);
            }

            // Extract "At Higher Levels" section if present
            if (preg_match('/At Higher Levels:\s*(.+?)(?=\n\n|\z)/s', $textContent, $matches)) {
                $higherLevels = trim($matches[1]);

                // Remove the "At Higher Levels" section from description
                $textContent = preg_replace('/\n*At Higher Levels:\s*.+?(?=\n\n|\z)/s', '', $textContent);
            }

            // Add the remaining text to description (even if we extracted higher levels/source)
            if (trim($textContent)) {
                $description .= $textContent."\n\n";
            }
        }

        // Parse roll elements for spell effects
        $effects = $this->parseRollElements($element, $higherLevels);

        // Parse projectile scaling from higher_levels text and apply to first damage effect
        $projectileScaling = $this->parseProjectileScaling($higherLevels, (int) $element->level);

        // For cantrips, also check description for beam scaling (Eldritch Blast)
        if ($projectileScaling === null && (int) $element->level === 0) {
            $projectileScaling = $this->parseCharacterLevelBeamScaling($description);
        }

        if ($projectileScaling !== null && ! empty($effects)) {
            // Apply projectile scaling to the first damage effect
            foreach ($effects as $index => $effect) {
                if ($effect['effect_type'] === 'damage') {
                    $effects[$index]['projectile_count'] = $projectileScaling['projectile_count'];
                    $effects[$index]['projectile_per_level'] = $projectileScaling['projectile_per_level'];
                    $effects[$index]['projectile_name'] = $projectileScaling['projectile_name'];
                    break; // Only apply to first damage effect
                }
            }
        }

        // Parse saving throws from description
        $savingThrows = $this->parseSavingThrows($description);

        // Parse data tables from description
        $randomTables = $this->parseDataTables($description);

        return [
            'name' => (string) $element->name,
            'level' => (int) $element->level,
            'school' => (string) $element->school,
            'casting_time' => (string) $element->time,
            'range' => (string) $element->range,
            'components' => $components,
            'material_components' => $materialComponents,
            'duration' => $duration,
            'needs_concentration' => $needsConcentration,
            'is_ritual' => $isRitual,
            'description' => trim($description),
            'higher_levels' => $higherLevels, // Extracted from "At Higher Levels:" section
            'classes' => $classes,
            'tags' => $tags, // NEW: Non-class categories (Touch Spells, Ritual Caster, Mark of X, etc.)
            'sources' => $sources, // NEW: Array of sources instead of single source
            'effects' => $effects,
            'saving_throws' => $savingThrows, // NEW: Saving throw requirements
            'random_tables' => $randomTables, // NEW: Random tables embedded in description
        ];
    }

    private function parseRollElements(SimpleXMLElement $element, ?string $higherLevels = null): array
    {
        $effects = [];
        $spellLevel = (int) $element->level;

        foreach ($element->roll as $roll) {
            $description = (string) $roll['description'];
            $diceFormula = (string) $roll;
            $rollLevel = isset($roll['level']) ? (int) $roll['level'] : null;

            // Determine effect type based on description
            $effectType = $this->determineEffectType($description);

            // Extract damage type name from description (e.g., "Acid Damage" -> "Acid")
            $damageTypeName = $this->extractDamageTypeName($description);

            // Determine scaling type and levels
            if ($rollLevel !== null) {
                if ($spellLevel === 0 && in_array($rollLevel, [0, 5, 11, 17])) {
                    // Cantrip scaling by character level
                    $scalingType = 'character_level';
                    $minCharacterLevel = $rollLevel;
                    $minSpellSlot = null;
                } else {
                    // Spell slot level scaling
                    $scalingType = 'spell_slot_level';
                    $minCharacterLevel = null;
                    $minSpellSlot = $rollLevel;
                }
            } else {
                // No scaling
                $scalingType = 'none';
                $minCharacterLevel = null;
                $minSpellSlot = null;
            }

            $effects[] = [
                'effect_type' => $effectType,
                'description' => $description,
                'damage_type_name' => $damageTypeName, // NEW: Pass damage type name for lookup
                'dice_formula' => $diceFormula,
                'base_value' => null,
                'scaling_type' => $scalingType,
                'min_character_level' => $minCharacterLevel,
                'min_spell_slot' => $minSpellSlot,
                'scaling_increment' => null, // Will be set below for damage/healing effects
            ];
        }

        // Apply scaling increment to damage and healing effects
        $scalingIncrement = $this->parseScalingIncrement($higherLevels);

        if ($scalingIncrement !== null) {
            foreach ($effects as &$effect) {
                if (in_array($effect['effect_type'], ['damage', 'healing'])) {
                    $effect['scaling_increment'] = $scalingIncrement;
                }
            }
            unset($effect); // Break reference to prevent unintended mutations
        }

        return $effects;
    }

    private function determineEffectType(string $description): string
    {
        $lowercaseDesc = strtolower($description);

        if (str_contains($lowercaseDesc, 'damage')) {
            return 'damage';
        }

        if (str_contains($lowercaseDesc, 'heal') || str_contains($lowercaseDesc, 'regain')) {
            return 'healing';
        }

        return 'other';
    }

    /**
     * Extract damage type name from effect description.
     *
     * Examples:
     * - "Acid Damage" -> "Acid"
     * - "Fire damage" -> "Fire"
     * - "Temporary Hit Points" -> null
     *
     * @param  string  $description  Effect description from roll element
     * @return string|null Damage type name or null if not found
     */
    private function extractDamageTypeName(string $description): ?string
    {
        // Match pattern: "{DamageType} Damage" (case-insensitive)
        if (preg_match('/^(\w+)\s+damage$/i', trim($description), $matches)) {
            // Capitalize first letter to match damage_types table format
            return ucfirst(strtolower($matches[1]));
        }

        return null;
    }
}
