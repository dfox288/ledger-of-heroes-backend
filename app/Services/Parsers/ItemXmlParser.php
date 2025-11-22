<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\LookupsGameEntities;
use App\Services\Parsers\Concerns\MapsAbilityCodes;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesCharges;
use App\Services\Parsers\Concerns\ParsesItemProficiencies;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Strategies\ChargedItemStrategy;
use App\Services\Parsers\Strategies\ItemTypeStrategy;
use App\Services\Parsers\Strategies\LegendaryStrategy;
use App\Services\Parsers\Strategies\PotionStrategy;
use App\Services\Parsers\Strategies\ScrollStrategy;
use App\Services\Parsers\Strategies\TattooStrategy;
use SimpleXMLElement;

class ItemXmlParser
{
    use LookupsGameEntities;
    use MapsAbilityCodes;
    use MatchesProficiencyTypes;
    use ParsesCharges;
    use ParsesItemProficiencies;
    use ParsesSourceCitations;

    /**
     * @var ItemTypeStrategy[]
     */
    protected array $strategies = [];

    public function __construct()
    {
        $this->initializeProficiencyTypes();
        $this->initializeStrategies();
    }

    /**
     * Initialize type-specific parsing strategies.
     */
    private function initializeStrategies(): void
    {
        $this->strategies = [
            new ChargedItemStrategy,
            new ScrollStrategy,
            new PotionStrategy,
            new TattooStrategy,
            new LegendaryStrategy,
        ];
    }

    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $items = [];

        foreach ($xml->item as $itemElement) {
            $items[] = $this->parseItem($itemElement);
        }

        return $items;
    }

    private function parseItem(SimpleXMLElement $element): array
    {
        // Concatenate all <text> elements (items can have multiple text blocks)
        $textParts = [];
        foreach ($element->text as $textElement) {
            $textParts[] = trim((string) $textElement);
        }
        $text = implode("\n\n", $textParts);

        // Parse range (can be "50/150" format)
        $range = (string) $element->range;
        $rangeNormal = null;
        $rangeLong = null;
        if (! empty($range) && str_contains($range, '/')) {
            [$rangeNormal, $rangeLong] = explode('/', $range, 2);
            $rangeNormal = (int) trim($rangeNormal);
            $rangeLong = (int) trim($rangeLong);
        } elseif (! empty($range) && is_numeric($range)) {
            $rangeNormal = (int) $range;
        }

        $detailString = (string) $element->detail;

        // Parse charge mechanics from description
        $chargeData = $this->parseCharges($text);

        $baseData = [
            'name' => (string) $element->name,
            'type_code' => (string) $element->type,
            'detail' => ! empty($detailString) ? $detailString : null,
            'rarity' => $this->parseRarity($detailString),
            'requires_attunement' => $this->parseAttunement($text, $detailString),
            'is_magic' => $this->parseMagic($element),
            'cost_cp' => $this->parseCost((string) $element->value),
            'weight' => isset($element->weight) ? (float) $element->weight : null,
            'damage_dice' => (string) $element->dmg1 ?: null,
            'versatile_damage' => (string) $element->dmg2 ?: null,
            'damage_type_code' => (string) $element->dmgType ?: null,
            'range_normal' => $rangeNormal,
            'range_long' => $rangeLong,
            'armor_class' => isset($element->ac) ? (int) $element->ac : null,
            'strength_requirement' => isset($element->strength) ? (int) $element->strength : null,
            'stealth_disadvantage' => strtoupper((string) $element->stealth) === 'YES',
            'description' => $text,
            'properties' => $this->parseProperties((string) $element->property),
            'sources' => $this->parseSourceCitations($text),
            'proficiencies' => $this->extractProficiencies($text),
            'modifiers' => $this->parseModifiers($element),
            'abilities' => $this->parseAbilities($element),
            'charges_max' => $chargeData['charges_max'],
            'recharge_formula' => $chargeData['recharge_formula'],
            'recharge_timing' => $chargeData['recharge_timing'],
        ];

        // Apply type-specific parsing strategies
        foreach ($this->strategies as $strategy) {
            $strategy->reset(); // Clear previous item's data

            if (! $strategy->appliesTo($baseData, $element)) {
                continue;
            }

            // Apply strategy enhancements
            $baseData['modifiers'] = $strategy->enhanceModifiers($baseData['modifiers'], $baseData, $element);
            $baseData['abilities'] = $strategy->enhanceAbilities($baseData['abilities'], $baseData, $element);
            $relationshipData = $strategy->enhanceRelationships($baseData, $element);
            $baseData = array_merge($baseData, $relationshipData);

            // Log strategy application
            $this->logStrategyMetrics($baseData['name'], get_class($strategy), $strategy->extractMetadata());
        }

        return $baseData;
    }

    /**
     * Log strategy metrics to the import-strategy channel.
     */
    private function logStrategyMetrics(string $itemName, string $strategyClass, array $metadata): void
    {
        $strategyName = class_basename($strategyClass);

        \Log::channel('import-strategy')->info("Strategy applied: {$strategyName}", [
            'item' => $itemName,
            'strategy' => $strategyName,
            'warnings' => $metadata['warnings'] ?? [],
            'metrics' => $metadata['metrics'] ?? [],
        ]);
    }

    private function parseCost(string $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        // Convert gold pieces to copper pieces (1 GP = 100 CP)
        return (int) round((float) $value * 100);
    }

    private function parseRarity(string $detail): string
    {
        if (empty($detail)) {
            return 'common';
        }

        // Known rarity values - ORDER MATTERS: check longer strings first to avoid "rare" matching "very rare"
        $rarities = ['very rare', 'legendary', 'artifact', 'uncommon', 'rare', 'common'];

        // Check if detail contains a known rarity
        $detailLower = strtolower($detail);
        foreach ($rarities as $rarity) {
            if (str_contains($detailLower, $rarity)) {
                return $rarity;
            }
        }

        // Default to common if no rarity found
        return 'common';
    }

    private function parseAttunement(string $text, string $detail): bool
    {
        // Check detail field first (primary location): "rare (requires attunement)"
        if (stripos($detail, 'requires attunement') !== false) {
            return true;
        }

        // Fallback: check description text (secondary location)
        return stripos($text, 'requires attunement') !== false;
    }

    private function parseMagic(SimpleXMLElement $element): bool
    {
        return strtoupper((string) $element->magic) === 'YES';
    }

    private function parseProperties(string $propertyString): array
    {
        if (empty($propertyString)) {
            return [];
        }

        return array_map('trim', explode(',', $propertyString));
    }

    private function extractProficiencies(string $text): array
    {
        $proficiencies = [];

        // Pattern 1: Explicit "Proficiency:" list (requirements)
        $pattern = '/Proficienc(?:y|ies):\s*([^\n]+)/i';
        if (preg_match($pattern, $text, $matches)) {
            $profList = array_map('trim', explode(',', $matches[1]));
            foreach ($profList as $profName) {
                $matchedType = $this->matchProficiencyType($profName);

                $proficiencies[] = [
                    'name' => $profName,
                    'type' => $this->inferProficiencyTypeFromName($profName),
                    'proficiency_type_id' => $matchedType?->id,
                    'grants' => false, // Items REQUIRE proficiency
                ];
            }
        }

        // Pattern 2: "you have proficiency with the X" (grants proficiency)
        $grantedProfs = $this->parseProficienciesFromText($text);
        foreach ($grantedProfs as $prof) {
            $matchedType = $this->matchProficiencyType($prof['proficiency_name']);

            $proficiencies[] = [
                'name' => $prof['proficiency_name'],
                'type' => $prof['proficiency_type'],
                'proficiency_type_id' => $matchedType?->id,
                'grants' => true, // Item GRANTS proficiency
            ];
        }

        return $proficiencies;
    }

    private function parseModifiers(SimpleXMLElement $element): array
    {
        $modifiers = [];

        foreach ($element->modifier as $modifierElement) {
            $category = (string) $modifierElement['category'];
            $text = trim((string) $modifierElement);

            // Parse structured data from text
            $parsed = $this->parseModifierText($text, $category);

            if ($parsed !== null) {
                $modifiers[] = $parsed;
            }
        }

        // Add stealth disadvantage modifier if present
        if (isset($element->stealth) && strtoupper((string) $element->stealth) === 'YES') {
            $modifiers[] = [
                'category' => 'skill',
                'skill_id' => null, // Will be looked up by name in importer
                'skill_name' => 'Stealth', // For lookup
                'ability_score_id' => null, // Will be looked up by code in importer
                'ability_score_code' => 'DEX', // For lookup
                'value' => 'disadvantage',
                'condition' => null,
            ];
        }

        // Add conditional speed penalty if strength requirement present
        if (isset($element->strength)) {
            $strengthReq = (int) $element->strength;
            $text = (string) $element->text;

            // Pattern: "speed is reduced by 10 feet" + mentions strength requirement
            if (preg_match('/speed\s+is\s+reduced\s+by\s+(\d+)\s+feet/i', $text, $matches)) {
                $speedPenalty = (int) $matches[1];

                $modifiers[] = [
                    'category' => 'speed',
                    'value' => -$speedPenalty,  // Negative value for penalty
                    'condition' => "strength < {$strengthReq}",
                    'skill_id' => null,
                    'ability_score_id' => null,
                    'damage_type_id' => null,
                ];
            }
        }

        // Parse "set score to X" modifiers from description text
        $setScoreModifiers = $this->parseSetScoreModifiers((string) $element->text);
        $modifiers = array_merge($modifiers, $setScoreModifiers);

        // Parse damage resistance modifiers from description text (potions, etc.)
        $resistanceModifiers = $this->parseResistanceModifiers((string) $element->text);
        $modifiers = array_merge($modifiers, $resistanceModifiers);

        return $modifiers;
    }

    /**
     * Parse "Your [Ability] score is [X]" patterns from description text.
     *
     * Examples:
     * - "Your Intelligence score is 19 while you wear this headband."
     * - "Your Strength score is 19 while you wear these gauntlets."
     *
     * @param  string  $text  Item description text
     * @return array Array of modifier data with 'set:X' value notation
     */
    private function parseSetScoreModifiers(string $text): array
    {
        $modifiers = [];

        // Pattern: "Your [Ability] score is [Number] while..."
        // Captures: 1=ability name, 2=score value, 3=condition including "while"
        if (preg_match('/Your\s+(\w+)\s+score\s+is\s+(\d+)\s+(while\s+[^.]+)/i', $text, $matches)) {
            $abilityName = $matches[1];
            $scoreValue = (int) $matches[2];
            $condition = trim($matches[3]);

            // Map ability name to code
            $abilityCode = $this->mapAbilityNameToCode($abilityName);

            $modifiers[] = [
                'category' => 'ability_score',
                'value' => "set:{$scoreValue}",
                'condition' => $condition,
                'ability_score_id' => null, // Will be resolved by importer
                'ability_score_code' => $abilityCode, // For lookup
                'skill_id' => null,
                'damage_type_id' => null,
            ];
        }

        return $modifiers;
    }

    /**
     * Parse damage resistance patterns from potion descriptions.
     *
     * Handles two patterns:
     * 1. Specific damage type: "you gain resistance to fire damage for 1 hour"
     * 2. All damage types: "you have resistance to all damage"
     *
     * @param  string  $text  Item description text
     * @return array Array of modifier data for resistance
     */
    private function parseResistanceModifiers(string $text): array
    {
        $modifiers = [];

        // Pattern 1: "resistance to all damage" (Potion of Invulnerability)
        // Check for "For X minutes/hours" before OR after the resistance text
        if (preg_match('/you (?:gain|have) resistance to all damage/i', $text)) {
            // Try to find duration either before or after
            $duration = null;
            if (preg_match('/(for \d+ (?:minute|hour)s?)/i', $text, $durationMatch)) {
                $duration = trim(strtolower($durationMatch[1]));
            }

            $modifiers[] = [
                'category' => 'damage_resistance',
                'value' => 'resistance:all',  // Special notation for all types
                'condition' => $duration,
                'ability_score_id' => null,
                'skill_id' => null,
                'damage_type_id' => null,
                // No damage_type_code - null damage_type_id means ALL types
            ];

            return $modifiers; // Return early - don't try to parse specific type
        }

        // Pattern 2: "resistance to [damage type] damage for [duration]"
        // Handles both "you gain resistance" and "you have resistance"
        if (preg_match('/you (?:gain|have) resistance to (\w+) damage[^.]*?(for [^.]+)/i', $text, $matches)) {
            $damageTypeName = ucfirst(strtolower(trim($matches[1]))); // Capitalize first letter to match seeder
            $duration = trim($matches[2]);

            $modifiers[] = [
                'category' => 'damage_resistance',
                'value' => 'resistance',
                'condition' => $duration,
                'ability_score_id' => null,
                'skill_id' => null,
                'damage_type_id' => null, // Will be resolved by importer
                'damage_type_name' => $damageTypeName, // For lookup by name (not code)
            ];
        }

        return $modifiers;
    }

    private function parseModifierText(string $text, string $xmlCategory): ?array
    {
        $text = strtolower($text);

        // Pattern: "category +/-value"
        if (! preg_match('/([\w\s]+)\s*([+\-]\d+)/', $text, $matches)) {
            return null; // Skip unparseable modifiers
        }

        $target = trim($matches[1]);
        $value = (int) $matches[2];

        // Map text to structured categories (order matters - check specific before general)
        $category = match (true) {
            str_contains($target, 'saving throw') => 'saving_throw',
            str_contains($target, 'spell attack') => 'spell_attack',
            str_contains($target, 'spell dc') => 'spell_dc',
            // AC modifiers: distinguish between magic enchantments and generic bonuses
            ($target === 'ac' || $target === 'armor class') && $xmlCategory === 'bonus' => 'ac_magic', // Magic item AC bonuses
            $target === 'ac' || $target === 'armor class' => 'ac', // Generic AC (exact match to avoid matching "acrobatics")
            str_contains($target, 'initiative') => 'initiative',
            str_contains($target, 'melee attack') => 'melee_attack',
            str_contains($target, 'melee damage') => 'melee_damage',
            str_contains($target, 'ranged attack') => 'ranged_attack',
            str_contains($target, 'ranged damage') => 'ranged_damage',
            str_contains($target, 'weapon attack') => 'weapon_attack',
            str_contains($target, 'weapon damage') => 'weapon_damage',
            str_contains($target, 'attack') => 'attack_bonus', // Generic attack (after specific checks)
            str_contains($target, 'damage') => 'damage_bonus', // Generic damage (after specific checks)
            $xmlCategory === 'ability score' => 'ability_score',
            $xmlCategory === 'skill' => 'skill',
            default => 'bonus', // Generic fallback
        };

        $result = [
            'category' => $category,
            'value' => $value,
            'ability_score_id' => null,
            'skill_id' => null,
            'damage_type_id' => null,
        ];

        // For ability score modifiers, match the ability
        if ($category === 'ability_score') {
            $result['ability_score_id'] = $this->matchAbilityScore($target);
        }

        // For skill modifiers, match the skill
        if ($category === 'skill') {
            $result['skill_id'] = $this->matchSkill($target);
        }

        return $result;
    }

    /**
     * Match ability score by fuzzy text search.
     * First tries exact lookup, then falls back to contains matching.
     */
    private function matchAbilityScore(string $text): ?int
    {
        // Try exact match first (common case)
        $exactMatch = $this->lookupAbilityScoreId($text);
        if ($exactMatch !== null) {
            return $exactMatch;
        }

        // Fall back to fuzzy matching (e.g., "Strength save" contains "strength")
        try {
            $text = strtolower($text);
            $abilities = \App\Models\AbilityScore::all();

            foreach ($abilities as $ability) {
                if (str_contains($text, strtolower($ability->name)) ||
                    str_contains($text, strtolower($ability->code))) {
                    return $ability->id;
                }
            }
        } catch (\Exception $e) {
            // Database not available
        }

        return null;
    }

    /**
     * Match skill by fuzzy text search.
     * First tries exact lookup, then falls back to contains matching.
     */
    private function matchSkill(string $text): ?int
    {
        // Try exact match first (common case)
        $exactMatch = $this->lookupSkillId($text);
        if ($exactMatch !== null) {
            return $exactMatch;
        }

        // Fall back to fuzzy matching (e.g., "Acrobatics check" contains "acrobatics")
        try {
            $text = strtolower($text);
            $skills = \App\Models\Skill::all();

            foreach ($skills as $skill) {
                if (str_contains($text, strtolower($skill->name))) {
                    return $skill->id;
                }
            }
        } catch (\Exception $e) {
            // Database not available
        }

        return null;
    }

    private function parseAbilities(SimpleXMLElement $element): array
    {
        $abilities = [];

        foreach ($element->roll as $rollElement) {
            $rollText = trim((string) $rollElement);

            // Extract description attribute if present
            $description = (string) $rollElement['description'];

            // Extract roll formula if present (e.g., "1d4", "2d6")
            $rollFormula = null;
            if (preg_match('/(\d+d\d+(?:\s*[+\-]\s*\d+)?)/', $rollText, $matches)) {
                $rollFormula = $matches[1];
            }

            $abilities[] = [
                'ability_type' => 'roll', // Default type for <roll> elements
                'name' => ! empty($description) ? $description : $rollText,  // Use description if available
                'description' => $rollText,  // Keep the roll text in description
                'roll_formula' => $rollFormula,
                'sort_order' => count($abilities),
            ];
        }

        return $abilities;
    }
}
