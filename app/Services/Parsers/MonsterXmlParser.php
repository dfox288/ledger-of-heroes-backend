<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\MapsAbilityCodes;
use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

/**
 * Parser for D&D 5e Monster XML files.
 *
 * Converts monster XML elements into structured arrays for import.
 * Handles combat stats, speeds, ability scores, traits, actions, and legendary actions.
 *
 * Used by: MonsterImporter
 */
class MonsterXmlParser
{
    use MapsAbilityCodes;
    use ParsesSourceCitations;

    /**
     * Parse monsters from XML content.
     *
     * @param  string  $xmlContent  The XML content as a string
     * @return array Array of monster data arrays
     */
    public function parse(string $xmlContent): array
    {
        $xml = XmlLoader::fromString($xmlContent);
        $monsters = [];

        foreach ($xml->monster as $monsterElement) {
            $monsters[] = $this->parseMonster($monsterElement);
        }

        return $monsters;
    }

    /**
     * Parse a single monster element into structured data.
     *
     * @param  SimpleXMLElement  $xml  The <monster> element
     * @return array Monster data with all attributes and relationships
     */
    protected function parseMonster(SimpleXMLElement $xml): array
    {
        return [
            // Basic info
            'name' => (string) $xml->name,
            'sort_name' => isset($xml->sortname) ? (string) $xml->sortname : null,
            'size' => (string) $xml->size,
            'type' => (string) $xml->type,
            'alignment' => (string) $xml->alignment ?: null,
            'is_npc' => isset($xml->npc) && strtoupper((string) $xml->npc) === 'YES',

            // Combat stats
            'armor_class' => $this->parseArmorClass((string) $xml->ac),
            'armor_type' => $this->extractArmorType((string) $xml->ac),
            'hit_points' => $this->parseHitPoints((string) $xml->hp),
            'hit_dice' => $this->extractHitDice((string) $xml->hp),

            // Speeds (spread operator to flatten array)
            ...$this->parseSpeed((string) $xml->speed),

            // Ability scores
            'strength' => (int) $xml->str,
            'dexterity' => (int) $xml->dex,
            'constitution' => (int) $xml->con,
            'intelligence' => (int) $xml->int,
            'wisdom' => (int) $xml->wis,
            'charisma' => (int) $xml->cha,

            // Challenge
            'challenge_rating' => (string) $xml->cr,
            'experience_points' => $this->calculateXP((string) $xml->cr),

            // Arrays
            'saving_throws' => $this->parseSavingThrows((string) $xml->save),
            'skills' => $this->parseSkills((string) $xml->skill),
            'damage_vulnerabilities' => (string) $xml->vulnerable ?: null,
            'damage_resistances' => (string) $xml->resist ?: null,
            'damage_immunities' => (string) $xml->immune ?: null,
            'condition_immunities' => (string) $xml->conditionImmune ?: null,
            'senses_raw' => (string) $xml->senses ?: null,
            'senses' => $this->parseSenses((string) $xml->senses),
            'passive_perception' => isset($xml->passive) ? (int) $xml->passive : null,
            'languages' => (string) $xml->languages ?: null,

            // Descriptions
            'description' => $this->parseDescription($xml->description),
            'environment' => (string) $xml->environment ?: null,

            // Related data (arrays)
            'traits' => $this->parseTraits($xml->trait),
            'actions' => $this->parseActions($xml->action),
            'reactions' => $this->parseActions($xml->reaction, 'reaction'),
            'legendary' => $this->parseLegendary($xml->legendary),

            // Spellcasting
            'slots' => (string) $xml->slots ?: null,
            'spells' => (string) $xml->spells ?: null,
        ];
    }

    /**
     * Parse armor class from string.
     *
     * @param  string  $ac  AC string (e.g., "17 (natural armor)")
     * @return int Numeric AC value
     */
    protected function parseArmorClass(string $ac): int
    {
        // Extract numeric value: "17 (natural armor)" → 17
        return (int) preg_replace('/\D.*/', '', $ac);
    }

    /**
     * Extract armor type from AC string.
     *
     * @param  string  $ac  AC string (e.g., "17 (natural armor)")
     * @return string|null Armor type or null if not specified
     */
    protected function extractArmorType(string $ac): ?string
    {
        // Extract text in parentheses: "17 (natural armor)" → "natural armor"
        if (preg_match('/\(([^)]+)\)/', $ac, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Parse hit points average from string.
     *
     * @param  string  $hp  HP string (e.g., "135 (18d10+36)")
     * @return int Average hit points
     */
    protected function parseHitPoints(string $hp): int
    {
        // Extract numeric value: "135 (18d10+36)" → 135
        return (int) preg_replace('/\s.*/', '', $hp);
    }

    /**
     * Extract hit dice formula from HP string.
     *
     * @param  string  $hp  HP string (e.g., "135 (18d10+36)")
     * @return string Hit dice formula (e.g., "18d10+36") or empty string
     */
    protected function extractHitDice(string $hp): string
    {
        // Extract text in parentheses: "135 (18d10+36)" → "18d10+36"
        if (preg_match('/\(([^)]+)\)/', $hp, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Parse speed string into individual movement types.
     *
     * @param  string  $speed  Speed string (e.g., "walk 20 ft., fly 50 ft.")
     * @return array Array with speed_walk, speed_fly, etc. and can_hover flag
     */
    protected function parseSpeed(string $speed): array
    {
        $speeds = [
            'speed_walk' => 0,
            'speed_fly' => null,
            'speed_swim' => null,
            'speed_burrow' => null,
            'speed_climb' => null,
            'can_hover' => false,
        ];

        // Extract all "type number ft" patterns
        if (preg_match_all('/(\w+)\s+(\d+)\s*ft/i', $speed, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $type = strtolower($match[1]);
                $value = (int) $match[2];

                $speeds["speed_{$type}"] = $value;
            }
        }

        // Check for hover ability
        if (str_contains(strtolower($speed), 'hover')) {
            $speeds['can_hover'] = true;
        }

        return $speeds;
    }

    /**
     * Parse saving throw bonuses.
     *
     * @param  string  $saves  Saves string (e.g., "Dex +7, Con +16, Wis +9")
     * @return array Array of ['ability' => 'DEX', 'bonus' => 7]
     */
    protected function parseSavingThrows(string $saves): array
    {
        if (empty($saves)) {
            return [];
        }

        $result = [];
        $parts = explode(',', $saves);

        foreach ($parts as $part) {
            // Match ability and bonus: "Dex +7" or "Str -2"
            if (preg_match('/(\w+)\s*([+\-]\d+)/', $part, $matches)) {
                $result[] = [
                    'ability' => strtoupper(substr($matches[1], 0, 3)),
                    'bonus' => (int) $matches[2],
                ];
            }
        }

        return $result;
    }

    /**
     * Parse skill proficiencies.
     *
     * @param  string  $skills  Skills string (e.g., "Perception +16, Stealth +7")
     * @return array Array of ['skill' => 'Perception', 'bonus' => 16]
     */
    protected function parseSkills(string $skills): array
    {
        if (empty($skills)) {
            return [];
        }

        $result = [];
        $parts = explode(',', $skills);

        foreach ($parts as $part) {
            // Match skill name and bonus: "Perception +16" or "Animal Handling +5"
            if (preg_match('/([A-Za-z\s]+)\s*([+\-]\d+)/', $part, $matches)) {
                $result[] = [
                    'skill' => trim($matches[1]),
                    'bonus' => (int) $matches[2],
                ];
            }
        }

        return $result;
    }

    /**
     * Parse trait elements.
     *
     * @param  iterable  $traits  Collection of <trait> elements
     * @return array Array of trait data with name, description, attack_data, recharge, sort_order
     */
    protected function parseTraits($traits): array
    {
        $result = [];
        $sortOrder = 0;

        foreach ($traits as $trait) {
            $result[] = [
                'name' => (string) $trait->name,
                'description' => (string) $trait->text,
                'attack_data' => $this->parseAttackData($trait->attack),
                'recharge' => (string) $trait->recharge ?: null,
                'sort_order' => $sortOrder++,
            ];
        }

        return $result;
    }

    /**
     * Parse action elements.
     *
     * @param  iterable  $actions  Collection of <action> or <reaction> elements
     * @param  string  $actionType  Type of action ('action' or 'reaction')
     * @return array Array of action data
     */
    protected function parseActions($actions, string $actionType = 'action'): array
    {
        $result = [];
        $sortOrder = 0;

        foreach ($actions as $action) {
            $result[] = [
                'action_type' => $actionType,
                'name' => (string) $action->name,
                'description' => (string) $action->text,
                'attack_data' => $this->parseAttackData($action->attack),
                'recharge' => null, // Extracted by strategies if present in name
                'sort_order' => $sortOrder++,
            ];
        }

        return $result;
    }

    /**
     * Parse legendary action elements.
     *
     * @param  iterable  $legendary  Collection of <legendary> elements
     * @return array Array of legendary action data with category support
     */
    protected function parseLegendary($legendary): array
    {
        $result = [];
        $sortOrder = 0;

        foreach ($legendary as $leg) {
            $result[] = [
                'name' => (string) $leg->name,
                'description' => (string) $leg->text,
                'attack_data' => $this->parseAttackData($leg->attack),
                'recharge' => (string) $leg->recharge ?: null,
                'category' => (string) $leg['category'] ?: null, // 'lair' or null
                'sort_order' => $sortOrder++,
            ];
        }

        return $result;
    }

    /**
     * Parse attack data elements into JSON array.
     *
     * @param  iterable  $attacks  Collection of <attack> elements
     * @return string|null JSON-encoded array of attack strings or null if empty
     */
    protected function parseAttackData($attacks): ?string
    {
        if (empty($attacks)) {
            return null;
        }

        // Convert multiple <attack> elements to JSON array
        $attacksArray = [];
        foreach ($attacks as $attack) {
            $attacksArray[] = (string) $attack;
        }

        return json_encode($attacksArray);
    }

    /**
     * Parse description element.
     *
     * @param  mixed  $description  Description element
     * @return string|null Description text or null if empty
     */
    protected function parseDescription($description): ?string
    {
        if (empty($description)) {
            return null;
        }

        return (string) $description;
    }

    /**
     * Parse senses string into structured array.
     *
     * Handles formats like:
     * - "darkvision 60 ft."
     * - "blindsight 30 ft. (blind beyond this radius)"
     * - "blindsight 10 ft., darkvision 120 ft."
     * - "blindsight 30 ft. or 10 ft. while deafened (blind beyond this radius)"
     *
     * @param  string|null  $senses  Senses string from XML
     * @return array Array of parsed senses with type, range, is_limited, notes
     */
    protected function parseSenses(?string $senses): array
    {
        if (empty($senses)) {
            return [];
        }

        $result = [];
        $senseTypes = ['darkvision', 'blindsight', 'tremorsense', 'truesight'];

        // Build pattern to match any sense type with its range and optional notes
        // Pattern captures: (senseType) (range) optionally (condition like "while deafened") optionally (parenthetical notes)
        $senseTypesPattern = implode('|', $senseTypes);
        $pattern = "/({$senseTypesPattern})\s+(\d+)\s*ft\.?(?:\s+or\s+\d+\s*ft\.?\s+while\s+(\w+))?\s*(?:\(([^)]+)\))?/i";

        // Use preg_match_all to find all senses in order of appearance
        if (preg_match_all($pattern, $senses, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $senseType = strtolower($match[1]);
                $range = (int) $match[2];
                $deafenedCondition = $match[3] ?? null;
                $parentheticalNotes = $match[4] ?? null;

                // Determine if this is a "blind beyond" limitation
                $isLimited = false;
                $notes = null;

                if ($parentheticalNotes) {
                    $isLimited = str_contains(strtolower($parentheticalNotes), 'blind beyond');

                    // Build notes string
                    if ($deafenedCondition) {
                        // Has both deafened condition and parenthetical
                        $notes = "or reduced while {$deafenedCondition}, {$parentheticalNotes}";
                    } else {
                        $notes = $parentheticalNotes;
                    }
                }

                $result[] = [
                    'type' => $senseType,
                    'range' => $range,
                    'is_limited' => $isLimited,
                    'notes' => $notes,
                ];
            }
        }

        return $result;
    }

    /**
     * Calculate experience points from challenge rating.
     *
     * Uses official D&D 5e CR to XP conversion table.
     *
     * @param  string  $cr  Challenge rating (e.g., "1/8", "1", "24")
     * @return int Experience points
     */
    protected function calculateXP(string $cr): int
    {
        // CR → XP mapping from D&D 5e rules
        $xpTable = [
            '0' => 10,
            '1/8' => 25,
            '1/4' => 50,
            '1/2' => 100,
            '1' => 200,
            '2' => 450,
            '3' => 700,
            '4' => 1100,
            '5' => 1800,
            '6' => 2300,
            '7' => 2900,
            '8' => 3900,
            '9' => 5000,
            '10' => 5900,
            '11' => 7200,
            '12' => 8400,
            '13' => 10000,
            '14' => 11500,
            '15' => 13000,
            '16' => 15000,
            '17' => 18000,
            '18' => 20000,
            '19' => 22000,
            '20' => 25000,
            '21' => 33000,
            '22' => 41000,
            '23' => 50000,
            '24' => 62000,
            '25' => 75000,
            '26' => 90000,
            '27' => 105000,
            '28' => 120000,
            '29' => 135000,
            '30' => 155000,
        ];

        return $xpTable[$cr] ?? 0;
    }
}
