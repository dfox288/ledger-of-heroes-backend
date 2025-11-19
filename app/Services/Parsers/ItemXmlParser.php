<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

class ItemXmlParser
{
    use MatchesProficiencyTypes;
    use ParsesSourceCitations;

    public function __construct()
    {
        $this->initializeProficiencyTypes();
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
        $text = (string) $element->text;

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

        return [
            'name' => (string) $element->name,
            'type_code' => (string) $element->type,
            'rarity' => $this->parseRarity((string) $element->detail),
            'requires_attunement' => $this->parseAttunement($text, (string) $element->detail),
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
        ];
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
        $pattern = '/Proficienc(?:y|ies):\s*([^\n]+)/i';

        if (preg_match($pattern, $text, $matches)) {
            $profList = array_map('trim', explode(',', $matches[1]));
            foreach ($profList as $profName) {
                $matchedType = $this->matchProficiencyType($profName);

                $proficiencies[] = [
                    'name' => $profName,
                    'type' => $this->inferProficiencyType($profName),
                    'proficiency_type_id' => $matchedType?->id,
                    'grants' => false, // Items REQUIRE proficiency
                ];
            }
        }

        return $proficiencies;
    }

    private function inferProficiencyType(string $name): string
    {
        $name = strtolower($name);

        // Armor types
        if (in_array($name, ['light armor', 'medium armor', 'heavy armor', 'shields'])) {
            return 'armor';
        }

        // Weapon types
        if (in_array($name, ['simple', 'martial', 'simple weapons', 'martial weapons']) ||
            str_contains($name, 'weapon')) {
            return 'weapon';
        }

        // Tool types
        if (str_contains($name, 'tools') || str_contains($name, 'kit')) {
            return 'tool';
        }

        // Default to weapon for specific weapon names
        return 'weapon';
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
            $target === 'ac' || $target === 'armor class' => 'ac', // Exact match to avoid matching "acrobatics"
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

    private function matchAbilityScore(string $text): ?int
    {
        static $abilities = null;

        if ($abilities === null) {
            try {
                $abilities = \App\Models\AbilityScore::all()
                    ->keyBy(fn ($a) => strtolower($a->name))
                    ->mapWithKeys(fn ($a, $k) => [
                        $k => $a->id,
                        strtolower($a->code) => $a->id,
                    ]);
            } catch (\Exception $e) {
                $abilities = collect();
            }
        }

        $text = strtolower($text);
        foreach ($abilities as $key => $id) {
            if (str_contains($text, $key)) {
                return $id;
            }
        }

        return null;
    }

    private function matchSkill(string $text): ?int
    {
        static $skills = null;

        if ($skills === null) {
            try {
                $skills = \App\Models\Skill::all()
                    ->mapWithKeys(fn ($s) => [strtolower($s->name) => $s->id]);
            } catch (\Exception $e) {
                $skills = collect();
            }
        }

        $text = strtolower($text);
        foreach ($skills as $key => $id) {
            if (str_contains($text, $key)) {
                return $id;
            }
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
