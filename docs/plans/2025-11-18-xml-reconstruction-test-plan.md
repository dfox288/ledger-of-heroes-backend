# XML Reconstruction Test Plan

**Date:** 2025-11-18
**Purpose:** Verify import completeness by reconstructing XML from database
**Status:** Planning

---

## Overview

The goal of this test is to verify that our importers capture **all** XML attributes by attempting to reconstruct the original XML from imported database records. Any missing or incorrect data reveals gaps in our import process.

### Methodology

1. **Parse XML** → Import to database
2. **Query database** → Reconstruct XML
3. **Compare** → Original XML vs. Reconstructed XML
4. **Report** → Missing attributes, incorrect values, structural differences

---

## Test Structure

### Phase 1: Spell XML Reconstruction ✅ (Ready to implement)

**Test File:** `tests/Feature/Importers/SpellXmlReconstructionTest.php`

#### What to Test:

1. **Core Spell Attributes**
   - ✓ Name
   - ✓ Level (0-9)
   - ✓ School (A, C, D, EN, EV, I, N, T)
   - ✓ Casting time
   - ✓ Range
   - ✓ Components (V, S, M)
   - ✓ Material components (if M present)
   - ✓ Duration
   - ✓ Concentration flag
   - ✓ Ritual flag

2. **Text Content**
   - ✓ Description text (combined from multiple `<text>` elements)
   - ✓ Higher levels text (extracted from "At Higher Levels" sections)
   - ✓ Source citations (extracted and stored in entity_sources)

3. **Spell Effects (Roll Elements)**
   - ✓ Effect description
   - ✓ Dice formula (e.g., "1d6", "2d8+5")
   - ✓ Effect type (damage, healing, other)
   - ✓ Damage type (if damage effect)
   - ✓ Scaling type (none, character_level, spell_slot_level)
   - ✓ Min character level (for cantrip scaling)
   - ✓ Min spell slot (for spell slot scaling)

4. **Class Associations**
   - ✓ Classes list (comma-separated, stripped of subclass parentheticals)
   - ✓ Base class extraction ("Fighter (Eldritch Knight)" → "Fighter")

5. **Multi-Source Citations**
   - ✓ Multiple sources parsed correctly
   - ✓ Source codes (PHB, DMG, XGE, TCE, MM, VGTM)
   - ✓ Page numbers

#### Test Cases:

```php
#[Test]
public function it_reconstructs_simple_cantrip()
{
    // Import: Acid Splash (cantrip with level-based scaling)
    // Verify: All <roll> elements, character level scaling
}

#[Test]
public function it_reconstructs_concentration_spell()
{
    // Import: Spell with concentration
    // Verify: needs_concentration = true, duration contains "Concentration"
}

#[Test]
public function it_reconstructs_ritual_spell()
{
    // Import: Alarm (ritual spell)
    // Verify: is_ritual = true, ritual tag present
}

#[Test]
public function it_reconstructs_spell_with_material_components()
{
    // Import: Spell with M component
    // Verify: material_components extracted from "M (description)"
}

#[Test]
public function it_reconstructs_spell_with_multiple_sources()
{
    // Import: Spell appearing in PHB and TCE
    // Verify: Multiple entity_sources records with different pages
}

#[Test]
public function it_reconstructs_spell_effects_with_damage_types()
{
    // Import: Fireball (damage + scaling)
    // Verify: damage_type_id set, spell slot scaling
}

#[Test]
public function it_reconstructs_class_associations()
{
    // Import: Spell with subclass notation "Fighter (Eldritch Knight)"
    // Verify: Base class "Fighter" associated, subclass stripped
}
```

#### Reconstruction Algorithm:

```php
private function reconstructSpellXml(Spell $spell): string
{
    $xml = '<spell>';
    $xml .= "<name>{$spell->name}</name>";
    $xml .= "<level>{$spell->level}</level>";
    $xml .= "<school>{$spell->spellSchool->code}</school>";

    if ($spell->is_ritual) {
        $xml .= '<ritual>YES</ritual>';
    }

    $xml .= "<time>{$spell->casting_time}</time>";
    $xml .= "<range>{$spell->range}</range>";

    // Reconstruct components
    $components = $spell->components;
    if ($spell->material_components) {
        $components = preg_replace('/M/', "M ({$spell->material_components})", $components);
    }
    $xml .= "<components>{$components}</components>";

    $xml .= "<duration>{$spell->duration}</duration>";

    // Reconstruct classes (include School: prefix if original had it)
    $classes = $spell->classes->pluck('name')->join(', ');
    $xml .= "<classes>{$classes}</classes>";

    // Reconstruct text with source
    $text = $spell->description;
    if ($spell->higher_levels) {
        $text .= "\n\n" . $spell->higher_levels;
    }

    // Add sources
    foreach ($spell->sources as $entitySource) {
        $source = $entitySource->source;
        $text .= "\n\nSource:\t{$source->name} ({$source->publication_year}) p. {$entitySource->pages}";
    }

    $xml .= "<text>{$text}</text>";

    // Reconstruct roll elements
    foreach ($spell->effects as $effect) {
        $level = $effect->min_character_level ?? $effect->min_spell_slot ?? '';
        $xml .= "<roll description=\"{$effect->description}\"";
        if ($level) {
            $xml .= " level=\"{$level}\"";
        }
        $xml .= ">{$effect->dice_formula}</roll>";
    }

    $xml .= '</spell>';

    return $xml;
}
```

#### Success Criteria:

- ✅ All core spell attributes match
- ✅ Text content matches (ignoring whitespace differences)
- ✅ All `<roll>` elements reconstructed with correct level attributes
- ✅ Source citations match (code + pages)
- ✅ Class associations match base class names
- ⚠️  **Expected Gaps:**
  - "School: X," prefix in classes (parser strips this)
  - Exact whitespace/newlines (normalized during import)
  - XML entity escaping (handled by XML library)

---

### Phase 2: Race XML Reconstruction ✅ (Ready to implement)

**Test File:** `tests/Feature/Importers/RaceXmlReconstructionTest.php`

#### What to Test:

1. **Core Race Attributes**
   - ✓ Name (including subrace notation "Dwarf (Hill)")
   - ✓ Base race name (for subraces)
   - ✓ Size code (M, S, L, etc.)
   - ✓ Speed (in feet)

2. **Ability Score Increases**
   - ✓ Ability code (STR, DEX, CON, INT, WIS, CHA)
   - ✓ Modifier value (+1, +2, etc.)
   - ✓ Multiple abilities (e.g., "Str +2, Cha +1")

3. **Traits**
   - ✓ Trait name
   - ✓ Trait category ("description" vs. specific features)
   - ✓ Trait text
   - ✓ Sort order (for consistent ordering)

4. **Proficiencies**
   - ✓ Proficiency type (skill, weapon, armor, tool)
   - ✓ Skill name (mapped to skills table)
   - ✓ Weapon/armor names (stored as proficiency_name)

5. **Random Tables**
   - ✓ Table name (from roll description)
   - ✓ Dice type (d6, d8, etc.)
   - ✓ Associated trait (via reference_type/reference_id)
   - ⚠️  **Known Gap:** Random table entries are embedded in trait text, not parsed separately

6. **Multi-Source Citations**
   - ✓ Multiple sources for races
   - ✓ Source codes and pages

#### Test Cases:

```php
#[Test]
public function it_reconstructs_simple_race()
{
    // Import: Dragonborn (no subrace)
    // Verify: Base race, ability bonuses, traits
}

#[Test]
public function it_reconstructs_subrace()
{
    // Import: Hill Dwarf (subrace)
    // Verify: parent_race_id set, name format "Dwarf (Hill)"
}

#[Test]
public function it_reconstructs_ability_bonuses()
{
    // Import: "Str +2, Cha +1"
    // Verify: Two modifiers created with correct ability_score_id
}

#[Test]
public function it_reconstructs_proficiencies()
{
    // Import: Skills, weapons, armor
    // Verify: Correct proficiency_type, skill_id or proficiency_name
}

#[Test]
public function it_reconstructs_traits_with_categories()
{
    // Import: Description trait vs. feature trait
    // Verify: category field set correctly
}

#[Test]
public function it_reconstructs_random_table_references()
{
    // Import: Trait with <roll> element
    // Verify: RandomTable created, trait.random_table_id set
}
```

#### Reconstruction Algorithm:

```php
private function reconstructRaceXml(Race $race): string
{
    $xml = '<race>';
    $xml .= "<name>{$race->name}</name>";
    $xml .= "<size>{$race->size->code}</size>";
    $xml .= "<speed>{$race->speed}</speed>";

    // Reconstruct ability bonuses
    $abilities = $race->modifiers()
        ->where('modifier_category', 'ability_score')
        ->with('abilityScore')
        ->get()
        ->map(fn($m) => $m->abilityScore->code . ' ' . $m->value)
        ->join(', ');

    if ($abilities) {
        $xml .= "<ability>{$abilities}</ability>";
    }

    // Reconstruct proficiencies
    foreach ($race->proficiencies as $prof) {
        $xml .= '<proficiency>';
        if ($prof->skill) {
            $xml .= $prof->skill->name;
        } else {
            $xml .= $prof->proficiency_name;
        }
        $xml .= '</proficiency>';
    }

    // Reconstruct traits (sorted by sort_order)
    foreach ($race->traits()->orderBy('sort_order')->get() as $trait) {
        $category = $trait->category ? " category=\"{$trait->category}\"" : '';
        $xml .= "<trait{$category}>";
        $xml .= "<name>{$trait->name}</name>";
        $xml .= "<text>{$trait->description}";

        // Add source if last trait
        // (Note: Sources are on race, not individual traits)

        $xml .= "</text>";

        // Add roll if trait has random table
        if ($trait->randomTable) {
            $xml .= "<roll>{$trait->randomTable->dice_type}</roll>";
        }

        $xml .= '</trait>';
    }

    $xml .= '</race>';

    return $xml;
}
```

#### Success Criteria:

- ✅ Race name and size match
- ✅ Ability bonuses reconstructed correctly
- ✅ All traits present in correct order
- ✅ Proficiencies captured
- ⚠️  **Expected Gaps:**
  - Random table entries (not yet parsed from trait text)
  - Exact trait text formatting (may differ in whitespace)

---

### Phase 3: Coverage Analysis (After Reconstruction Tests)

**Test File:** `tests/Feature/Importers/XmlCoverageAnalysisTest.php`

#### What to Measure:

1. **Attribute Coverage**
   - % of XML attributes successfully imported
   - List of missing/unsupported attributes
   - List of attributes not reconstructable

2. **Data Fidelity**
   - % of values that match exactly
   - % of values that match semantically (ignoring whitespace)
   - List of data transformations applied

3. **Relationship Completeness**
   - % of relationships preserved
   - Missing relationship data

#### Test Cases:

```php
#[Test]
public function it_reports_spell_xml_coverage()
{
    // Import all PHB spells
    // Reconstruct all spells
    // Compare attributes
    // Generate coverage report
}

#[Test]
public function it_identifies_missing_spell_attributes()
{
    // Parse sample spell with all possible attributes
    // Check which attributes are lost during import
    // Report missing mappings
}

#[Test]
public function it_reports_race_xml_coverage()
{
    // Same as spells, but for races
}
```

#### Coverage Report Format:

```
XML Reconstruction Coverage Report
===================================

Spells (361 tested):
- Core attributes: 100% (10/10)
- Text content: 95% (source citations extracted separately)
- Roll elements: 90% (damage types not always captured)
- Class associations: 100%
- Multi-source: 100%

Gaps Identified:
1. "At Higher Levels" text not always parsed from description
2. Some damage types not mapped (e.g., "psychic" in older XML)
3. School prefix in classes stripped (intentional)

Races (19 tested):
- Core attributes: 100% (4/4)
- Ability bonuses: 100%
- Traits: 95% (category field sometimes missing in XML)
- Proficiencies: 90% (tools not always distinguished from weapons)
- Random tables: 50% (table entries not parsed)

Gaps Identified:
1. Random table entries embedded in trait text (not structured)
2. Language proficiencies treated as text, not structured data
3. Some tool proficiencies lack type classification
```

---

## Implementation Order

### Step 1: Create Test Helper Traits
**File:** `tests/TestCase.php` or dedicated trait

```php
trait ReconstructsXml
{
    protected function reconstructSpellXml(Spell $spell): SimpleXMLElement;
    protected function reconstructRaceXml(Race $race): SimpleXMLElement;
    protected function compareXmlElements(SimpleXMLElement $original, SimpleXMLElement $reconstructed): array;
    protected function normalizeWhitespace(string $text): string;
}
```

### Step 2: Implement Spell Reconstruction Tests
1. Create `SpellXmlReconstructionTest.php`
2. Implement 7 test cases (cantrip, concentration, ritual, material, multi-source, damage, classes)
3. Run tests, identify gaps
4. Document findings

### Step 3: Implement Race Reconstruction Tests
1. Create `RaceXmlReconstructionTest.php`
2. Implement 6 test cases (simple race, subrace, abilities, proficiencies, traits, random tables)
3. Run tests, identify gaps
4. Document findings

### Step 4: Coverage Analysis
1. Create `XmlCoverageAnalysisTest.php`
2. Run bulk reconstruction tests
3. Generate coverage reports
4. Identify systematic gaps

### Step 5: Gap Resolution
1. Review identified gaps
2. Determine which are acceptable (design decisions) vs. bugs
3. Update importers/parsers to fix bugs
4. Document intentional gaps in CLAUDE.md

---

## Success Metrics

### Minimum Acceptable Coverage:
- **Core attributes:** 100% (name, level, school for spells; name, size, speed for races)
- **Relationships:** 95% (classes, sources, traits, proficiencies)
- **Text content:** 90% (some whitespace normalization expected)
- **Effects/Modifiers:** 85% (complex parsing edge cases acceptable)

### Excellent Coverage:
- **Core attributes:** 100%
- **Relationships:** 98%
- **Text content:** 95%
- **Effects/Modifiers:** 90%

---

## Expected Findings (Hypotheses)

### Likely Gaps in Spell Import:
1. ❓ "At Higher Levels" text not always separated from main description
2. ❓ Some spell effect damage types not mapped (missing from damage_types seed)
3. ❓ Complex scaling patterns not fully captured (e.g., "1d6 per slot level")
4. ✅ School prefix in classes intentionally stripped
5. ✅ Whitespace normalization intentional

### Likely Gaps in Race Import:
1. ❓ Random table entries not parsed (embedded in trait text)
2. ❓ Languages treated as proficiency_name, not dedicated field
3. ❓ Tool proficiencies lack detailed classification
4. ❓ Darkvision range not captured as structured data (in trait text)
5. ✅ Trait category inferred, not always in XML

---

## Next Steps After Testing

1. **Document gaps** in CLAUDE.md under "Known Limitations"
2. **Prioritize fixes:**
   - P0: Missing core attributes (blocks API)
   - P1: Missing relationships (degraded API)
   - P2: Text parsing improvements (nice to have)
   - P3: Whitespace/formatting (cosmetic)

3. **Update parsers** for P0/P1 issues
4. **Re-run tests** to verify fixes
5. **Apply same pattern** to future importers (Items, Monsters, etc.)

---

## Test Execution

```bash
# Run spell reconstruction tests
docker compose exec php php artisan test --filter=SpellXmlReconstruction

# Run race reconstruction tests
docker compose exec php php artisan test --filter=RaceXmlReconstruction

# Run coverage analysis
docker compose exec php php artisan test --filter=XmlCoverageAnalysis

# Run all reconstruction tests
docker compose exec php php artisan test tests/Feature/Importers/
```

---

## Benefits of This Approach

1. **Systematic verification** - Tests every imported attribute
2. **Objective metrics** - Coverage percentages, not subjective "looks good"
3. **Future-proof** - Same pattern applies to all entity types
4. **Regression prevention** - Tests fail if parser loses data
5. **Documentation** - Test code documents expected XML structure
6. **Debugging aid** - Reconstruction failures pinpoint exact missing data

---

**This test plan ensures our importers are production-ready before scaling to all 86 XML files.** 🧪
