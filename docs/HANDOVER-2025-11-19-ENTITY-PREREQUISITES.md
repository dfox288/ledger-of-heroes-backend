# Entity Prerequisites System - Session Handover

**Date:** 2025-11-19
**Branch:** `feature/entity-prerequisites`
**Status:** 🟡 **IN PROGRESS** - Batch 1 Complete (Infrastructure), Batch 2+ Pending
**Next Session:** Continue with Batch 2 (Parser Enhancements)

---

## 🎯 Session Goal

Replace text-based prerequisite storage with a **polymorphic `entity_prerequisites` table** that:
- Links prerequisites to actual entities (AbilityScore, Race, ProficiencyType)
- Supports complex AND/OR logic via `group_id`
- Works for Feats, Items, Classes, and future entities
- Enables queryable, validated prerequisite data

---

## ✅ Completed Work (Batch 1: Infrastructure)

### 1. Database Schema ✅
**File:** `database/migrations/2025_11_19_181657_create_entity_prerequisites_table.php`

Created `entity_prerequisites` table with **double polymorphic** structure:

```php
entity_prerequisites:
  - id
  - reference_type      // App\Models\Feat, App\Models\Item (who HAS the prerequisite)
  - reference_id
  - prerequisite_type   // App\Models\AbilityScore, App\Models\Race, etc. (what IS required)
  - prerequisite_id
  - minimum_value       // For ability scores: STR >= 13
  - description         // For free-form: "Spellcasting feature"
  - group_id            // Logical grouping: same group = OR, different = AND
```

**Key Design Decision:** Using `prerequisite_type`/`prerequisite_id` (not `target_type`/`target_id`) for proper Laravel polymorphic naming.

### 2. Eloquent Model ✅
**File:** `app/Models/EntityPrerequisite.php`

- Double `morphTo()` relationships: `reference()` + `prerequisite()`
- No timestamps (consistent with other entity tables)
- Mass assignable fields configured

### 3. Factory with TDD ✅
**File:** `database/factories/EntityPrerequisiteFactory.php`
**Tests:** `tests/Unit/Factories/EntityPrerequisiteFactoryTest.php`

**State Methods:**
- `forEntity(class, id)` - Set which entity has the prerequisite
- `abilityScore(code, min)` - Ability score requirement (e.g., STR 13)
- `race(raceId)` - Race requirement
- `proficiency(profTypeId)` - Proficiency requirement
- `feature(description)` - Free-form feature requirement
- `inGroup(groupId)` - Set logical group for AND/OR logic

**Test Coverage:** 7 tests, 18 assertions - all passing ✅

### 4. Model Relationships ✅
**Files:** `app/Models/Feat.php`, `app/Models/Item.php`
**Tests:** `tests/Feature/Models/EntityPrerequisiteModelTest.php`

Added `prerequisites()` relationship to Feat and Item models:
```php
public function prerequisites(): MorphMany
{
    return $this->morphMany(EntityPrerequisite::class, 'reference');
}
```

**Test Coverage:** 8 tests, 16 assertions - all passing ✅

### 5. Column Rename to Avoid Conflict ✅
**File:** `database/migrations/2025_11_19_182611_rename_prerequisites_to_prerequisites_text_on_feats_table.php`

**Problem:** `feats.prerequisites` column shadowed `prerequisites()` relationship.
**Solution:** Renamed `prerequisites` → `prerequisites_text`

**Benefits:**
- Clean relationship access: `$feat->prerequisites` (relationship)
- Legacy text preserved: `$feat->prerequisites_text` (backward compat)
- No more naming conflicts

**Updated Files:**
- `app/Models/Feat.php` - Updated fillable + search scope
- `database/factories/FeatFactory.php` - Updated to use new column name
- All tests updated to use clean syntax

### 6. PHPUnit 11 Compliance ✅
**Updated:** `CLAUDE.md`, all new test files

**Changed:**
```php
// ❌ OLD (deprecated)
/** @test */
public function it_works() { }

// ✅ NEW (required)
#[\PHPUnit\Framework\Attributes\Test]
public function it_works() { }
```

**Rationale:** Doc-comment metadata deprecated in PHPUnit 11, removed in PHPUnit 12.

**Documentation:** Added requirement to CLAUDE.md with examples.

---

## 📊 Current State

**Test Status:**
- ✅ **355 tests passing** (2033 assertions)
- ✅ **15 new tests** added (Batch 1)
- ✅ **Zero warnings** (PHPUnit 11 compliant)
- ✅ **Zero regressions**
- ⏱️ **Duration:** ~3.5 seconds

**Git Status:**
- **Branch:** `feature/entity-prerequisites`
- **Commits:** 4 (all atomic, well-documented)
  1. `c95f775` - Migration: entity_prerequisites table
  2. `aee0db2` - Model: EntityPrerequisite
  3. `6daca8b` - Factory: EntityPrerequisiteFactory with TDD
  4. `494477f` - Relationships: prerequisites() on Feat/Item
  5. `bbc48e3` - Fix: Rename prerequisites column
  6. `72609c4` - Docs: PHPUnit 11 requirement

**Code Quality:**
- ✅ All code formatted with Pint
- ✅ Full TDD coverage (RED → GREEN → REFACTOR)
- ✅ Follows established patterns (entity_sources, entity_conditions, etc.)

---

## 🚧 Pending Work (Batches 2-7)

### Batch 2: Parser Enhancements 🔄 NEXT
**Estimated:** 1-2 hours
**File to Create:** `app/Services/Parsers/FeatXmlParser.php` (update)
**Tests to Create:** `tests/Unit/Parsers/FeatXmlParserPrerequisitesTest.php`

**Tasks:**
1. Add `parsePrerequisites()` method to FeatXmlParser
2. Parse 6+ prerequisite patterns:
   - Ability score minimums: "Dexterity 13 or higher"
   - Dual ability scores: "Intelligence or Wisdom 13 or higher"
   - Single race: "Elf"
   - Multiple races: "Dwarf, Gnome, Halfling"
   - Proficiencies: "Proficiency with medium armor"
   - Features: "The ability to cast at least one spell"
   - Complex AND/OR: "Dwarf, Gnome, Halfling, Small Race, Proficiency in Acrobatics"
3. Return structured array with `prerequisite_type`, `prerequisite_id`, `minimum_value`, `description`, `group_id`
4. **Use TDD:** Write 10+ tests first, watch them fail, then implement

**Reference Data from Production:**
```
Unique Prerequisites Found (26 total):
- "Dexterity 13 or higher"
- "The ability to cast at least one spell"
- "Strength 13 or higher"
- "Proficiency with medium armor"
- "Proficiency with heavy armor"
- "Charisma 13 or higher"
- "Proficiency with light armor"
- "Intelligence or Wisdom 13 or higher"
- "Elf"
- "Spellcasting or Pact Magic feature"
- "Halfling"
- "Dragonborn"
- "Dwarf"
- "Dwarf, Gnome, Halfling, Small Race, Proficiency in Acrobatics"
... (and more)
```

### Batch 3: Importer Updates
**Estimated:** 30 minutes
**File to Update:** `app/Services/Importers/FeatImporter.php`
**Tests to Create:** `tests/Feature/Importers/FeatImporterPrerequisitesTest.php`

**Tasks:**
1. Add `importPrerequisites()` method
2. Look up `prerequisite_id` from parsed data (ability_score_id, race_id, proficiency_type_id)
3. Create `EntityPrerequisite` records with proper group_id
4. Handle re-imports (delete old prerequisites first)
5. **TDD:** 7 importer tests

### Batch 4: API Layer
**Estimated:** 30 minutes
**Files to Create:**
- `app/Http/Resources/EntityPrerequisiteResource.php`
- `tests/Feature/Api/FeatPrerequisitesApiTest.php`

**Tasks:**
1. Create EntityPrerequisiteResource with nested entity details
2. Update FeatResource to include prerequisites
3. Update ItemResource to include prerequisites
4. Update FeatController to eager-load prerequisites (prevent N+1)
5. **TDD:** 3 API tests

### Batch 5: Item Migration
**Estimated:** 30 minutes
**File to Create:** Migration to migrate `items.strength_requirement` → `entity_prerequisites`

**Tasks:**
1. Create data migration
2. Convert all items with `strength_requirement` to EntityPrerequisite records
3. Keep legacy column for backward compatibility
4. **TDD:** 1 migration test

### Batch 6: Production Import & Quality Gates
**Estimated:** 20 minutes

**Tasks:**
1. Fresh database: `php artisan migrate:fresh --seed`
2. Re-import all feats: `for file in import-files/feats-*.xml; do php artisan import:feats "$file"; done`
3. Verify data: Check prerequisite counts, group_id logic, FK integrity
4. Run full test suite (expect ~370 tests)
5. Format code with Pint
6. Commit: "feat: complete entity prerequisites system"

### Batch 7: Documentation
**Estimated:** 15 minutes

**Tasks:**
1. Update `docs/SESSION-HANDOVER.md` with completion notes
2. Update `CLAUDE.md` with new feature
3. Document API changes (new fields in FeatResource, ItemResource)

---

## 🧠 Key Design Decisions

### 1. Double Polymorphic Structure
**Why:** Allows ANY entity to have prerequisites that reference ANY other entity.

**Example:**
```php
// Feat "Heavily Armored" requires "Proficiency with medium armor"
EntityPrerequisite:
  reference_type: App\Models\Feat
  reference_id: 42
  prerequisite_type: App\Models\ProficiencyType
  prerequisite_id: 15  // Medium Armor
```

### 2. Group-Based AND/OR Logic
**Why:** Avoids JSON blobs while staying queryable. Maps to SQL `WHERE (A OR B) AND (C OR D)`.

**Example:** "Dwarf OR Gnome OR Halfling AND Proficiency in Acrobatics"
```php
// Group 1: Races (OR)
['prerequisite_type' => Race, 'prerequisite_id' => 2, 'group_id' => 1] // Dwarf
['prerequisite_type' => Race, 'prerequisite_id' => 5, 'group_id' => 1] // Gnome
['prerequisite_type' => Race, 'prerequisite_id' => 7, 'group_id' => 1] // Halfling

// Group 2: Proficiency (AND with group 1)
['prerequisite_type' => ProficiencyType, 'prerequisite_id' => 8, 'group_id' => 2] // Acrobatics
```

**Validation Logic:**
```php
$group1Pass = $character->race_id IN (2, 5, 7);  // Any one race
$group2Pass = $character->hasProficiency(8);     // Must have this
$canTakeFeat = $group1Pass && $group2Pass;       // Both groups required
```

### 3. Nullable prerequisite_type/prerequisite_id
**Why:** Supports free-form prerequisites like "Spellcasting feature" that don't have entities yet.

**Example:**
```php
// "The ability to cast at least one spell" - not an entity
EntityPrerequisite:
  prerequisite_type: null
  prerequisite_id: null
  description: "The ability to cast at least one spell"
```

**Future:** When we add `class_features` table, we can migrate these to FKs.

### 4. Column Rename (prerequisites → prerequisites_text)
**Why:** Eliminates attribute/relationship naming conflict in Feat model.

**Trade-off:** Requires migration + updates, but gains clean API and testability.

---

## 🔍 Things to Watch Out For

### 1. Parser Complexity
The prerequisite parsing is **the most complex part** of this feature:
- 6+ different patterns to match
- Some patterns overlap (e.g., "Elf" vs "Elf (Drow)")
- Complex AND/OR logic requires proper group_id assignment
- Free-form fallback for unmatched patterns

**Recommendation:** Start with simple patterns, add complexity incrementally, test each pattern thoroughly.

### 2. Race/Proficiency Lookup Failures
Parser needs to handle cases where lookups fail:
- Race "Elf (Wood)" might not exist (parse as "Wood Elf"?)
- Proficiency "Athletics" might not be in proficiency_types (it's not currently!)
- Use fuzzy matching + fallback to free-form description

### 3. Test Data Seeding
Tests need ability_scores, proficiency_types, and races seeded:
```php
protected $seed = true; // Auto-seed for all tests
```

Without this, factory lookups fail (we discovered this in Batch 1).

### 4. Attribute/Relationship Conflicts
**Always check** for column/relationship name conflicts when adding new relationships.

**Pattern to avoid:**
```php
// ❌ BAD - column shadows relationship
$fillable = ['prerequisites'];
public function prerequisites() { return $this->morphMany(...); }

// ✅ GOOD - different names
$fillable = ['prerequisites_text'];
public function prerequisites() { return $this->morphMany(...); }
```

---

## 📝 Implementation Plan Summary

**Full plan created using Laravel Superpowers:**
- 7 batches total
- Estimated 6-8 hours total (now 4-6 remaining)
- Full TDD coverage required
- Each batch: RED → GREEN → REFACTOR → COMMIT

**Current Progress:**
- ✅ Batch 1: Infrastructure (100%)
- 🔄 Batch 2: Parser (0% - NEXT)
- ⏸️ Batch 3-7: Pending

---

## 🚀 Next Session: Quick Start

### 1. Verify Branch State
```bash
git checkout feature/entity-prerequisites
git log --oneline -6  # Should show 6 commits
docker compose exec php php artisan test  # Should pass 355 tests
```

### 2. Start Batch 2: Parser Enhancements
**Read the plan above, then:**
```bash
# Create test file FIRST (TDD)
touch tests/Unit/Parsers/FeatXmlParserPrerequisitesTest.php

# Write tests for all 6+ patterns
# Watch them fail (RED)
# Then implement parsePrerequisites() method
# Watch them pass (GREEN)
```

### 3. Use Laravel Superpowers
**Consider using:**
- `laravel:tdd-with-pest` skill for TDD workflow
- `laravel:executing-plans` skill to execute remaining batches

### 4. Keep Test Coverage High
Every new method needs tests BEFORE implementation.

**Target:** ~30 new tests by end of Batch 2.

---

## 📚 Reference Files

**Key Files to Review:**
- `database/migrations/2025_11_19_181657_create_entity_prerequisites_table.php` - Schema
- `app/Models/EntityPrerequisite.php` - Model
- `database/factories/EntityPrerequisiteFactory.php` - Factory patterns
- `tests/Feature/Models/EntityPrerequisiteModelTest.php` - Relationship examples
- `CLAUDE.md` - PHPUnit 11 requirement + TDD mandate

**Existing Parser Examples:**
- `app/Services/Parsers/FeatXmlParser.php` - Already has modifier/proficiency parsing
- `app/Services/Parsers/RaceXmlParser.php` - Complex parsing patterns
- `tests/Unit/Parsers/FeatXmlParserTest.php` - Parser test patterns

---

## ✅ Success Criteria (End of All Batches)

When this feature is complete, you should have:

1. ✅ **~370 tests passing** (30+ new tests for prerequisites)
2. ✅ **138 feats** with structured prerequisite data (not just text)
3. ✅ **Queryable prerequisites** via relationships
4. ✅ **API includes prerequisites** with nested entity details
5. ✅ **Items migrated** from strength_requirement to prerequisites
6. ✅ **Zero technical debt** (all polymorphic, no hardcoded logic)
7. ✅ **Documentation updated** (HANDOVER, CLAUDE.md, API docs)

---

## 💬 Final Notes

**This is a high-value feature:**
- Unlocks prerequisite-based queries ("show feats for STR 16 Fighter")
- Establishes pattern for future entities (Classes, Monsters)
- Replaces text parsing with structured, validated data

**Estimated remaining time:** 4-6 hours across Batches 2-7.

**Questions for next session:**
1. Should we handle "Small Race" as a size prerequisite, or keep it free-form?
2. Should we create a `class_features` table now, or migrate "Spellcasting" to free-form?
3. Do we need a `character_class_levels` prerequisite type for multiclassing?

---

**Status:** 🟢 Ready for Batch 2 - Parser is the critical path!
**Next Session:** Start fresh, review this handover, execute Batches 2-7.

🚀 Good luck!
