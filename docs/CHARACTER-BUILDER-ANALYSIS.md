# D&D 5e Character Builder: Comprehensive Analysis

**Date:** 2025-11-25
**Status:** Planning Complete - Ready for Implementation
**Estimated Effort:** 79-108 hours (MVP: 52-68 hours)

---

## Executive Summary

The D&D 5e Importer API has **exceptional foundational data** for building a character management system. All 7 entity types (races, classes, backgrounds, feats, spells, items, monsters) are complete with rich polymorphic relationships. The primary gap is the **character persistence layer** - we need to track character instances, not just reference data.

**Current State:** Complete D&D reference API (compendium)
**Target State:** Character management API with CRUD, level progression, spell selection, inventory

**Key Discovery:** ASI (Ability Score Improvement) tracking already exists in `modifiers` table, saving 4-6 hours of development time.

---

## Table of Contents

1. [What We Have](#what-we-have)
2. [What's Missing](#whats-missing)
3. [Database Schema Required](#database-schema-required)
4. [Business Logic Required](#business-logic-required)
5. [API Endpoints Required](#api-endpoints-required)
6. [Implementation Phases](#implementation-phases)
7. [Development Effort Estimates](#development-effort-estimates)
8. [Quick Wins Before Starting](#quick-wins-before-starting)
9. [Technical Recommendations](#technical-recommendations)

---

## What We Have

### ✅ Complete Reference Data (Production Ready)

| Entity | Count | Completeness | Notes |
|--------|-------|--------------|-------|
| **Spells** | 477 | 100% | All schools, classes, components, effects, saving throws |
| **Classes** | 131 | 100% | Base + subclasses, features by level, spell slots, proficiencies |
| **Races** | 115 | 100% | Ability bonuses, traits, proficiencies, languages, innate spells |
| **Items** | 516 | 95% | Weapons, armor, magic items with full stats |
| **Backgrounds** | 34 | 100% | Proficiencies, equipment, languages, traits |
| **Feats** | 138 | 100% | Prerequisites, ability bonuses, proficiency grants |
| **Monsters** | 598 | 100% | Not needed for character builder |

### ✅ Excellent Data Structures

#### ASI Tracking (COMPLETE - No Work Needed!)
```php
// Already exists in modifiers table:
Fighter ASI levels: [4, 6, 8, 12, 14, 16, 19] // 7 ASIs
Most classes: [4, 8, 12, 16, 19]              // 5 ASIs

$fighter = CharacterClass::where('slug', 'fighter')->first();
$asiLevels = $fighter->modifiers()
    ->where('modifier_category', 'ability_score')
    ->orderBy('level')
    ->pluck('level')
    ->toArray();
```

**Storage:**
- `modifiers.modifiable_type` = `'App\Models\CharacterClass'`
- `modifiers.modifier_category` = `'ability_score'`
- `modifiers.level` = ASI level (4, 6, 8, etc.)
- `modifiers.value` = `'+2'` (total ability points to distribute)
- `modifiers.ability_score_id` = `NULL` (player chooses which abilities)

#### Class Level Progression (COMPLETE)
- ✅ Spell slots per level (1-20) fully imported
- ✅ Cantrips known per level
- ✅ Spells known per level (for known-spell casters)

#### Class Features (COMPLETE)
- ✅ All features with level requirements
- ✅ Descriptions with scaling rules
- ✅ Random tables for choices (fighting styles, warlock invocations)
- ✅ Subclass feature inheritance

#### Polymorphic Architecture (COMPLETE)
- ✅ `entity_proficiencies` - Reusable proficiency grants
- ✅ `entity_modifiers` - Ability/skill/AC bonuses
- ✅ `entity_traits` - Named abilities and descriptions
- ✅ `entity_languages` - Language grants with choices
- ✅ `entity_spells` - Innate and item spells
- ✅ `entity_prerequisites` - Feat/item requirements
- ✅ `entity_sources` - Book citations
- ✅ `entity_items` - Starting equipment with choices

#### Starting Equipment Choices (COMPLETE)
```php
// Fighter level 1 equipment choices already parsed:
Choice Group A: [chain mail, leather armor + longbow + 20 arrows]
Choice Group B: [martial weapon + shield, two martial weapons]
Choice Group C: [light crossbow + 20 bolts, two handaxes]
Choice Group D: [dungeoneer's pack, explorer's pack]
```

### ✅ API Features Already Built

- **Meilisearch Integration:** 40+ indexed fields per entity, fast filtering
- **Redis Caching:** 93.7% performance improvement on show endpoints
- **Form Request Pattern:** Validation + OpenAPI documentation
- **Resource Pattern:** Consistent JSON serialization
- **Service Layer:** Business logic separation
- **TDD Infrastructure:** 1,489 tests passing (99.7% pass rate)

---

## What's Missing

### 1. Character Persistence Layer (CRITICAL)

We need **8 new database tables** to store character instances:

#### `characters` table (Core)
```sql
CREATE TABLE characters (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,                    -- FK to users (owner)
    name VARCHAR(255) NOT NULL,
    race_id BIGINT NOT NULL,           -- FK to races
    background_id BIGINT NOT NULL,     -- FK to backgrounds
    alignment VARCHAR(2),               -- LG, NG, CG, LN, N, CN, LE, NE, CE
    level TINYINT NOT NULL DEFAULT 1,  -- Total character level (1-20)
    experience_points INT DEFAULT 0,

    -- Hit Points
    current_hp SMALLINT NOT NULL,
    max_hp SMALLINT NOT NULL,
    temp_hp SMALLINT DEFAULT 0,
    hit_dice_remaining JSON,           -- {"d6": 0, "d8": 2, "d10": 1, "d12": 0}

    -- Death Saves
    death_save_successes TINYINT DEFAULT 0,  -- 0-3
    death_save_failures TINYINT DEFAULT 0,   -- 0-3
    is_stable BOOLEAN DEFAULT TRUE,

    -- Combat Stats (calculated, but can cache)
    armor_class TINYINT,
    speed TINYINT,
    initiative_modifier TINYINT,
    proficiency_bonus TINYINT,
    passive_perception TINYINT,

    -- Other
    inspiration BOOLEAN DEFAULT FALSE,

    -- Personality (from background)
    personality_traits TEXT,
    ideals TEXT,
    bonds TEXT,
    flaws TEXT,
    backstory TEXT,

    -- Metadata
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_race_id (race_id),
    INDEX idx_background_id (background_id)
);
```

#### `character_classes` junction table (Multiclassing Support)
```sql
CREATE TABLE character_classes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT NOT NULL,      -- FK to characters
    class_id BIGINT NOT NULL,          -- FK to classes (base class OR subclass)
    class_level TINYINT NOT NULL,      -- Level in THIS class (1-20)
    subclass_id BIGINT,                -- FK to classes (subclass, if chosen)
    subclass_selected_at_level TINYINT, -- Character level when subclass chosen
    hit_points_rolled JSON,            -- [8, 6, 7, 10] - track each level's HP roll
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE KEY unique_character_class (character_id, class_id),
    INDEX idx_character_id (character_id),
    INDEX idx_class_id (class_id)
);
```

#### `character_ability_scores` table
```sql
CREATE TABLE character_ability_scores (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT NOT NULL,
    ability_score_id BIGINT NOT NULL,  -- FK to ability_scores (STR, DEX, CON, INT, WIS, CHA)
    base_score TINYINT NOT NULL,       -- From point buy/rolling/standard array (8-15 typically)
    racial_bonus TINYINT DEFAULT 0,    -- Denormalized from race modifiers
    asi_bonus TINYINT DEFAULT 0,       -- Sum of Ability Score Improvements
    feat_bonus TINYINT DEFAULT 0,      -- From feats (Athlete, etc.)
    other_bonus TINYINT DEFAULT 0,     -- Magic items, temporary effects
    override_score TINYINT,            -- For magic items that SET scores (Belt of Giant Strength = 19)
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE KEY unique_character_ability (character_id, ability_score_id),
    INDEX idx_character_id (character_id)
);
```

**Calculation:** `final_score = override_score ?? (base_score + racial_bonus + asi_bonus + feat_bonus + other_bonus)`

#### `character_spells` junction table
```sql
CREATE TABLE character_spells (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT NOT NULL,
    spell_id BIGINT NOT NULL,          -- FK to spells
    source_type ENUM('class', 'race', 'feat', 'item'),
    source_id BIGINT,                  -- FK to character_classes/character_feats/character_items
    is_prepared BOOLEAN DEFAULT FALSE, -- For prepared casters (Cleric, Druid, Wizard)
    is_always_prepared BOOLEAN DEFAULT FALSE, -- Domain spells, oath spells, racial spells
    learned_at_level TINYINT,          -- Audit trail
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE KEY unique_character_spell (character_id, spell_id),
    INDEX idx_character_id (character_id),
    INDEX idx_spell_id (spell_id)
);
```

#### `character_items` junction table (Inventory)
```sql
CREATE TABLE character_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT NOT NULL,
    item_id BIGINT,                    -- FK to items (NULL for custom items)
    custom_item_name VARCHAR(255),     -- For non-standard items
    quantity INT DEFAULT 1,
    is_equipped BOOLEAN DEFAULT FALSE,
    is_attuned BOOLEAN DEFAULT FALSE,  -- Max 3 attuned items
    container_id BIGINT,               -- FK to character_items (for bags of holding)
    notes TEXT,                        -- Custom notes, +1 modifications, etc.
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_character_id (character_id),
    INDEX idx_item_id (item_id)
);
```

#### `character_feats` junction table
```sql
CREATE TABLE character_feats (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT NOT NULL,
    feat_id BIGINT NOT NULL,           -- FK to feats
    taken_at_level TINYINT NOT NULL,   -- Character level when taken (4, 6, 8, etc.)
    class_id BIGINT,                   -- FK to character_classes (which class level granted this?)
    choices_json JSON,                 -- For feats with choices (Fighting Initiate, Skill Expert)
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE KEY unique_character_feat (character_id, feat_id),
    INDEX idx_character_id (character_id)
);
```

#### `character_proficiencies` table
```sql
CREATE TABLE character_proficiencies (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT NOT NULL,
    proficiency_type ENUM('skill', 'saving_throw', 'armor', 'weapon', 'tool', 'language'),
    proficiency_type_id BIGINT,        -- FK to proficiency_types/skills
    skill_id BIGINT,                   -- FK to skills (for skill proficiencies)
    proficiency_name VARCHAR(255),     -- For custom proficiencies
    source_type ENUM('race', 'class', 'background', 'feat'),
    source_id BIGINT,
    expertise BOOLEAN DEFAULT FALSE,   -- Rogue/Bard double proficiency
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_character_id (character_id)
);
```

#### `character_currencies` table
```sql
CREATE TABLE character_currencies (
    character_id BIGINT PRIMARY KEY,   -- One row per character
    copper INT DEFAULT 0,
    silver INT DEFAULT 0,
    electrum INT DEFAULT 0,
    gold INT DEFAULT 0,
    platinum INT DEFAULT 0,
    updated_at TIMESTAMP
);
```

### 2. Missing Data in Existing Entities

#### Subclass Selection Level (PARTIALLY COMPLETE)
**Current Status:**
- ✅ Feature name indicates subclass selection ("Martial Archetype", "Divine Domain")
- ✅ Level is tracked in `class_features.level`
- ❌ Not structured - buried in feature text

**Solution:** Add `subclass_selection_level` column to `classes` table

```sql
ALTER TABLE classes ADD COLUMN subclass_selection_level TINYINT AFTER hit_die;

-- Populate:
UPDATE classes c
INNER JOIN class_features cf ON cf.class_id = c.id
SET c.subclass_selection_level = cf.level
WHERE cf.feature_name IN (
    'Martial Archetype',      -- Fighter (3)
    'Divine Domain',          -- Cleric (1)
    'Sacred Oath',            -- Paladin (3)
    'Primal Path',            -- Barbarian (3)
    'Monastic Tradition',     -- Monk (3)
    'Arcane Tradition',       -- Wizard (2)
    'Roguish Archetype',      -- Rogue (3)
    'Ranger Archetype',       -- Ranger (3)
    'Bardic College',         -- Bard (3)
    'Druidic Circle',         -- Druid (2)
    'Sorcerous Origin',       -- Sorcerer (1)
    'Otherworldly Patron',    -- Warlock (1)
    'Artificer Specialist'    -- Artificer (3)
);
```

**Effort:** 1-2 hours (migration + population script + test)

#### Multiclass Prerequisites (TBD)
**Status:** 🔍 Requires investigation
- XML files may contain "Strength 13" data for multiclassing
- `entity_prerequisites` table architecture exists
- Needs verification if data is imported

**Action:** Investigate XML structure and current import logic

### 3. Missing Lookup Tables

#### XP Advancement Table
```sql
CREATE TABLE character_advancement (
    level TINYINT PRIMARY KEY,
    xp_required INT NOT NULL,
    proficiency_bonus TINYINT NOT NULL
);

INSERT INTO character_advancement (level, xp_required, proficiency_bonus) VALUES
(1, 0, 2), (2, 300, 2), (3, 900, 2), (4, 2700, 2),
(5, 6500, 3), (6, 14000, 3), (7, 23000, 3), (8, 34000, 3),
(9, 48000, 4), (10, 64000, 4), (11, 85000, 4), (12, 100000, 4),
(13, 120000, 5), (14, 140000, 5), (15, 165000, 5), (16, 195000, 5),
(17, 225000, 6), (18, 265000, 6), (19, 305000, 6), (20, 355000, 6);
```

---

## Business Logic Required

### 1. Ability Score Management

```php
namespace App\Services;

class AbilityScoreService
{
    /**
     * Generate ability scores using specified method
     *
     * @param string $method 'point_buy'|'standard_array'|'rolled'
     * @return array ['STR' => 15, 'DEX' => 14, ...]
     */
    public function generateAbilityScores(string $method, array $choices = []): array
    {
        return match($method) {
            'standard_array' => $this->applyStandardArray($choices), // [15,14,13,12,10,8]
            'point_buy' => $this->validatePointBuy($choices),         // 27 points, min 8, max 15
            'rolled' => $choices, // Assume client rolled and sent results
        };
    }

    /**
     * Calculate ability modifier from score
     *
     * @param int $score Ability score (1-30)
     * @return int Modifier (-5 to +10)
     */
    public function calculateModifier(int $score): int
    {
        return (int) floor(($score - 10) / 2);
    }

    /**
     * Apply racial bonuses to base scores
     */
    public function applyRacialBonuses(array $baseScores, Race $race): array
    {
        $modifiers = $race->modifiers()
            ->where('modifier_category', 'ability_score')
            ->get();

        foreach ($modifiers as $modifier) {
            $ability = AbilityScore::find($modifier->ability_score_id)->code;
            $baseScores[$ability] += (int) $modifier->value;
        }

        return $baseScores;
    }

    /**
     * Get final ability score (base + racial + ASI + feats + items)
     */
    public function getFinalScore(Character $character, AbilityScore $ability): int
    {
        $abilityScore = $character->abilityScores()
            ->where('ability_score_id', $ability->id)
            ->first();

        // If magic item sets score (Belt of Giant Strength = 19)
        if ($abilityScore->override_score) {
            return $abilityScore->override_score;
        }

        return $abilityScore->base_score
            + $abilityScore->racial_bonus
            + $abilityScore->asi_bonus
            + $abilityScore->feat_bonus
            + $abilityScore->other_bonus;
    }
}
```

### 2. Hit Points Calculation

```php
namespace App\Services;

class HitPointService
{
    /**
     * Calculate initial HP at level 1
     *
     * @param CharacterClass $class
     * @param int $conModifier
     * @return int Initial max HP
     */
    public function calculateInitialHP(CharacterClass $class, int $conModifier): int
    {
        return $class->hit_die + $conModifier;
    }

    /**
     * Calculate HP gained on level up
     *
     * @param int $hitDie d6, d8, d10, or d12
     * @param int $conModifier
     * @param string $method 'roll'|'average'
     * @param int|null $rolledValue If method='roll', the rolled value
     * @return int HP to add
     */
    public function calculateLevelUpHP(
        int $hitDie,
        int $conModifier,
        string $method = 'average',
        ?int $rolledValue = null
    ): int {
        $baseHP = match($method) {
            'roll' => $rolledValue ?? rand(1, $hitDie),
            'average' => (int) floor($hitDie / 2) + 1,
        };

        return max(1, $baseHP + $conModifier); // Minimum 1 HP
    }

    /**
     * Recalculate max HP (when CON changes or Tough feat taken)
     */
    public function recalculateMaxHP(Character $character): int
    {
        $conModifier = app(AbilityScoreService::class)
            ->calculateModifier(
                $character->getFinalAbilityScore('CON')
            );

        $maxHP = 0;

        foreach ($character->classes as $characterClass) {
            $hitDie = $characterClass->class->hit_die;
            $hpRolls = json_decode($characterClass->hit_points_rolled, true);

            // First level: max hit die + CON
            $maxHP += $hitDie + $conModifier;

            // Subsequent levels: rolled/average + CON
            foreach (array_slice($hpRolls, 1) as $roll) {
                $maxHP += $roll + $conModifier;
            }
        }

        // Tough feat: +2 HP per level
        if ($character->hasFeat('tough')) {
            $maxHP += $character->level * 2;
        }

        return $maxHP;
    }
}
```

### 3. Armor Class Calculation (Complex!)

```php
namespace App\Services;

class ArmorClassService
{
    /**
     * Calculate character's Armor Class
     *
     * This is EXTREMELY complex with many edge cases:
     * - No armor: 10 + DEX
     * - Light armor: armor.ac_base + DEX
     * - Medium armor: armor.ac_base + min(DEX, 2)
     * - Heavy armor: armor.ac_base
     * - Barbarian unarmored: 10 + DEX + CON
     * - Monk unarmored: 10 + DEX + WIS
     * - Mage Armor spell: 13 + DEX (replaces base AC)
     * - Shield: +2
     * - Magic bonuses: +1/+2/+3
     * - Fighting Style (Defense): +1 while wearing armor
     * - Many other edge cases...
     */
    public function calculateAC(Character $character): int
    {
        // Get equipped armor and shield
        $armor = $character->equippedArmor();
        $shield = $character->equippedShield();

        // Determine base AC
        $baseAC = $this->calculateBaseAC($character, $armor);

        // Add shield bonus
        $shieldBonus = $shield ? ($shield->armor_class ?? 2) : 0;

        // Add magic bonuses
        $magicBonus = $this->calculateMagicACBonus($character, $armor, $shield);

        // Add class feature bonuses (Fighting Style, etc.)
        $featureBonus = $this->calculateFeatureACBonus($character, $armor);

        return $baseAC + $shieldBonus + $magicBonus + $featureBonus;
    }

    private function calculateBaseAC(Character $character, ?Item $armor): int
    {
        // No armor equipped
        if (!$armor) {
            return $this->calculateUnarmoredAC($character);
        }

        $dexModifier = $character->getAbilityModifier('DEX');

        // Light armor: full DEX bonus
        if ($armor->item_type->code === 'LA') {
            return $armor->armor_class + $dexModifier;
        }

        // Medium armor: max +2 DEX bonus
        if ($armor->item_type->code === 'MA') {
            return $armor->armor_class + min($dexModifier, 2);
        }

        // Heavy armor: no DEX bonus
        if ($armor->item_type->code === 'HA') {
            return $armor->armor_class;
        }

        return 10 + $dexModifier; // Default
    }

    private function calculateUnarmoredAC(Character $character): int
    {
        $dexMod = $character->getAbilityModifier('DEX');

        // Barbarian: 10 + DEX + CON (if not wearing armor)
        if ($character->hasClassFeature('Unarmored Defense', 'Barbarian')) {
            $conMod = $character->getAbilityModifier('CON');
            return 10 + $dexMod + $conMod;
        }

        // Monk: 10 + DEX + WIS (if not wearing armor or shield)
        if ($character->hasClassFeature('Unarmored Defense', 'Monk') && !$character->equippedShield()) {
            $wisMod = $character->getAbilityModifier('WIS');
            return 10 + $dexMod + $wisMod;
        }

        // Mage Armor spell: 13 + DEX
        if ($character->hasActiveSpell('mage-armor')) {
            return 13 + $dexMod;
        }

        // Default: 10 + DEX
        return 10 + $dexMod;
    }
}
```

### 4. Proficiency Bonus Calculation (Simple)

```php
namespace App\Services;

class ProficiencyService
{
    /**
     * Calculate proficiency bonus by total character level
     *
     * @param int $level Total character level (1-20)
     * @return int Proficiency bonus (+2 to +6)
     */
    public function calculateProficiencyBonus(int $level): int
    {
        return (int) ceil($level / 4) + 1;
    }

    /**
     * Levels 1-4:   +2
     * Levels 5-8:   +3
     * Levels 9-12:  +4
     * Levels 13-16: +5
     * Levels 17-20: +6
     */
}
```

### 5. Spell Save DC & Attack Bonus

```php
namespace App\Services;

class SpellcastingService
{
    /**
     * Calculate spell save DC
     *
     * Formula: 8 + proficiency bonus + spellcasting ability modifier
     */
    public function calculateSpellSaveDC(Character $character, CharacterClass $class): int
    {
        $spellAbility = $class->class->spellcastingAbility;
        $abilityMod = $character->getAbilityModifier($spellAbility->code);
        $profBonus = app(ProficiencyService::class)
            ->calculateProficiencyBonus($character->level);

        return 8 + $profBonus + $abilityMod;
    }

    /**
     * Calculate spell attack bonus
     *
     * Formula: proficiency bonus + spellcasting ability modifier
     */
    public function calculateSpellAttackBonus(Character $character, CharacterClass $class): int
    {
        $spellAbility = $class->class->spellcastingAbility;
        $abilityMod = $character->getAbilityModifier($spellAbility->code);
        $profBonus = app(ProficiencyService::class)
            ->calculateProficiencyBonus($character->level);

        return $profBonus + $abilityMod;
    }
}
```

### 6. Multiclass Spell Slots (Very Complex)

```php
namespace App\Services;

class MulticlassSpellSlotService
{
    /**
     * Calculate multiclass spell slots
     *
     * Rules:
     * - Add FULL caster levels (Wizard, Sorcerer, Cleric, Druid, Bard)
     * - Add 1/2 Paladin/Ranger levels (rounded down)
     * - Add 1/3 Eldritch Knight/Arcane Trickster levels (rounded down)
     * - Add 1/2 Artificer levels (rounded up)
     * - Warlock Pact Magic is SEPARATE (doesn't combine)
     * - Consult Multiclass Spellcaster table
     *
     * @return array ['1st' => 4, '2nd' => 3, '3rd' => 2, ...]
     */
    public function calculateSpellSlots(Character $character): array
    {
        $casterLevel = 0;

        foreach ($character->classes as $charClass) {
            $class = $charClass->class;
            $level = $charClass->class_level;

            // Skip non-casters
            if (!$class->spellcasting_ability_id) {
                continue;
            }

            // Full casters
            if (in_array($class->slug, ['wizard', 'sorcerer', 'cleric', 'druid', 'bard'])) {
                $casterLevel += $level;
            }

            // Half casters (Paladin, Ranger)
            elseif (in_array($class->slug, ['paladin', 'ranger'])) {
                $casterLevel += (int) floor($level / 2);
            }

            // Third casters (Eldritch Knight, Arcane Trickster)
            elseif (in_array($class->slug, ['fighter-eldritch-knight', 'rogue-arcane-trickster'])) {
                $casterLevel += (int) floor($level / 3);
            }

            // Artificer (half caster, rounds up)
            elseif ($class->slug === 'artificer') {
                $casterLevel += (int) ceil($level / 2);
            }
        }

        return $this->getSpellSlotsByLevel($casterLevel);
    }

    private function getSpellSlotsByLevel(int $level): array
    {
        // Multiclass Spellcaster Table (PHB p.164)
        $slotTable = [
            1 => [2, 0, 0, 0, 0, 0, 0, 0, 0],
            2 => [3, 0, 0, 0, 0, 0, 0, 0, 0],
            3 => [4, 2, 0, 0, 0, 0, 0, 0, 0],
            4 => [4, 3, 0, 0, 0, 0, 0, 0, 0],
            5 => [4, 3, 2, 0, 0, 0, 0, 0, 0],
            6 => [4, 3, 3, 0, 0, 0, 0, 0, 0],
            7 => [4, 3, 3, 1, 0, 0, 0, 0, 0],
            8 => [4, 3, 3, 2, 0, 0, 0, 0, 0],
            9 => [4, 3, 3, 3, 1, 0, 0, 0, 0],
            10 => [4, 3, 3, 3, 2, 0, 0, 0, 0],
            11 => [4, 3, 3, 3, 2, 1, 0, 0, 0],
            12 => [4, 3, 3, 3, 2, 1, 0, 0, 0],
            13 => [4, 3, 3, 3, 2, 1, 1, 0, 0],
            14 => [4, 3, 3, 3, 2, 1, 1, 0, 0],
            15 => [4, 3, 3, 3, 2, 1, 1, 1, 0],
            16 => [4, 3, 3, 3, 2, 1, 1, 1, 0],
            17 => [4, 3, 3, 3, 2, 1, 1, 1, 1],
            18 => [4, 3, 3, 3, 3, 1, 1, 1, 1],
            19 => [4, 3, 3, 3, 3, 2, 1, 1, 1],
            20 => [4, 3, 3, 3, 3, 2, 2, 1, 1],
        ];

        $slots = $slotTable[min($level, 20)] ?? [0, 0, 0, 0, 0, 0, 0, 0, 0];

        return [
            '1st' => $slots[0],
            '2nd' => $slots[1],
            '3rd' => $slots[2],
            '4th' => $slots[3],
            '5th' => $slots[4],
            '6th' => $slots[5],
            '7th' => $slots[6],
            '8th' => $slots[7],
            '9th' => $slots[8],
        ];
    }
}
```

---

## API Endpoints Required

### Character Management (CRUD)

```php
// CREATE
POST /api/v1/characters
Body: {
    "name": "Thorin Ironforge",
    "race_id": 15,
    "background_id": 3,
    "alignment": "LG",
    "ability_scores": {
        "generation_method": "standard_array",
        "assignments": {"STR": 15, "DEX": 12, "CON": 14, "INT": 8, "WIS": 10, "CHA": 13}
    }
}

// READ
GET /api/v1/characters                 // List user's characters
GET /api/v1/characters/{id}            // Full character sheet
GET /api/v1/characters/{id}/stats      // Calculated stats (AC, HP, saves, skills, spell DC)

// UPDATE
PATCH /api/v1/characters/{id}
Body: {
    "name": "Thorin Oakenshield",
    "personality_traits": "Brave and loyal",
    "backstory": "Exiled dwarf prince..."
}

// DELETE
DELETE /api/v1/characters/{id}
```

### Level Progression

```php
// LEVEL UP
POST /api/v1/characters/{id}/level-up
Body: {
    "class_id": 12,              // Which class to level (multiclass support)
    "hp_roll": 8,                // Or "average": true
    "asi_or_feat": "asi",        // At ASI levels
    "asi_choices": {             // If asi_or_feat = "asi"
        "STR": +1,
        "DEX": +1
    },
    "feat_id": 42,               // If asi_or_feat = "feat"
    "spell_choices": [15, 23],   // Spells learned this level
    "subclass_id": 45            // If at subclass selection level
}

// LEVEL HISTORY
GET /api/v1/characters/{id}/level-history
Response: [
    {"level": 1, "class": "Fighter", "hp_rolled": 10, "choices": {...}},
    {"level": 2, "class": "Fighter", "hp_rolled": 7, "choices": {...}},
    ...
]
```

### Spell Management

```php
// GET SPELLS
GET /api/v1/characters/{id}/spells
Response: {
    "known": [...],      // Spells character knows
    "prepared": [...],   // Spells currently prepared (for prepared casters)
    "always_prepared": [...], // Domain/oath/racial spells
    "spell_slots": {
        "1st": {"max": 4, "used": 2},
        "2nd": {"max": 3, "used": 1},
        ...
    }
}

// AVAILABLE SPELLS (What CAN be learned?)
GET /api/v1/characters/{id}/available-spells?class_id=12
Response: [
    // Filtered by:
    // - Character's class spell list
    // - Character level (max spell level)
    // - Not already known
    // - Spell slot availability
]

// LEARN SPELL
POST /api/v1/characters/{id}/spells
Body: {
    "spell_id": 42,
    "source_class_id": 12  // Which class is learning this spell?
}

// FORGET SPELL (known casters only, on level up)
DELETE /api/v1/characters/{id}/spells/{spellId}

// PREPARE SPELLS (prepared casters: Cleric, Druid, Wizard)
POST /api/v1/characters/{id}/prepare-spells
Body: {
    "spell_ids": [15, 23, 42, 67, 89, 103, 156, 201]  // Up to (WIS/INT mod + level) spells
}
```

### Inventory Management

```php
// GET INVENTORY
GET /api/v1/characters/{id}/inventory
Response: {
    "equipped": [...],
    "backpack": [...],
    "attuned": [...],    // Max 3
    "currency": {"cp": 0, "sp": 0, "ep": 0, "gp": 50, "pp": 0}
}

// ADD ITEM
POST /api/v1/characters/{id}/inventory
Body: {
    "item_id": 42,
    "quantity": 1,
    "notes": "Found in dragon hoard"
}

// EQUIP/UNEQUIP/ATTUNE
PATCH /api/v1/characters/{id}/inventory/{characterItemId}
Body: {
    "is_equipped": true,
    "is_attuned": true
}

// REMOVE ITEM
DELETE /api/v1/characters/{id}/inventory/{characterItemId}
```

### Feat Management

```php
// GET FEATS
GET /api/v1/characters/{id}/feats
Response: [
    {"feat": {...}, "taken_at_level": 4, "choices": {...}},
    {"feat": {...}, "taken_at_level": 8, "choices": {...}}
]

// AVAILABLE FEATS (What qualifies for?)
GET /api/v1/characters/{id}/available-feats
Response: [
    // Filtered by:
    // - Prerequisite ability scores met
    // - Prerequisite race met
    // - Prerequisite proficiencies met
    // - Not already taken
]

// TAKE FEAT (during level up at ASI level)
POST /api/v1/characters/{id}/feats
Body: {
    "feat_id": 42,
    "class_id": 12,          // Which class level grants this ASI?
    "taken_at_level": 4,
    "choices": {...}         // For feats with choices
}
```

### Combat & Rest

```php
// UPDATE HP
PATCH /api/v1/characters/{id}/hp
Body: {
    "current_hp": 25,
    "temp_hp": 5
}

// SHORT REST
POST /api/v1/characters/{id}/short-rest
Body: {
    "hit_dice_spent": {"d8": 2}  // Spend 2d8 hit dice to recover HP
}

// LONG REST
POST /api/v1/characters/{id}/long-rest
// Restores:
// - HP to max
// - All spell slots
// - Hit dice (up to half total, rounded down)
// - Class features (rage uses, ki points, etc.)

// DEATH SAVE
POST /api/v1/characters/{id}/death-save
Body: {
    "roll": 15,              // D20 roll result
    "result": "success"      // success, failure, or critical
}
```

### Character Builder Helpers

```php
// VALIDATE MULTICLASS
POST /api/v1/character-builder/validate-multiclass
Body: {
    "character_id": 123,
    "new_class_id": 45
}
Response: {
    "valid": true,
    "prerequisites_met": {
        "STR": {"required": 13, "actual": 15, "met": true},
        "CHA": {"required": 13, "actual": 12, "met": false}
    }
}

// GET STARTING EQUIPMENT CHOICES
GET /api/v1/character-builder/starting-equipment/{classId}
Response: {
    "choices": [
        {
            "group": "A",
            "options": [
                {"items": [{"id": 15, "name": "Chain Mail"}]},
                {"items": [{"id": 23, "name": "Leather Armor"}, {"id": 67, "name": "Longbow"}]}
            ]
        },
        ...
    ]
}
```

---

## Implementation Phases

### Phase 1: Core Character CRUD (12-16 hours)

**Deliverables:**
- `characters` table migration
- `character_ability_scores` table
- `Character` model with relationships
- `CharacterService` for CRUD operations
- `AbilityScoreService` for score generation (point buy, standard array, rolling)
- API endpoints: `POST /characters`, `GET /characters/{id}`, `PATCH /characters/{id}`, `DELETE /characters/{id}`
- Form Requests: `CharacterStoreRequest`, `CharacterUpdateRequest`
- Resources: `CharacterResource`, `CharacterSheetResource`
- Tests: Character CRUD tests (20+ tests)

**Success Criteria:**
- Can create character with name, race, background
- Can generate ability scores via point buy/standard array
- Can apply racial bonuses automatically
- Can retrieve character with calculated ability modifiers

---

### Phase 2: Single-Class Characters (14-18 hours)

**Deliverables:**
- `character_classes` table
- `character_proficiencies` table
- `HitPointService` for HP calculation
- `ProficiencyService` for proficiency bonus
- Level-up API: `POST /characters/{id}/level-up`
- Proficiency tracking from race/class/background
- HP calculation (initial + level-up, rolled or average)
- Tests: Level progression tests (25+ tests)

**Success Criteria:**
- Can create level 1 Fighter
- Can level up to level 5
- HP calculated correctly (initial max die + CON, subsequent rolls/average + CON)
- Proficiencies aggregated from race + class + background
- Proficiency bonus increases at levels 5, 9, 13, 17

---

### Phase 3: Spell Management (12-16 hours)

**Deliverables:**
- `character_spells` table
- `SpellcastingService` for spell DC/attack bonus
- Spell learning API: `POST /characters/{id}/spells`
- Spell preparation API: `POST /characters/{id}/prepare-spells`
- Available spells API: `GET /characters/{id}/available-spells`
- Spell slot tracking
- Tests: Spell selection tests (30+ tests)

**Success Criteria:**
- Wizard can learn spells from spell list
- Cleric can prepare spells (limited by WIS + level)
- Spell save DC calculated correctly (8 + prof + ability mod)
- Spell slots match class level progression
- Can't learn spells above character level

---

### Phase 4: Inventory & Equipment (12-16 hours)

**Deliverables:**
- `character_items` table
- `character_currencies` table
- `ArmorClassService` for AC calculation
- Inventory API: `GET/POST/PATCH/DELETE /characters/{id}/inventory`
- Attunement tracking (max 3 items)
- Starting equipment from class/background
- Tests: Inventory tests (25+ tests)

**Success Criteria:**
- Can add/remove items from inventory
- Can equip armor and weapons
- AC calculated correctly (light/medium/heavy armor + DEX + shield + magic)
- Attunement limit enforced (max 3)
- Currency tracking

---

### Phase 5: Feats & ASI (8-12 hours)

**Deliverables:**
- `character_feats` table
- ASI selection during level-up
- Feat selection during level-up
- Available feats API: `GET /characters/{id}/available-feats`
- Prerequisite validation
- Tests: Feat selection tests (20+ tests)

**Success Criteria:**
- At level 4, can choose ASI (+1 STR, +1 DEX) or feat
- Prerequisite checking (ability scores, race, proficiency)
- Feat bonuses applied to character stats
- ASI bonuses recalculate ability modifiers, HP, spell DC, etc.

---

### Phase 6: Multiclassing (12-16 hours)

**Deliverables:**
- Multiclass prerequisite checking
- Multiple `character_classes` records
- `MulticlassSpellSlotService`
- Multiclass validation API
- Tests: Multiclass tests (30+ tests)

**Success Criteria:**
- Can multiclass Fighter 5 / Rogue 3
- Multiclass prerequisites enforced (STR 13, DEX 13, etc.)
- Spell slots calculated correctly for multiclass spellcasters
- Hit dice tracked per class (5d10 + 3d8)
- Proficiency bonus based on total level (not individual classes)

---

### Phase 7: Combat Tracking (6-8 hours)

**Deliverables:**
- HP update API: `PATCH /characters/{id}/hp`
- Short rest API: `POST /characters/{id}/short-rest`
- Long rest API: `POST /characters/{id}/long-rest`
- Death save API: `POST /characters/{id}/death-save`
- Condition tracking
- Tests: Combat tests (15+ tests)

**Success Criteria:**
- Can update current HP, temp HP
- Short rest spends hit dice to recover HP
- Long rest restores HP, spell slots, hit dice (half)
- Death saves tracked (3 successes = stable, 3 failures = dead)

---

### Phase 8: Polish & Export (8-12 hours)

**Deliverables:**
- Character sheet JSON export
- Character sheet PDF export (optional)
- Character sharing (read-only tokens)
- Level history/audit trail
- Tests: Export tests (10+ tests)

**Success Criteria:**
- Can export character to JSON
- Can share character via token (read-only)
- Level history shows all level-up decisions

---

## Development Effort Estimates

### MVP (Phases 1-4): 50-66 hours
**Features:**
- Single-class characters, levels 1-5
- Ability scores, HP, proficiency tracking
- Spell management (learn, prepare, cast)
- Inventory and AC calculation

**Timeline:** 6-8 weeks at 8h/week

---

### Full Character Builder (Phases 1-7): 76-102 hours
**Features:** Everything in MVP plus:
- Feats and ASI
- Multiclassing
- Combat tracking (HP, death saves, rest)

**Timeline:** 10-13 weeks at 8h/week

---

### Complete System (Phases 1-8): 84-114 hours
**Features:** Everything above plus:
- Character export (JSON, PDF)
- Character sharing
- Level history audit trail

**Timeline:** 11-14 weeks at 8h/week

---

### Revised Estimate (With ASI Already Done)

**ASI tracking already complete** → Save 4-6 hours

**Adjusted Totals:**
- MVP: **46-60 hours**
- Full: **72-96 hours**
- Complete: **79-108 hours**

---

## Quick Wins Before Starting

### Task 1: Add Subclass Selection Level (1-2 hours)

```bash
# Create migration
php artisan make:migration add_subclass_selection_level_to_classes_table

# Populate field via Eloquent query
php artisan tinker
>>> App\Models\CharacterClass::whereHas('features', function($q) {
...     $q->where('feature_name', 'Martial Archetype');
... })->update(['subclass_selection_level' => 3]);
```

**Test:**
```php
$fighter = CharacterClass::where('slug', 'fighter')->first();
$this->assertEquals(3, $fighter->subclass_selection_level);
```

---

### Task 2: Investigate Multiclass Prerequisites (2-3 hours)

**Steps:**
1. Search XML for multiclass prerequisite tags
2. Check if already imported to `entity_prerequisites`
3. If missing, update `ClassImporter`
4. Write tests

**Example Investigation:**
```bash
grep -r "multiclass" import-files/class-*.xml
grep -r "prerequisite" import-files/class-*.xml
```

---

### Task 3: Create XP Advancement Lookup Table (30 minutes)

```php
// Migration
Schema::create('character_advancement', function (Blueprint $table) {
    $table->tinyInteger('level')->primary();
    $table->integer('xp_required');
    $table->tinyInteger('proficiency_bonus');
});

// Seeder
$levels = [
    1 => [0, 2], 2 => [300, 2], 3 => [900, 2], 4 => [2700, 2],
    5 => [6500, 3], 6 => [14000, 3], 7 => [23000, 3], 8 => [34000, 3],
    9 => [48000, 4], 10 => [64000, 4], 11 => [85000, 4], 12 => [100000, 4],
    13 => [120000, 5], 14 => [140000, 5], 15 => [165000, 5], 16 => [195000, 5],
    17 => [225000, 6], 18 => [265000, 6], 19 => [305000, 6], 20 => [355000, 6],
];

foreach ($levels as $level => [$xp, $prof]) {
    DB::table('character_advancement')->insert([
        'level' => $level,
        'xp_required' => $xp,
        'proficiency_bonus' => $prof,
    ]);
}
```

---

## Technical Recommendations

### 1. Use TDD Approach (Mandatory)

**Follow existing pattern:**
```php
// Feature test example:
public function test_can_create_character_with_race_and_class()
{
    $race = Race::factory()->create(['name' => 'Dwarf']);
    $class = CharacterClass::factory()->create(['name' => 'Fighter']);
    $background = Background::factory()->create(['name' => 'Soldier']);

    $response = $this->postJson('/api/v1/characters', [
        'name' => 'Thorin',
        'race_id' => $race->id,
        'class_id' => $class->id,
        'background_id' => $background->id,
        'ability_scores' => [
            'generation_method' => 'standard_array',
            'assignments' => ['STR' => 15, 'DEX' => 12, 'CON' => 14, 'INT' => 8, 'WIS' => 10, 'CHA' => 13],
        ],
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['data' => ['id', 'name', 'race', 'class', 'ability_scores']]);

    $this->assertDatabaseHas('characters', ['name' => 'Thorin', 'race_id' => $race->id]);
}
```

### 2. Leverage Existing Patterns

**Form Requests for Validation:**
```php
class CharacterStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'race_id' => 'required|exists:races,id',
            'class_id' => 'required|exists:classes,id',
            'background_id' => 'required|exists:backgrounds,id',
            'ability_scores.generation_method' => 'required|in:point_buy,standard_array,rolled',
            'ability_scores.assignments' => 'required|array',
            'ability_scores.assignments.STR' => 'required|integer|min:3|max:18',
            // ... other abilities
        ];
    }
}
```

**Services for Business Logic:**
```php
class CharacterService
{
    public function __construct(
        private AbilityScoreService $abilityService,
        private HitPointService $hpService,
        private ProficiencyService $profService,
    ) {}

    public function createCharacter(array $data): Character
    {
        // Create character
        // Apply racial bonuses
        // Calculate initial HP
        // Aggregate proficiencies
        return $character;
    }
}
```

**Resources for API Responses:**
```php
class CharacterResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'race' => new RaceResource($this->whenLoaded('race')),
            'classes' => CharacterClassResource::collection($this->whenLoaded('classes')),
            'ability_scores' => $this->abilityScores->mapWithKeys(fn($as) => [
                $as->ability->code => [
                    'score' => $this->getFinalScore($as->ability),
                    'modifier' => $this->getModifier($as->ability),
                ],
            ]),
            'stats' => [
                'hp' => ['current' => $this->current_hp, 'max' => $this->max_hp],
                'ac' => $this->armor_class,
                'proficiency_bonus' => $this->proficiency_bonus,
            ],
        ];
    }
}
```

### 3. Caching Strategy

**Cache calculated stats:**
```php
class Character extends Model
{
    public function getArmorClassAttribute(): int
    {
        return Cache::remember(
            "character.{$this->id}.ac",
            now()->addMinutes(60),
            fn() => app(ArmorClassService::class)->calculateAC($this)
        );
    }

    // Invalidate cache on equipment change
    protected static function boot()
    {
        parent::boot();

        static::updated(function($character) {
            Cache::forget("character.{$character->id}.ac");
        });
    }
}
```

### 4. Database Indexes

```php
// Characters table indexes
$table->index('user_id');
$table->index('race_id');
$table->index('background_id');
$table->index(['user_id', 'level']);

// Character classes table
$table->unique(['character_id', 'class_id']);
$table->index('character_id');

// Character spells table
$table->unique(['character_id', 'spell_id']);
$table->index(['character_id', 'is_prepared']);
```

### 5. API Versioning

All new endpoints under `/api/v1/characters/*` to match existing pattern.

---

## Priority Matrix

### 🔴 CRITICAL (Cannot build characters without)
1. Character persistence tables (characters, character_classes, character_ability_scores)
2. Ability score generation (point buy, standard array, rolling)
3. HP calculation (initial + level-up)
4. Proficiency bonus calculation
5. Character CRUD API endpoints

### 🟡 IMPORTANT (Playable but limited without)
1. Spell management (learn, prepare, cast)
2. Inventory management
3. AC calculation
4. Feat selection
5. Available spells/feats filtering

### 🟢 NICE-TO-HAVE (Enhancements)
1. Multiclassing
2. Combat tracking (death saves, conditions)
3. Character export (JSON, PDF)
4. Character sharing
5. Level history audit trail

---

## Success Metrics

### MVP Success Criteria
- ✅ Can create level 1 Fighter with race and background
- ✅ Ability scores calculated correctly (base + racial bonuses)
- ✅ HP calculated correctly (hit die + CON modifier)
- ✅ Proficiency bonus correct for character level
- ✅ Can level up to level 5
- ✅ Can learn and prepare spells (if spellcaster)
- ✅ Can manage inventory and equipment
- ✅ AC calculated correctly for equipped armor

### Full System Success Criteria
- ✅ Everything in MVP
- ✅ Can take feats or ASI at level 4
- ✅ Can multiclass (Fighter 5 / Rogue 3)
- ✅ Multiclass spell slots calculated correctly
- ✅ Can track HP, death saves, conditions
- ✅ Short/long rest mechanics work
- ✅ Character export to JSON
- ✅ All 1,489+ tests passing

---

## Conclusion

**Current State:** Excellent foundation with complete reference data
**Target State:** Full character management system with CRUD, leveling, spells, inventory
**Effort:** 79-108 hours for complete system (MVP in 46-60 hours)
**Risk:** Low - leveraging existing architecture patterns
**ROI:** High - transforms compendium API into interactive character builder

**Recommendation:** Start with MVP (Phases 1-4) to validate architecture, then expand to full system based on user feedback.

---

**Last Updated:** 2025-11-25
**Author:** Claude Code Analysis
**Status:** Ready for Implementation
