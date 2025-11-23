# Monster Importer - Strategy Pattern Design

**Date:** 2025-11-22
**Status:** Design Complete, Ready for Implementation
**Estimated Effort:** 6-8 hours with TDD

---

## Overview

Implement a complete Monster Importer using the Strategy Pattern to handle type-specific parsing logic for D&D 5e monsters across 9 bestiary XML files (~354 monsters in MM alone, 2.6MB).

**Key Goals:**
- Import all monster data from 9 bestiary XML files
- Use Strategy Pattern for type-specific enhancements (dragons, spellcasters, undead, swarms)
- Leverage 6 newly-extracted traits from 2025-11-22 refactoring
- Achieve 85%+ test coverage with real XML fixtures
- Provide detailed import statistics and logging

---

## Architecture

### Core Components

```
MonsterImporter (Base Importer)
    ├─ MonsterXmlParser (XML → Array)
    ├─ AbstractMonsterStrategy (Strategy Interface)
    │   ├─ DragonStrategy (53 dragons)
    │   ├─ SpellcasterStrategy (60+ casters)
    │   ├─ UndeadStrategy (32 undead)
    │   ├─ SwarmStrategy (10 swarms)
    │   └─ DefaultStrategy (All other types)
    └─ Uses Traits:
        ├─ ImportsEntitySpells
        ├─ ImportsSavingThrows
        ├─ ImportsConditions
        ├─ ImportsModifiers
        ├─ ImportsSources
        └─ CachesLookupTables
```

### Monster Type Distribution (MM)

Based on analysis of `bestiary-mm.xml`:

| Type        | Count | Strategy            | Complexity |
|-------------|-------|---------------------|------------|
| Beast       | 91    | DefaultStrategy     | Low        |
| Humanoid    | 74    | SpellcasterStrategy | Medium     |
| Dragon      | 53    | DragonStrategy      | High       |
| Monstrosity | 53    | DefaultStrategy     | Low-Medium |
| Fiend       | 37    | DefaultStrategy     | Medium     |
| Undead      | 32    | UndeadStrategy      | Medium     |
| Elemental   | 23    | DefaultStrategy     | Low        |
| Swarm       | 10    | SwarmStrategy       | Medium     |
| Others      | ~30   | DefaultStrategy     | Low        |

---

## Database Schema (Already Complete)

### Main Table: `monsters`

```sql
- id, name, slug
- size_id (FK to sizes)
- type (beast, humanoid, dragon, etc.)
- alignment (Lawful Good, Chaotic Evil, etc.)
- armor_class, armor_type ("natural armor", "plate mail")
- hit_points_average, hit_dice ("8d8+16")
- speed_walk, speed_fly, speed_swim, speed_burrow, speed_climb, can_hover
- strength, dexterity, constitution, intelligence, wisdom, charisma
- challenge_rating ("1/8", "1", "24"), experience_points
- description
- source_id (FK), source_pages
```

### Related Tables

1. **monster_traits** - Passive abilities (Amphibious, Pack Tactics, Legendary Resistance)
   - `monster_id`, `name`, `description`, `attack_data`, `sort_order`

2. **monster_actions** - Actions, reactions, bonus actions
   - `monster_id`, `action_type` ('action', 'reaction', 'bonus_action')
   - `name`, `description`, `attack_data`, `recharge`, `sort_order`

3. **monster_legendary_actions** - Legendary actions and lair actions
   - `monster_id`, `name`, `description`, `action_cost` (1-3)
   - `is_lair_action`, `attack_data`, `recharge`, `sort_order`

4. **monster_spellcasting** - Spellcasting ability details
   - `monster_id`, `description`, `spell_slots`, `spellcasting_ability`
   - `spell_save_dc`, `spell_attack_bonus`

5. **monster_spells** - Monster-spell junction (composite PK)
   - `monster_id`, `spell_id`, `usage_type` ('at_will', '1/day', '3/day', 'slot')
   - `usage_limit` ("1/day", "3/day each")

### Polymorphic Tables (Reused from other entities)

- **modifiers** - Skill proficiencies, saving throw bonuses, damage resistances
- **entity_conditions** - Condition immunities (poisoned, charmed, frightened)
- **entity_sources** - Multi-source citations
- **entity_languages** - Languages known

---

## XML Structure Analysis

### Sample Monster XML (Aboleth)

```xml
<monster>
    <name>Aboleth</name>
    <size>L</size>
    <type>aberration</type>
    <alignment>Lawful Evil</alignment>
    <ac>17 (natural armor)</ac>
    <hp>135 (18d10+36)</hp>
    <speed>walk 10 ft., swim 40 ft.</speed>
    <str>21</str>
    <dex>9</dex>
    <con>15</con>
    <int>18</int>
    <wis>15</wis>
    <cha>18</cha>
    <save>Con +6, Int +8, Wis +6</save>
    <skill>History +12, Perception +10</skill>
    <passive>20</passive>
    <languages>Deep Speech, telepathy 120 ft.</languages>
    <cr>10</cr>
    <vulnerable/>
    <resist/>
    <immune/>
    <conditionImmune/>
    <senses>darkvision 120 ft.</senses>

    <trait>
        <name>Amphibious</name>
        <text>The aboleth can breathe air and water.</text>
    </trait>

    <action>
        <name>Multiattack</name>
        <text>The aboleth makes three tentacle attacks.</text>
    </action>

    <action>
        <name>Tentacle</name>
        <text>Melee Weapon Attack: +9 to hit, reach 10 ft., one target. Hit: 12 (2d6 + 5) bludgeoning damage.</text>
        <attack>Bludgeoning Damage|+9|2d6+5</attack>
    </action>

    <legendary>
        <name>Legendary Actions (3/Turn)</name>
        <recharge>3/TURN</recharge>
        <text>The aboleth can take 3 legendary actions...</text>
    </legendary>

    <legendary>
        <name>Psychic Drain (Costs 2 Actions)</name>
        <text>One creature charmed by the aboleth takes 10 (3d6) psychic damage...</text>
        <attack>Psychic Damage||3d6</attack>
    </legendary>

    <legendary category="lair">
        <name>Lair Actions</name>
        <text>When fighting inside its lair...</text>
    </legendary>

    <description>Before the coming of the gods...</description>
    <environment>underdark</environment>
</monster>
```

### Spellcaster XML (Acolyte)

```xml
<monster>
    <name>Acolyte</name>
    <!-- ... basic stats ... -->
    <slots>0,3</slots>
    <spells>light, sacred flame, thaumaturgy, bless, cure wounds, sanctuary</spells>
</monster>
```

### Dragon XML (Ancient Red Dragon)

```xml
<monster>
    <name>Ancient Red Dragon</name>
    <type>dragon</type>
    <cr>24</cr>

    <trait>
        <name>Legendary Resistance (3/Day)</name>
        <recharge>3/DAY</recharge>
        <text>If the dragon fails a saving throw, it can choose to succeed instead.</text>
    </trait>

    <action>
        <name>Fire Breath (Recharge 5-6)</name>
        <text>The dragon exhales fire in a 60-foot cone. Each creature in that area must make a DC 24 Dexterity saving throw, taking 91 (26d6) fire damage on a failed save, or half as much damage on a successful one.</text>
        <attack>Fire Damage||26d6</attack>
    </action>

    <legendary>
        <name>Wing Attack (Costs 2 Actions)</name>
        <text>The dragon beats its wings...</text>
    </legendary>
</monster>
```

---

## Strategy Pattern Implementation

### AbstractMonsterStrategy

```php
namespace App\Services\Importers\Strategies\Monster;

abstract class AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to the given monster data
     */
    abstract public function appliesTo(array $monsterData): bool;

    /**
     * Apply type-specific enhancements to parsed traits
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        return $traits; // Default: no enhancement
    }

    /**
     * Apply type-specific action parsing (multiattack, recharge, etc.)
     */
    public function enhanceActions(array $actions, array $monsterData): array
    {
        return $actions; // Default: no enhancement
    }

    /**
     * Parse legendary actions with cost detection
     */
    public function enhanceLegendaryActions(array $legendary, array $monsterData): array
    {
        foreach ($legendary as &$action) {
            // Extract cost from name: "Psychic Drain (Costs 2 Actions)" → 2
            $action['action_cost'] = $this->extractActionCost($action['name']);

            // Detect lair actions via category attribute
            $action['is_lair_action'] = ($action['category'] ?? null) === 'lair';
        }
        return $legendary;
    }

    /**
     * Post-creation hook for additional relationship syncing
     * (e.g., SpellcasterStrategy syncs spells)
     */
    public function afterCreate(Monster $monster, array $monsterData): void
    {
        // Override in strategies that need post-creation work
    }

    /**
     * Extract metadata for logging and statistics
     */
    public function extractMetadata(array $monsterData): array
    {
        return []; // Strategy-specific metrics
    }

    /**
     * Extract action cost from legendary action name
     * "Wing Attack (Costs 2 Actions)" → 2
     * "Detect" → 1 (default)
     */
    protected function extractActionCost(string $name): int
    {
        if (preg_match('/\(Costs? (\d+) Actions?\)/i', $name, $matches)) {
            return (int) $matches[1];
        }
        return 1; // Default cost
    }
}
```

### Strategy 1: DragonStrategy

**Responsibility:** Breath weapons, legendary resistance, lair actions

```php
namespace App\Services\Importers\Strategies\Monster;

class DragonStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type']), 'dragon');
    }

    public function enhanceActions(array $actions, array $monsterData): array
    {
        foreach ($actions as &$action) {
            // Detect breath weapon pattern
            if (str_contains($action['name'], 'Breath')) {
                // Extract recharge: "Fire Breath (Recharge 5-6)" → "5-6"
                if (preg_match('/\(Recharge ([\d\-]+)\)/i', $action['name'], $matches)) {
                    $action['recharge'] = $matches[1];
                }
            }

            // Detect multiattack variants
            if ($action['name'] === 'Multiattack') {
                // Could extract: "makes 3 attacks: one with bite, two with claws"
                // For now, store raw description
            }
        }
        return $actions;
    }

    public function enhanceTraits(array $traits, array $monsterData): array
    {
        foreach ($traits as &$trait) {
            // Legendary Resistance (3/Day) → extract recharge
            if (str_contains($trait['name'], 'Legendary Resistance')) {
                if (preg_match('/\((\d+)\/Day\)/i', $trait['name'], $matches)) {
                    $trait['recharge'] = $matches[1] . '/DAY';
                }
            }
        }
        return $traits;
    }

    public function extractMetadata(array $monsterData): array
    {
        $breathWeapons = collect($monsterData['actions'] ?? [])
            ->filter(fn($a) => str_contains($a['name'], 'Breath'))
            ->count();

        $lairActions = collect($monsterData['legendary'] ?? [])
            ->filter(fn($l) => ($l['category'] ?? null) === 'lair')
            ->count();

        return [
            'breath_weapons_detected' => $breathWeapons,
            'legendary_resistance' => collect($monsterData['traits'] ?? [])
                ->contains(fn($t) => str_contains($t['name'], 'Legendary Resistance')),
            'lair_actions' => $lairActions,
        ];
    }
}
```

### Strategy 2: SpellcasterStrategy

**Responsibility:** Parse spell slots and spell lists, sync to monster_spells

```php
namespace App\Services\Importers\Strategies\Monster;

use App\Services\Importers\Concerns\ImportsEntitySpells;
use App\Models\MonsterSpellcasting;

class SpellcasterStrategy extends AbstractMonsterStrategy
{
    use ImportsEntitySpells;

    public function appliesTo(array $monsterData): bool
    {
        return isset($monsterData['spells']) && !empty($monsterData['spells']);
    }

    public function afterCreate(Monster $monster, array $monsterData): void
    {
        // Create monster_spellcasting record
        MonsterSpellcasting::create([
            'monster_id' => $monster->id,
            'description' => $this->buildSpellcastingDescription($monsterData),
            'spell_slots' => $monsterData['slots'] ?? null,
            'spellcasting_ability' => $this->detectSpellcastingAbility($monsterData),
            'spell_save_dc' => $this->extractSpellSaveDC($monsterData),
            'spell_attack_bonus' => $this->extractSpellAttackBonus($monsterData),
        ]);

        // Parse spell list: "light, sacred flame, bless (1/day), cure wounds (3/day)"
        $spells = $this->parseSpellList($monsterData['spells']);

        // Use ImportsEntitySpells trait for spell matching
        $this->importEntitySpells(
            entity: $monster,
            spellsData: $spells,
            pivotTable: 'monster_spells',
            pivotData: fn($spell) => [
                'usage_type' => $spell['usage_type'], // 'at_will', '1/day', '3/day', 'slot'
                'usage_limit' => $spell['usage_limit'],
            ]
        );
    }

    protected function parseSpellList(string $spells): array
    {
        // "light, sacred flame, bless (1/day), cure wounds (3/day each)"
        // → [
        //     ['name' => 'Light', 'usage_type' => 'at_will', 'usage_limit' => null],
        //     ['name' => 'Bless', 'usage_type' => '1/day', 'usage_limit' => '1/day'],
        //   ]

        $result = [];
        $parts = array_map('trim', explode(',', $spells));

        foreach ($parts as $part) {
            // Extract usage pattern: "bless (1/day)" or "cure wounds (3/day each)"
            if (preg_match('/^(.+?)\s*\(([^)]+)\)$/i', $part, $matches)) {
                $spellName = trim($matches[1]);
                $usage = trim($matches[2]);

                $result[] = [
                    'name' => $spellName,
                    'usage_type' => $this->normalizeUsageType($usage),
                    'usage_limit' => $usage,
                ];
            } else {
                // No usage pattern → at will or slot-based
                $result[] = [
                    'name' => trim($part),
                    'usage_type' => 'at_will',
                    'usage_limit' => null,
                ];
            }
        }

        return $result;
    }

    protected function normalizeUsageType(string $usage): string
    {
        if (str_contains(strtolower($usage), 'at will')) {
            return 'at_will';
        }
        if (preg_match('/(\d+)\/day/i', $usage)) {
            return 'daily';
        }
        return 'slot'; // Slot-based casting
    }

    protected function buildSpellcastingDescription(array $monsterData): string
    {
        // Build description from slots/spells or extract from trait
        return "Spellcasting ability details"; // TODO: Extract from traits if present
    }

    protected function detectSpellcastingAbility(array $monsterData): ?string
    {
        // Look for spellcasting trait or infer from class type
        // For now, return null (can be enhanced)
        return null;
    }

    protected function extractSpellSaveDC(array $monsterData): ?int
    {
        // Search actions/traits for "spell save DC X"
        return null; // TODO: Extract from text
    }

    protected function extractSpellAttackBonus(array $monsterData): ?int
    {
        // Search actions/traits for "+X to hit with spell attacks"
        return null; // TODO: Extract from text
    }

    public function extractMetadata(array $monsterData): array
    {
        $spellList = $this->parseSpellList($monsterData['spells']);

        return [
            'total_spells' => count($spellList),
            'at_will_spells' => collect($spellList)->where('usage_type', 'at_will')->count(),
            'daily_spells' => collect($spellList)->where('usage_type', 'daily')->count(),
            'has_spell_slots' => !empty($monsterData['slots']),
        ];
    }
}
```

### Strategy 3: UndeadStrategy

**Responsibility:** Turn resistance, life drain, condition immunities

```php
namespace App\Services\Importers\Strategies\Monster;

class UndeadStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return strtolower($monsterData['type']) === 'undead';
    }

    public function enhanceTraits(array $traits, array $monsterData): array
    {
        foreach ($traits as &$trait) {
            // Detect turn resistance
            if (str_contains(strtolower($trait['description']), 'turn undead')) {
                $trait['is_turn_resistance'] = true;
            }

            // Detect sunlight sensitivity
            if (str_contains(strtolower($trait['name']), 'sunlight')) {
                $trait['is_sunlight_sensitivity'] = true;
            }
        }
        return $traits;
    }

    public function enhanceActions(array $actions, array $monsterData): array
    {
        foreach ($actions as &$action) {
            // Detect life drain pattern
            if (str_contains(strtolower($action['description']), 'necrotic damage') &&
                str_contains(strtolower($action['description']), 'hit point maximum')) {
                $action['is_life_drain'] = true;
            }
        }
        return $actions;
    }

    public function extractMetadata(array $monsterData): array
    {
        return [
            'has_turn_resistance' => collect($monsterData['traits'] ?? [])
                ->contains(fn($t) => str_contains(strtolower($t['description'] ?? ''), 'turn undead')),
            'has_sunlight_sensitivity' => collect($monsterData['traits'] ?? [])
                ->contains(fn($t) => str_contains(strtolower($t['name'] ?? ''), 'sunlight')),
            'condition_immunities' => $monsterData['conditionImmune'] ?? '',
        ];
    }
}
```

### Strategy 4: SwarmStrategy

**Responsibility:** Parse swarm size, special damage rules

```php
namespace App\Services\Importers\Strategies\Monster;

class SwarmStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type']), 'swarm');
    }

    public function enhanceTraits(array $traits, array $monsterData): array
    {
        foreach ($traits as &$trait) {
            // Detect swarm damage resistance pattern
            if (str_contains(strtolower($trait['description']), 'resistant to') ||
                str_contains(strtolower($trait['name']), 'swarm')) {
                $trait['is_swarm_trait'] = true;
            }
        }
        return $traits;
    }

    public function extractMetadata(array $monsterData): array
    {
        // Extract individual creature size from type: "swarm of Medium beasts" → "Medium"
        $individualSize = null;
        if (preg_match('/swarm of (\w+)/i', $monsterData['type'], $matches)) {
            $individualSize = $matches[1];
        }

        return [
            'individual_creature_size' => $individualSize,
            'swarm_size' => $monsterData['size'],
        ];
    }
}
```

### Strategy 5: DefaultStrategy

**Responsibility:** Fallback for all other monster types (beasts, monstrosities, etc.)

```php
namespace App\Services\Importers\Strategies\Monster;

class DefaultStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return true; // Always applicable as fallback
    }

    // Uses base implementations (no enhancements)
}
```

---

## MonsterXmlParser

```php
namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\ParsesSourceCitations;
use App\Services\Parsers\Concerns\MapsAbilityCodes;
use SimpleXMLElement;

class MonsterXmlParser
{
    use ParsesSourceCitations;
    use MapsAbilityCodes;

    public function parse(string $xmlPath): array
    {
        $xml = simplexml_load_file($xmlPath);
        $monsters = [];

        foreach ($xml->monster as $monsterElement) {
            $monsters[] = $this->parseMonster($monsterElement);
        }

        return $monsters;
    }

    protected function parseMonster(SimpleXMLElement $xml): array
    {
        return [
            // Basic info
            'name' => (string) $xml->name,
            'size' => (string) $xml->size,
            'type' => (string) $xml->type,
            'alignment' => (string) $xml->alignment ?: null,

            // Combat stats
            'armor_class' => $this->parseArmorClass((string) $xml->ac),
            'armor_type' => $this->extractArmorType((string) $xml->ac),
            'hit_points' => $this->parseHitPoints((string) $xml->hp),
            'hit_dice' => $this->extractHitDice((string) $xml->hp),

            // Speeds
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
            'senses' => (string) $xml->senses ?: null,
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

    protected function parseArmorClass(string $ac): int
    {
        // "17 (natural armor)" → 17
        return (int) preg_replace('/\D.*/', '', $ac);
    }

    protected function extractArmorType(string $ac): ?string
    {
        // "17 (natural armor)" → "natural armor"
        if (preg_match('/\(([^)]+)\)/', $ac, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function parseHitPoints(string $hp): int
    {
        // "135 (18d10+36)" → 135
        return (int) preg_replace('/\s.*/', '', $hp);
    }

    protected function extractHitDice(string $hp): string
    {
        // "135 (18d10+36)" → "18d10+36"
        if (preg_match('/\(([^)]+)\)/', $hp, $matches)) {
            return $matches[1];
        }
        return '';
    }

    protected function parseSpeed(string $speed): array
    {
        // "walk 20 ft., fly 50 ft., swim 30 ft." → ['speed_walk' => 20, 'speed_fly' => 50, ...]
        $speeds = [
            'speed_walk' => 0,
            'speed_fly' => null,
            'speed_swim' => null,
            'speed_burrow' => null,
            'speed_climb' => null,
            'can_hover' => false,
        ];

        if (preg_match_all('/(\w+)\s+(\d+)\s*ft/i', $speed, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $type = strtolower($match[1]);
                $value = (int) $match[2];

                $speeds["speed_{$type}"] = $value;
            }
        }

        if (str_contains(strtolower($speed), 'hover')) {
            $speeds['can_hover'] = true;
        }

        return $speeds;
    }

    protected function parseSavingThrows(string $saves): array
    {
        // "Dex +7, Con +16, Wis +9" → [['ability' => 'DEX', 'bonus' => 7], ...]
        if (empty($saves)) {
            return [];
        }

        $result = [];
        $parts = explode(',', $saves);

        foreach ($parts as $part) {
            if (preg_match('/(\w+)\s*([+\-]\d+)/', $part, $matches)) {
                $result[] = [
                    'ability' => strtoupper(substr($matches[1], 0, 3)),
                    'bonus' => (int) $matches[2],
                ];
            }
        }

        return $result;
    }

    protected function parseSkills(string $skills): array
    {
        // "Perception +16, Stealth +7" → [['skill' => 'Perception', 'bonus' => 16], ...]
        if (empty($skills)) {
            return [];
        }

        $result = [];
        $parts = explode(',', $skills);

        foreach ($parts as $part) {
            if (preg_match('/([A-Za-z\s]+)\s*([+\-]\d+)/', $part, $matches)) {
                $result[] = [
                    'skill' => trim($matches[1]),
                    'bonus' => (int) $matches[2],
                ];
            }
        }

        return $result;
    }

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

    protected function parseDescription($description): ?string
    {
        if (empty($description)) {
            return null;
        }

        return (string) $description;
    }

    protected function calculateXP(string $cr): int
    {
        // CR → XP mapping
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
```

---

## MonsterImporter

```php
namespace App\Services\Importers;

use App\Models\Monster;
use App\Models\MonsterTrait;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use App\Services\Parsers\MonsterXmlParser;
use App\Services\Importers\Strategies\Monster\DragonStrategy;
use App\Services\Importers\Strategies\Monster\SpellcasterStrategy;
use App\Services\Importers\Strategies\Monster\UndeadStrategy;
use App\Services\Importers\Strategies\Monster\SwarmStrategy;
use App\Services\Importers\Strategies\Monster\DefaultStrategy;
use Illuminate\Support\Facades\Log;

class MonsterImporter extends BaseImporter
{
    use ImportsSources;
    use ImportsConditions;
    use ImportsModifiers;
    use ImportsSavingThrows;

    protected array $strategies = [];
    protected array $strategyStats = [];

    public function __construct()
    {
        parent::__construct();
        $this->initializeStrategies();
    }

    protected function initializeStrategies(): void
    {
        $this->strategies = [
            new DragonStrategy(),
            new SpellcasterStrategy(),
            new UndeadStrategy(),
            new SwarmStrategy(),
            new DefaultStrategy(), // Fallback (must be last)
        ];
    }

    protected function selectStrategy(array $monsterData): AbstractMonsterStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->appliesTo($monsterData)) {
                return $strategy;
            }
        }

        // Should never reach here due to DefaultStrategy fallback
        return new DefaultStrategy();
    }

    public function import(string $xmlPath): array
    {
        $parser = new MonsterXmlParser();
        $monsters = $parser->parse($xmlPath);

        $created = 0;
        $updated = 0;

        foreach ($monsters as $monsterData) {
            $strategy = $this->selectStrategy($monsterData);
            $strategyName = class_basename($strategy);

            // Track strategy usage
            if (!isset($this->strategyStats[$strategyName])) {
                $this->strategyStats[$strategyName] = [
                    'count' => 0,
                    'warnings' => 0,
                ];
            }
            $this->strategyStats[$strategyName]['count']++;

            // Apply strategy enhancements
            $monsterData['traits'] = $strategy->enhanceTraits(
                $monsterData['traits'],
                $monsterData
            );

            $monsterData['actions'] = $strategy->enhanceActions(
                array_merge($monsterData['actions'], $monsterData['reactions']),
                $monsterData
            );

            $monsterData['legendary'] = $strategy->enhanceLegendaryActions(
                $monsterData['legendary'],
                $monsterData
            );

            // Import monster
            $monster = $this->importEntity($monsterData);

            // Strategy post-creation hook (for spellcasters, etc.)
            $strategy->afterCreate($monster, $monsterData);

            // Log strategy metadata
            $metadata = $strategy->extractMetadata($monsterData);
            Log::channel('import-strategy')->info($strategyName, array_merge(
                ['monster' => $monsterData['name']],
                $metadata
            ));

            if ($monster->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($monsters),
            'strategy_stats' => $this->strategyStats,
        ];
    }

    protected function importEntity(array $monsterData): Monster
    {
        // Lookup size
        $size = $this->lookupCached('sizes', 'code', strtoupper($monsterData['size']));

        // Create/update monster
        $monster = Monster::updateOrCreate(
            ['slug' => $this->generateSlug($monsterData['name'])],
            [
                'name' => $monsterData['name'],
                'size_id' => $size->id,
                'type' => $monsterData['type'],
                'alignment' => $monsterData['alignment'],
                'armor_class' => $monsterData['armor_class'],
                'armor_type' => $monsterData['armor_type'],
                'hit_points_average' => $monsterData['hit_points'],
                'hit_dice' => $monsterData['hit_dice'],
                'speed_walk' => $monsterData['speed_walk'],
                'speed_fly' => $monsterData['speed_fly'],
                'speed_swim' => $monsterData['speed_swim'],
                'speed_burrow' => $monsterData['speed_burrow'],
                'speed_climb' => $monsterData['speed_climb'],
                'can_hover' => $monsterData['can_hover'],
                'strength' => $monsterData['strength'],
                'dexterity' => $monsterData['dexterity'],
                'constitution' => $monsterData['constitution'],
                'intelligence' => $monsterData['intelligence'],
                'wisdom' => $monsterData['wisdom'],
                'charisma' => $monsterData['charisma'],
                'challenge_rating' => $monsterData['challenge_rating'],
                'experience_points' => $monsterData['experience_points'],
                'description' => $monsterData['description'],
            ]
        );

        // Import related data
        $this->importTraits($monster, $monsterData['traits']);
        $this->importActions($monster, $monsterData['actions']);
        $this->importLegendaryActions($monster, $monsterData['legendary']);
        $this->importModifiers($monster, $monsterData);
        $this->importConditions($monster, $monsterData['condition_immunities'] ?? '');
        $this->importSources($monster, $monsterData['description'] ?? '');

        return $monster;
    }

    protected function importTraits(Monster $monster, array $traits): void
    {
        // Clear existing
        $monster->traits()->delete();

        foreach ($traits as $trait) {
            MonsterTrait::create([
                'monster_id' => $monster->id,
                'name' => $trait['name'],
                'description' => $trait['description'],
                'attack_data' => $trait['attack_data'],
                'sort_order' => $trait['sort_order'],
            ]);
        }
    }

    protected function importActions(Monster $monster, array $actions): void
    {
        // Clear existing
        $monster->actions()->delete();

        foreach ($actions as $action) {
            MonsterAction::create([
                'monster_id' => $monster->id,
                'action_type' => $action['action_type'],
                'name' => $action['name'],
                'description' => $action['description'],
                'attack_data' => $action['attack_data'],
                'recharge' => $action['recharge'],
                'sort_order' => $action['sort_order'],
            ]);
        }
    }

    protected function importLegendaryActions(Monster $monster, array $legendary): void
    {
        // Clear existing
        $monster->legendaryActions()->delete();

        foreach ($legendary as $action) {
            MonsterLegendaryAction::create([
                'monster_id' => $monster->id,
                'name' => $action['name'],
                'description' => $action['description'],
                'action_cost' => $action['action_cost'],
                'is_lair_action' => $action['is_lair_action'],
                'attack_data' => $action['attack_data'],
                'recharge' => $action['recharge'],
                'sort_order' => $action['sort_order'],
            ]);
        }
    }

    protected function importModifiers(Monster $monster, array $monsterData): void
    {
        $modifiers = [];

        // Saving throw bonuses
        foreach ($monsterData['saving_throws'] as $save) {
            $modifiers[] = [
                'modifier_type' => 'saving_throw',
                'modifier_category' => 'ability_' . strtolower($save['ability']),
                'value' => (string) $save['bonus'],
            ];
        }

        // Skill proficiencies
        foreach ($monsterData['skills'] as $skill) {
            $modifiers[] = [
                'modifier_type' => 'skill',
                'modifier_category' => strtolower(str_replace(' ', '_', $skill['skill'])),
                'value' => (string) $skill['bonus'],
            ];
        }

        // Damage resistances/immunities/vulnerabilities
        if (!empty($monsterData['damage_resistances'])) {
            $modifiers[] = [
                'modifier_type' => 'resistance',
                'modifier_category' => 'damage',
                'condition' => $monsterData['damage_resistances'],
            ];
        }

        if (!empty($monsterData['damage_immunities'])) {
            $modifiers[] = [
                'modifier_type' => 'immunity',
                'modifier_category' => 'damage',
                'condition' => $monsterData['damage_immunities'],
            ];
        }

        if (!empty($monsterData['damage_vulnerabilities'])) {
            $modifiers[] = [
                'modifier_type' => 'vulnerability',
                'modifier_category' => 'damage',
                'condition' => $monsterData['damage_vulnerabilities'],
            ];
        }

        $this->importModifiers($monster, $modifiers);
    }
}
```

---

## Artisan Command

```php
namespace App\Console\Commands;

use App\Services\Importers\MonsterImporter;
use Illuminate\Console\Command;

class ImportMonstersCommand extends Command
{
    protected $signature = 'import:monsters {file}';
    protected $description = 'Import monsters from XML file';

    public function handle(MonsterImporter $importer): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Importing monsters from: {$file}");

        $result = $importer->import($file);

        $this->newLine();
        $this->info("✓ Successfully imported {$result['total']} monsters");

        // Display strategy statistics
        $this->newLine();
        $this->info('Strategy Statistics:');

        $rows = [];
        foreach ($result['strategy_stats'] as $strategy => $stats) {
            $rows[] = [
                $strategy,
                $stats['count'],
                $stats['warnings'] ?? 0,
            ];
        }

        $this->table(
            ['Strategy', 'Monsters Enhanced', 'Warnings'],
            $rows
        );

        $this->newLine();
        $this->warn('⚠ Detailed logs: storage/logs/import-strategy-' . date('Y-m-d') . '.log');

        return 0;
    }
}
```

---

## Testing Strategy

### Test Structure

```
tests/
├── Unit/
│   ├── Parsers/
│   │   └── MonsterXmlParserTest.php
│   └── Strategies/
│       └── Monster/
│           ├── DragonStrategyTest.php
│           ├── SpellcasterStrategyTest.php
│           ├── UndeadStrategyTest.php
│           ├── SwarmStrategyTest.php
│           └── DefaultStrategyTest.php
└── Feature/
    └── Importers/
        └── MonsterImporterTest.php
```

### Sample Test: DragonStrategyTest

```php
namespace Tests\Unit\Strategies\Monster;

use Tests\TestCase;
use App\Services\Importers\Strategies\Monster\DragonStrategy;

class DragonStrategyTest extends TestCase
{
    protected DragonStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DragonStrategy();
    }

    /** @test */
    public function it_applies_to_dragon_type()
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'humanoid']));
    }

    /** @test */
    public function it_extracts_breath_weapon_recharge()
    {
        $actions = [[
            'name' => 'Fire Breath (Recharge 5-6)',
            'description' => 'The dragon exhales fire...',
        ]];

        $enhanced = $this->strategy->enhanceActions($actions, []);

        $this->assertEquals('5-6', $enhanced[0]['recharge']);
    }

    /** @test */
    public function it_extracts_legendary_resistance_recharge()
    {
        $traits = [[
            'name' => 'Legendary Resistance (3/Day)',
            'description' => 'If the dragon fails a saving throw...',
        ]];

        $enhanced = $this->strategy->enhanceTraits($traits, []);

        $this->assertEquals('3/DAY', $enhanced[0]['recharge']);
    }

    /** @test */
    public function it_detects_lair_actions()
    {
        $legendary = [
            [
                'name' => 'Detect',
                'description' => 'The dragon makes a Wisdom check.',
                'category' => null,
            ],
            [
                'name' => 'Lair Actions',
                'description' => 'On initiative count 20...',
                'category' => 'lair',
            ],
        ];

        $enhanced = $this->strategy->enhanceLegendaryActions($legendary, []);

        $this->assertFalse($enhanced[0]['is_lair_action']);
        $this->assertTrue($enhanced[1]['is_lair_action']);
    }

    /** @test */
    public function it_extracts_legendary_action_costs()
    {
        $legendary = [
            ['name' => 'Detect', 'description' => '...'],
            ['name' => 'Wing Attack (Costs 2 Actions)', 'description' => '...'],
        ];

        $enhanced = $this->strategy->enhanceLegendaryActions($legendary, []);

        $this->assertEquals(1, $enhanced[0]['action_cost']);
        $this->assertEquals(2, $enhanced[1]['action_cost']);
    }
}
```

### Real XML Fixtures

Store fixtures in `tests/Fixtures/xml/monsters/`:

- `ancient_red_dragon.xml` - Dragon with breath weapon, legendary actions, lair
- `acolyte.xml` - Spellcaster with slots and spell list
- `lich.xml` - Undead with legendary actions, spellcasting
- `swarm_of_rats.xml` - Swarm mechanics
- `owlbear.xml` - Simple beast (default strategy)

---

## Import Order & Master Command

Update `import:all` command to include monsters:

```php
// In ImportAllCommand::handle()

// Step 7: Import monsters (after spells exist for spellcaster monsters)
$this->importMonsters();

protected function importMonsters(): void
{
    $this->info('Importing monsters (STEP 7/7)');

    $files = glob(base_path('import-files/bestiary-*.xml'));
    $this->info("Found " . count($files) . " file(s)");

    foreach ($files as $file) {
        $this->info("  → " . basename($file));
        $this->call('import:monsters', ['file' => $file]);
    }
}
```

---

## Success Criteria

- ✅ All 9 bestiary XML files import successfully
- ✅ 85%+ test coverage across all strategies
- ✅ Strategy pattern isolates type-specific logic
- ✅ Reuses 6 traits from 2025-11-22 refactoring
- ✅ Import statistics show strategy usage breakdown
- ✅ Detailed logging to `storage/logs/import-strategy-{date}.log`
- ✅ All 937+ tests still passing after implementation

---

## Implementation Checklist

```markdown
- [ ] Create MonsterXmlParser with speed/AC/HP parsing
- [ ] Create AbstractMonsterStrategy base class
- [ ] Implement DragonStrategy (breath weapons, legendary resistance)
- [ ] Implement SpellcasterStrategy (spell list parsing, uses ImportsEntitySpells)
- [ ] Implement UndeadStrategy (turn resistance, life drain)
- [ ] Implement SwarmStrategy (size extraction)
- [ ] Implement DefaultStrategy (fallback)
- [ ] Create MonsterImporter with strategy selection
- [ ] Create import:monsters command
- [ ] Update import:all to include monsters
- [ ] Write parser tests (speed, AC, HP, skills, saves)
- [ ] Write strategy tests with real XML fixtures (85%+ coverage)
- [ ] Write feature test for full import flow
- [ ] Run full test suite (ensure 937+ tests pass)
- [ ] Import all 9 bestiary files
- [ ] Verify API endpoints work (/api/v1/monsters)
- [ ] Update CHANGELOG.md
- [ ] Update session handover doc
- [ ] Commit with clear message
```

---

## Estimated Breakdown

| Task | Hours |
|------|-------|
| MonsterXmlParser | 1.0 |
| Strategy classes (5 total) | 2.0 |
| MonsterImporter | 1.0 |
| Command + logging | 0.5 |
| Unit tests (parser + strategies) | 2.0 |
| Feature tests | 0.5 |
| Documentation + cleanup | 0.5 |
| **TOTAL** | **7.5 hours** |

With TDD discipline and using existing traits, estimate: **6-8 hours**

---

## Notes

- **Trait reuse**: ImportsEntitySpells eliminates ~100 lines for spellcaster strategy
- **Schema complete**: All 5 monster tables already migrated and tested
- **Pattern proven**: Item strategy pattern (2025-11-22) validates this approach
- **Low risk**: Isolated feature, doesn't affect existing 937 tests
- **High value**: Completes core D&D content (spells + items + monsters = 80% of game data)
