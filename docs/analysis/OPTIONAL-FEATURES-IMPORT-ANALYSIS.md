# Optional Features XML Import Analysis

**Date:** 2025-11-23
**Status:** Analysis Complete - Awaiting Implementation Decision
**Files Analyzed:** `optionalfeatures-phb.xml`, `optionalfeatures-tce.xml`, `optionalfeatures-xge.xml`
**Total Content:** ~106KB, 100+ optional features across 8 categories

---

## Executive Summary

The `optionalfeatures*.xml` files contain **character customization options** that players select as part of class progression (Warlock invocations, Fighter maneuvers, etc.). These files present **unique import challenges** due to:

1. **Inconsistent data structure** - Same content type uses both `<spell>` and `<feat>` XML elements
2. **Overloaded fields** - `<classes>` field serves as category label rather than class association
3. **Unstructured resource costs** - Ki points, sorcery points embedded in text rather than dedicated fields
4. **Prerequisite variability** - Requirements encoded in name, text, or prerequisite fields inconsistently
5. **Semantic complexity** - These are **selection-based progression options**, not standalone entities like spells or feats

**Recommendation:** Create new `OptionalFeature` model with strategy pattern importer. Estimated effort: 12-16 hours.

---

## File Inventory

| File | Size | Primary Sources | Key Content |
|------|------|-----------------|-------------|
| `optionalfeatures-phb.xml` | 47KB | Player's Handbook 2014 | Invocations (30), Elemental Disciplines (15), Maneuvers (16), Metamagic (8), Fighting Styles (6) |
| `optionalfeatures-tce.xml` | 32KB | Tasha's Cauldron of Everything | Artificer Infusions (15), Additional Invocations (7), Maneuvers (7), Metamagic (2), Fighting Styles (5), Runes (6) |
| `optionalfeatures-xge.xml` | 27KB | Xanathar's Guide to Everything | Additional Invocations (15), Arcane Shots (8) |

**Total Optional Features:** ~100 entries

---

## Content Category Breakdown

### 1. Warlock Eldritch Invocations (~52 total)

**Description:** Customizable abilities Warlocks select at levels 2, 5, 9, 12, 15, 18, 20
**XML Structure:** `<spell>` elements with `<classes>Eldritch Invocations</classes>`

**Example:**
```xml
<spell>
  <name>Invocation: Agonizing Blast</name>
  <level>0</level>
  <classes>Eldritch Invocations</classes>
  <text>Prerequisite: Eldritch Blast cantrip

When you cast eldritch blast, add your Charisma modifier to the damage it deals on a hit.

Source:	Player's Handbook (2014) p. 110</text>
</spell>
```

**Key Characteristics:**
- Often have prerequisites (level, pact boon, specific spells)
- Some grant at-will spell casting
- Passive bonuses or activated abilities
- Name prefix: `"Invocation: "`

**Import Challenges:**
- Spell-granting invocations need `entity_spells` relationships
- Prerequisites range from simple ("5th level") to complex ("15th level, Pact of the Chain")
- No structured field for granted spells - must parse from text

---

### 2. Monk Elemental Disciplines (15)

**Description:** Way of the Four Elements monks select disciplines that mimic spells using ki points
**XML Structure:** `<spell>` elements with complete spell metadata

**Example:**
```xml
<spell>
  <name>Elemental Discipline: Breath of Winter</name>
  <level>0</level>
  <school>EV</school>
  <time>1 action</time>
  <range>Self (60-foot cone)</range>
  <components>V, S, M (6 ki points)</components>
  <duration>Instantaneous</duration>
  <classes>Monk (Way of the Four Elements)</classes>
  <text>Prerequisite: 17th level Monk

You can spend 6 ki points to cast cone of cold.

Cone of Cold:
A blast of cold air erupts from your hands. Each creature in a 60-foot cone must make a Constitution saving throw. A creature takes 8d8 cold damage on a failed save, or half as much damage on a successful one.
	A creature killed by this spell becomes a frozen statue until it thaws.

Source:	Player's Handbook (2014) p. 81</text>
  <roll description="Cold Damage, 6 Ki Points">8d8</roll>
</spell>
```

**Key Characteristics:**
- Ki point cost in `<components>` field: `"M (6 ki points)"`
- Often replicate existing spells with ki point scaling
- Include full spell descriptions inline
- Name prefix: `"Elemental Discipline: "`
- Subclass specified: `"Monk (Way of the Four Elements)"`

**Import Challenges:**
- Resource cost (ki points) embedded in components text
- Spell replication relationship unclear (should we link to base spell?)
- Damage scaling formulas in text

---

### 3. Fighter Maneuvers (23)

**Description:** Battle Master fighters select maneuvers that enhance combat using superiority dice
**XML Structure:** `<spell>` elements (PHB), some duplicated as `<feat>` in XGE

**Example:**
```xml
<spell>
  <name>Maneuver: Commander's Strike</name>
  <level>0</level>
  <classes>Maneuver Options</classes>
  <text>When you take the Attack action on your turn, you can forgo one of your attacks and use a bonus action to direct one of your companions to strike. When you do so, choose a friendly creature who can see or hear you and expend one superiority die. That creature can immediately use its reaction to make one weapon attack, adding the superiority die to the attack's damage roll.

Source:	Player's Handbook (2014) p. 74</text>
</spell>
```

**Key Characteristics:**
- Use superiority dice (size varies by level: d6/d8/d10/d12)
- Activate during specific combat triggers
- No dedicated resource cost field
- Name prefix: `"Maneuver: "`
- Generic category: `"Maneuver Options"`

**Import Challenges:**
- No structured resource cost (superiority dice in text only)
- Activation triggers vary (bonus action, reaction, during attack, etc.)
- Die size scaling not in XML

---

### 4. Sorcerer Metamagic (10)

**Description:** Sorcerers select metamagic options at levels 3, 10, 17 to modify spell effects
**XML Structure:** `<spell>` elements with sorcery point costs in components

**Example:**
```xml
<spell>
  <name>Metamagic: Careful Spell</name>
  <level>0</level>
  <components>(1 sorcery point)</components>
  <classes>Metamagic Options</classes>
  <text>When you cast a spell that forces other creatures to make a saving throw, you can protect some of those creatures from the spell's full force. To do so, you spend 1 sorcery point and choose a number of those creatures up to your Charisma modifier (minimum of one creature). A chosen creature automatically succeeds on its saving throw against the spell.

Source:	Player's Handbook (2014) p. 102</text>
</spell>
```

**Key Characteristics:**
- Sorcery point cost in `<components>`: `"(1 sorcery point)"`
- Modify existing spells rather than create new effects
- Name prefix: `"Metamagic: "`
- Relatively simple prerequisites (just class level)

**Import Challenges:**
- Resource cost parsing from components field
- Relationship to spells is indirect (modifies casting, not tied to specific spells)
- Variable costs (Twinned Spell cost = spell level)

---

### 5. Fighting Styles (11)

**Description:** Fighters, Paladins, Rangers select one fighting style to specialize combat approach
**XML Structure:** `<feat>` elements with prerequisite and special fields

**Example:**
```xml
<feat>
  <name>Fighting Style: Archery</name>
  <prerequisite>Fighting Style Feature</prerequisite>
  <text>You gain a +2 bonus to attack rolls you make with ranged weapons.

Source:	Player's Handbook (2014) p. 72</text>
  <special>fighting style archery</special>
</feat>
```

**Key Characteristics:**
- Passive bonuses (no activation required)
- Clear mechanical effects (+2 to attacks, +1 AC, etc.)
- Name prefix: `"Fighting Style: "`
- Generic prerequisite: `"Fighting Style Feature"`
- Some are class-specific (Blessed Warrior for Paladins only)

**Import Challenges:**
- Minimal - cleanest structure of all categories
- Bonuses need to be imported as `modifiers` records
- Class restrictions sometimes in text ("Paladin" or "Ranger")

---

### 6. Artificer Infusions (15)

**Description:** Artificers infuse items with magical properties at levels 2, 6, 10, 14, 18
**XML Structure:** `<spell>` elements with enchantment school codes

**Example:**
```xml
<spell>
  <name>Infusion: Enhanced Weapon</name>
  <level>0</level>
  <school>EN</school>
  <time>1 action</time>
  <range>Touch</range>
  <components>S, M</components>
  <classes>Artificer Infusions</classes>
  <text>Item: A simple or martial weapon

This magic weapon grants a +1 bonus to attack and damage rolls made with it.
	The bonus increases to +2 when you reach 10th level in this class.

Source:	Eberron: Rising from the Last War p. 62,
		Tasha's Cauldron of Everything p. 21</text>
</spell>
```

**Key Characteristics:**
- Enchant specific item types ("A suit of armor", "A rod, staff, or wand")
- Level-scaling bonuses common
- Name prefix: `"Infusion: "`
- Some require attunement
- Level prerequisites in name or text

**Import Challenges:**
- Item type requirements need parsing from text
- Level-scaling effects not structured
- Attunement requirement buried in text
- Special infusion: "Replicate Magic Item" references DMG items

---

### 7. Rune Knight Runes (6)

**Description:** Rune Knight fighters inscribe giant runes on equipment for magical effects
**XML Structure:** `<spell>` elements with Fighter subclass

**Example:**
```xml
<spell>
  <name>Rune: Cloud Rune</name>
  <level>0</level>
  <classes>Fighter (Rune Knight)</classes>
  <text>This rune emulates the deceptive magic used by some cloud giants. While wearing or carrying an object inscribed with this rune, you have advantage on Dexterity (Sleight of Hand) checks and Charisma (Deception) checks.
	In addition, when you or a creature you can see within 30 feet of you is hit by an attack roll, you can use your reaction to invoke the rune and choose a different creature within 30 feet of you, other than the attacker. The chosen creature becomes the target of the attack, using the same roll. This magic can transfer the attack's effects regardless of the attack's range. Once you invoke this rune, you can't do so again until you finish a short or long rest.

Source:	Tasha's Cauldron of Everything p. 44</text>
</spell>
```

**Key Characteristics:**
- Passive benefits + activated abilities
- Recharge on short/long rest
- Name prefix: `"Rune: "`
- Tied to specific subclass: `"Fighter (Rune Knight)"`
- Some have level prerequisites

**Import Challenges:**
- Dual mode (passive + active) mechanics
- Recharge mechanics not structured
- Usage limits vary per rune

---

### 8. Arcane Archer Arcane Shots (8)

**Description:** Arcane Archer fighters imbue arrows with magical effects
**XML Structure:** `<spell>` elements with school codes and damage rolls

**Example:**
```xml
<spell>
  <name>Arcane Shot: Banishing Arrow</name>
  <level>0</level>
  <school>A</school>
  <time>part of the Attack action to fire a magic arrow</time>
  <classes>Fighter (Arcane Archer): Arcane Shot</classes>
  <text>You use abjuration magic to try to temporarily banish your target to a harmless location in the Feywild. The creature hit by the arrow must also succeed on a Charisma saving throw or be banished. While banished in this way, the target's speed is 0, and it is incapacitated. At the end of its next turn, the target reappears in the space it vacated or in the nearest unoccupied space if that space is occupied.
	After you reach 18th level in this class, a target also takes 2d6 force damage when the arrow hits it.

Source:	Xanathar's Guide to Everything p. 29</text>
  <roll description="Force Damage" level="18">2d6</roll>
</spell>
```

**Key Characteristics:**
- Damage scaling at 18th level
- Spell school associations
- Name prefix: `"Arcane Shot: "`
- Subclass with additional descriptor: `"Fighter (Arcane Archer): Arcane Shot"`
- Saving throw DCs often mentioned

**Import Challenges:**
- Level-based damage scaling (2d6 → 4d6 at level 18)
- Saving throw relationships
- Limited uses per short rest (not in XML)

---

## Critical Data Structure Issues

### Issue 1: Dual Element Types for Same Content

**Problem:** Optional features use both `<spell>` and `<feat>` XML elements inconsistently.

**Evidence:**
- PHB: Invocations as `<spell>` only
- XGE: Invocations as BOTH `<spell>` (lines 3-154) AND `<feat>` (lines 262-365)
- Fighting Styles: Always `<feat>` elements

**Example Duplication:**
```xml
<!-- As spell -->
<spell>
  <name>Invocation: Aspect of the Moon</name>
  <level>0</level>
  <classes>Eldritch Invocations</classes>
  <text>Prerequisite: Pact of the Tome feature...</text>
</spell>

<!-- As feat (same file, 250 lines later) -->
<feat>
  <name>Invocation: Aspect of the Moon</name>
  <prerequisite>Pact of the Tome</prerequisite>
  <text>You no longer need to sleep...</text>
</feat>
```

**Impact:**
- Parser must handle both element types
- Risk of duplicate imports if not deduplicated by name
- Inconsistent field availability (spells have `<level>`, feats have `<prerequisite>`)

**Resolution Strategy:**
- Treat element type as formatting artifact, not semantic distinction
- Use name prefix to determine category
- Deduplicate by slug during import
- Prefer `<spell>` version when duplicates exist (has more metadata)

---

### Issue 2: Overloaded `<classes>` Field

**Problem:** The `<classes>` field serves as category label rather than class association like in spell imports.

**Current Spell Import Usage:**
```xml
<spell>
  <name>Fireball</name>
  <classes>Sorcerer, Wizard</classes>  <!-- Which classes can cast this -->
</spell>
```

**Optional Features Usage:**
```xml
<spell>
  <name>Invocation: Agonizing Blast</name>
  <classes>Eldritch Invocations</classes>  <!-- Category, not a class -->
</spell>

<spell>
  <name>Elemental Discipline: Breath of Winter</name>
  <classes>Monk (Way of the Four Elements)</classes>  <!-- Parent class + subclass -->
</spell>

<spell>
  <name>Maneuver: Riposte</name>
  <classes>Maneuver Options</classes>  <!-- Generic category -->
</spell>
```

**Patterns Observed:**

| Pattern | Example | Meaning |
|---------|---------|---------|
| Category Label | `"Eldritch Invocations"` | Feature type (all Warlocks) |
| Category Label | `"Maneuver Options"` | Feature type (Battle Masters) |
| Category Label | `"Metamagic Options"` | Feature type (all Sorcerers) |
| Parent Class Only | N/A | Not used |
| Parent + Subclass | `"Monk (Way of the Four Elements)"` | Specific subclass only |
| Parent + Subclass + Descriptor | `"Fighter (Arcane Archer): Arcane Shot"` | Subclass with feature group |

**Impact:**
- Cannot reuse existing `ImportsClassAssociations` trait without modification
- Need new parser method to extract parent class vs. category
- Some features available to multiple classes (Fighting Styles → Fighter, Paladin, Ranger)

**Resolution Strategy:**
```php
// New parser methods needed
public function parseCategoryFromClasses(string $classes): string
{
    return match(true) {
        str_contains($classes, 'Invocation') => 'invocation',
        str_contains($classes, 'Maneuver') => 'maneuver',
        str_contains($classes, 'Metamagic') => 'metamagic',
        str_contains($classes, 'Infusion') => 'infusion',
        str_contains($classes, 'Rune Knight') => 'rune',
        str_contains($classes, 'Arcane Shot') => 'arcane_shot',
        str_contains($classes, 'Elemental Discipline') => 'elemental_discipline',
        default => 'unknown',
    };
}

public function parseParentClass(string $classes): ?string
{
    // "Monk (Way of the Four Elements)" → "Monk"
    if (preg_match('/^([A-Z][a-z]+)\s*\(/', $classes, $matches)) {
        return $matches[1];
    }
    return null;
}

public function parseSubclass(string $classes): ?string
{
    // "Monk (Way of the Four Elements)" → "Way of the Four Elements"
    if (preg_match('/\(([^)]+)\)/', $classes, $matches)) {
        return trim(explode(':', $matches[1])[0]);
    }
    return null;
}
```

---

### Issue 3: Unstructured Resource Costs

**Problem:** Resource costs (ki points, sorcery points, superiority dice) embedded in text fields rather than dedicated structured data.

**Patterns Found:**

| Resource Type | Encoding Location | Example |
|---------------|-------------------|---------|
| Ki Points | `<components>` | `"V, S, M (6 ki points)"` |
| Ki Points | `<components>` | `"(1 ki point)"` |
| Sorcery Points | `<components>` | `"(1 sorcery point)"` |
| Sorcery Points | `<components>` | `"(3 sorcery points)"` |
| Superiority Dice | Text only | "expend one superiority die" |

**Examples:**
```xml
<!-- Ki points in components -->
<spell>
  <name>Elemental Discipline: Breath of Winter</name>
  <components>V, S, M (6 ki points)</components>
</spell>

<!-- Sorcery points in components -->
<spell>
  <name>Metamagic: Careful Spell</name>
  <components>(1 sorcery point)</components>
</spell>

<!-- Superiority dice only in text -->
<spell>
  <name>Maneuver: Commander's Strike</name>
  <text>...expend one superiority die...</text>
</spell>
```

**Impact:**
- Need regex parsing to extract resource costs
- Variable costs (e.g., Twinned Spell = spell level) require formula storage
- Superiority dice have no structured representation
- Cannot easily query "all features costing ≤2 ki points"

**Resolution Strategy:**
```php
// New parser method
public function parseResourceCost(string $components): ?array
{
    // "(6 ki points)" → ["type" => "ki_points", "amount" => 6]
    if (preg_match('/\((\d+)\s+ki\s+points?\)/i', $components, $matches)) {
        return ['type' => 'ki_points', 'amount' => (int)$matches[1]];
    }

    // "(1 sorcery point)" → ["type" => "sorcery_points", "amount" => 1]
    if (preg_match('/\((\d+)\s+sorcery\s+points?\)/i', $components, $matches)) {
        return ['type' => 'sorcery_points', 'amount' => (int)$matches[1]];
    }

    return null;
}

// Store as JSON in database
$optionalFeature->resource_cost = json_encode([
    'type' => 'ki_points',
    'amount' => 6,
    'formula' => null,  // For variable costs like "spell level"
]);
```

---

### Issue 4: Inconsistent Prerequisite Encoding

**Problem:** Prerequisites appear in multiple locations with varying formats.

**Encoding Locations:**

1. **In `<text>` field (most common):**
```xml
<text>Prerequisite: 14th level Artificer

Item: A suit of armor (requires attunement)...</text>
```

2. **In name (some PHB infusions):**
```xml
<name>Infusion: Boots Of The Winding Path (Level 6)</name>
```

3. **In `<prerequisite>` field (feats only):**
```xml
<feat>
  <name>Fighting Style: Archery</name>
  <prerequisite>Fighting Style Feature</prerequisite>
</feat>
```

**Prerequisite Complexity:**

| Complexity | Example | Parsing Challenge |
|------------|---------|-------------------|
| Simple Level | `"Prerequisite: 5th level Warlock"` | Extract number + class |
| Simple Feature | `"Prerequisite: Pact of the Chain"` | Match feature name |
| Simple Spell | `"Prerequisite: Eldritch Blast cantrip"` | Match spell name |
| Compound (AND) | `"Prerequisite: 15th level, Pact of the Chain"` | Split on comma |
| Compound (OR) | `"Prerequisite: hex spell or a warlock feature that curses"` | Multiple options |
| Generic | `"Prerequisite: Fighting Style Feature"` | Class agnostic |

**Impact:**
- Must scan multiple fields to find prerequisites
- Cannot easily filter by "available at level X"
- Compound prerequisites require multi-record relationships
- OR prerequisites are ambiguous (how to represent in DB?)

**Resolution Strategy:**
```php
// Leverage existing ImportsPrerequisites trait with enhancements
public function extractPrerequisites(string $text, ?string $name = null): array
{
    $prerequisites = [];

    // Check text field first
    if (preg_match('/Prerequisite:\s*(.+?)(?:\n\n|\n[A-Z]|$)/s', $text, $matches)) {
        $prereqText = trim($matches[1]);

        // Level requirement: "5th level Warlock"
        if (preg_match('/(\d+)(?:st|nd|rd|th)\s+level(?:\s+(\w+))?/i', $prereqText, $m)) {
            $prerequisites[] = [
                'type' => 'level',
                'value' => (int)$m[1],
                'class' => $m[2] ?? null,
            ];
        }

        // Feature requirement: "Pact of the Chain"
        if (preg_match('/Pact of the (\w+)/i', $prereqText, $m)) {
            $prerequisites[] = [
                'type' => 'class_feature',
                'value' => 'Pact of the ' . $m[1],
            ];
        }

        // Spell requirement: "Eldritch Blast cantrip"
        if (preg_match('/([a-z\s]+)\s+(cantrip|spell)/i', $prereqText, $m)) {
            $prerequisites[] = [
                'type' => 'spell',
                'value' => trim($m[1]),
            ];
        }
    }

    // Check name field for level
    if ($name && preg_match('/\(Level (\d+)\)/i', $name, $matches)) {
        $prerequisites[] = [
            'type' => 'level',
            'value' => (int)$matches[1],
            'class' => null,
        ];
    }

    return $prerequisites;
}
```

**Database Representation:**
```php
// Reuse existing entity_prerequisites table
EntityPrerequisite::create([
    'entity_type' => 'optional_feature',
    'entity_id' => $optionalFeature->id,
    'prerequisite_type' => 'level',
    'prerequisite_id' => null,
    'value' => 5,
    'description' => '5th level Warlock',
]);

EntityPrerequisite::create([
    'entity_type' => 'optional_feature',
    'entity_id' => $optionalFeature->id,
    'prerequisite_type' => 'class_feature',
    'prerequisite_id' => null,  // Feature lookup not implemented
    'value' => null,
    'description' => 'Pact of the Chain',
]);
```

---

### Issue 5: Spell-Granting Features vs. Passive Abilities

**Problem:** Some optional features grant spell casting, others provide passive bonuses - fundamentally different mechanics.

**Spell-Granting Examples:**
```xml
<!-- Invocation: Armor of Shadows -->
<text>You can cast mage armor on yourself at will, without expending a spell slot or material components.</text>

<!-- Invocation: Eldritch Sight -->
<text>You can cast detect magic at will, without expending a spell slot.</text>

<!-- Elemental Discipline: Clench of the North Wind -->
<text>You can spend 3 ki points to cast hold person.</text>
```

**Passive Ability Examples:**
```xml
<!-- Fighting Style: Archery -->
<text>You gain a +2 bonus to attack rolls you make with ranged weapons.</text>

<!-- Invocation: Devil's Sight -->
<text>You can see normally in darkness, both magical and nonmagical, to a distance of 120 feet.</text>

<!-- Rune: Stone Rune -->
<text>While wearing or carrying an object inscribed with this rune, you have advantage on Wisdom (Insight) checks, and you have darkvision out to a range of 120 feet.</text>
```

**Mixed Mode Examples (Passive + Activated):**
```xml
<!-- Rune: Cloud Rune -->
<text>This rune emulates the deceptive magic used by some cloud giants. While wearing or carrying an object inscribed with this rune, you have advantage on Dexterity (Sleight of Hand) checks and Charisma (Deception) checks.
	In addition, when you or a creature you can see within 30 feet of you is hit by an attack roll, you can use your reaction to invoke the rune and choose a different creature within 30 feet of you, other than the attacker.</text>
```

**Categorization:**

| Category | Spell-Granting? | Passive Bonuses? | Activated Abilities? |
|----------|-----------------|------------------|----------------------|
| Invocations | ~40% | ~30% | ~30% |
| Elemental Disciplines | 80% (ki-fueled spells) | 20% | 0% |
| Maneuvers | 0% | 0% | 100% |
| Metamagic | 0% (modifies spells) | 0% | 100% |
| Fighting Styles | 0% | 100% | 0% |
| Infusions | 0% | 100% (enchantments) | 0% |
| Runes | 0% | 50% | 50% |
| Arcane Shots | 0% | 0% | 100% |

**Impact:**
- Spell-granting features need `entity_spells` relationships
- Passive bonuses need `modifiers` records
- Mixed mode features need BOTH relationships
- Query patterns differ: "features that grant spells" vs. "features that give +2 AC"

**Resolution Strategy:**
```php
// Flag in database
$optionalFeature->grants_spells = true;   // If "cast X spell" in text
$optionalFeature->is_passive = false;      // If requires activation
$optionalFeature->has_resource_cost = true; // If uses ki/sorcery/superiority

// Relationships
$optionalFeature->spells();     // Entity spells granted
$optionalFeature->modifiers();  // Passive bonuses

// Import logic
if (preg_match('/cast\s+([a-z\s]+)\s+(?:at will|once|spell)/i', $text, $matches)) {
    $spellName = trim($matches[1]);
    $this->importEntitySpell($optionalFeature, $spellName, [
        'frequency' => $this->parseSpellFrequency($text),  // "at will", "once per long rest"
        'resource_cost' => $this->parseResourceCost($components),
    ]);
}

if (preg_match('/gain\s+a\s+\+(\d+)\s+bonus\s+to\s+(.+)/i', $text, $matches)) {
    $this->importModifier($optionalFeature, [
        'modifier_type' => $this->parseModifierType($matches[2]),  // "attack rolls", "AC"
        'value' => '+' . $matches[1],
    ]);
}
```

---

## Comparison with Existing Infrastructure

### ✅ Reusable Components

We have strong existing infrastructure that can be leveraged:

#### 1. Prerequisites System ✅
**File:** `app/Services/Importers/Traits/ImportsPrerequisites.php`

**Current Capabilities:**
- Import polymorphic prerequisites
- Handle entity relationships
- Store prerequisite descriptions

**Enhancements Needed:**
- Multi-field scanning (text + name + prerequisite)
- Compound prerequisite splitting ("level AND feature")
- OR prerequisite representation

**Usage:**
```php
// Existing trait can handle:
$this->importPrerequisites($xml, 'optional_feature', $optionalFeature->id);

// Would create:
EntityPrerequisite::create([
    'entity_type' => 'optional_feature',
    'entity_id' => 123,
    'prerequisite_type' => 'level',
    'value' => 5,
    'description' => '5th level Warlock',
]);
```

---

#### 2. Modifiers System ✅
**File:** `app/Models/Modifier.php` (polymorphic)

**Current Capabilities:**
- Track bonuses to AC, attack rolls, damage, ability scores
- Polymorphic entity relationships
- Modifier categories (we added these for AC system)

**Perfect For:**
- Fighting Styles (+2 ranged attack, +1 AC)
- Passive invocation bonuses
- Rune passive effects

**Usage:**
```php
// Fighting Style: Archery
Modifier::create([
    'entity_type' => 'optional_feature',
    'entity_id' => $optionalFeature->id,
    'modifier_type' => 'attack_rolls',
    'modifier_category' => 'ranged_weapons',
    'value' => '+2',
]);

// Fighting Style: Defense
Modifier::create([
    'entity_type' => 'optional_feature',
    'entity_id' => $optionalFeature->id,
    'modifier_type' => 'armor_class',
    'modifier_category' => 'armor',
    'value' => '+1',
]);
```

---

#### 3. Entity Spells System ✅
**File:** `app/Services/Importers/Traits/ImportsEntitySpells.php`

**Current Capabilities:**
- Link entities to spells they can cast
- Case-insensitive spell lookup
- Flexible pivot data (frequency, notes, etc.)
- Already used for races, classes, monsters

**Perfect For:**
- Invocations that grant spells (Armor of Shadows → *mage armor*)
- Elemental Disciplines (Breath of Winter → *cone of cold*)

**Usage:**
```php
// Invocation: Armor of Shadows
$this->importEntitySpell(
    entityType: 'optional_feature',
    entityId: $optionalFeature->id,
    spellName: 'mage armor',
    pivotData: [
        'frequency' => 'at_will',
        'resource_cost' => null,
        'notes' => 'No spell slot or material components required',
    ]
);

// Elemental Discipline: Breath of Winter
$this->importEntitySpell(
    entityType: 'optional_feature',
    entityId: $optionalFeature->id,
    spellName: 'cone of cold',
    pivotData: [
        'frequency' => 'ki_points',
        'resource_cost' => 6,
        'notes' => 'Spend 6 ki points',
    ]
);
```

---

#### 4. Class Associations System ⚠️
**File:** `app/Services/Importers/Traits/ImportsClassAssociations.php`

**Current Capabilities:**
- Resolve class/subclass names with fuzzy matching
- Handle "Base Class" and "Base Class (Subclass)" formats
- Alias mapping for common variations

**Needs Adaptation For:**
- Category detection (not all `<classes>` values are actual classes)
- Multiple class availability (Fighting Styles → Fighter, Paladin, Ranger)

**Can Handle:**
```php
// This format works today:
$this->resolveClass("Monk (Way of the Four Elements)");
// → Returns: ['base_class' => 'Monk', 'subclass' => 'Way of the Four Elements']

// This format works today:
$this->resolveClass("Fighter (Arcane Archer): Arcane Shot");
// → Returns: ['base_class' => 'Fighter', 'subclass' => 'Arcane Archer']
```

**Needs New Logic:**
```php
// These are categories, not classes:
"Eldritch Invocations"  → category = 'invocation', parent_class = 'Warlock'
"Maneuver Options"      → category = 'maneuver', parent_class = 'Fighter'
"Metamagic Options"     → category = 'metamagic', parent_class = 'Sorcerer'
```

---

#### 5. Source Citations System ✅
**File:** `app/Services/Importers/Traits/ImportsSources.php`

**Current Capabilities:**
- Parse "Source: Xanathar's Guide to Everything p. 57" format
- Create polymorphic entity_sources relationships
- Handle multiple sources per entity

**No Changes Needed** - works perfectly for optional features.

---

### ❌ Missing Infrastructure

#### 1. Resource Cost Tracking
**What's Missing:**
- No database field for resource costs
- No standardized cost types (ki points, sorcery points, superiority dice)
- No formula support for variable costs

**Proposed Solution:**
```php
// Add to optional_features table
$table->json('resource_cost')->nullable();

// Store as:
{
    "type": "ki_points",
    "amount": 6,
    "formula": null
}

// Or for variable costs:
{
    "type": "sorcery_points",
    "amount": null,
    "formula": "spell_level"
}
```

---

#### 2. Usage Limits/Recharge Mechanics
**What's Missing:**
- No tracking for "once per short rest", "once per long rest"
- No charges/uses per day system

**Examples:**
- Invocation: Cloak of Flies - "once per short or long rest"
- Arcane Shots - "twice per short rest" (not in XML)
- Runes - "once per short or long rest"

**Proposed Solution:**
```php
// Add to optional_features table
$table->string('recharge_type')->nullable();  // 'short_rest', 'long_rest', 'dawn'
$table->integer('uses_per_rest')->nullable(); // 1, 2, null (unlimited)

// Parse from text:
if (preg_match('/once.*(?:short|long)\s+rest/i', $text)) {
    $optionalFeature->recharge_type = 'short_rest';
    $optionalFeature->uses_per_rest = 1;
}
```

---

#### 3. Activation/Trigger Mechanics
**What's Missing:**
- No structured data for activation methods (action, bonus action, reaction)
- No trigger conditions (during attack, when hit, on your turn)

**Examples:**
- Maneuver: Riposte - "reaction when creature misses you"
- Metamagic: Quickened Spell - "when you cast a spell"
- Rune: Cloud Rune - "reaction when attack hits within 30 feet"

**Proposed Solution:**
```php
// Add to optional_features table
$table->string('activation_type')->nullable();  // 'action', 'bonus_action', 'reaction', 'passive'
$table->text('trigger_condition')->nullable();  // Free text for now

// Extract from time field or text
$activationType = match(true) {
    str_contains($time, 'bonus action') => 'bonus_action',
    str_contains($time, 'reaction') => 'reaction',
    str_contains($time, '1 action') => 'action',
    empty($time) && str_contains($text, 'passive') => 'passive',
    default => null,
};
```

---

#### 4. Level-Scaling Effects
**What's Missing:**
- No support for "increases to 4d6 at 18th level"
- No level-based bonuses (Enhanced Weapon: +1 → +2 at level 10)

**Examples:**
- All Arcane Shots scale at 18th level
- Many Artificer Infusions scale at 10th level
- Elemental Disciplines have ki point scaling

**Proposed Solution:**
```php
// Add to optional_features table
$table->json('scaling_effects')->nullable();

// Store as:
{
    "10": {"modifier": "+2", "description": "Bonus increases to +2"},
    "18": {"damage": "4d6", "description": "Damage increases to 4d6"}
}

// Or create separate table:
CREATE TABLE optional_feature_scaling (
    id INT PRIMARY KEY,
    optional_feature_id INT,
    level INT,
    effect_type VARCHAR(50),  // 'damage', 'modifier', 'duration'
    effect_value VARCHAR(100),
    description TEXT
);
```

---

## Data Quality Concerns

### 🔴 Critical Issues

#### 1. No Unique Identifiers Beyond Name
**Problem:** Only `<name>` field for identification, no IDs or codes.

**Evidence:**
- XGE contains duplicate invocations as both `<spell>` and `<feat>` with identical names
- Names sometimes have level in them: "Infusion: Boots Of The Winding Path (Level 6)"

**Risk:**
- Duplicate imports if not deduplicated by slug
- Name changes across sourcebooks could break references

**Mitigation:**
```php
// Generate slug from normalized name
$name = "Invocation: Agonizing Blast";
$normalizedName = preg_replace('/\(Level \d+\)/i', '', $name);  // Remove level suffix
$slug = Str::slug(trim($normalizedName));  // "invocation-agonizing-blast"

// Check for existing before insert
OptionalFeature::firstOrCreate(
    ['slug' => $slug],
    ['name' => $name, /* ... */]
);
```

---

#### 2. Level Requirements Not Consistently Encoded
**Problem:** Level prerequisites in 3 different locations with 3 different formats.

**Evidence:**
```xml
<!-- In text field -->
<text>Prerequisite: 17th level Monk...</text>

<!-- In name field -->
<name>Infusion: Arcane Propulsion Armor (Level 14)</name>

<!-- In prerequisite field -->
<prerequisite>5th level</prerequisite>
```

**Risk:**
- Missing level requirements if only one field checked
- Inconsistent filtering ("show me all features for level 5 character")

**Mitigation:**
```php
// Multi-field extraction with priority order
$level = null;

// Priority 1: prerequisite field (feats only)
if (isset($xml->prerequisite)) {
    if (preg_match('/(\d+)(?:st|nd|rd|th)\s+level/i', (string)$xml->prerequisite, $m)) {
        $level = (int)$m[1];
    }
}

// Priority 2: text field (most common)
if (!$level && preg_match('/Prerequisite:\s*(\d+)(?:st|nd|rd|th)\s+level/i', $text, $m)) {
    $level = (int)$m[1];
}

// Priority 3: name field (some infusions)
if (!$level && preg_match('/\(Level (\d+)\)/i', $name, $m)) {
    $level = (int)$m[1];
}

// Store in prerequisites table for consistent querying
if ($level) {
    EntityPrerequisite::create([
        'entity_type' => 'optional_feature',
        'entity_id' => $optionalFeature->id,
        'prerequisite_type' => 'level',
        'value' => $level,
    ]);
}
```

---

#### 3. Resource Costs Buried in Prose
**Problem:** Ki points, sorcery points only in text fields, no structured data.

**Evidence:**
```xml
<components>V, S, M (6 ki points)</components>  <!-- Best case -->
<components>(1 sorcery point)</components>      <!-- Good -->
<text>...expend one superiority die...</text>   <!-- Worst case -->
```

**Risk:**
- Cannot query "all features costing ≤2 ki points"
- Variable costs like Twinned Spell ("spell level") require text parsing
- Superiority dice size varies by level (not in XML at all)

**Mitigation:**
```php
// Extract and normalize
public function parseResourceCost(string $components, string $text): ?array
{
    // Ki points
    if (preg_match('/\((\d+)\s+ki\s+points?\)/i', $components, $m)) {
        return ['type' => 'ki_points', 'amount' => (int)$m[1]];
    }

    // Sorcery points
    if (preg_match('/\((\d+)\s+sorcery\s+points?\)/i', $components, $m)) {
        return ['type' => 'sorcery_points', 'amount' => (int)$m[1]];
    }

    // Variable sorcery points
    if (preg_match('/equal to the spell\'?s level/i', $text)) {
        return ['type' => 'sorcery_points', 'amount' => null, 'formula' => 'spell_level'];
    }

    // Superiority die (no amount, size varies)
    if (preg_match('/expend (?:one|1) superiority di(?:ce|e)/i', $text)) {
        return ['type' => 'superiority_die', 'amount' => 1];
    }

    return null;
}

// Store as JSON
$optionalFeature->resource_cost = json_encode($resourceCost);
```

---

### 🟡 Moderate Issues

#### 1. Duplicate Entries in XGE File
**Problem:** XGE contains invocations as both `<spell>` (lines 3-154) and `<feat>` (lines 262-365) elements.

**Evidence:**
```xml
<!-- Line 3-11 -->
<spell>
  <name>Invocation: Aspect of the Moon</name>
  <level>0</level>
  <classes>Eldritch Invocations</classes>
  <text>Prerequisite: Pact of the Tome feature...</text>
</spell>

<!-- Line 262-267 -->
<feat>
  <name>Invocation: Aspect of the Moon</name>
  <prerequisite>Pact of the Tome</prerequisite>
  <text>You no longer need to sleep...</text>
</feat>
```

**Impact:**
- Will create duplicate records if not deduplicated
- `<spell>` version has more metadata (level, classes, full text)
- `<feat>` version has cleaner prerequisite field

**Mitigation:**
```php
// Deduplicate by slug during import
public function import(string $filePath): ImportResult
{
    // ... parse XML ...

    foreach ($allElements as $element) {
        $name = (string)$element->name;
        $slug = $this->generateSlug($name);

        // Skip if already imported (from earlier file or earlier in this file)
        if (OptionalFeature::where('slug', $slug)->exists()) {
            $this->log("Skipping duplicate: {$name}");
            continue;
        }

        // Import...
    }
}
```

---

#### 2. Prerequisite Complexity (Compound AND/OR)
**Problem:** Some prerequisites have multiple conditions with AND or OR logic.

**Examples:**
```xml
<!-- Simple AND -->
<text>Prerequisite: 15th level, Pact of the Chain</text>

<!-- Complex OR -->
<text>Prerequisite: 5th level, hex spell or a warlock feature that curses</text>
```

**Current System:**
- `entity_prerequisites` table supports multiple records per entity (implicit AND)
- No support for OR relationships

**Mitigation:**
```php
// For simple AND (comma-separated), create multiple prerequisite records
$prereqText = "15th level, Pact of the Chain";
$parts = array_map('trim', explode(',', $prereqText));

foreach ($parts as $part) {
    if (preg_match('/(\d+)(?:st|nd|rd|th)\s+level/i', $part, $m)) {
        EntityPrerequisite::create([
            'entity_type' => 'optional_feature',
            'entity_id' => $optionalFeature->id,
            'prerequisite_type' => 'level',
            'value' => (int)$m[1],
        ]);
    } elseif (preg_match('/Pact of the (\w+)/i', $part, $m)) {
        EntityPrerequisite::create([
            'entity_type' => 'optional_feature',
            'entity_id' => $optionalFeature->id,
            'prerequisite_type' => 'class_feature',
            'description' => 'Pact of the ' . $m[1],
        ]);
    }
}

// For OR relationships, store full text in description
if (str_contains($prereqText, ' or ')) {
    EntityPrerequisite::create([
        'entity_type' => 'optional_feature',
        'entity_id' => $optionalFeature->id,
        'prerequisite_type' => 'complex',
        'description' => $prereqText,  // "hex spell or a warlock feature that curses"
    ]);
}
```

---

#### 3. Class/Subclass Ambiguity
**Problem:** Some features specify subclass, others don't, but category implies class.

**Evidence:**
```xml
<!-- Category implies class -->
<classes>Eldritch Invocations</classes>  <!-- All Warlocks -->
<classes>Metamagic Options</classes>     <!-- All Sorcerers -->

<!-- Explicit subclass -->
<classes>Monk (Way of the Four Elements)</classes>  <!-- Only this subclass -->
<classes>Fighter (Arcane Archer): Arcane Shot</classes>  <!-- Only this subclass -->

<!-- Ambiguous: Multiple classes can use -->
<name>Fighting Style: Archery</name>  <!-- Fighter, Paladin, Ranger, but not in XML -->
```

**Impact:**
- Cannot determine "which classes can use this" from XML alone
- Fighting Styles are especially problematic (available to 3 classes)

**Mitigation:**
```php
// Create optional_feature_classes pivot table
CREATE TABLE optional_feature_classes (
    id INT PRIMARY KEY,
    optional_feature_id INT,
    character_class_id INT,
    subclass_required BOOLEAN DEFAULT FALSE,
    subclass_name VARCHAR(100) NULLABLE
);

// Map categories to classes (hardcoded knowledge)
protected array $categoryClassMap = [
    'invocation' => ['Warlock'],
    'elemental_discipline' => ['Monk'],  // Way of the Four Elements only
    'maneuver' => ['Fighter'],  // Battle Master only
    'metamagic' => ['Sorcerer'],
    'infusion' => ['Artificer'],
    'rune' => ['Fighter'],  // Rune Knight only
    'arcane_shot' => ['Fighter'],  // Arcane Archer only
];

protected array $fightingStyleClasses = ['Fighter', 'Paladin', 'Ranger'];

// Import class associations
public function importClassAssociations(OptionalFeature $feature): void
{
    $category = $feature->category;

    if ($category === 'fighting_style') {
        foreach ($this->fightingStyleClasses as $className) {
            $class = CharacterClass::where('name', $className)->first();
            if ($class) {
                DB::table('optional_feature_classes')->insert([
                    'optional_feature_id' => $feature->id,
                    'character_class_id' => $class->id,
                ]);
            }
        }
    } else {
        $classes = $this->categoryClassMap[$category] ?? [];
        foreach ($classes as $className) {
            $class = CharacterClass::where('name', $className)->first();
            if ($class) {
                DB::table('optional_feature_classes')->insert([
                    'optional_feature_id' => $feature->id,
                    'character_class_id' => $class->id,
                ]);
            }
        }
    }
}
```

---

## Recommended Implementation

### Phase 1: Database Schema

```php
// database/migrations/YYYY_MM_DD_create_optional_features_table.php
Schema::create('optional_features', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('category', 50);  // 'invocation', 'maneuver', etc.
    $table->text('description');
    $table->text('prerequisite_text')->nullable();

    // Resource cost (JSON: {"type": "ki_points", "amount": 6})
    $table->json('resource_cost')->nullable();

    // Spell-like metadata (for elemental disciplines, some invocations)
    $table->string('casting_time', 100)->nullable();
    $table->string('range', 100)->nullable();
    $table->string('components', 100)->nullable();
    $table->string('duration', 100)->nullable();
    $table->string('school_code', 5)->nullable();

    // Activation/recharge
    $table->string('activation_type', 50)->nullable();  // 'action', 'bonus_action', 'reaction', 'passive'
    $table->string('recharge_type', 50)->nullable();    // 'short_rest', 'long_rest', 'dawn'
    $table->integer('uses_per_rest')->nullable();

    // Flags
    $table->boolean('grants_spells')->default(false);
    $table->boolean('is_passive')->default(false);

    // Scaling (JSON: {"10": {"modifier": "+2"}, "18": {"damage": "4d6"}})
    $table->json('scaling_effects')->nullable();

    $table->timestamps();

    $table->index('category');
    $table->index(['category', 'slug']);
});

// Pivot: Which classes can use this feature
Schema::create('optional_feature_classes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('optional_feature_id')->constrained()->onDelete('cascade');
    $table->foreignId('character_class_id')->constrained()->onDelete('cascade');
    $table->string('subclass_name', 100)->nullable();  // If only specific subclass
    $table->timestamps();

    $table->unique(['optional_feature_id', 'character_class_id'], 'feature_class_unique');
});
```

**Reused Tables:**
- `entity_sources` - Source citations (polymorphic)
- `entity_prerequisites` - Prerequisites (polymorphic)
- `entity_spells` - Spell-granting features (polymorphic)
- `modifiers` - Passive bonuses (polymorphic)
- `tags` - Categorization via Spatie Tags (polymorphic)

---

### Phase 2: Model

```php
// app/Models/OptionalFeature.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Tags\HasTags;

class OptionalFeature extends BaseModel
{
    use HasTags;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'prerequisite_text',
        'resource_cost',
        'casting_time',
        'range',
        'components',
        'duration',
        'school_code',
        'activation_type',
        'recharge_type',
        'uses_per_rest',
        'grants_spells',
        'is_passive',
        'scaling_effects',
    ];

    protected $casts = [
        'resource_cost' => 'array',
        'scaling_effects' => 'array',
        'grants_spells' => 'boolean',
        'is_passive' => 'boolean',
    ];

    // Which classes can select this feature
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(CharacterClass::class, 'optional_feature_classes')
            ->withPivot('subclass_name')
            ->withTimestamps();
    }

    // Prerequisites (level, features, spells)
    public function prerequisites(): MorphMany
    {
        return $this->morphMany(EntityPrerequisite::class, 'entity');
    }

    // Spells granted by this feature (invocations, elemental disciplines)
    public function spells(): MorphMany
    {
        return $this->morphMany(EntitySpell::class, 'entity');
    }

    // Passive bonuses (fighting styles, some invocations)
    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'entity');
    }

    // Source citations
    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'entity');
    }

    // Scope: Get features by category
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Scope: Get features available at level X
    public function scopeAvailableAtLevel($query, int $level)
    {
        return $query->whereDoesntHave('prerequisites', function($q) use ($level) {
            $q->where('prerequisite_type', 'level')
              ->where('value', '>', $level);
        });
    }

    // Scope: Get spell-granting features
    public function scopeGrantsSpells($query)
    {
        return $query->where('grants_spells', true);
    }

    // Scope: Get passive features
    public function scopePassive($query)
    {
        return $query->where('is_passive', true);
    }
}
```

---

### Phase 3: Parser

```php
// app/Services/Parsers/OptionalFeatureParser.php
namespace App\Services\Parsers;

class OptionalFeatureParser
{
    /**
     * Determine category from XML classes field or name prefix
     */
    public function parseCategoryFromClasses(string $classes): string
    {
        return match(true) {
            str_contains($classes, 'Eldritch Invocation') => 'invocation',
            str_contains($classes, 'Maneuver') => 'maneuver',
            str_contains($classes, 'Metamagic') => 'metamagic',
            str_contains($classes, 'Artificer Infusion') => 'infusion',
            str_contains($classes, 'Rune Knight') => 'rune',
            str_contains($classes, 'Arcane Shot') => 'arcane_shot',
            str_contains($classes, 'Elemental Discipline') ||
                str_contains($classes, 'Four Elements') => 'elemental_discipline',
            default => 'unknown',
        };
    }

    /**
     * Determine category from name prefix (backup method)
     */
    public function parseCategoryFromName(string $name): string
    {
        return match(true) {
            str_starts_with($name, 'Invocation:') => 'invocation',
            str_starts_with($name, 'Maneuver:') => 'maneuver',
            str_starts_with($name, 'Metamagic:') => 'metamagic',
            str_starts_with($name, 'Infusion:') => 'infusion',
            str_starts_with($name, 'Rune:') => 'rune',
            str_starts_with($name, 'Arcane Shot:') => 'arcane_shot',
            str_starts_with($name, 'Elemental Discipline:') => 'elemental_discipline',
            str_starts_with($name, 'Fighting Style:') => 'fighting_style',
            default => 'unknown',
        };
    }

    /**
     * Extract resource cost from components field
     * Returns: ["type" => "ki_points", "amount" => 6, "formula" => null]
     */
    public function parseResourceCost(?string $components, string $text): ?array
    {
        if (!$components && !$text) {
            return null;
        }

        $combined = ($components ?? '') . ' ' . $text;

        // Ki points: "(6 ki points)"
        if (preg_match('/\((\d+)\s+ki\s+points?\)/i', $combined, $m)) {
            return ['type' => 'ki_points', 'amount' => (int)$m[1], 'formula' => null];
        }

        // Sorcery points: "(1 sorcery point)"
        if (preg_match('/\((\d+)\s+sorcery\s+points?\)/i', $combined, $m)) {
            return ['type' => 'sorcery_points', 'amount' => (int)$m[1], 'formula' => null];
        }

        // Variable sorcery points: "equal to the spell's level"
        if (preg_match('/equal to the spell\'?s level/i', $text)) {
            return ['type' => 'sorcery_points', 'amount' => null, 'formula' => 'spell_level'];
        }

        // Superiority die: "expend one superiority die"
        if (preg_match('/expend (?:one|1) superiority di(?:ce|e)/i', $text)) {
            return ['type' => 'superiority_die', 'amount' => 1, 'formula' => null];
        }

        return null;
    }

    /**
     * Extract parent class from classes field
     * "Monk (Way of the Four Elements)" → "Monk"
     */
    public function parseParentClass(string $classes): ?string
    {
        if (preg_match('/^([A-Z][a-z]+)\s*\(/', $classes, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract subclass from classes field
     * "Monk (Way of the Four Elements)" → "Way of the Four Elements"
     * "Fighter (Arcane Archer): Arcane Shot" → "Arcane Archer"
     */
    public function parseSubclass(string $classes): ?string
    {
        if (preg_match('/\(([^)]+)\)/', $classes, $m)) {
            // Remove any trailing descriptor after colon
            return trim(explode(':', $m[1])[0]);
        }
        return null;
    }

    /**
     * Extract all prerequisites from text and name fields
     * Returns: [
     *   ['type' => 'level', 'value' => 5, 'description' => '5th level Warlock'],
     *   ['type' => 'class_feature', 'value' => null, 'description' => 'Pact of the Chain'],
     * ]
     */
    public function extractPrerequisites(string $text, string $name, ?string $prerequisiteField): array
    {
        $prerequisites = [];
        $prereqText = '';

        // Priority 1: prerequisite field (feats)
        if ($prerequisiteField) {
            $prereqText = $prerequisiteField;
        }

        // Priority 2: text field (most common)
        elseif (preg_match('/Prerequisite:\s*(.+?)(?:\n\n|\n[A-Z]|$)/s', $text, $m)) {
            $prereqText = trim($m[1]);
        }

        // Priority 3: name field (some infusions)
        elseif (preg_match('/\(Level (\d+)\)/i', $name, $m)) {
            $prerequisites[] = [
                'type' => 'level',
                'value' => (int)$m[1],
                'description' => "Level {$m[1]}",
            ];
            return $prerequisites;
        }

        if (!$prereqText) {
            return $prerequisites;
        }

        // Split on comma for compound AND prerequisites
        $parts = array_map('trim', preg_split('/,\s*(?![^(]*\))/', $prereqText));

        foreach ($parts as $part) {
            // Level requirement: "5th level" or "5th level Warlock"
            if (preg_match('/(\d+)(?:st|nd|rd|th)\s+level(?:\s+(\w+))?/i', $part, $m)) {
                $prerequisites[] = [
                    'type' => 'level',
                    'value' => (int)$m[1],
                    'description' => $part,
                ];
            }

            // Pact boon requirement: "Pact of the Chain"
            elseif (preg_match('/Pact of the (\w+)/i', $part, $m)) {
                $prerequisites[] = [
                    'type' => 'class_feature',
                    'value' => null,
                    'description' => 'Pact of the ' . $m[1],
                ];
            }

            // Spell requirement: "Eldritch Blast cantrip"
            elseif (preg_match('/([a-z\s]+)\s+(cantrip|spell)/i', $part, $m)) {
                $prerequisites[] = [
                    'type' => 'spell',
                    'value' => trim($m[1]),
                    'description' => $part,
                ];
            }

            // Generic feature: "Fighting Style Feature"
            elseif (preg_match('/([A-Z][a-z\s]+)\s+Feature/i', $part, $m)) {
                $prerequisites[] = [
                    'type' => 'class_feature',
                    'value' => null,
                    'description' => $part,
                ];
            }

            // OR prerequisite (store as complex)
            elseif (str_contains($part, ' or ')) {
                $prerequisites[] = [
                    'type' => 'complex',
                    'value' => null,
                    'description' => $part,
                ];
            }
        }

        return $prerequisites;
    }

    /**
     * Detect if feature grants spells (for entity_spells relationship)
     * Returns: ['spell_name' => 'mage armor', 'frequency' => 'at_will']
     */
    public function detectGrantedSpells(string $text): array
    {
        $spells = [];

        // Pattern: "cast <spell> at will"
        if (preg_match_all('/cast\s+([a-z\s]+)\s+at will/i', $text, $matches)) {
            foreach ($matches[1] as $spellName) {
                $spells[] = [
                    'spell_name' => trim($spellName),
                    'frequency' => 'at_will',
                ];
            }
        }

        // Pattern: "cast <spell> once"
        if (preg_match_all('/cast\s+([a-z\s]+)\s+once/i', $text, $matches)) {
            foreach ($matches[1] as $spellName) {
                $spells[] = [
                    'spell_name' => trim($spellName),
                    'frequency' => 'once_per_rest',
                ];
            }
        }

        // Pattern: "spend X ki points to cast <spell>"
        if (preg_match_all('/spend\s+(\d+)\s+ki\s+points?\s+to\s+cast\s+([a-z\s]+)/i', $text, $matches)) {
            foreach ($matches[2] as $idx => $spellName) {
                $spells[] = [
                    'spell_name' => trim($spellName),
                    'frequency' => 'ki_points',
                    'resource_cost' => (int)$matches[1][$idx],
                ];
            }
        }

        return $spells;
    }

    /**
     * Parse activation type and trigger
     * Returns: ['type' => 'reaction', 'trigger' => 'when you take damage']
     */
    public function parseActivation(string $text, ?string $time): ?array
    {
        // Check time field first
        if ($time) {
            $type = match(true) {
                str_contains($time, 'bonus action') => 'bonus_action',
                str_contains($time, 'reaction') => 'reaction',
                str_contains($time, '1 action') => 'action',
                default => null,
            };

            if ($type === 'reaction') {
                // Extract trigger condition
                if (preg_match('/when\s+(.+?)(?:\.|,|\n|$)/i', $text, $m)) {
                    return ['type' => 'reaction', 'trigger' => trim($m[1])];
                }
            }

            if ($type) {
                return ['type' => $type, 'trigger' => null];
            }
        }

        // Check text for activation keywords
        if (preg_match('/as\s+a\s+(bonus action|reaction|action)/i', $text, $m)) {
            $type = str_replace(' ', '_', strtolower($m[1]));
            return ['type' => $type, 'trigger' => null];
        }

        // Passive if no activation mentioned
        if (!str_contains(strtolower($text), 'action') &&
            !str_contains(strtolower($text), 'reaction')) {
            return ['type' => 'passive', 'trigger' => null];
        }

        return null;
    }

    /**
     * Parse recharge mechanics
     * Returns: ['type' => 'short_rest', 'uses' => 1]
     */
    public function parseRecharge(string $text): ?array
    {
        // "once per short rest" or "once per long rest"
        if (preg_match('/once\s+(?:per|until\s+you\s+finish\s+a)\s+(short|long)\s+rest/i', $text, $m)) {
            return [
                'type' => $m[1] . '_rest',
                'uses' => 1,
            ];
        }

        // "twice per short rest"
        if (preg_match('/(\d+|twice|thrice)\s+(?:times?\s+)?per\s+(short|long)\s+rest/i', $text, $m)) {
            $uses = match(strtolower($m[1])) {
                'twice' => 2,
                'thrice' => 3,
                default => (int)$m[1],
            };

            return [
                'type' => $m[2] . '_rest',
                'uses' => $uses,
            ];
        }

        // "regains 1d6 expended charges daily at dawn"
        if (preg_match('/(?:regains?|recharges?).+(?:daily at dawn|at dawn)/i', $text)) {
            return [
                'type' => 'dawn',
                'uses' => null,  // Variable recharge
            ];
        }

        return null;
    }

    /**
     * Extract level-scaling effects
     * Returns: {
     *   "10": {"modifier": "+2", "description": "Bonus increases to +2"},
     *   "18": {"damage": "4d6", "description": "Damage increases to 4d6"}
     * }
     */
    public function parseScalingEffects(string $text): ?array
    {
        $scaling = [];

        // Pattern: "increases to X when you reach Nth level"
        preg_match_all('/increases?\s+to\s+([^\s]+).+?(\d+)(?:st|nd|rd|th)\s+level/i', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $value = $match[1];
            $level = (int)$match[2];

            $scaling[$level] = [
                'value' => $value,
                'description' => trim($match[0]),
            ];
        }

        // Pattern: "The X damage increases to Y when you reach 18th level"
        preg_match_all('/The\s+(.+?)\s+increases?\s+to\s+([^\s]+).+?(\d+)(?:st|nd|rd|th)\s+level/i', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $type = trim($match[1]);
            $value = $match[2];
            $level = (int)$match[3];

            if (!isset($scaling[$level])) {
                $scaling[$level] = [];
            }

            $scaling[$level][strtolower(str_replace(' ', '_', $type))] = $value;
            $scaling[$level]['description'] = trim($match[0]);
        }

        return empty($scaling) ? null : $scaling;
    }
}
```

---

### Phase 4: Importer with Strategy Pattern

```php
// app/Services/Importers/OptionalFeatureImporter.php
namespace App\Services\Importers;

use App\Models\OptionalFeature;
use App\Services\Parsers\OptionalFeatureParser;
use App\Services\Importers\Traits\{
    ImportsSources,
    ImportsPrerequisites,
    ImportsEntitySpells,
    ImportsModifiers,
};
use SimpleXMLElement;

class OptionalFeatureImporter extends BaseImporter
{
    use ImportsSources,
        ImportsPrerequisites,
        ImportsEntitySpells,
        ImportsModifiers;

    protected OptionalFeatureParser $parser;
    protected array $strategies = [];

    public function __construct()
    {
        parent::__construct();
        $this->parser = new OptionalFeatureParser();
        $this->initializeStrategies();
    }

    protected function initializeStrategies(): void
    {
        $this->strategies = [
            'invocation' => new InvocationStrategy($this->parser),
            'elemental_discipline' => new ElementalDisciplineStrategy($this->parser),
            'maneuver' => new ManeuverStrategy($this->parser),
            'metamagic' => new MetamagicStrategy($this->parser),
            'fighting_style' => new FightingStyleStrategy($this->parser),
            'infusion' => new InfusionStrategy($this->parser),
            'rune' => new RuneStrategy($this->parser),
            'arcane_shot' => new ArcaneShotStrategy($this->parser),
        ];
    }

    public function import(string $filePath): array
    {
        $this->log("Importing optional features from: {$filePath}");

        $xml = simplexml_load_file($filePath);
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        // Process both <spell> and <feat> elements
        $spellElements = $xml->xpath('//spell') ?: [];
        $featElements = $xml->xpath('//feat') ?: [];
        $allElements = array_merge($spellElements, $featElements);

        foreach ($allElements as $element) {
            try {
                $name = (string)$element->name;

                // Skip if not an optional feature (check name prefixes)
                if (!$this->isOptionalFeature($name)) {
                    continue;
                }

                // Generate slug for deduplication
                $slug = $this->generateSlug($name);

                // Skip duplicates (XGE has invocations as both spell and feat)
                if (OptionalFeature::where('slug', $slug)->exists()) {
                    $this->log("Skipping duplicate: {$name}");
                    $skipped++;
                    continue;
                }

                // Import the feature
                $feature = $this->importFeature($element);

                if ($feature) {
                    $imported++;
                    $this->log("Imported: {$feature->name} ({$feature->category})");
                } else {
                    $errors++;
                    $this->log("Failed to import: {$name}");
                }

            } catch (\Exception $e) {
                $errors++;
                $this->log("Error importing {$name}: " . $e->getMessage());
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    protected function isOptionalFeature(string $name): bool
    {
        $prefixes = [
            'Invocation:',
            'Elemental Discipline:',
            'Maneuver:',
            'Metamagic:',
            'Fighting Style:',
            'Infusion:',
            'Rune:',
            'Arcane Shot:',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function generateSlug(string $name): string
    {
        // Remove level suffix: "Infusion: Boots (Level 6)" → "Infusion: Boots"
        $normalized = preg_replace('/\s*\(Level \d+\)\s*/i', '', $name);
        return \Str::slug(trim($normalized));
    }

    protected function importFeature(SimpleXMLElement $xml): ?OptionalFeature
    {
        $name = (string)$xml->name;
        $text = (string)$xml->text;
        $classes = (string)($xml->classes ?? '');
        $level = (int)($xml->level ?? 0);

        // Determine category
        $category = $this->parser->parseCategoryFromClasses($classes);
        if ($category === 'unknown') {
            $category = $this->parser->parseCategoryFromName($name);
        }

        // Base feature data
        $featureData = [
            'name' => $name,
            'slug' => $this->generateSlug($name),
            'category' => $category,
            'description' => $text,
            'prerequisite_text' => $this->extractPrerequisiteText($text),
            'resource_cost' => $this->parser->parseResourceCost(
                (string)($xml->components ?? ''),
                $text
            ),
            'casting_time' => (string)($xml->time ?? null),
            'range' => (string)($xml->range ?? null),
            'components' => (string)($xml->components ?? null),
            'duration' => (string)($xml->duration ?? null),
            'school_code' => (string)($xml->school ?? null),
            'grants_spells' => !empty($this->parser->detectGrantedSpells($text)),
            'scaling_effects' => $this->parser->parseScalingEffects($text),
        ];

        // Parse activation/recharge
        $activation = $this->parser->parseActivation($text, (string)($xml->time ?? ''));
        if ($activation) {
            $featureData['activation_type'] = $activation['type'];
            $featureData['is_passive'] = $activation['type'] === 'passive';
        }

        $recharge = $this->parser->parseRecharge($text);
        if ($recharge) {
            $featureData['recharge_type'] = $recharge['type'];
            $featureData['uses_per_rest'] = $recharge['uses'];
        }

        // Create feature
        $feature = OptionalFeature::create($featureData);

        // Use strategy pattern for category-specific imports
        $strategy = $this->strategies[$category] ?? null;
        if ($strategy) {
            $strategy->import($feature, $xml);
        }

        // Common imports (all features)
        $this->importCommonData($feature, $xml);

        return $feature;
    }

    protected function extractPrerequisiteText(string $text): ?string
    {
        if (preg_match('/Prerequisite:\s*(.+?)(?:\n\n|\n[A-Z]|$)/s', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    protected function importCommonData(OptionalFeature $feature, SimpleXMLElement $xml): void
    {
        // Source citations
        $this->importSources($xml, 'optional_feature', $feature->id);

        // Prerequisites
        $prerequisites = $this->parser->extractPrerequisites(
            (string)$xml->text,
            (string)$xml->name,
            (string)($xml->prerequisite ?? '')
        );

        foreach ($prerequisites as $prereq) {
            \App\Models\EntityPrerequisite::create([
                'entity_type' => 'optional_feature',
                'entity_id' => $feature->id,
                'prerequisite_type' => $prereq['type'],
                'value' => $prereq['value'] ?? null,
                'description' => $prereq['description'],
            ]);
        }
    }
}
```

---

### Phase 5: Import Strategies (Example: Invocation)

```php
// app/Services/Importers/Strategies/InvocationStrategy.php
namespace App\Services\Importers\Strategies;

use App\Models\OptionalFeature;
use App\Services\Parsers\OptionalFeatureParser;
use App\Services\Importers\Traits\ImportsEntitySpells;
use SimpleXMLElement;

class InvocationStrategy
{
    use ImportsEntitySpells;

    public function __construct(
        protected OptionalFeatureParser $parser
    ) {}

    public function import(OptionalFeature $feature, SimpleXMLElement $xml): void
    {
        $text = (string)$xml->text;

        // Import granted spells
        $grantedSpells = $this->parser->detectGrantedSpells($text);

        foreach ($grantedSpells as $spellData) {
            $this->importEntitySpell(
                entityType: 'optional_feature',
                entityId: $feature->id,
                spellName: $spellData['spell_name'],
                pivotData: [
                    'frequency' => $spellData['frequency'],
                    'resource_cost' => $spellData['resource_cost'] ?? null,
                    'notes' => $this->extractSpellNotes($text, $spellData['spell_name']),
                ]
            );
        }

        // Link to Warlock class
        $warlockClass = \App\Models\CharacterClass::where('name', 'Warlock')->first();
        if ($warlockClass) {
            $feature->classes()->attach($warlockClass->id);
        }
    }

    protected function extractSpellNotes(string $text, string $spellName): ?string
    {
        // Extract any special conditions (e.g., "without expending a spell slot")
        $pattern = "/cast\s+" . preg_quote($spellName, '/') . "\s+(.+?)(?:\.|$)/i";

        if (preg_match($pattern, $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
```

**Additional Strategies Needed:**
- `ElementalDisciplineStrategy` - Import ki point costs, link to Monk class
- `ManeuverStrategy` - Mark as Battle Master only, link to Fighter
- `MetamagicStrategy` - Link to Sorcerer class
- `FightingStyleStrategy` - **Import modifiers**, link to Fighter/Paladin/Ranger
- `InfusionStrategy` - Parse item type requirements, link to Artificer
- `RuneStrategy` - Import passive + active effects, link to Fighter (Rune Knight)
- `ArcaneShotStrategy` - Import scaling damage, link to Fighter (Arcane Archer)

---

## API Endpoints

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::get('/optional-features', [OptionalFeatureController::class, 'index']);
    Route::get('/optional-features/{idOrSlug}', [OptionalFeatureController::class, 'show']);
});

// Example requests:
GET /api/v1/optional-features?category=invocation&level=5
GET /api/v1/optional-features?grants_spells=true
GET /api/v1/optional-features?class=Fighter&subclass=Battle%20Master
GET /api/v1/optional-features/invocation-agonizing-blast
```

**Query Parameters:**
- `category` - Filter by category (invocation, maneuver, etc.)
- `level` - Show features available at this level
- `class` - Filter by class name
- `subclass` - Filter by subclass name
- `grants_spells` - Boolean, features that grant spell casting
- `is_passive` - Boolean, passive vs. activated features
- `resource_type` - Filter by resource (ki_points, sorcery_points, etc.)

---

## Testing Strategy

### Unit Tests

```php
// tests/Unit/Parsers/OptionalFeatureParserTest.php
test('parseCategoryFromClasses detects invocation', function () {
    $parser = new OptionalFeatureParser();
    $category = $parser->parseCategoryFromClasses('Eldritch Invocations');
    expect($category)->toBe('invocation');
});

test('parseResourceCost extracts ki points', function () {
    $parser = new OptionalFeatureParser();
    $cost = $parser->parseResourceCost('V, S, M (6 ki points)', '');
    expect($cost)->toBe(['type' => 'ki_points', 'amount' => 6, 'formula' => null]);
});

test('extractPrerequisites handles compound AND prerequisites', function () {
    $parser = new OptionalFeatureParser();
    $prereqs = $parser->extractPrerequisites(
        'Prerequisite: 15th level, Pact of the Chain',
        '',
        null
    );
    expect($prereqs)->toHaveCount(2);
    expect($prereqs[0]['type'])->toBe('level');
    expect($prereqs[1]['type'])->toBe('class_feature');
});
```

### Feature Tests

```php
// tests/Feature/Importers/OptionalFeatureImporterTest.php
test('imports warlock invocations from PHB file', function () {
    $importer = new OptionalFeatureImporter();
    $result = $importer->import('import-files/optionalfeatures-phb.xml');

    expect($result['imported'])->toBeGreaterThan(0);
    expect(OptionalFeature::where('category', 'invocation')->count())->toBeGreaterThan(20);
});

test('deduplicates invocations from XGE file', function () {
    // Import PHB first
    $importer = new OptionalFeatureImporter();
    $importer->import('import-files/optionalfeatures-phb.xml');

    $countBefore = OptionalFeature::count();

    // Import XGE (has duplicates)
    $result = $importer->import('import-files/optionalfeatures-xge.xml');

    expect($result['skipped'])->toBeGreaterThan(0);  // Should skip duplicates
});

test('invocation grants spell relationship', function () {
    $importer = new OptionalFeatureImporter();
    $importer->import('import-files/optionalfeatures-phb.xml');

    $invocation = OptionalFeature::where('slug', 'invocation-armor-of-shadows')->first();

    expect($invocation)->not->toBeNull();
    expect($invocation->grants_spells)->toBeTrue();
    expect($invocation->spells)->toHaveCount(1);
    expect($invocation->spells->first()->name)->toBe('Mage Armor');
});

test('fighting style creates modifiers', function () {
    $importer = new OptionalFeatureImporter();
    $importer->import('import-files/optionalfeatures-phb.xml');

    $fightingStyle = OptionalFeature::where('slug', 'fighting-style-archery')->first();

    expect($fightingStyle)->not->toBeNull();
    expect($fightingStyle->modifiers)->toHaveCount(1);
    expect($fightingStyle->modifiers->first()->value)->toBe('+2');
});
```

### API Tests

```php
// tests/Feature/Api/OptionalFeatureControllerTest.php
test('GET /optional-features returns paginated list', function () {
    OptionalFeature::factory()->count(30)->create();

    $response = $this->getJson('/api/v1/optional-features');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'name', 'slug', 'category']],
        'meta' => ['current_page', 'total'],
    ]);
});

test('GET /optional-features filters by category', function () {
    OptionalFeature::factory()->create(['category' => 'invocation']);
    OptionalFeature::factory()->create(['category' => 'maneuver']);

    $response = $this->getJson('/api/v1/optional-features?category=invocation');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

test('GET /optional-features filters by level', function () {
    // Create feature with level 5 prerequisite
    $feature = OptionalFeature::factory()->create(['name' => 'Test Feature']);
    \App\Models\EntityPrerequisite::create([
        'entity_type' => 'optional_feature',
        'entity_id' => $feature->id,
        'prerequisite_type' => 'level',
        'value' => 5,
    ]);

    // Create feature with no prerequisites
    OptionalFeature::factory()->create(['name' => 'Basic Feature']);

    // Request features available at level 3
    $response = $this->getJson('/api/v1/optional-features?level=3');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');  // Only basic feature
    $response->assertJsonFragment(['name' => 'Basic Feature']);
});
```

---

## Usage Examples

### Character Builder Integration

```php
// Get all invocations available for a 5th-level Warlock with Pact of the Chain
$invocations = OptionalFeature::ofCategory('invocation')
    ->availableAtLevel(5)
    ->whereHas('prerequisites', function($q) {
        $q->where('description', 'like', '%Pact of the Chain%');
    })
    ->orWhereDoesntHave('prerequisites', function($q) {
        $q->where('prerequisite_type', 'class_feature');
    })
    ->with(['spells', 'modifiers', 'sources'])
    ->get();

// Get all maneuvers available for Battle Master
$maneuvers = OptionalFeature::ofCategory('maneuver')
    ->with(['sources', 'prerequisites'])
    ->get();

// Get all fighting styles available for Paladins
$warlockClass = CharacterClass::where('name', 'Paladin')->first();
$fightingStyles = OptionalFeature::ofCategory('fighting_style')
    ->whereHas('classes', function($q) use ($warlockClass) {
        $q->where('character_class_id', $warlockClass->id);
    })
    ->with('modifiers')
    ->get();

// Get all features that grant spell casting
$spellGrantingFeatures = OptionalFeature::grantsSpells()
    ->with(['spells.spell'])
    ->get();

foreach ($spellGrantingFeatures as $feature) {
    echo "{$feature->name} grants:\n";
    foreach ($feature->spells as $entitySpell) {
        echo "  - {$entitySpell->spell->name} ({$entitySpell->pivot->frequency})\n";
    }
}
```

---

## Implementation Checklist

### ✅ Phase 1: Database (2-3 hours)
- [ ] Create `optional_features` migration
- [ ] Create `optional_feature_classes` pivot migration
- [ ] Create `OptionalFeature` model with relationships
- [ ] Create factory for testing
- [ ] Run migrations and verify schema

### ✅ Phase 2: Parser (2-3 hours)
- [ ] Create `OptionalFeatureParser` class
- [ ] Implement `parseCategoryFromClasses()`
- [ ] Implement `parseResourceCost()`
- [ ] Implement `extractPrerequisites()`
- [ ] Implement `detectGrantedSpells()`
- [ ] Implement `parseActivation()` and `parseRecharge()`
- [ ] Implement `parseScalingEffects()`
- [ ] Write unit tests for all parser methods

### ✅ Phase 3: Base Importer (2-3 hours)
- [ ] Create `OptionalFeatureImporter` class extending `BaseImporter`
- [ ] Implement `import()` method with duplicate detection
- [ ] Implement `importFeature()` core logic
- [ ] Implement `importCommonData()` (sources, prerequisites)
- [ ] Write feature tests for base importer

### ✅ Phase 4: Strategies (4-5 hours)
- [ ] Create base `OptionalFeatureStrategy` interface
- [ ] Implement `InvocationStrategy` (spell grants)
- [ ] Implement `FightingStyleStrategy` (modifiers)
- [ ] Implement `ElementalDisciplineStrategy` (ki points + spells)
- [ ] Implement `ManeuverStrategy` (superiority dice)
- [ ] Implement `MetamagicStrategy` (sorcery points)
- [ ] Implement `InfusionStrategy` (item enchantments)
- [ ] Implement `RuneStrategy` (passive + active)
- [ ] Implement `ArcaneShotStrategy` (scaling damage)
- [ ] Write tests for each strategy

### ✅ Phase 5: API (1-2 hours)
- [ ] Create `OptionalFeatureController`
- [ ] Implement `index()` with filtering (category, level, class)
- [ ] Implement `show()` with relationships
- [ ] Create `OptionalFeatureResource` for serialization
- [ ] Create Form Requests: `OptionalFeatureIndexRequest`, `OptionalFeatureShowRequest`
- [ ] Write API tests

### ✅ Phase 6: Console Command (1 hour)
- [ ] Create `ImportOptionalFeaturesCommand`
- [ ] Add to `import:all` master command
- [ ] Test import order (doesn't depend on other entities)

### ✅ Phase 7: Documentation (1 hour)
- [ ] Update `CLAUDE.md` with optional features info
- [ ] Update `docs/README.md` with API endpoints
- [ ] Create session handover document
- [ ] Update CHANGELOG.md

---

## Estimated Effort

| Phase | Time | Complexity |
|-------|------|------------|
| Database | 2-3 hours | Low |
| Parser | 2-3 hours | Medium (regex patterns) |
| Base Importer | 2-3 hours | Medium |
| Strategies (8 total) | 4-5 hours | Medium-High |
| API | 1-2 hours | Low |
| Console Command | 1 hour | Low |
| Documentation | 1 hour | Low |
| **Total** | **13-17 hours** | **Medium-High** |

---

## Future Enhancements

### Post-MVP Improvements

1. **Class Feature Integration**
   - Import full class features (not just optional ones)
   - Link optional features to "selection points" (e.g., "Warlocks choose 2 invocations at level 2")
   - Validate prerequisites against class features

2. **Interactive Selection UI**
   - Character builder interface for selecting invocations/maneuvers/etc.
   - Show available choices based on level and prerequisites
   - Highlight spell-granting features

3. **Effect Simulation**
   - Calculate actual effects (e.g., Fighting Style: Archery → "+2 to ranged attack" applied to character sheet)
   - Track resource usage (ki points, sorcery points, superiority dice)
   - Recharge mechanics during rest

4. **Advanced Queries**
   - "Show me all features that grant advantage"
   - "Show me all features that cost ≤2 ki points"
   - "Show me all invocations for a Hexblade Warlock"

5. **Homebrew Support**
   - Allow custom optional features
   - Community voting/rating system

---

## Decision Points

### ❓ Should We Implement Now?

**Arguments FOR:**
- High value for character builders
- Completes the "character options" import suite
- Relatively self-contained (doesn't depend on other unimported data)

**Arguments AGAINST:**
- Core compendium (spells, monsters, items, classes) is already complete
- Requires significant parser complexity for inconsistent data
- Limited use without full class feature system

**Recommendation:** **Defer to post-launch** unless character builder is immediate priority.

---

### ❓ New Model vs. Extend Existing?

**Option A: New `OptionalFeature` Model** (Recommended)
- ✅ Clear semantic separation
- ✅ Dedicated schema for optional feature metadata
- ✅ Easier to query and filter
- ❌ More database tables

**Option B: Extend `ClassFeature` Model**
- ✅ Reuses existing infrastructure
- ❌ Confusing semantics (not all class features are optional)
- ❌ Schema doesn't fit well (no resource costs, recharge, etc.)

**Decision:** Use new `OptionalFeature` model.

---

### ❓ Handle Duplicates How?

**XGE file has invocations as both `<spell>` and `<feat>` elements.**

**Option A: Deduplicate by Slug** (Recommended)
- Skip second occurrence of same slug
- Prefer `<spell>` version (more metadata)

**Option B: Merge Data**
- Import both, merge fields
- Complexity: which field wins?

**Option C: Import Both**
- Mark as duplicates in database
- UI chooses which to display

**Decision:** Deduplicate by slug, skip duplicates.

---

## Reference Files

**This Analysis:**
- `docs/analysis/OPTIONAL-FEATURES-IMPORT-ANALYSIS.md` (this file)

**XML Files:**
- `import-files/optionalfeatures-phb.xml`
- `import-files/optionalfeatures-tce.xml`
- `import-files/optionalfeatures-xge.xml`

**Related Documentation:**
- `docs/SESSION-HANDOVER-2025-11-22-ITEM-PARSER-STRATEGIES-COMPLETE.md` - Strategy pattern reference
- `docs/SESSION-HANDOVER-2025-11-22-MONSTER-IMPORTER-COMPLETE.md` - Complex importer example

**Existing Infrastructure:**
- `app/Services/Importers/Traits/ImportsPrerequisites.php`
- `app/Services/Importers/Traits/ImportsEntitySpells.php`
- `app/Services/Importers/Traits/ImportsModifiers.php`
- `app/Services/Importers/Traits/ImportsClassAssociations.php`
- `app/Models/EntityPrerequisite.php`
- `app/Models/EntitySpell.php`
- `app/Models/Modifier.php`

---

## Contact Points for Future Implementation

When ready to implement, start here:

1. **Read this analysis** - Comprehensive data structure understanding
2. **Review strategy pattern** - `docs/SESSION-HANDOVER-2025-11-22-ITEM-PARSER-STRATEGIES-COMPLETE.md`
3. **Database design** - See "Phase 1: Database Schema" section above
4. **Parser implementation** - See "Phase 3: Parser" section above
5. **Import strategies** - See "Phase 5: Import Strategies" section above

**Questions to Answer Before Starting:**
- Is character builder a priority? (determines urgency)
- Do we need full class feature import first? (determines dependencies)
- What's the target release? (determines whether to defer)

---

**End of Analysis**
