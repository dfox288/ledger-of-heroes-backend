# XML Reconstruction Tests - Comprehensive Expansion Plan

**Date:** 2025-11-20
**Branch:** `main` (work directly on main, or create feature branch if preferred)
**Status:** Ready for parallel execution
**Estimated Duration:** 8-12 hours total (2-3 hours per parallel agent)
**Agents Required:** 4 parallel agents for maximum efficiency

---

## Overview

Expand XML reconstruction test coverage from 4/6 importers (67%) to 6/6 (100%), and add tests for recently-added features (languages, prerequisites). This ensures complete round-trip verification that no data is lost during XML import.

**Current State:**
- ✅ SpellXmlReconstructionTest (8 tests)
- ✅ RaceXmlReconstructionTest (8 tests)
- ✅ ItemXmlReconstructionTest (14 tests)
- ✅ BackgroundXmlReconstructionTest (6 tests)
- ❌ ClassXmlReconstructionTest (MISSING)
- ❌ FeatXmlReconstructionTest (MISSING)

**Target State:**
- All 6 importers have reconstruction tests
- Languages verified in Race + Background tests
- Prerequisites verified in Feat + Item tests
- All incomplete tests fixed
- ~60 total reconstruction tests (+67% coverage)

---

## Parallel Execution Strategy

**4 Independent Agents (can run simultaneously):**

1. **Agent 1** - FeatXmlReconstructionTest (Priority 1, ~6-8 tests)
2. **Agent 2** - ClassXmlReconstructionTest (Priority 1, ~8-10 tests)
3. **Agent 3** - Language tests + Race/Background enhancements (Priority 2, ~4 tests)
4. **Agent 4** - Item prerequisites + fix incomplete test (Priority 3, ~3 tests)

**Dependencies:** None - all agents work on independent test files or test methods within different files.

---

## Agent 1: FeatXmlReconstructionTest

**Goal:** Create comprehensive reconstruction tests for FeatImporter covering prerequisites, modifiers, proficiencies, conditions.

**File:** `tests/Feature/Importers/FeatXmlReconstructionTest.php`

### Task 1.1: Create test file structure
**Command:**
```bash
# No command - will use Write tool
```

**File:** `tests/Feature/Importers/FeatXmlReconstructionTest.php`
**Content:**
```php
<?php

namespace Tests\Feature\Importers;

use App\Models\Feat;
use App\Services\Importers\FeatImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookup data

    private FeatImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new FeatImporter;
    }

    // Tests go here...

    private function createTempXmlFile(string $xmlContent): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'feat_test_');
        file_put_contents($tempFile, $xmlContent);
        return $tempFile;
    }
}
```

### Task 1.2: Test basic feat reconstruction
**Test:** `it_reconstructs_simple_feat`

**XML Example:**
```xml
<feat>
  <name>Alert</name>
  <text>Always on the lookout for danger, you gain the following benefits:

  • You gain a +5 bonus to initiative.
  • You can't be surprised while you are conscious.
  • Other creatures don't gain advantage on attack rolls against you as a result of being unseen by you.

  Source: Player's Handbook (2014) p. 165</text>
  <modifier category="bonus">initiative +5</modifier>
</feat>
```

**Assertions:**
- Name, slug, description imported
- Modifier created (initiative +5)
- Source citation imported
- No prerequisites

### Task 1.3: Test ability score prerequisites
**Test:** `it_reconstructs_feat_with_ability_prerequisite`

**XML Example:**
```xml
<feat>
  <name>Grappler</name>
  <prerequisite>Strength 13 or higher</prerequisite>
  <text>You've developed skills to aid you in grappling.

  Source: Player's Handbook (2014) p. 167</text>
</feat>
```

**Assertions:**
- Prerequisites text stored
- EntityPrerequisite record created with ability_score type
- ability_score_id = Strength
- value = 13

### Task 1.4: Test dual ability score prerequisites
**Test:** `it_reconstructs_feat_with_dual_ability_prerequisite`

**XML Example:**
```xml
<feat>
  <name>Observant</name>
  <prerequisite>Intelligence or Wisdom 13 or higher</prerequisite>
  <text>Quick to notice details of your environment.

  Source: Player's Handbook (2014) p. 168</text>
  <modifier category="ability score">Intelligence +1</modifier>
  <modifier category="ability score">Wisdom +1</modifier>
</feat>
```

**Assertions:**
- 2 EntityPrerequisite records (same group_id = OR logic)
- Both have value = 13
- Modifiers for INT +1 and WIS +1

### Task 1.5: Test race prerequisites
**Test:** `it_reconstructs_feat_with_race_prerequisites`

**XML Example:**
```xml
<feat>
  <name>Dwarven Fortitude</name>
  <prerequisite>Dwarf</prerequisite>
  <text>You have the blood of dwarf heroes flowing through your veins.

  Source: Xanathar's Guide to Everything (2017) p. 74</text>
</feat>
```

**Assertions:**
- EntityPrerequisite with race_id pointing to Dwarf
- Race prerequisite type

### Task 1.6: Test multiple race prerequisites (OR logic)
**Test:** `it_reconstructs_feat_with_multiple_race_prerequisites`

**XML Example:**
```xml
<feat>
  <name>Squat Nimbleness</name>
  <prerequisite>Dwarf, Gnome, Halfling</prerequisite>
  <text>You are uncommonly nimble for your race.

  Source: Xanathar's Guide to Everything (2017) p. 75</text>
</feat>
```

**Assertions:**
- 3 EntityPrerequisite records with same group_id (OR logic)
- All have race prerequisite type

### Task 1.7: Test proficiency prerequisites
**Test:** `it_reconstructs_feat_with_proficiency_prerequisite`

**XML Example:**
```xml
<feat>
  <name>Medium Armor Master</name>
  <prerequisite>Proficiency with medium armor</prerequisite>
  <text>You have practiced moving in medium armor.

  Source: Player's Handbook (2014) p. 168</text>
</feat>
```

**Assertions:**
- EntityPrerequisite with proficiency_type_id
- Proficiency type = "Medium Armor"

### Task 1.8: Test feat with proficiencies granted
**Test:** `it_reconstructs_feat_with_proficiencies`

**XML Example:**
```xml
<feat>
  <name>Weapon Master</name>
  <text>You have practiced extensively with a variety of weapons, gaining the following benefits:

  • Increase your Strength or Dexterity score by 1, to a maximum of 20.
  • You gain proficiency with four weapons of your choice.

  Proficiency: longsword, greatsword, longbow, heavy crossbow

  Source: Player's Handbook (2014) p. 170</text>
  <proficiency>longsword, greatsword, longbow, heavy crossbow</proficiency>
</feat>
```

**Assertions:**
- 4 Proficiency records created
- All linked to feat via proficiencies() relationship

### Task 1.9: Test feat with conditions
**Test:** `it_reconstructs_feat_with_conditions`

**XML Example:**
```xml
<feat>
  <name>Elven Accuracy</name>
  <prerequisite>Elf or Half-Elf</prerequisite>
  <text>When you have advantage on an attack roll using Dexterity, Intelligence, Wisdom, or Charisma, you can reroll one of the dice once.

  Source: Xanathar's Guide to Everything (2017) p. 74</text>
</feat>
```

**Assertions:**
- Conditions imported if parser supports (check EntityCondition model)

### Task 1.10: Run tests and verify
**Commands:**
```bash
docker compose exec php php artisan test --filter=FeatXmlReconstructionTest
docker compose exec php ./vendor/bin/pint tests/Feature/Importers/FeatXmlReconstructionTest.php
```

**Success Criteria:**
- All tests pass
- Code formatted with Pint
- No regressions in other tests

---

## Agent 2: ClassXmlReconstructionTest

**Goal:** Create comprehensive reconstruction tests for ClassImporter covering base classes, subclasses, features, progression, counters.

**File:** `tests/Feature/Importers/ClassXmlReconstructionTest.php`

### Task 2.1: Create test file structure
**File:** `tests/Feature/Importers/ClassXmlReconstructionTest.php`

**Content:**
```php
<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookup data (ability scores)

    private ClassImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ClassImporter;
    }

    // Tests go here...

    private function createTempXmlFile(string $xmlContent): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'class_test_');
        file_put_contents($tempFile, $xmlContent);
        return $tempFile;
    }
}
```

### Task 2.2: Test base class reconstruction
**Test:** `it_reconstructs_simple_base_class`

**XML Example:**
```xml
<class>
  <name>Fighter</name>
  <hd>10</hd>
  <proficiency>All armor, shields, simple weapons, martial weapons</proficiency>
  <spellAbility></spellAbility>
  <autolevel level="1">
    <feature>
      <name>Fighting Style</name>
      <text>You adopt a particular style of fighting as your specialty.

Source: Player's Handbook (2014) p. 70</text>
    </feature>
  </autolevel>
</class>
```

**Assertions:**
- name = "Fighter", slug = "fighter"
- hit_die = 10
- parent_class_id = null (base class)
- proficiencies created (armor, weapons)
- features created with level = 1
- sources imported

### Task 2.3: Test subclass with parent relationship
**Test:** `it_reconstructs_subclass_with_parent`

**XML Example:**
```xml
<class>
  <name>Fighter (Battle Master)</name>
  <hd>0</hd>
  <autolevel level="3">
    <feature>
      <name>Battle Master Archetype</name>
      <text>You learn maneuvers that are fueled by special dice called superiority dice.

Source: Player's Handbook (2014) p. 73</text>
    </feature>
  </autolevel>
</class>
```

**Assertions:**
- name = "Fighter (Battle Master)"
- slug = "fighter-battle-master" (hierarchical)
- parent_class_id points to Fighter
- features created with level = 3
- Base Fighter class auto-created if not exists

### Task 2.4: Test spellcasting class
**Test:** `it_reconstructs_spellcasting_class`

**XML Example:**
```xml
<class>
  <name>Wizard</name>
  <hd>6</hd>
  <proficiency>Daggers, darts, slings, quarterstaffs, light crossbows</proficiency>
  <spellAbility>Intelligence</spellAbility>
  <autolevel level="1">
    <slots>2,3,0,0,0,0,0,0,0,0</slots>
    <feature>
      <name>Spellcasting</name>
      <text>You have learned to cast spells.

Source: Player's Handbook (2014) p. 114</text>
    </feature>
  </autolevel>
</class>
```

**Assertions:**
- spellcasting_ability_id = Intelligence
- ClassLevelProgression record created for level 1
- Spell slots: [2,3,0,0,0,0,0,0,0,0] parsed correctly

### Task 2.5: Test class with counters
**Test:** `it_reconstructs_class_with_counters`

**XML Example:**
```xml
<class>
  <name>Barbarian</name>
  <hd>12</hd>
  <proficiency>Light armor, medium armor, shields, simple weapons, martial weapons</proficiency>
  <spellAbility></spellAbility>
  <autolevel level="1">
    <feature>
      <name>Rage</name>
      <text>You can enter a rage as a bonus action.

Source: Player's Handbook (2014) p. 48</text>
    </feature>
    <counter>
      <name>Rage</name>
      <value>2</value>
      <reset>L</reset>
    </counter>
  </autolevel>
</class>
```

**Assertions:**
- ClassCounter created
- counter_name = "Rage"
- value = 2
- reset_on = "L" (long rest)
- linked to Barbarian class

### Task 2.6: Test level progression
**Test:** `it_reconstructs_level_progression`

**XML Example:**
```xml
<class>
  <name>Cleric</name>
  <hd>8</hd>
  <spellAbility>Wisdom</spellAbility>
  <autolevel level="1">
    <slots>3,2,0,0,0,0,0,0,0,0</slots>
  </autolevel>
  <autolevel level="2">
    <slots>3,3,0,0,0,0,0,0,0,0</slots>
  </autolevel>
</class>
```

**Assertions:**
- 2 ClassLevelProgression records created
- Level 1: cantrips=3, 1st=2
- Level 2: cantrips=3, 1st=3

### Task 2.7: Test class proficiencies
**Test:** `it_reconstructs_class_proficiencies`

**XML Example:**
```xml
<class>
  <name>Rogue</name>
  <hd>8</hd>
  <proficiency>Light armor, simple weapons, hand crossbows, longswords, rapiers, shortswords</proficiency>
  <savingThrows>DEX, INT</savingThrows>
</class>
```

**Assertions:**
- Proficiency records created for armor + weapons
- Saving throw proficiencies linked to DEX and INT ability scores

### Task 2.8: Test multiple features per level
**Test:** `it_reconstructs_multiple_features_per_level`

**XML Example:**
```xml
<class>
  <name>Ranger</name>
  <hd>10</hd>
  <autolevel level="1">
    <feature>
      <name>Favored Enemy</name>
      <text>You have significant experience studying, tracking, and hunting one type of enemy.</text>
    </feature>
    <feature>
      <name>Natural Explorer</name>
      <text>You are particularly familiar with one type of natural environment.</text>
    </feature>
  </autolevel>
</class>
```

**Assertions:**
- 2 ClassFeature records for level 1
- sort_order differentiates them

### Task 2.9: Test class sources
**Test:** `it_reconstructs_class_sources`

**XML Example:**
```xml
<class>
  <name>Monk</name>
  <hd>8</hd>
  <autolevel level="1">
    <feature>
      <name>Unarmored Defense</name>
      <text>While you are wearing no armor and not wielding a shield, your AC equals 10 + your Dexterity modifier + your Wisdom modifier.

Source: Player's Handbook (2014) p. 78</text>
    </feature>
  </autolevel>
</class>
```

**Assertions:**
- entity_sources record created
- source.code = "PHB"
- pages = "78"

### Task 2.10: Run tests and verify
**Commands:**
```bash
docker compose exec php php artisan test --filter=ClassXmlReconstructionTest
docker compose exec php ./vendor/bin/pint tests/Feature/Importers/ClassXmlReconstructionTest.php
```

**Success Criteria:**
- All tests pass
- Code formatted with Pint
- No regressions

---

## Agent 3: Language Tests for Race + Background

**Goal:** Add language verification tests to existing RaceXmlReconstructionTest and BackgroundXmlReconstructionTest.

**Files:**
- `tests/Feature/Importers/RaceXmlReconstructionTest.php`
- `tests/Feature/Importers/BackgroundXmlReconstructionTest.php`

### Task 3.1: Read existing test files
**Commands:**
```bash
# Use Read tool to read both files
```

### Task 3.2: Add language test to RaceXmlReconstructionTest
**Test:** `it_reconstructs_language_associations`

**Add to file:** `tests/Feature/Importers/RaceXmlReconstructionTest.php`

**XML Example:**
```xml
<race>
  <name>Elf</name>
  <size>M</size>
  <speed>30</speed>
  <ability>Dex +2</ability>
  <trait>
    <name>Languages</name>
    <text>You can speak, read, and write Common and Elvish.

Source: Player's Handbook (2014) p. 23</text>
  </trait>
</race>
```

**Assertions:**
- 2 EntityLanguage records created
- languages: Common, Elvish
- entity_type = Race
- entity_id = race.id

### Task 3.3: Add language choice slot test to RaceXmlReconstructionTest
**Test:** `it_reconstructs_language_choice_slots`

**XML Example:**
```xml
<race>
  <name>Half-Elf</name>
  <size>M</size>
  <speed>30</speed>
  <ability>Cha +2</ability>
  <trait>
    <name>Languages</name>
    <text>You can speak, read, and write Common, Elvish, and one extra language of your choice.

Source: Player's Handbook (2014) p. 39</text>
  </trait>
</race>
```

**Assertions:**
- 2 fixed language records (Common, Elvish)
- 1 choice slot record (language_id = null, choice_count = 1)

### Task 3.4: Add language test to BackgroundXmlReconstructionTest
**Test:** `it_reconstructs_background_languages`

**Add to file:** `tests/Feature/Importers/BackgroundXmlReconstructionTest.php`

**XML Example:**
```xml
<background>
  <name>Acolyte</name>
  <proficiency>Insight, Religion</proficiency>
  <trait>
    <name>Description</name>
    <text>You have spent your life in service to a temple.

• Languages: Two of your choice

Source: Player's Handbook (2014) p. 127</text>
  </trait>
</background>
```

**Assertions:**
- EntityLanguage record with choice_count = 2
- language_id = null (choice slot)

### Task 3.5: Add slug verification to existing tests
**Enhancement:** Add explicit slug assertions to existing subrace/subclass tests

**In RaceXmlReconstructionTest::it_reconstructs_subrace_with_parent:**
```php
$this->assertEquals('dwarf-hill', $subrace->slug);
```

**In ClassXmlReconstructionTest::it_reconstructs_subclass_with_parent (Agent 2's file):**
```php
$this->assertEquals('fighter-battle-master', $subclass->slug);
```

### Task 3.6: Run tests and verify
**Commands:**
```bash
docker compose exec php php artisan test --filter=RaceXmlReconstructionTest
docker compose exec php php artisan test --filter=BackgroundXmlReconstructionTest
docker compose exec php ./vendor/bin/pint tests/Feature/Importers/RaceXmlReconstructionTest.php
docker compose exec php ./vendor/bin/pint tests/Feature/Importers/BackgroundXmlReconstructionTest.php
```

**Success Criteria:**
- All new tests pass
- No regressions in existing tests
- Code formatted with Pint

---

## Agent 4: Item Prerequisites + Fix Incomplete Test

**Goal:** Add prerequisite test to ItemXmlReconstructionTest and fix incomplete modifier test.

**File:** `tests/Feature/Importers/ItemXmlReconstructionTest.php`

### Task 4.1: Read existing test file
**Command:**
```bash
# Use Read tool
```

### Task 4.2: Add prerequisite test
**Test:** `it_reconstructs_strength_requirement_as_prerequisite`

**Add to file:** `tests/Feature/Importers/ItemXmlReconstructionTest.php`

**XML Example:**
```xml
<item>
  <name>Plate Armor</name>
  <type>HA</type>
  <weight>65</weight>
  <value>1500.0</value>
  <ac>18</ac>
  <strength>15</strength>
  <stealth>YES</stealth>
  <text>Heavy armor that provides excellent protection.

Proficiency: heavy armor

Source: Player's Handbook (2014) p. 145</text>
</item>
```

**Assertions:**
- strength_requirement = 15 (backward compat column)
- EntityPrerequisite record created
- prerequisite_type = AbilityScore
- ability_score_id = Strength
- value = 15

### Task 4.3: Investigate and fix incomplete modifier test
**Current test (line 310):** `it_reconstructs_item_with_modifiers`
```php
$this->markTestIncomplete('Modifier parsing edge case - needs investigation...');
```

**Actions:**
1. Uncomment the test code
2. Run the test to see actual failure
3. Debug: What categories are actually created?
4. Either:
   - Fix parser if bug exists
   - Update test assertions to match actual behavior
   - Document as known limitation if unsupported

**Expected outcome:**
- Test passes OR
- Test documents known limitation with clear comment

### Task 4.4: Add prerequisite test for magic items
**Test:** `it_reconstructs_magic_item_with_class_prerequisite`

**XML Example:**
```xml
<item>
  <name>Staff of the Magi</name>
  <detail>legendary (requires attunement by a sorcerer, warlock, or wizard)</detail>
  <type>ST</type>
  <magic>YES</magic>
  <text>This staff can be wielded as a magic quarterstaff.

Source: Dungeon Master's Guide (2014) p. 203</text>
</item>
```

**Assertions:**
- requires_attunement = true
- Check if parser extracts class prerequisites from detail field
- If supported: EntityPrerequisite records for Sorcerer/Warlock/Wizard
- If not supported: Document as enhancement opportunity

### Task 4.5: Run tests and verify
**Commands:**
```bash
docker compose exec php php artisan test --filter=ItemXmlReconstructionTest
docker compose exec php ./vendor/bin/pint tests/Feature/Importers/ItemXmlReconstructionTest.php
```

**Success Criteria:**
- All tests pass (or documented as incomplete with rationale)
- No regressions
- Code formatted with Pint

---

## Integration & Final Verification

**After all 4 agents complete:**

### Step 1: Run full test suite
```bash
docker compose exec php php artisan test
```

**Expected:**
- All tests pass
- No regressions
- New test count: ~60 tests (up from 36)

### Step 2: Format all code
```bash
docker compose exec php ./vendor/bin/pint
```

### Step 3: Git commit
```bash
git add tests/Feature/Importers/
git commit -m "test: add comprehensive XML reconstruction tests for classes and feats

- Add ClassXmlReconstructionTest (10 tests)
- Add FeatXmlReconstructionTest (9 tests)
- Add language verification to Race + Background tests (4 tests)
- Add prerequisite verification to Item + Feat tests (3 tests)
- Fix incomplete modifier test in ItemXmlReconstructionTest
- Add hierarchical slug verification

Coverage increased from 4/6 importers (67%) to 6/6 (100%)
Total reconstruction tests: 36 → 62 (+72% coverage)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

### Step 4: Update documentation
**File:** `docs/SESSION-HANDOVER-2025-11-20-RECONSTRUCTION-TESTS.md`

**Content:** Summary of work completed, test counts, coverage improvements.

---

## Success Criteria

- [ ] 2 new test classes created (Class, Feat)
- [ ] ~26 new tests added across all files
- [ ] All 6 importers have reconstruction tests
- [ ] Languages tested in Race + Background
- [ ] Prerequisites tested in Feat + Item
- [ ] All tests pass (478 → ~504 tests)
- [ ] No regressions
- [ ] Code formatted with Pint
- [ ] Git committed with clear message
- [ ] Documentation updated

---

## Rollback Plan

If any agent encounters blockers:

1. **Tests fail due to importer bugs:** Document issue, mark test as incomplete with TODO
2. **Parser doesn't support feature:** Document as known limitation, create issue for enhancement
3. **Database schema missing:** Verify migrations applied, check if feature actually implemented
4. **Conflicts between agents:** None expected - agents work on independent files/methods

---

## Notes

- Each agent should work independently - no coordination needed
- All agents use `protected $seed = true` to get lookup data
- Follow PHPUnit 11 attribute style: `#[Test]` not `/** @test */`
- Use `createTempXmlFile()` helper for test data
- XML examples are simplified - expand as needed for edge cases
- Importer behavior is source of truth - tests document actual behavior

---

**Plan Status:** ✅ Ready for parallel execution
**Agents Required:** 4
**Estimated Completion:** 2-3 hours (with parallel execution)
