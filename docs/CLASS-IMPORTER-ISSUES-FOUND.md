# Class Importer Issues Found - 2025-11-19

## Investigation Results

### 1. ✅ FIXED: Druid Level Progression Missing
**Status:** Resolved after reimport
**Issue:** Druid had `hit_die = 0` and `level_progression count = 0`
**Root Cause:** Unknown - possibly incomplete initial import
**Solution:** Reimporting `class-druid-phb.xml` fixed the issue
**Current State:** Druid now has:
- `hit_die = 8` ✅
- `level_progression count = 20` ✅
- Features imported correctly ✅

---

### 2. ⚠️  TODO: Spells Known Counter Should Be Spell Progression
**Status:** Needs fixing
**Issue:** "Spells Known" is imported as a counter, but it's actually spell progression data
**Example:** Eldritch Knight has "Spells Known" counter entries for levels 3-20
**Current Behavior:**
```xml
<autolevel level="3">
  <counter>
    <name>Spells Known</name>
    <value>3</value>
  </counter>
</autolevel>
```

**Desired Behavior:** Should be stored in `class_level_progression.spells_known` column instead of `class_counters` table

**Impact:**
- "Spells Known" clutters the counters table
- Data is in the wrong semantic location
- Makes API responses confusing

**Solution:**
1. Add `spells_known` column to `class_level_progression` table
2. Update ClassXmlParser to parse "Spells Known" counters into spell progression
3. Update ClassImporter to save to correct table
4. Add migration to move existing data

---

### 3. ⚠️  TODO: Proficiency Choice Support
**Status:** Needs implementation
**Issue:** Skill proficiencies list all options, but don't indicate "choose X from this list"
**Example:** Fighter XML has:
```xml
<proficiency>Strength, Constitution, Acrobatics, Animal Handling, Athletics, History, Insight, Intimidation, Perception, Survival</proficiency>
<numSkills>2</numSkills>
```

**Current Behavior:**
- All 10 skills are imported as proficiencies
- No indication that user should choose only 2
- `numSkills` is parsed but stored at class level, not linked to proficiencies

**Desired Behavior:**
- Mark skill proficiencies as "choice-based"
- Store `numSkills` value with the proficiency group
- API should return: `{type: "skill", choices_allowed: 2, options: [...]}`

**Impact:**
- Frontend can't properly render "choose 2 skills" interface
- Character builders would grant all skills incorrectly

**Solution:**
1. Add `is_choice` boolean and `choices_allowed` int to `proficiencies` table
2. Update ClassImporter to mark skill proficiencies as choices when `numSkills` exists
3. Update API resource to expose choice metadata

---

### 4. ⚠️  CONFIRMED: Parse Modifiers from Features
**Status:** Confirmed - Needs implementation (FUTURE ENHANCEMENT)
**Issue:** Class features contain `<modifier>` elements that aren't being parsed

**Investigation Results (BATCH 1.1):**
- ✅ Found 10 modifier elements across 4 class files
- Files: Barbarian (3), Monk (5), Ranger TCE (1), Sidekick Warrior (1)
- Types: Speed bonuses, Ability score increases, AC bonuses

**Current Behavior:**
- Features are parsed as plain text only
- Modifiers within features are stored in description but not as structured data

**Examples:**
```xml
<!-- Barbarian Fast Movement -->
<feature>
  <modifier category="bonus">speed +10</modifier>
</feature>

<!-- Barbarian Primal Champion -->
<feature>
  <modifier category="ability score">strength +4</modifier>
  <modifier category="ability score">constitution +4</modifier>
</feature>
```

**Decision:** Document for future enhancement, not in current scope to stay on timeline

---

### 5. ✅  CLOSED: Parse Proficiencies from Features
**Status:** Closed - Non-issue
**Issue:** Investigated whether class features contain `<proficiency>` elements

**Investigation Results (BATCH 1.1):**
- ❌ No `<proficiency>` elements found within `<feature>` elements
- Searched all 42 class XML files
- Proficiencies only appear at class level, not feature level

**Conclusion:** Not an issue - no action needed

---

### 6. ⚠️  TODO: Parse Random Tables from Features
**Status:** Needs investigation
**Issue:** Class features (especially Eldritch Knight) may contain embedded random tables
**Example:** Eldritch Knight might have spell selection tables

**Current Behavior:**
- Features are stored as plain text
- Tables in descriptions are not extracted to `random_tables`

**Investigation Needed:**
```bash
grep -B 2 -A 20 "Eldritch Knight" import-files/class-fighter-phb.xml | grep -E "\|[0-9]"
```

---

## Priority Recommendations

1. **High Priority:** Fix "Spells Known" counter → spell progression migration
2. **High Priority:** Implement proficiency choice support (`numSkills`)
3. **Medium Priority:** Investigate and parse feature modifiers
4. **Medium Priority:** Investigate and parse feature proficiencies
5. **Low Priority:** Extract random tables from feature descriptions (may not exist in class XML)

---

## Test Coverage Needed

After fixes, add tests for:
- ✅ Parsing `numSkills` for choice-based proficiencies
- ✅ Spells Known stored in spell progression, not counters
- ✅ Druid spell progression imports correctly
- ⚠️  Feature modifiers parse correctly (if they exist)
- ⚠️  Feature proficiencies parse correctly (if they exist)
- ⚠️  Feature random tables parse correctly (if they exist)

---

## 📋 Implementation Plan Created

**Date:** 2025-11-20 (Evening)
**Plan Location:** `docs/plans/2025-11-20-class-importer-enhancements.md`
**Estimated Effort:** 6 hours
**Status:** ✅ Ready for execution

The plan includes:
- 4 phases with 13 batches
- Full TDD approach with tests first
- Database migrations with rollback support
- Parser + Importer + API updates
- Data migration for existing records
- Comprehensive test coverage
- Quality gates and verification

**Next Steps:**
1. Review the plan in `docs/plans/2025-11-20-class-importer-enhancements.md`
2. Create branch: `feature/class-importer-enhancements`
3. Execute batches sequentially with TDD
4. Verify with fresh imports after each phase

---

**Investigation Date:** 2025-11-19
**Planning Date:** 2025-11-20 (Evening)
**Investigator:** Claude Code
**Branch:** `feature/entity-prerequisites` (current)
**Next Branch:** `feature/class-importer-enhancements` (to be created)
