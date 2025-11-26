# Polymorphic Tables & Schema Cleanup Audit (2025-11-23)

## Executive Summary

**Total Polymorphic Tables:** 10
**Tables Using `reference_type/reference_id`:** 9
**Tables Using `entity_type/entity_id`:** 1
**Unused/Empty Tables:** 3
**Tables Requiring Rename:** 2
**Tables for Deletion:** 3

---

## 1. Polymorphic Table Inventory

### ✅ Standard Polymorphic Pattern (9 tables)

All using `reference_type` + `reference_id` columns:

| Table Name | Row Count | Purpose | Status |
|-----------|-----------|---------|--------|
| `entity_conditions` | 41 | Links entities to conditions (immune, resistant, etc.) | ✅ Active |
| `entity_items` | 274 | Links entities to equipment/items | ✅ Active |
| `entity_languages` | 88 | Links entities to language proficiencies | ✅ Active |
| `entity_modifiers` | 4,097 | Links entities to ability/skill/damage modifiers | ✅ Active |
| `entity_prerequisites` | 240 | Links entities to prerequisites (double polymorphic) | ✅ Active |
| `entity_sources` | 3,747 | Links entities to sourcebook citations | ✅ Active |
| `entity_spells` | 1,220 | Links entities to spells (innate casting, scrolls, etc.) | ✅ Active |
| `proficiencies` | 1,660 | Links entities to proficiencies (weapons, armor, tools) | ⚠️ RENAME to `entity_proficiencies` |
| `traits` | 562 | Links entities to character traits | ⚠️ RENAME to `entity_traits` |

**Notes:**
- `proficiencies` and `traits` follow polymorphic pattern but lack `entity_` prefix
- All contain rich metadata beyond just relationship data

### ⚠️ Non-Standard Polymorphic Pattern (1 table)

| Table Name | Row Count | Purpose | Columns | Status |
|-----------|-----------|---------|---------|--------|
| `entity_saving_throws` | 408 | Links entities to saving throws with DC/modifiers | `entity_type` + `entity_id` | ✅ Active (acceptable exception) |

**Why Different?** Uses `entity_type/entity_id` instead of `reference_type/reference_id`. This is acceptable as saving throws are entity-specific, not "references."

---

## 2. Unused/Empty Tables ⚠️

### Tables with 0 Rows (3 tables)

| Table Name | Purpose | Migration | Model | Status |
|-----------|---------|-----------|-------|--------|
| `ability_score_bonuses` | Polymorphic FK-based ability score bonuses | ✅ Created | ❌ No model | 🗑️ **DELETE** |
| `skill_proficiencies` | Polymorphic FK-based skill proficiencies | ✅ Created | ❌ No model | 🗑️ **DELETE** |
| `monster_spellcasting` | Monster spellcasting metadata | ✅ Created | ✅ Has model | 🗑️ **DELETE** |

### Why These Are Unused

#### 1. `ability_score_bonuses` - Replaced by `entity_modifiers`

**Original Design (Empty):**
```php
// FK-based polymorphism with 0 defaults
Schema::create('ability_score_bonuses', function (Blueprint $table) {
    $table->unsignedBigInteger('ability_score_id');
    $table->unsignedTinyInteger('bonus');
    $table->unsignedBigInteger('race_id')->default(0);
    $table->unsignedBigInteger('class_id')->default(0);
    $table->unsignedBigInteger('background_id')->default(0);
    $table->unsignedBigInteger('feat_id')->default(0);
});
```

**Current Implementation (4,097 rows):**
```php
// Using entity_modifiers with category = 'ability_score'
// Example: +2 DEX for High Elf
entity_modifiers: {
    reference_type: 'App\\Models\\Race',
    reference_id: 5,  // High Elf
    modifier_category: 'ability_score',
    ability_score_id: 2,  // DEX
    value: '+2'
}
```

**Verdict:** `ability_score_bonuses` is **dead code** - never populated, replaced by `entity_modifiers`.

#### 2. `skill_proficiencies` - Replaced by `proficiencies`

**Original Design (Empty):**
```php
// FK-based polymorphism with 0 defaults
Schema::create('skill_proficiencies', function (Blueprint $table) {
    $table->unsignedBigInteger('skill_id');
    $table->unsignedBigInteger('race_id')->default(0);
    $table->unsignedBigInteger('class_id')->default(0);
    $table->unsignedBigInteger('background_id')->default(0);
    $table->unsignedBigInteger('feat_id')->default(0);
});
```

**Current Implementation (1,660 rows in `proficiencies`):**
```php
// Using proficiencies with skill_id
// Example: Perception proficiency for Ranger
proficiencies: {
    reference_type: 'App\\Models\\CharacterClass',
    reference_id: 7,  // Ranger
    proficiency_type: 'skill',
    skill_id: 11,  // Perception
    grants: true
}
```

**Verdict:** `skill_proficiencies` is **dead code** - never populated, replaced by `proficiencies` table.

#### 3. `monster_spellcasting` - Replaced by `entity_spells`

**Original Design (0 rows, has Model + Factory):**
```php
// Intended for monster spellcasting metadata
Schema::create('monster_spellcasting', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('monster_id');
    $table->text('description');
    $table->string('spell_slots', 100)->nullable();
    $table->string('spellcasting_ability', 50)->nullable();
    $table->unsignedTinyInteger('spell_save_dc')->nullable();
    $table->tinyInteger('spell_attack_bonus')->nullable();
});
```

**Current Implementation (entity_spells used instead):**
```php
// Monster spells stored in entity_spells (1,220 rows)
// Example: Lich casting Power Word Kill
entity_spells: {
    reference_type: 'App\\Models\\Monster',
    reference_id: 123,  // Lich
    spell_id: 456,  // Power Word Kill
    ability_score_id: 4,  // Intelligence
    usage_limit: '1/day'
}
```

**Verdict:** `monster_spellcasting` is **dead code** - SpellcasterStrategy uses `entity_spells`, not this table.

**Evidence:**
```bash
# Table has 0 rows despite 129 spellcasting monsters
mysql> SELECT COUNT(*) FROM monster_spellcasting;
+----------+
| COUNT(*) |
+----------+
|        0 |
+----------+

# Spells are in entity_spells instead
mysql> SELECT COUNT(*) FROM entity_spells WHERE reference_type = 'App\\Models\\Monster';
+----------+
| COUNT(*) |
+----------+
|     1098 |
+----------+
```

---

## 3. Architecture Issues

### Issue 1: FK-Based Polymorphism Pattern (Bad Design)

**Problem:** `ability_score_bonuses` and `skill_proficiencies` use FK-based polymorphism with `0` as "null" semantics:

```php
// BAD: 0 defaults for "not applicable"
$table->unsignedBigInteger('race_id')->default(0);
$table->unsignedBigInteger('class_id')->default(0);
$table->unsignedBigInteger('background_id')->default(0);
$table->unsignedBigInteger('feat_id')->default(0);
```

**Why This Is Bad:**
1. **No FK constraints** - Cannot enforce referential integrity (0 doesn't exist in parent tables)
2. **Inefficient queries** - Must check all 4 columns to find owner (`WHERE race_id > 0 OR class_id > 0 OR ...`)
3. **Composite primary keys** - Requires all 4 FKs in PK (ugly, error-prone)
4. **Data integrity risk** - Multiple FKs could be set accidentally (no DB-level enforcement)

**Correct Pattern (Already Used):**
```php
// GOOD: Type/ID polymorphism with real FK constraints
$table->string('reference_type');  // 'App\\Models\\Race'
$table->unsignedBigInteger('reference_id');  // 5 (High Elf)
$table->index(['reference_type', 'reference_id']);
```

### Issue 2: Redundant Table Design

**Problem:** Created specialized tables (`ability_score_bonuses`, `skill_proficiencies`) instead of using flexible `entity_modifiers` and `proficiencies` tables.

**Result:** Unused tables that were immediately bypassed by importers.

---

## 4. Recommendations

### Phase 1: Delete Dead Tables (HIGH Priority)

**Tables to Delete:**
1. `ability_score_bonuses` - 0 rows, no model, replaced by `entity_modifiers`
2. `skill_proficiencies` - 0 rows, no model, replaced by `proficiencies`
3. `monster_spellcasting` - 0 rows, has model/factory but replaced by `entity_spells`

**Files to Delete:**
- Migration: `database/migrations/2025_11_17_210222_create_polymorphic_tables.php` (creates `ability_score_bonuses`, `skill_proficiencies`)
- Model: `app/Models/MonsterSpellcasting.php`
- Resource: `app/Http/Resources/MonsterSpellcastingResource.php`
- Factory: `database/factories/MonsterSpellcastingFactory.php`

**Files to Update:**
- Migration: `database/migrations/2025_11_17_215248_create_monster_related_tables.php` (remove `monster_spellcasting` schema)
- Model: `app/Models/Monster.php` (remove `spellcasting()` relationship if exists)
- Tests: Remove references to `MonsterSpellcasting` in `tests/Feature/Api/MonsterEnhancedFilteringApiTest.php`, `tests/Unit/Models/MonsterTest.php`

### Phase 2: Rename Polymorphic Tables (MEDIUM Priority)

**Tables to Rename:**
1. `proficiencies` → `entity_proficiencies`
2. `traits` → `entity_traits`

**Rationale:**
- Establishes consistent `entity_*` prefix across all polymorphic tables
- Clarifies purpose: these are entity relationships, not standalone lookup tables
- Aligns with existing convention (`entity_modifiers`, `entity_spells`, etc.)

**Impact:**
- Models: `Proficiency.php`, `CharacterTrait.php` (update `$table` property)
- Relationships: Update `belongsToMany()` in Race, Class, Background, Feat, Item models
- Importers: 7 importers reference these tables
- Tests: ~20-30 test files

---

## 5. Proposed Migration Plan

### Step 1: Drop Unused Tables (20 minutes)

```php
// New migration: xxxx_drop_unused_polymorphic_tables.php
public function up(): void
{
    Schema::dropIfExists('ability_score_bonuses');
    Schema::dropIfExists('skill_proficiencies');
    Schema::dropIfExists('monster_spellcasting');
}
```

**Verify Safety:**
```bash
# Confirm 0 rows before dropping
docker compose exec mysql mysql -udnd_user -pdnd_password dnd_compendium -e "
    SELECT 'ability_score_bonuses' AS tbl, COUNT(*) FROM ability_score_bonuses
    UNION ALL
    SELECT 'skill_proficiencies', COUNT(*) FROM skill_proficiencies
    UNION ALL
    SELECT 'monster_spellcasting', COUNT(*) FROM monster_spellcasting;
"
```

### Step 2: Delete Associated Files (10 minutes)

```bash
# Delete Model, Resource, Factory
rm app/Models/MonsterSpellcasting.php
rm app/Http/Resources/MonsterSpellcastingResource.php
rm database/factories/MonsterSpellcastingFactory.php

# Update Monster model to remove spellcasting relationship
# Update tests to remove MonsterSpellcasting references
```

### Step 3: Rename Polymorphic Tables (2-3 hours)

```php
// New migration: xxxx_rename_polymorphic_tables_to_entity_prefix.php
public function up(): void
{
    Schema::rename('proficiencies', 'entity_proficiencies');
    Schema::rename('traits', 'entity_traits');
}
```

**Update Files:**
- 2 Models (`Proficiency.php`, `CharacterTrait.php`)
- 7 Importers (Race, Class, Background, Feat, Monster, Item, + traits)
- ~30 Tests

---

## 6. Final Polymorphic Table Schema (After Cleanup)

### All Polymorphic Tables (11 total)

| Table Name | Polymorphic Columns | Row Count | Purpose |
|-----------|-------------------|-----------|---------|
| `entity_conditions` | reference_type, reference_id | 41 | Condition immunities/vulnerabilities |
| `entity_items` | reference_type, reference_id | 274 | Equipment associations |
| `entity_languages` | reference_type, reference_id | 88 | Language proficiencies |
| `entity_modifiers` | reference_type, reference_id | 4,097 | Ability/skill/damage modifiers |
| `entity_prerequisites` | reference_type, reference_id | 240 | Prerequisites (double polymorphic) |
| `entity_proficiencies` | reference_type, reference_id | 1,660 | Weapon/armor/tool proficiencies |
| `entity_saving_throws` | entity_type, entity_id | 408 | Saving throws with DC/modifiers |
| `entity_sources` | reference_type, reference_id | 3,747 | Sourcebook citations |
| `entity_spells` | reference_type, reference_id | 1,220 | Spell associations |
| `entity_traits` | reference_type, reference_id | 562 | Character traits |
| `random_tables` | reference_type, reference_id | 363 | Random table metadata |

**Notes:**
- ✅ All use `entity_*` prefix (consistent naming)
- ✅ 10 use `reference_type/reference_id` (standard pattern)
- ✅ 1 uses `entity_type/entity_id` (`entity_saving_throws` - acceptable exception)
- ✅ No unused/empty tables remaining

---

## 7. Benefits of Cleanup

1. **Remove Dead Code** - Delete 3 unused tables + 4 unused files
2. **Simplify Schema** - Fewer tables to maintain, clearer architecture
3. **Consistent Naming** - All polymorphic tables use `entity_*` prefix
4. **Improve Documentation** - Schema is self-documenting
5. **Reduce Confusion** - No "ghost" tables with 0 rows

---

## 8. Risk Assessment

**Deletion Risk:** ⚠️ **ZERO RISK**
- All 3 tables have 0 rows
- `MonsterSpellcasting` model/factory exist but are never used
- No production data loss (dev environment only)

**Rename Risk:** ⚠️ **LOW RISK**
- Well-defined scope (2 tables, ~40 file updates)
- Comprehensive test suite catches breakages
- No external API changes (only internal ORM)

---

## 9. Estimated Effort

| Phase | Tasks | Time |
|-------|-------|------|
| Delete unused tables | Write migration, delete 4 files, update tests | 30 minutes |
| Rename polymorphic tables | Write migration, update 2 models, 7 importers, 30 tests | 2-3 hours |
| **Total** | | **3-3.5 hours** |

---

## 10. Final Recommendation

**Proceed with both phases:**
1. ✅ **Delete all 3 unused tables** (zero risk, immediate benefit)
2. ✅ **Rename `proficiencies` and `traits`** (low risk, establishes consistent naming)

**Result:** Clean, consistent polymorphic table architecture with `entity_*` prefix convention across the entire schema.
