# Class Importer - Comprehensive Brainstorming & Implementation Plan

**Date:** 2025-11-19
**Status:** Planning Phase
**Priority:** ⭐ HIGHEST (CLAUDE.md recommendation)

---

## 📊 Current State Analysis

### Available Resources
- ✅ **35 XML files** ready to import (~16,810 lines total)
- ✅ **Database schema complete** (classes, class_features, class_level_progression, class_counters)
- ✅ **13 base classes seeded** in database
- ✅ **Hierarchical slug system** ready (`fighter-battle-master`)
- ✅ **7 reusable importer/parser traits** available
- ✅ **Polymorphic tables** ready (traits, proficiencies, modifiers, sources, etc.)

### XML Files Breakdown
```
13 Core Classes × Multiple Sources:
- Artificer (1 file - TCE)
- Barbarian (3 files - PHB, TCE, XGE)
- Bard (3 files - PHB, TCE, XGE)
- Cleric (4 files - PHB, TCE, XGE, DMG)
- Druid (3 files - PHB, TCE, XGE)
- Fighter (3 files - PHB, TCE, XGE)
- Monk (3 files - PHB, TCE, XGE)
- Paladin (4 files - PHB, TCE, XGE, DMG)
- Ranger (3 files - PHB, TCE, XGE)
- Rogue (3 files - PHB, TCE, XGE)
- Sorcerer (3 files - PHB, TCE, XGE)
- Warlock (3 files - PHB, TCE, XGE)
- Wizard (3 files - PHB, TCE, XGE)
+ 3 Sidekick classes (Expert, Spellcaster, Warrior)
```

---

## 🎯 XML Structure Analysis

### Base Class Elements
```xml
<class>
  <name>Fighter</name>
  <hd>10</hd>                                    <!-- Hit die -->
  <proficiency>Strength, Constitution, ...</proficiency>  <!-- Saving throws + skills -->
  <numSkills>2</numSkills>                       <!-- Number of skill choices -->
  <armor>Light Armor, Medium Armor, ...</armor>  <!-- Armor proficiencies -->
  <weapons>Simple Weapons, Martial Weapons</weapons>
  <tools>None</tools>
  <wealth>5d4x10</wealth>                        <!-- Starting gold -->
  <spellAbility>Intelligence</spellAbility>      <!-- For spellcasters -->
  <slotsReset>L</slotsReset>                     <!-- L=long rest, S=short rest -->

  <!-- Level progression -->
  <autolevel level="1">
    <feature optional="YES">
      <name>Starting Fighter</name>
      <text>...</text>
    </feature>
  </autolevel>

  <!-- Spell slots (for spellcasters) -->
  <autolevel level="3">
    <slots optional="YES">2,2</slots>            <!-- Cantrips, 1st level slots -->
  </autolevel>

  <!-- Counters (Ki, Rage, etc.) -->
  <autolevel level="3">
    <counter>
      <name>Superiority Die</name>
      <value>4</value>
      <reset>S</reset>
      <subclass>Battle Master</subclass>         <!-- Subclass-specific! -->
    </counter>
  </autolevel>

  <!-- Traits (flavor text) -->
  <trait>
    <name>Fighter</name>
    <text>A human in clanging plate armor...</text>
  </trait>

  <!-- Subclasses -->
  <!-- Martial Archetype: Battle Master -->
  <autolevel level="3">
    <feature optional="YES">
      <name>Martial Archetype: Battle Master</name>
      <text>Those who emulate the archetypal Battle Master...</text>
    </feature>
  </autolevel>
</class>
```

### Key Patterns Identified

**1. Subclass Naming Convention:**
- Pattern: `"[Subclass Type]: [Subclass Name]"` or `"[Feature Name] ([Subclass Name])"`
- Examples:
  - `"Martial Archetype: Battle Master"`
  - `"Primal Path: Path of the Berserker"`
  - `"Combat Superiority (Battle Master)"`

**2. Level Progression:**
- Features at specific levels (autolevel element)
- `optional="YES"` for subclass features and multiclass rules
- Sort order needed for multiple features at same level

**3. Counters:**
- Can be base class (Ki Points) or subclass-specific (Superiority Die)
- Include reset timing (S/L/null)
- `<subclass>` tag indicates subclass ownership

**4. Spell Slots:**
- Comma-separated: `"2,2"` = 2 cantrips, 2 1st-level slots
- `"2,4,3"` = 2 cantrips, 4 1st-level, 3 2nd-level
- Optional (Eldritch Knight, Arcane Trickster)

---

## 🏗️ Proposed Database Strategy

### What We Can Reuse (Existing Tables)

#### ✅ Polymorphic Tables (reference_type + reference_id)
```php
// Already working with Races, Backgrounds, Feats
character_traits        → Class flavor text
proficiencies          → Starting proficiencies
modifiers              → NOT NEEDED (classes don't have modifiers)
entity_sources         → Multi-source support (PHB + TCE + XGE)
entity_languages       → NOT COMMON (rare for classes)
entity_conditions      → NOT APPLICABLE
entity_prerequisites   → NOT APPLICABLE
random_tables          → Possibly for subclass tables?
```

#### ✅ Class-Specific Tables (Already Exist)
```php
classes                     → Base class + subclass records
class_features              → Features at each level
class_level_progression     → Spell slot progression
class_counters              → Ki, Rage, Superiority Dice, etc.
```

### Table Usage Strategy

**classes table:**
```php
Fighter (base class):
  - id: 1
  - name: "Fighter"
  - parent_class_id: NULL
  - hit_die: 10
  - description: "A human in clanging plate armor..."
  - slug: "fighter"

Battle Master (subclass):
  - id: 14
  - name: "Battle Master"
  - parent_class_id: 1  // Points to Fighter
  - hit_die: 10  // Inherited
  - description: "Those who emulate the archetypal Battle Master..."
  - slug: "fighter-battle-master"
```

**class_features table:**
```php
// Base class feature
{
  class_id: 1,  // Fighter
  level: 1,
  feature_name: "Fighting Style",
  is_optional: false,
  description: "You adopt a particular style...",
  sort_order: 0
}

// Subclass feature
{
  class_id: 14,  // Battle Master
  level: 3,
  feature_name: "Combat Superiority",
  is_optional: true,  // Subclass features are optional
  description: "When you choose this archetype...",
  sort_order: 0
}
```

**class_counters table:**
```php
// Monk Ki Points (base class)
{
  class_id: 6,  // Monk
  level: 2,
  counter_name: "Ki Points",
  counter_value: 2,
  reset_timing: 'S'  // Short rest
}

// Battle Master Superiority Die (subclass)
{
  class_id: 14,  // Battle Master subclass
  level: 3,
  counter_name: "Superiority Die",
  counter_value: 4,
  reset_timing: 'S'
}
```

**class_level_progression table:**
```php
// Only for spellcasters!
{
  class_id: 13,  // Wizard
  level: 1,
  cantrips_known: 3,
  spell_slots_1st: 2,
  spell_slots_2nd: NULL,
  // ...
}

// Eldritch Knight (partial caster subclass)
{
  class_id: 15,  // Battle Master subclass
  level: 3,
  cantrips_known: 2,
  spell_slots_1st: 2,
  // ...
}
```

**proficiencies table:**
```php
// Fighter starting proficiencies
{
  reference_type: "App\\Models\\CharacterClass",
  reference_id: 1,  // Fighter
  proficiency_type: "armor",
  proficiency_name: "Light Armor",
  proficiency_type_id: 1,  // FK to proficiency_types
  grants: true,
  is_choice: false
}

// Skill choices
{
  reference_type: "App\\Models\\CharacterClass",
  reference_id: 1,
  proficiency_type: "skill",
  proficiency_name: "Choose 2 from Acrobatics, Animal Handling...",
  grants: true,
  is_choice: true,
  quantity: 2
}
```

**character_traits table:**
```php
// Flavor text
{
  reference_type: "App\\Models\\CharacterClass",
  reference_id: 1,
  trait_name: "Fighter",
  trait_category: "description",
  description: "A human in clanging plate armor...",
  random_table_id: NULL
}

{
  reference_type: "App\\Models\\CharacterClass",
  reference_id: 1,
  trait_name: "Well-Rounded Specialists",
  trait_category: "description",
  description: "Fighters learn the basics...",
  random_table_id: NULL
}
```

---

## 🔄 Reusable Components

### Parser Traits (Already Built)
```php
✅ ParsesSourceCitations       → Extract "Source: PHB p. 70"
✅ MatchesProficiencyTypes     → Fuzzy match proficiencies
✅ MatchesLanguages            → NOT NEEDED for classes
```

### Importer Traits (Already Built)
```php
✅ ImportsSources              → entity_sources polymorphic
✅ ImportsTraits               → character_traits polymorphic
✅ ImportsProficiencies        → proficiencies polymorphic
```

### NEW Traits Needed
```php
❌ ParsesClassFeatures         → Extract features from <autolevel> elements
❌ ParsesSpellSlots            → Parse "2,3,2" format
❌ ParsesCounters              → Extract <counter> elements
❌ ImportsClassFeatures        → Save to class_features table
❌ ImportsSpellProgression     → Save to class_level_progression table
❌ ImportsCounters             → Save to class_counters table
```

---

## 🎬 Implementation Strategy

### Phase 1: Parser Foundation (2-3 hours)
**Goal:** Parse base class data from XML

1. **Create `ClassXmlParser`**
   - Parse basic class data (name, hit_die, proficiencies, etc.)
   - Extract traits (flavor text)
   - Parse starting proficiencies (armor, weapons, tools, skills)
   - Extract source citations from traits

2. **Tests:**
   - Unit tests for parser methods
   - Test with real Fighter XML
   - Verify all fields extracted correctly

**Deliverables:**
- `app/Services/Parsers/ClassXmlParser.php`
- `tests/Unit/Parsers/ClassXmlParserTest.php`

### Phase 2: Base Class Importer (2-3 hours)
**Goal:** Import base classes (NO subclasses yet)

1. **Create `ClassImporter`**
   - Use `ImportsSources` trait
   - Use `ImportsTraits` trait
   - Use `ImportsProficiencies` trait
   - Import base class to `classes` table
   - Import traits to `character_traits` table
   - Import proficiencies to `proficiencies` table
   - Import sources to `entity_sources` table

2. **Tests:**
   - Feature test for full import flow
   - Test reimport behavior (update existing)
   - Test proficiency matching

3. **Artisan Command:**
   - `php artisan import:classes {file}`

**Deliverables:**
- `app/Services/Importers/ClassImporter.php`
- `app/Console/Commands/ImportClassesCommand.php`
- `tests/Feature/Importers/ClassImporterTest.php`

### Phase 3: Feature Parsing & Import (3-4 hours)
**Goal:** Parse and import class features from autolevel elements

1. **Extend Parser:**
   - Parse `<autolevel>` elements
   - Extract `<feature>` elements
   - Handle `optional="YES"` flag
   - Extract source from feature text
   - Determine sort order for features at same level

2. **Extend Importer:**
   - Import to `class_features` table
   - Handle feature updates on reimport

3. **Tests:**
   - Test feature parsing at various levels
   - Test optional vs required features
   - Test sort order logic

**Deliverables:**
- Updated `ClassXmlParser`
- Updated `ClassImporter`
- Feature import tests

### Phase 4: Spell Slot Progression (1-2 hours)
**Goal:** Parse and import spell slot tables

1. **Extend Parser:**
   - Parse `<slots>` elements
   - Split comma-separated values
   - Map to cantrips + 9 spell levels
   - Handle optional slots (Eldritch Knight)

2. **Extend Importer:**
   - Import to `class_level_progression` table
   - Only for spellcasters

3. **Tests:**
   - Test slot parsing for full casters (Wizard)
   - Test optional slots (Fighter Eldritch Knight)
   - Test half-casters (Paladin, Ranger)

**Deliverables:**
- Spell slot parsing + import
- Tests for various caster types

### Phase 5: Counter Support (1-2 hours)
**Goal:** Parse and import resource counters (Ki, Rage, etc.)

1. **Extend Parser:**
   - Parse `<counter>` elements
   - Extract name, value, reset timing
   - Note `<subclass>` tag for Phase 6

2. **Extend Importer:**
   - Import to `class_counters` table
   - Skip subclass counters for now

3. **Tests:**
   - Test Monk Ki Points
   - Test Barbarian Rage
   - Test reset timing (S/L/null)

**Deliverables:**
- Counter parsing + import
- Tests for base class counters

### Phase 6: Subclass Support (4-6 hours) ⚠️ MOST COMPLEX
**Goal:** Parse and import subclasses as separate class records

1. **Subclass Detection Strategy:**
   ```php
   // Pattern matching for subclass features
   - "Martial Archetype: Battle Master" → Extract "Battle Master"
   - "Combat Superiority (Battle Master)" → Extract "Battle Master"
   - <counter><subclass>Battle Master</subclass></counter>

   // Group features by subclass
   - Build map: "Battle Master" → [features, counters, slots]
   ```

2. **Extend Parser:**
   - Detect subclass features by pattern matching
   - Group features/counters/slots by subclass
   - Return array of subclasses per base class

3. **Extend Importer:**
   - Create subclass records in `classes` table
   - Set `parent_class_id` to base class
   - Generate hierarchical slugs: `fighter-battle-master`
   - Import subclass features, counters, slots

4. **Tests:**
   - Test Fighter with 3 subclasses
   - Test Cleric with many subclasses
   - Test hierarchical slugs
   - Test parent_class_id relationships

**Deliverables:**
- Subclass detection + import
- Tests for multiple subclass scenarios

### Phase 7: Multi-Source Merging (2-3 hours)
**Goal:** Merge data from multiple XML files (PHB + TCE + XGE)

**Challenge:**
- Fighter appears in 3 files (PHB, TCE, XGE)
- PHB has base class + original subclasses
- TCE/XGE add NEW subclasses + optional features

**Strategy:**
```php
// Import order matters!
1. Import PHB first (base class + original subclasses)
2. Import TCE (adds new subclasses, optional features)
3. Import XGE (adds new subclasses, optional features)

// On reimport:
- Update base class description if changed
- Add new features (don't delete old ones)
- Add new subclasses
- Update existing subclass features
```

**Implementation:**
- Upsert classes by name
- Upsert features by (class_id, level, feature_name)
- Use `entity_sources` for multi-source tracking

**Tests:**
- Import Fighter PHB → verify data
- Import Fighter TCE → verify NEW subclasses added
- Import Fighter XGE → verify no data loss

**Deliverables:**
- Multi-file import strategy
- Tests for incremental updates

### Phase 8: API Support (1-2 hours)
**Goal:** Expose classes via API

1. **API Resource:**
   - ClassResource (with relationships)
   - Include features, counters, spell progression
   - Include proficiencies, traits, sources

2. **Controller:**
   - List classes (filterable)
   - Show class (with subclasses)
   - Show subclass (with parent)

3. **Tests:**
   - API response structure tests
   - Test relationships loaded
   - Test filtering/pagination

**Deliverables:**
- `app/Http/Resources/ClassResource.php`
- `app/Http/Controllers/Api/ClassController.php`
- `tests/Feature/Api/ClassApiTest.php`

---

## ⏱️ Time Estimates

| Phase | Task | Estimated Time |
|-------|------|----------------|
| 1 | Parser Foundation | 2-3 hours |
| 2 | Base Class Importer | 2-3 hours |
| 3 | Feature Parsing | 3-4 hours |
| 4 | Spell Slot Progression | 1-2 hours |
| 5 | Counter Support | 1-2 hours |
| 6 | Subclass Support | 4-6 hours |
| 7 | Multi-Source Merging | 2-3 hours |
| 8 | API Support | 1-2 hours |
| **TOTAL** | **Full Implementation** | **16-25 hours** |

**With existing traits and patterns: ~12-18 hours realistically**

---

## 🚀 Quick Wins Strategy

### Option A: Vertical Slice (RECOMMENDED)
**Goal:** Get ONE complete class working end-to-end

1. Parse + Import Fighter (base class only)
2. Add features for Fighter
3. Add spell slots for Eldritch Knight
4. Add counters for Battle Master
5. Add subclasses (3 total)
6. Add API support
7. Repeat for other 12 classes

**Pros:** Shows progress fast, validates approach
**Cons:** Subclass logic is complex upfront

### Option B: Horizontal Layers
**Goal:** Build parser → importer → API layer by layer

1. Parse ALL classes (base data only)
2. Import ALL base classes
3. Add features to ALL classes
4. Add subclasses to ALL classes
5. Add API support

**Pros:** Less context switching
**Cons:** No visible progress until later phases

**RECOMMENDATION: Option A (Vertical Slice)**

---

## 🎯 Success Criteria

**Before marking ANY phase complete:**
- [ ] TDD followed (tests written first)
- [ ] All new tests pass
- [ ] Full test suite passes (no regressions)
- [ ] Code formatted with Pint
- [ ] Verified with real XML import
- [ ] API returns correct data structure

---

## 📝 Data Migration Considerations

### Existing Seeded Classes
```php
// 13 base classes already seeded via CharacterClassSeeder
// Strategy: KEEP them, upsert by name
// Benefit: Existing relationships preserved
```

### Import Command Usage
```bash
# Import all base classes first
for file in import-files/class-*-phb.xml; do
  php artisan import:classes "$file"
done

# Then add TCE/XGE subclasses
for file in import-files/class-*-tce.xml; do
  php artisan import:classes "$file"
done

for file in import-files/class-*-xge.xml; do
  php artisan import:classes "$file"
done
```

---

## 🔥 Potential Challenges

### 1. Subclass Detection
**Challenge:** XML doesn't explicitly mark subclasses
**Solution:** Pattern matching on feature names + `<subclass>` tags in counters

### 2. Multi-Source Merging
**Challenge:** Same class in 3 files, need intelligent merging
**Solution:** Upsert strategy + entity_sources tracking

### 3. Optional Features
**Challenge:** Multiclass rules, variant features, subclass-specific
**Solution:** `is_optional` flag in class_features table

### 4. Spell Slot Parsing
**Challenge:** Comma-separated format, optional slots
**Solution:** Regex parsing + conditional import (only if slots exist)

### 5. Counter Reset Timing
**Challenge:** Some counters reset on long rest, some short, some never
**Solution:** `reset_timing` column (L/S/null)

### 6. Feature Sort Order
**Challenge:** Multiple features at same level need display order
**Solution:** `sort_order` column, increment per level

---

## 📚 Key Learnings from Other Importers

### From RaceImporter:
✅ Use polymorphic tables extensively
✅ Upsert by slug for idempotency
✅ Clear and recreate relationships on reimport
✅ Use traits for DRY code

### From BackgroundImporter:
✅ Parse embedded random tables
✅ Extract source citations from text
✅ Handle choice-based proficiencies (quantity field)

### From FeatImporter:
✅ Parse modifiers from XML elements
✅ Handle prerequisites
✅ Support optional features

### From ItemImporter:
✅ Parse abilities with roll formulas
✅ Handle complex XML structures
✅ Multi-source citation handling

---

## 💡 Recommendations

### Must Haves:
1. **Start with Fighter** (good test case - has subclasses, spell slots, counters)
2. **Use TDD religiously** (tests first, always)
3. **Reuse existing traits** (ImportsSources, ImportsTraits, ImportsProficiencies)
4. **Incremental commits** (one phase at a time)

### Nice to Haves (Later):
- Spell list associations (which spells each class can learn)
- Equipment starting options parsing
- Multiclass prerequisite parsing (Str 13 or Dex 13)

### Out of Scope (For Now):
- Class spell lists (separate feature)
- Homebrew class support
- Character builder integration

---

## 🎉 End Result

**After full implementation:**
```bash
php artisan import:classes import-files/class-fighter-phb.xml
# Imports Fighter + 3 subclasses (Battle Master, Champion, Eldritch Knight)
# Creates 4 class records, 60+ features, 20 spell slot records, 40+ counter records
# All with multi-source tracking, proficiencies, traits

# API response:
GET /api/v1/classes/fighter
{
  "id": 1,
  "name": "Fighter",
  "slug": "fighter",
  "hit_die": 10,
  "subclasses": [
    {"id": 14, "name": "Battle Master", "slug": "fighter-battle-master"},
    {"id": 15, "name": "Champion", "slug": "fighter-champion"},
    {"id": 16, "name": "Eldritch Knight", "slug": "fighter-eldritch-knight"}
  ],
  "features": [...],
  "proficiencies": [...],
  "traits": [...],
  "sources": [...]
}
```

**Total entities imported:** ~13 base classes + ~50 subclasses + ~2000 features + ~400 spell progression records + ~300 counters

---

## ✅ Next Steps

1. **Review this brainstorm with user**
2. **Get approval on approach**
3. **Start Phase 1: Parser Foundation**
4. **Follow TDD strictly**
5. **Commit after each phase**

**Let's build this! 🚀**
