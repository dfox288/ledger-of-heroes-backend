# Class XML Import Improvements Analysis

**Created:** 2025-11-23
**Status:** Proposed
**Complexity:** Medium-High (3-5 days)

## Executive Summary

This document analyzes 6 structural data quality issues in class XML imports discovered during Rogue class review. The issues affect feature assignment, proficiency representation, random table parsing, roll tracking, equipment matching, and spell slot assignment.

---

## Issue 1: Subclass Features Incorrectly Assigned to Base Class

### Problem Description

Base class features list ALL subclass features mixed together. The XML structure has subclass-specific features at the top level with identifiers in their names (e.g., "(Arcane Trickster)"), but the current parser doesn't filter them out from the base class.

**Example from `class-rogue-phb.xml` (lines 12-67):**
```xml
<class>
  <name>Rogue</name>
  <!-- Spell slots ONLY for Arcane Trickster subclass -->
  <spellAbility>Intelligence</spellAbility>
  <slotsReset>L</slotsReset>
  <autolevel level="3">
    <slots optional="YES">3,2</slots>  <!-- Arcane Trickster only! -->
  </autolevel>
  <!-- ... more slot levels ... -->

  <!-- Spell counters ONLY for Arcane Trickster -->
  <autolevel level="3">
    <counter>
      <name>Spells Known</name>
      <value>3</value>
    </counter>
  </autolevel>
</class>
```

**Current Behavior:**
- Base Rogue class gets `spellcasting_ability_id` = Intelligence
- Base Rogue class gets spell slots at levels 3-20 (incorrect!)
- All features with "(Arcane Trickster)" in name are included in base class

### Cross-Class Verification

**Wizard (`class-wizard-phb.xml`):**
- ✅ **NO** `<slots optional="YES">` tags
- ✅ Spell slots at top level without optional attribute
- ✅ Subclass features clearly marked: "School of Abjuration", "School of Conjuration", etc.
- Pattern: `Arcane Tradition: {SubclassName}`

**Fighter (`class-fighter-phb.xml`):**
- ✅ **NO** `<slots optional="YES">` tags
- ✅ Spell slots only for Eldritch Knight subclass
- ✅ Clear pattern: `Martial Archetype: {SubclassName}`

**Consistency:** Archetype-based classes (Rogue, Fighter, Barbarian, etc.) use:
- `optional="YES"` for subclass-specific spell slots
- Pattern: `{ArchetypePrefix}: {SubclassName}` for intro features
- Parentheses for subsequent features: `Feature Name ({SubclassName})`

### Proposed Solution

**Phase 1: Filter Subclass Features from Base Class**

```php
// ClassXmlParser.php - detectSubclasses()
private function detectSubclasses(array $features, array $counters): array
{
    // ... existing detection logic ...

    // NEW: Remove subclass features from base class features array
    foreach ($subclasses as $subclass) {
        foreach ($features as $key => $feature) {
            // Remove if feature belongs to this subclass
            if ($this->featureBelongsToSubclass($feature['name'], $subclass['name'])) {
                unset($features[$key]);
            }
        }
    }

    // Re-index array after unsetting
    $features = array_values($features);

    return ['subclasses' => $subclasses, 'filtered_base_features' => $features];
}

private function featureBelongsToSubclass(string $featureName, string $subclassName): bool
{
    // Pattern 1: "Archetype: Subclass Name"
    if (preg_match('/^(?:Martial Archetype|Roguish Archetype|etc):\s*' . preg_quote($subclassName, '/') . '$/i', $featureName)) {
        return true;
    }

    // Pattern 2: "Feature Name (Subclass Name)"
    if (preg_match('/\(' . preg_quote($subclassName, '/') . '\)$/i', $featureName)) {
        return true;
    }

    return false;
}
```

**Phase 2: Update Parser Return Signature**

```php
// ClassXmlParser.php - parseClass()
private function parseClass(SimpleXMLElement $element): array
{
    // ... existing parsing ...

    // Detect subclasses and filter base features
    $subclassData = $this->detectSubclasses($data['features'], $data['counters']);
    $data['subclasses'] = $subclassData['subclasses'];
    $data['features'] = $subclassData['filtered_base_features'];  // Use filtered list

    return $data;
}
```

**Impact:**
- Base classes will no longer have subclass features
- Subclasses will correctly contain only their own features
- Spell slots handled separately (see Issue #6)

---

## Issue 2: Proficiency Choices Not Properly Structured

### Problem Description

Proficiencies with choices (e.g., "Choose 4 from Acrobatics, Athletics, ...") are currently stored as individual rows, each with `quantity = 4`. This creates N duplicate rows instead of 1 row representing "choose 4 from these N options."

**Example from Rogue:**
```xml
<numSkills>4</numSkills>
<proficiency>Dexterity, Intelligence, Acrobatics, Athletics, Deception, Insight, Intimidation, Investigation, Perception, Performance, Persuasion, Sleight Of Hand, Stealth</proficiency>
```

**Current Behavior (11 skill rows created):**
```
| type  | name                 | is_choice | quantity |
|-------|---------------------|-----------|----------|
| skill | Acrobatics          | true      | 4        |
| skill | Athletics           | true      | 4        |
| skill | Deception           | true      | 4        |
| ... 8 more rows with quantity=4
```

**Problem:** This doesn't represent "choose 4 from 11 options" - it suggests choosing 4 of each skill!

### Proposed Solution

**Database Migration: Add `choice_group` Column**

```php
// database/migrations/xxxx_add_choice_group_to_entity_proficiencies_table.php
Schema::table('entity_proficiencies', function (Blueprint $table) {
    $table->string('choice_group')->nullable()->after('quantity');
    $table->text('choice_description')->nullable()->after('choice_group');
});
```

**Updated Parsing Logic:**

```php
// ClassXmlParser.php - parseProficiencies()
private function parseProficiencies(SimpleXMLElement $element): array
{
    $proficiencies = [];
    $numSkills = isset($element->numSkills) ? (int) $element->numSkills : null;

    if (isset($element->proficiency)) {
        $items = array_map('trim', explode(',', (string) $element->proficiency));
        $abilityScores = ['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'];

        // Separate saving throws from skills
        $skills = [];
        foreach ($items as $item) {
            if (in_array($item, $abilityScores)) {
                // Saving throw - always granted
                $proficiencies[] = [
                    'type' => 'saving_throw',
                    'name' => $item,
                    'is_choice' => false,
                    'quantity' => 1,
                ];
            } else {
                // Skill - add to choice pool
                $skills[] = $item;
            }
        }

        // NEW: Create single choice group for all skills
        if ($numSkills !== null && !empty($skills)) {
            $choiceGroup = uniqid('skill_choice_', true);

            foreach ($skills as $skill) {
                $proficiencyType = $this->matchProficiencyType($skill);
                $proficiencies[] = [
                    'type' => 'skill',
                    'name' => $skill,
                    'proficiency_type_id' => $proficiencyType?->id,
                    'is_choice' => true,
                    'quantity' => $numSkills,  // Number to choose
                    'choice_group' => $choiceGroup,  // Links all choices together
                    'choice_description' => "Choose {$numSkills} from available skills",
                ];
            }
        } elseif (!empty($skills)) {
            // No choice - all skills granted automatically
            foreach ($skills as $skill) {
                $proficiencyType = $this->matchProficiencyType($skill);
                $proficiencies[] = [
                    'type' => 'skill',
                    'name' => $skill,
                    'proficiency_type_id' => $proficiencyType?->id,
                    'is_choice' => false,
                    'quantity' => 1,
                ];
            }
        }
    }

    return $proficiencies;
}
```

**API Response Example:**

```json
{
  "proficiencies": {
    "saving_throws": [
      {"name": "Dexterity", "is_choice": false},
      {"name": "Intelligence", "is_choice": false}
    ],
    "skill_choices": {
      "choice_description": "Choose 4 from available skills",
      "quantity": 4,
      "options": [
        {"name": "Acrobatics", "proficiency_type_id": 15},
        {"name": "Athletics", "proficiency_type_id": 16},
        ... 9 more options
      ]
    }
  }
}
```

**Impact:**
- Frontend can correctly render "Choose 4 skills from: ..."
- Character builders can enforce choice limits
- Database accurately represents game rules

---

## Issue 3: Random Tables in Feature Text Not Parsed

### Problem Description

Some features contain random tables embedded in their description text that aren't extracted into the `random_tables` polymorphic table.

**Example Pattern (hypothetical based on other entities):**

```xml
<feature>
  <name>Personality Quirk</name>
  <text>You gain a quirk from the following table:

  d8 | Quirk
  1 | Always speaks in rhymes
  2 | Collects buttons
  3 | Afraid of horses
  ... etc
  </text>
</feature>
```

**Current Behavior:**
- `ImportRandomTablesFromText` trait is called in `ClassImporter.php` (lines 75-80)
- Should handle pipe-delimited tables: `1 | Result`
- May not handle all table formats present in class features

### Verification Needed

**Action Items:**
1. Search all class XML files for pipe-delimited tables: `grep -r " | " import-files/class-*.xml`
2. Check for `<roll>` elements in feature text (separate from feature-level `<roll>` tags)
3. Verify `ImportRandomTablesFromText` trait handles all formats

**Example Check:**
```bash
# Find features with table-like content
grep -A 10 "d[46810]" import-files/class-rogue-*.xml | grep "|"
```

### Proposed Solution

**IF tables are found:**

```php
// ClassImporter.php - importFeatures() - ALREADY EXISTS but verify it works
private function importFeatures(CharacterClass $class, array $features): void
{
    foreach ($features as $featureData) {
        $feature = ClassFeature::create([
            'class_id' => $class->id,
            'level' => $featureData['level'],
            'feature_name' => $featureData['name'],
            'is_optional' => $featureData['is_optional'],
            'description' => $featureData['description'],
            'sort_order' => $featureData['sort_order'],
        ]);

        // NEW: Import random tables from feature description
        $this->importRandomTablesFromText($feature, $featureData['description']);
    }
}
```

**Impact:**
- Features with embedded tables will have structured data
- Can query for "all features with random outcomes"
- Frontend can render tables as interactive rolls

---

## Issue 4: Feature `<roll>` Nodes Not Captured

### Problem Description

Some features have `<roll>` XML elements that provide level-scaled values (e.g., Sneak Attack damage progression), but these aren't currently stored in the database.

**Example from Rogue (lines 285-300):**
```xml
<feature>
  <name>Sneak Attack</name>
  <text>... extra 1d6 damage ... increases by 1d6 every odd level ...</text>
  <roll description="Extra Damage" level="1">1d6</roll>
  <roll description="Extra Damage" level="2">2d6</roll>
  <roll description="Extra Damage" level="3">3d6</roll>
  <roll description="Extra Damage" level="4">4d6</roll>
  <roll description="Extra Damage" level="5">5d6</roll>
  <roll description="Extra Damage" level="6">6d6</roll>
  <roll description="Extra Damage" level="7">7d6</roll>
  <roll description="Extra Damage" level="8">8d6</roll>
  <roll description="Extra Damage" level="9">9d6</roll>
</feature>
```

**Current Behavior:**
- `<roll>` elements are completely ignored
- Must calculate Sneak Attack dice manually from text description
- No structured data for level-scaled features

### Database Design Proposal

**New Table: `class_feature_rolls`**

```php
// database/migrations/xxxx_create_class_feature_rolls_table.php
Schema::create('class_feature_rolls', function (Blueprint $table) {
    $table->id();
    $table->foreignId('class_feature_id')->constrained('class_features')->onDelete('cascade');
    $table->unsignedInteger('level');
    $table->string('description')->nullable();  // "Extra Damage", "Ki Points", etc.
    $table->string('formula');  // "1d6", "2d8+4", "10", etc.
    $table->timestamps();

    $table->index(['class_feature_id', 'level']);
});
```

**Parser Enhancement:**

```php
// ClassXmlParser.php - parseFeatures()
private function parseFeatures(SimpleXMLElement $element): array
{
    $features = [];
    $sortOrder = 0;

    foreach ($element->autolevel as $autolevel) {
        $level = (int) $autolevel['level'];

        foreach ($autolevel->feature as $featureElement) {
            $isOptional = isset($featureElement['optional']) && (string) $featureElement['optional'] === 'YES';
            $name = (string) $featureElement->name;
            $text = (string) $featureElement->text;
            $sources = $this->parseSourceCitations($text);

            // NEW: Parse roll elements
            $rolls = [];
            foreach ($featureElement->roll as $rollElement) {
                $rolls[] = [
                    'level' => (int) ($rollElement['level'] ?? $level),
                    'description' => (string) ($rollElement['description'] ?? null),
                    'formula' => (string) $rollElement,
                ];
            }

            $features[] = [
                'level' => $level,
                'name' => $name,
                'description' => trim($text),
                'is_optional' => $isOptional,
                'sources' => $sources,
                'sort_order' => $sortOrder++,
                'rolls' => $rolls,  // NEW
            ];
        }
    }

    return $features;
}
```

**Importer Enhancement:**

```php
// ClassImporter.php - importFeatures()
private function importFeatures(CharacterClass $class, array $features): void
{
    foreach ($features as $featureData) {
        $feature = ClassFeature::create([
            'class_id' => $class->id,
            'level' => $featureData['level'],
            'feature_name' => $featureData['name'],
            'is_optional' => $featureData['is_optional'],
            'description' => $featureData['description'],
            'sort_order' => $featureData['sort_order'],
        ]);

        // Existing: Import random tables from description
        $this->importRandomTablesFromText($feature, $featureData['description']);

        // NEW: Import roll formulas
        if (!empty($featureData['rolls'])) {
            foreach ($featureData['rolls'] as $roll) {
                ClassFeatureRoll::create([
                    'class_feature_id' => $feature->id,
                    'level' => $roll['level'],
                    'description' => $roll['description'],
                    'formula' => $roll['formula'],
                ]);
            }
        }
    }
}
```

**Impact:**
- Character sheets can show level-scaled feature values
- API can return structured progression data
- Frontend can render "at your level" calculations

---

## Issue 5: Equipment Parsing Issues

### Two Sub-Problems

#### 5.1: Item Relationships Not Created

**Problem:**
- Equipment descriptions stored as text
- No FK relationship to `items` table
- Can't query "which classes start with longswords"

**Example Current Behavior:**
```
| item_id | description          | is_choice | quantity |
|---------|---------------------|-----------|----------|
| NULL    | a rapier            | true      | 1        |
| NULL    | a shortsword        | true      | 1        |
```

**Proposed Solution:**

```php
// ClassImporter.php - importEquipment()
private function importEquipment(CharacterClass $class, array $equipmentData): void
{
    if (empty($equipmentData['items'])) {
        return;
    }

    $class->equipment()->delete();

    foreach ($equipmentData['items'] as $itemData) {
        // NEW: Try to match item by name
        $item = $this->matchItemByName($itemData['description']);

        $class->equipment()->create([
            'item_id' => $item?->id,  // FK if match found
            'description' => $itemData['description'],  // Keep raw text
            'is_choice' => $itemData['is_choice'],
            'quantity' => $itemData['quantity'],
            'choice_description' => $itemData['is_choice']
                ? 'Starting equipment choice'
                : null,
        ]);
    }
}

// NEW: Fuzzy item name matcher
private function matchItemByName(string $description): ?\App\Models\Item
{
    // Clean description: "a rapier" → "rapier"
    $cleaned = preg_replace('/^(?:a|an|the)\s+/i', '', $description);
    $cleaned = trim($cleaned);

    // Try exact match first
    $item = \App\Models\Item::whereRaw('LOWER(name) = ?', [strtolower($cleaned)])->first();
    if ($item) {
        return $item;
    }

    // Try partial match: "longbow and arrows (20)" → search for "longbow"
    $mainItem = preg_split('/\s+and\s+|\s+or\s+/i', $cleaned)[0];
    $mainItem = preg_replace('/\s*\([^)]*\)/', '', $mainItem);  // Remove parentheticals

    return \App\Models\Item::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($mainItem) . '%'])->first();
}
```

#### 5.2: Regex Extraction Too Aggressive

**Problem:**
Equipment choice regex removes too much text from descriptions, resulting in incorrect data stored.

**Example from `class-fighter-phb.xml`:**
```xml
• (a) chain mail or (b) leather armor, longbow, and arrows (20)
```

**Current Parser (lines 516-524):**
```php
if (preg_match_all('/\(([a-z])\)\s*([^()]+?)(?=\s+or\s+\(|\s*$)/i', $bulletText, $choices)) {
    // Extracts:
    // Choice (a): "chain mail "
    // Choice (b): "leather armor, longbow, and arrows "  <-- MISSING (20)!
}
```

**Proposed Fix:**

```php
// ClassXmlParser.php - parseEquipmentChoices()
private function parseEquipmentChoices(string $text): array
{
    $items = [];

    preg_match_all('/[•\-]\s*(.+?)(?=\n[•\-]|\n\n|$)/s', $text, $bullets);

    foreach ($bullets[1] as $bulletText) {
        $bulletText = trim($bulletText);

        // NEW: Improved choice regex that preserves trailing content
        // Matches: "(a) chain mail or (b) leather armor, longbow, and arrows (20)"
        // Groups: (a) → "chain mail", (b) → "leather armor, longbow, and arrows (20)"
        if (preg_match_all('/\(([a-z])\)\s*(.+?)(?=\s+or\s+\([a-z]\)|$)/is', $bulletText, $choices)) {
            foreach ($choices[2] as $choiceText) {
                // Don't strip parentheticals - they're part of the description
                $items[] = [
                    'description' => trim($choiceText),
                    'is_choice' => true,
                    'quantity' => 1,
                ];
            }
        } else {
            // Simple item - existing logic is OK
            $parts = preg_split('/\s+and\s+|,\s+and\s+/i', $bulletText);

            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part)) {
                    continue;
                }

                // Extract quantity: "four javelins" → quantity=4
                $quantity = 1;
                if (preg_match('/^(two|three|four|five|six|seven|eight|nine|ten)\s+/i', $part, $qtyMatch)) {
                    $quantity = $this->convertWordToNumber(strtolower($qtyMatch[1]));
                    $part = preg_replace('/^(two|three|four|five|six|seven|eight|nine|ten)\s+/i', '', $part);
                }

                $items[] = [
                    'description' => trim($part),
                    'is_choice' => false,
                    'quantity' => $quantity,
                ];
            }
        }
    }

    return $items;
}
```

**Test Cases to Verify:**
```php
// Input: "(a) chain mail or (b) leather armor, longbow, and arrows (20)"
// Expected:
[
    ['description' => 'chain mail', 'is_choice' => true, 'quantity' => 1],
    ['description' => 'leather armor, longbow, and arrows (20)', 'is_choice' => true, 'quantity' => 1],
]

// Input: "An explorer's pack, and four javelins"
// Expected:
[
    ['description' => "An explorer's pack", 'is_choice' => false, 'quantity' => 1],
    ['description' => 'javelins', 'is_choice' => false, 'quantity' => 4],
]
```

---

## Issue 6: Spell Slots Only for Subclasses

### Problem Description

Arcane Trickster (Rogue subclass) is the ONLY Rogue variant with spellcasting, but spell slots are defined at the class level with `optional="YES"` attribute:

```xml
<class>
  <name>Rogue</name>
  <spellAbility>Intelligence</spellAbility>
  <slotsReset>L</slotsReset>
  <autolevel level="3">
    <slots optional="YES">3,2</slots>  <!-- Arcane Trickster only -->
  </autolevel>
  <!-- ... levels 4-20 ... -->
</class>
```

**Current Behavior:**
- Base Rogue class gets `spellcasting_ability_id` populated
- Base Rogue class gets spell progression from levels 3-20
- This is incorrect - only Arcane Trickster should have these

### Cross-Class Verification

| Class | Spellcasting | optional="YES"? | Correct Behavior |
|-------|-------------|----------------|------------------|
| Wizard | All subclasses | NO | Base class has spell slots |
| Cleric | All subclasses | NO | Base class has spell slots |
| Rogue | **1 subclass only** | **YES** | **Subclass only should have slots** |
| Fighter | **1 subclass only** (Eldritch Knight) | **YES** | **Subclass only should have slots** |
| Barbarian | **0 subclasses** | N/A | No spell slots anywhere |

**Pattern:** `optional="YES"` means "this progression is only for a specific subclass"

### Proposed Solution

**Option A: Filter Optional Spell Slots from Base Class (Recommended)**

```php
// ClassXmlParser.php - parseSpellSlots()
private function parseSpellSlots(SimpleXMLElement $element): array
{
    $spellProgression = [];

    foreach ($element->autolevel as $autolevel) {
        $level = (int) $autolevel['level'];

        if (isset($autolevel->slots)) {
            // NEW: Check if slots are marked as optional (subclass-only)
            $isOptional = isset($autolevel->slots['optional'])
                && (string) $autolevel->slots['optional'] === 'YES';

            // Skip optional slots for base class - they'll be handled in subclass detection
            if ($isOptional) {
                continue;  // Don't add to base class progression
            }

            $slotsString = (string) $autolevel->slots;
            $slots = array_map('intval', explode(',', $slotsString));

            $progression = [
                'level' => $level,
                'cantrips_known' => $slots[0] ?? 0,
                'spell_slots_1st' => $slots[1] ?? 0,
                'spell_slots_2nd' => $slots[2] ?? 0,
                'spell_slots_3rd' => $slots[3] ?? 0,
                'spell_slots_4th' => $slots[4] ?? 0,
                'spell_slots_5th' => $slots[5] ?? 0,
                'spell_slots_6th' => $slots[6] ?? 0,
                'spell_slots_7th' => $slots[7] ?? 0,
                'spell_slots_8th' => $slots[8] ?? 0,
                'spell_slots_9th' => $slots[9] ?? 0,
            ];

            $spellProgression[] = $progression;
        }

        // Handle "Spells Known" counter ...
        // (existing logic unchanged)
    }

    return $spellProgression;
}
```

**Option B: Store Spell Progression at Subclass Level**

```php
// ClassXmlParser.php - detectSubclasses()
private function detectSubclasses(array $features, array $counters, SimpleXMLElement $element): array
{
    // ... existing subclass detection ...

    foreach ($subclasses as &$subclass) {
        // NEW: Check if this subclass has spellcasting
        if ($this->subclassHasSpellcasting($subclass['name'], $features)) {
            // Parse optional spell slots for this specific subclass
            $subclass['spell_progression'] = $this->parseOptionalSpellSlots($element);
        }
    }
    unset($subclass);

    return $subclasses;
}

private function subclassHasSpellcasting(string $subclassName, array $features): bool
{
    foreach ($features as $feature) {
        if (str_contains($feature['name'], $subclassName)
            && str_contains(strtolower($feature['name']), 'spellcasting')) {
            return true;
        }
    }
    return false;
}

private function parseOptionalSpellSlots(SimpleXMLElement $element): array
{
    $progression = [];

    foreach ($element->autolevel as $autolevel) {
        if (isset($autolevel->slots)) {
            $isOptional = isset($autolevel->slots['optional'])
                && (string) $autolevel->slots['optional'] === 'YES';

            if ($isOptional) {
                // Parse and add to subclass progression
                // ... (same parsing logic as base parseSpellSlots)
            }
        }
    }

    return $progression;
}
```

**Recommendation:** Use **Option A** (simpler, less refactoring). Option B is more architecturally pure but requires more changes.

### Also Handle `<spellAbility>` Tag

```php
// ClassXmlParser.php - parseClass()
private function parseClass(SimpleXMLElement $element): array
{
    $data = [
        'name' => (string) $element->name,
        'hit_die' => (int) $element->hd,
    ];

    // NEW: Only set spellcasting ability if class has non-optional spell slots
    $hasBaseSpellcasting = $this->hasNonOptionalSpellSlots($element);
    if ($hasBaseSpellcasting && isset($element->spellAbility)) {
        $data['spellcasting_ability'] = (string) $element->spellAbility;
    }

    // ... rest of parsing ...
}

private function hasNonOptionalSpellSlots(SimpleXMLElement $element): bool
{
    foreach ($element->autolevel as $autolevel) {
        if (isset($autolevel->slots)) {
            $isOptional = isset($autolevel->slots['optional'])
                && (string) $autolevel->slots['optional'] === 'YES';

            if (!$isOptional) {
                return true;  // Found at least one non-optional slot progression
            }
        }
    }
    return false;
}
```

**Impact:**
- Base Rogue class: NO spell slots, NO spellcasting ability
- Arcane Trickster subclass: YES spell slots, YES spellcasting ability (Intelligence)
- Character builders can correctly show spellcasting only for appropriate subclasses

---

## Testing Strategy

### Phase 1: Unit Tests

```php
// tests/Unit/Parsers/ClassXmlParserTest.php

#[Test]
public function it_filters_subclass_features_from_base_class()
{
    $xml = <<<XML
<compendium>
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <autolevel level="1">
      <feature>
        <name>Sneak Attack</name>
        <text>Base class feature</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Arcane Trickster</name>
        <text>Subclass intro</text>
      </feature>
      <feature optional="YES">
        <name>Spellcasting (Arcane Trickster)</name>
        <text>Subclass feature</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

    $parser = new ClassXmlParser();
    $result = $parser->parse($xml);

    // Base class should only have Sneak Attack
    $baseFeatures = array_filter($result[0]['features'], fn($f) => !$f['is_optional']);
    $this->assertCount(1, $baseFeatures);
    $this->assertEquals('Sneak Attack', $baseFeatures[0]['name']);

    // Subclass should have 2 features
    $this->assertCount(1, $result[0]['subclasses']);
    $this->assertEquals('Arcane Trickster', $result[0]['subclasses'][0]['name']);
    $this->assertCount(2, $result[0]['subclasses'][0]['features']);
}

#[Test]
public function it_groups_proficiency_choices_correctly()
{
    $xml = <<<XML
<compendium>
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <numSkills>4</numSkills>
    <proficiency>Dexterity, Intelligence, Acrobatics, Athletics, Deception</proficiency>
  </class>
</compendium>
XML;

    $parser = new ClassXmlParser();
    $result = $parser->parse($xml);

    $proficiencies = $result[0]['proficiencies'];

    // Should have 2 saving throws (not choices)
    $savingThrows = array_filter($proficiencies, fn($p) => $p['type'] === 'saving_throw');
    $this->assertCount(2, $savingThrows);

    // Should have 3 skills in a choice group
    $skills = array_filter($proficiencies, fn($p) => $p['type'] === 'skill');
    $this->assertCount(3, $skills);

    // All skills should share the same choice_group
    $choiceGroups = array_unique(array_column($skills, 'choice_group'));
    $this->assertCount(1, $choiceGroups);

    // All skills should have quantity=4 (choose 4)
    foreach ($skills as $skill) {
        $this->assertEquals(4, $skill['quantity']);
    }
}

#[Test]
public function it_ignores_optional_spell_slots_for_base_class()
{
    $xml = <<<XML
<compendium>
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <spellAbility>Intelligence</spellAbility>
    <autolevel level="3">
      <slots optional="YES">3,2</slots>
    </autolevel>
  </class>
</compendium>
XML;

    $parser = new ClassXmlParser();
    $result = $parser->parse($xml);

    // Base class should have NO spell progression
    $this->assertEmpty($result[0]['spell_progression']);

    // Base class should NOT have spellcasting ability set
    $this->assertArrayNotHasKey('spellcasting_ability', $result[0]);
}
```

### Phase 2: Integration Tests

```php
// tests/Feature/Importers/ClassImporterTest.php

#[Test]
public function it_imports_rogue_with_correct_subclass_separation()
{
    $xmlPath = base_path('import-files/class-rogue-phb.xml');
    $importer = new ClassImporter();

    $importer->importFile($xmlPath);

    // Base Rogue class
    $rogue = CharacterClass::where('slug', 'rogue')->first();
    $this->assertNotNull($rogue);
    $this->assertNull($rogue->spellcasting_ability_id);  // No spellcasting

    // Base class features should NOT include Arcane Trickster features
    $baseFeatures = $rogue->features;
    $this->assertFalse($baseFeatures->contains('feature_name', 'like', '%Arcane Trickster%'));

    // Arcane Trickster subclass
    $arcaneTrickster = CharacterClass::where('slug', 'rogue-arcane-trickster')->first();
    $this->assertNotNull($arcaneTrickster);
    $this->assertNotNull($arcaneTrickster->spellcasting_ability_id);  // Has spellcasting

    // Subclass should have spell progression
    $this->assertTrue($arcaneTrickster->levelProgression()->exists());
}

#[Test]
public function it_creates_proficiency_choice_groups()
{
    $xmlPath = base_path('import-files/class-rogue-phb.xml');
    $importer = new ClassImporter();

    $importer->importFile($xmlPath);

    $rogue = CharacterClass::where('slug', 'rogue')->first();

    // Should have saving throw proficiencies (Dex, Int)
    $savingThrows = $rogue->proficiencies()->where('type', 'saving_throw')->get();
    $this->assertCount(2, $savingThrows);

    // Should have skill proficiencies with choice_group
    $skills = $rogue->proficiencies()->where('type', 'skill')->get();
    $choiceGroups = $skills->pluck('choice_group')->unique()->values();

    // All skills should be in exactly 1 choice group
    $this->assertCount(1, $choiceGroups);
    $this->assertNotNull($choiceGroups[0]);

    // All skills should have quantity=4
    foreach ($skills as $skill) {
        $this->assertEquals(4, $skill->quantity);
    }
}
```

### Phase 3: Manual Verification

**Test XML Files:**
1. `class-rogue-phb.xml` - Arcane Trickster spellcasting
2. `class-fighter-phb.xml` - Eldritch Knight spellcasting
3. `class-wizard-phb.xml` - Full class spellcasting (no optional)
4. `class-barbarian-phb.xml` - No spellcasting at all

**Verification Checklist:**
- [ ] Base Rogue has no spell slots
- [ ] Base Rogue has no spellcasting ability
- [ ] Arcane Trickster has spell slots starting at level 3
- [ ] Arcane Trickster has Intelligence spellcasting ability
- [ ] No "(Arcane Trickster)" features in base Rogue
- [ ] Proficiency choice groups created correctly
- [ ] Equipment items matched to items table where possible
- [ ] Feature rolls stored in database
- [ ] Sneak Attack progression accessible via API

---

## Implementation Plan

### Week 1: Core Parsing Improvements
- **Day 1-2:** Issue #1 - Subclass feature filtering
- **Day 3:** Issue #6 - Optional spell slot handling
- **Day 4:** Issue #2 - Proficiency choice groups (migration + parser)
- **Day 5:** Unit tests + integration tests

### Week 2: Feature Enhancements
- **Day 1:** Issue #4 - Feature roll parsing and storage
- **Day 2:** Issue #5.1 - Equipment item matching
- **Day 3:** Issue #5.2 - Equipment regex fix
- **Day 4:** Issue #3 - Verify random table parsing (if needed)
- **Day 5:** Full manual verification + documentation update

### Dependencies
- None (all changes are additive or fix bugs)

### Breaking Changes
- **Database schema**: New tables/columns (requires migration)
- **API responses**: Proficiency structure will change (requires frontend update)

### Rollback Plan
- Keep existing parser logic in separate methods
- Use feature flags for new behavior
- Can revert migrations if issues found

---

## Success Criteria

1. ✅ Base Rogue class has zero spell slots
2. ✅ Arcane Trickster subclass has spell progression
3. ✅ No subclass features appear in base class
4. ✅ Proficiencies represent "choose N from X" correctly
5. ✅ Equipment items link to items table where possible
6. ✅ Sneak Attack progression accessible via structured data
7. ✅ All existing tests still pass
8. ✅ Manual verification of all 4 test classes successful

---

## Future Enhancements (Out of Scope)

1. **Character Builder Integration**
   - API endpoint: `GET /api/v1/classes/{id}/at-level/{level}`
   - Returns all features, proficiencies, spell slots available at specific level

2. **Subclass Spell Lists**
   - Some subclasses expand spell lists (e.g., Arcane Trickster "any wizard spell")
   - Would require `subclass_spell_restrictions` table

3. **Multiclass Spell Slot Calculation**
   - API helper to calculate total spell slots from multiple classes
   - Uses formula from PHB p. 164 (already in multiclass feature text)

4. **Feature Replacement Tracking**
   - Some features replace earlier features (e.g., "Sneak Attack (2)" replaces "Sneak Attack")
   - Add `replaces_feature_id` FK to track this

---

## Questions for Review

1. **Proficiency Choice Groups:** Should we use UUID for `choice_group` or an integer sequence?
2. **Equipment Matching:** Should we log when items can't be matched (for data quality review)?
3. **Spell Slot Ownership:** Prefer Option A (filter optional) or Option B (subclass-level parsing)?
4. **Feature Rolls:** Should we support formula evaluation (e.g., "1d6" → random roll) or just store strings?
5. **Backward Compatibility:** Do we need to support old API responses during transition period?

---

**Status:** Ready for team review and prioritization
**Estimated Effort:** 10-12 development days (2 weeks)
**Risk Level:** Medium (database schema changes, API contract changes)
