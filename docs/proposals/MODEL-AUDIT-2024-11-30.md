# Entity Model Audit Report

**Date:** 2024-11-30
**Status:** Proposal
**Priority:** Low
**GitHub Issue:** [#86](https://github.com/dfox288/dnd-rulebook-project/issues/86)

---

## Executive Summary

Comprehensive audit of all 44 entity models identified opportunities for consolidation, consistency improvements, and data integrity fixes. The schema is functional and production-ready but has accumulated technical debt from rapid development.

**Key Metrics:**
- 44 models + 9 trait concerns
- 12 polymorphic junction tables
- 8 main searchable entities
- 11 lookup/reference tables

---

## Table of Contents

1. [Entity Architecture Overview](#1-entity-architecture-overview)
2. [Critical Issues](#2-critical-issues)
3. [Consolidation Opportunities](#3-consolidation-opportunities)
4. [Naming Inconsistencies](#4-naming-inconsistencies)
5. [Relationship Issues](#5-relationship-issues)
6. [Recommendations](#6-recommendations)
7. [Implementation Roadmap](#7-implementation-roadmap)

---

## 1. Entity Architecture Overview

### Tier 1: Primary Entities (Searchable)

| Entity | Table | Key Relationships |
|--------|-------|-------------------|
| Spell | spells | School, Classes, Effects, SavingThrows, Conditions |
| Monster | monsters | Size, Traits, Actions, LegendaryActions, Spells, Conditions, Senses |
| CharacterClass | classes | Spells, Features, LevelProgression, OptionalFeatures, Counters |
| Race | races | Size, Parent/Subraces, Spells, Languages, Traits, Senses |
| Item | items | ItemType, DamageType, Properties, Abilities, Spells, SavingThrows |
| Background | backgrounds | Languages, Traits, Equipment, Proficiencies |
| Feat | feats | Spells, Conditions, Modifiers, Prerequisites, Proficiencies |
| OptionalFeature | optional_features | Classes, DataTables, Spells, SpellSchool |

### Tier 2: Child/Component Entities

| Entity | Parent | Relationship |
|--------|--------|--------------|
| MonsterAction | Monster | HasMany |
| MonsterLegendaryAction | Monster | HasMany |
| MonsterTrait | Monster | HasMany |
| ClassFeature | CharacterClass | HasMany (hierarchical) |
| ClassLevelProgression | CharacterClass | HasMany |
| ClassCounter | CharacterClass | HasMany |
| ItemAbility | Item | HasMany |
| SpellEffect | Spell | HasMany |
| CharacterTrait | Polymorphic | MorphMany |

### Tier 3: Polymorphic Junction Tables

| Table | Purpose | Key Pivot Columns |
|-------|---------|-------------------|
| entity_sources | Source citations | source_id, page_number |
| entity_spells | Spell grants | spell_id, ability_score_id, level_requirement, usage_limit, is_choice, charges_cost_* |
| entity_conditions | Condition immunities/inflictions | condition_id, effect_type, description |
| entity_languages | Language grants | language_id, is_choice, quantity |
| entity_senses | Special senses | sense_id, range_feet, is_limited, notes |
| entity_proficiencies | Proficiency grants | proficiency_type_id, skill_id, item_id, is_choice, choice_group |
| entity_modifiers | Stat modifiers | modifier_category, value, ability_score_id, skill_id, damage_type_id |
| entity_saving_throws | Save DCs | ability_score_id, dc, save_effect, is_initial_save |
| entity_prerequisites | Requirements | prerequisite_type, prerequisite_id, minimum_value, group_id |
| entity_data_tables | Random/reference tables | table_type, dice_formula |
| entity_items | Equipment grants | item_id, quantity, is_choice, choice_group |
| entity_traits | Character traits | category, name, description |

### Tier 4: Lookup/Reference Tables

| Table | Purpose |
|-------|---------|
| sources | Sourcebook metadata |
| spell_schools | Abjuration, Conjuration, etc. |
| damage_types | Fire, Cold, Necrotic, etc. |
| conditions | Blinded, Charmed, etc. |
| languages | Common, Elvish, etc. |
| skills | Acrobatics, Arcana, etc. |
| ability_scores | STR, DEX, CON, etc. |
| sizes | Tiny, Small, Medium, etc. |
| item_types | Weapon, Armor, etc. |
| item_properties | Finesse, Heavy, etc. |
| senses | Darkvision, Blindsight, etc. |
| proficiency_types | Skill, Tool, Weapon, Armor proficiencies |

---

## 2. Critical Issues

### 2.1 Missing Foreign Key Constraints (HIGH)

**Problem:** Polymorphic tables have FK columns without database constraints.

**Affected Tables:**
```
entity_spells.spell_id         → spells.id (no constraint)
entity_conditions.condition_id → conditions.id (no constraint)
entity_languages.language_id   → languages.id (no constraint)
entity_senses.sense_id         → senses.id (no constraint)
```

**Risk:** Orphan records if referenced entities are deleted.

**Fix:** Add FK constraints with cascade deletes.

### 2.2 Missing Composite Indexes (MEDIUM)

**Problem:** Polymorphic lookups query `WHERE reference_type = ? AND reference_id = ?` without indexes.

**Affected Tables:** All `entity_*` tables.

**Fix:** Add composite index `(reference_type, reference_id)` to each table.

### 2.3 Missing Cascade Deletes (MEDIUM)

**Problem:** HasMany child tables may orphan records on parent deletion.

**Affected Tables:**
```
monster_actions.monster_id      → monsters.id
monster_traits.monster_id       → monsters.id
monster_legendary_actions.monster_id → monsters.id
class_features.class_id         → classes.id
item_abilities.item_id          → items.id
spell_effects.spell_id          → spells.id
```

**Fix:** Add `onDelete('cascade')` to foreign key constraints.

---

## 3. Consolidation Opportunities

### 3.1 entity_spells Column Bloat

**Current Schema:**
```sql
-- 15+ columns, not all used by all entity types
spell_id, ability_score_id, level_requirement, usage_limit, is_cantrip,
is_choice, choice_count, choice_group, max_level, school_id, class_id,
is_ritual_only, charges_cost_min, charges_cost_max, charges_cost_formula
```

**Issue:** `school_id` and `class_id` duplicate data from the spells table.

**Analysis:**
- These columns exist for **spell choices** where `spell_id IS NULL`
- Example: "Choose a 1st-level necromancy spell" stores `school_id` without `spell_id`
- This is intentional design for feat/race spell choices

**Recommendation:** Document this pattern rather than refactor. Add migration comments.

### 3.2 Trait Table Fragmentation

**Current State:**
- `entity_traits` - Polymorphic traits for Race, Class, Background
- `monster_traits` - Direct HasMany for Monster abilities
- `class_features` - Direct HasMany for Class features (hierarchical)

**Issue:** "Trait" concept scattered across multiple tables with different schemas.

**Options:**
1. **Keep as-is** - Different enough in purpose (race flavor vs monster abilities vs class mechanics)
2. **Rename for clarity** - `CharacterTrait` → `EntityDescriptor` to distinguish from `MonsterTrait`

**Recommendation:** Option 2 - rename for clarity (low priority).

### 3.3 Spell Association Patterns

**Current State:**
| Entity | Relationship | Table |
|--------|--------------|-------|
| CharacterClass | BelongsToMany | class_spells |
| Monster | MorphToMany | entity_spells |
| Item | MorphToMany | entity_spells |
| Race | MorphToMany | entity_spells |
| Feat | MorphToMany | entity_spells |

**Issue:** CharacterClass uses dedicated pivot; others use polymorphic.

**Analysis:** This is intentional - class spell lists are the "primary" association (thousands of rows), while entity_spells handles "special" spell grants with extra metadata.

**Recommendation:** Document the pattern. No refactoring needed.

---

## 4. Naming Inconsistencies

### 4.1 Table Naming Conventions

| Pattern | Examples | Convention |
|---------|----------|------------|
| Standard Laravel | class_spells, item_property | Alphabetical |
| Polymorphic | entity_sources, entity_spells | Prefix-based |
| HasMany | monster_actions, class_features | parent_children |

**Issue:** Mixed conventions make it hard to predict table names.

**Recommendation:** Document the three patterns in CLAUDE.md.

### 4.2 Relationship Method Naming

| Type | Convention | Examples |
|------|------------|----------|
| BelongsTo | Singular | `size()`, `itemType()`, `spellSchool()` |
| HasMany | Plural | `actions()`, `features()`, `traits()` |
| MorphToMany | Plural | `spells()`, `conditions()`, `modifiers()` |

**Issue:** Generally consistent, but some edge cases:
- `Monster::spells()` vs `Monster::entitySpellRecords()` (recently refactored)

**Recommendation:** Enforce convention in code review.

### 4.3 Model Class Names

| Model | Table | Why Custom? |
|-------|-------|-------------|
| CharacterClass | classes | "Class" is PHP reserved word |
| Proficiency | entity_proficiencies | Polymorphic usage |
| CharacterTrait | entity_traits | Polymorphic usage |

**Recommendation:** Add comments to each model explaining custom table name.

---

## 5. Relationship Issues

### 5.1 Missing Inverse Relationships

**Condition.php:**
```php
// Has:
public function spells() { ... }   // ✓
public function monsters() { ... } // ✓

// Missing:
public function feats() { ... }    // ✗ Feats can grant condition immunity
public function items() { ... }    // ✗ Items can grant condition immunity
public function races() { ... }    // ✗ Races can grant condition immunity
```

**Recommendation:** Add missing inverses or document limitations.

### 5.2 Undocumented Polymorphic Entity Types

**Problem:** No clear documentation of which entity types can be `reference_type` in each polymorphic table.

**Example - entity_spells:**
```php
// Can reference: Monster, Item, Race, Feat, ClassFeature, OptionalFeature
// But this isn't documented anywhere
```

**Recommendation:** Add PHPDoc to each polymorphic relationship documenting valid types.

### 5.3 Circular Self-References

**Models with self-references:**
- `CharacterClass::parentClass()` ↔ `CharacterClass::subclasses()`
- `Race::parent()` ↔ `Race::subraces()`
- `ClassFeature::parentFeature()` ↔ `ClassFeature::childFeatures()`

**Risk:** Cycles possible (A → B → A) though importer shouldn't create them.

**Recommendation:** Low priority - add validation if issues arise.

---

## 6. Recommendations

### Priority 1: Critical (Data Integrity)

| Action | Effort | Files |
|--------|--------|-------|
| Add FK constraints to entity_* tables | 2-3h | New migration |
| Add cascade deletes to HasMany relationships | 2-3h | New migration |
| Add composite indexes for polymorphic queries | 2h | New migration |

### Priority 2: High (Consistency)

| Action | Effort | Files |
|--------|--------|-------|
| Document polymorphic entity types per table | 2h | Model PHPDoc |
| Add missing inverse relationships | 2-3h | Lookup models |
| Document table naming conventions | 1h | CLAUDE.md |

### Priority 3: Medium (Clarity)

| Action | Effort | Files |
|--------|--------|-------|
| Rename CharacterTrait → EntityDescriptor | 3-4h | Multiple |
| Standardize toSearchableArray() structure | 4-6h | All searchable models |
| Add comments for custom table names | 1h | 3 models |

### Priority 4: Low (Nice-to-Have)

| Action | Effort | Files |
|--------|--------|-------|
| Add soft deletes to main entities | 3-4h | All main models |
| Create DTO layer for API responses | 8-12h | New directory |
| Normalize spell effect storage | 4-6h | SpellEffect model |

---

## 7. Implementation Roadmap

### Phase 1: Database Integrity (Sprint 1)
**Estimated:** 6-8 hours

1. Create migration for FK constraints on `entity_*` tables
2. Create migration for cascade deletes on HasMany tables
3. Create migration for composite indexes
4. Test with existing data

### Phase 2: Documentation (Sprint 2)
**Estimated:** 4-6 hours

1. Add PHPDoc documenting polymorphic entity types
2. Update CLAUDE.md with table naming conventions
3. Add comments to models with custom table names

### Phase 3: Refactoring (Future Sprint)
**Estimated:** 10-15 hours

1. Rename CharacterTrait → EntityDescriptor
2. Add missing inverse relationships
3. Standardize toSearchableArray() structure

---

## Appendix A: Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        MAIN ENTITIES                             │
├─────────────────────────────────────────────────────────────────┤
│  Spell ─────┬──── SpellSchool                                   │
│             ├──── SpellEffect ──── DamageType                   │
│             ├──── class_spells ──── CharacterClass              │
│             └──── entity_spells ◄─┬─ Monster                    │
│                                   ├─ Item                       │
│                                   ├─ Race                       │
│                                   ├─ Feat                       │
│                                   └─ ClassFeature               │
├─────────────────────────────────────────────────────────────────┤
│  Monster ───┬──── Size                                          │
│             ├──── MonsterAction                                 │
│             ├──── MonsterLegendaryAction                        │
│             ├──── MonsterTrait                                  │
│             └──── entity_* (spells, conditions, senses, etc.)   │
├─────────────────────────────────────────────────────────────────┤
│  CharacterClass ─┬── ClassFeature (hierarchical)                │
│                  ├── ClassLevelProgression                      │
│                  ├── ClassCounter                               │
│                  ├── class_spells                               │
│                  ├── class_optional_feature ── OptionalFeature  │
│                  └── Self-reference (parent/subclasses)         │
├─────────────────────────────────────────────────────────────────┤
│  Race ──────┬──── Size                                          │
│             ├──── Self-reference (parent/subraces)              │
│             └──── entity_* (spells, languages, traits, etc.)    │
├─────────────────────────────────────────────────────────────────┤
│  Item ──────┬──── ItemType                                      │
│             ├──── DamageType                                    │
│             ├──── ItemAbility                                   │
│             ├──── item_property ── ItemProperty                 │
│             └──── entity_* (spells, saving_throws, etc.)        │
├─────────────────────────────────────────────────────────────────┤
│  Background, Feat, OptionalFeature                              │
│             └──── entity_* (various polymorphic relationships)  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    POLYMORPHIC TABLES                            │
├─────────────────────────────────────────────────────────────────┤
│  entity_sources      │ Any entity → Source                      │
│  entity_spells       │ Monster/Item/Race/Feat/Feature → Spell   │
│  entity_conditions   │ Monster/Spell/Feat/Item → Condition      │
│  entity_languages    │ Race/Background → Language               │
│  entity_senses       │ Monster/Race → Sense                     │
│  entity_proficiencies│ Class/Race/Background/Feat → Proficiency │
│  entity_modifiers    │ Monster/Class/Race/Feat/Item → Modifier  │
│  entity_saving_throws│ Spell/Item → AbilityScore                │
│  entity_prerequisites│ Feat/Item/OptionalFeature → Any          │
│  entity_data_tables  │ Any entity → DataTable                   │
│  entity_items        │ Class/Background/Race → Item             │
│  entity_traits       │ Race/Class/Background → Trait            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Appendix B: Migration Templates

### FK Constraints Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // entity_spells
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->foreign('spell_id')
                ->references('id')->on('spells')
                ->onDelete('cascade');
            $table->foreign('ability_score_id')
                ->references('id')->on('ability_scores')
                ->onDelete('set null');
        });

        // entity_conditions
        Schema::table('entity_conditions', function (Blueprint $table) {
            $table->foreign('condition_id')
                ->references('id')->on('conditions')
                ->onDelete('cascade');
        });

        // ... repeat for other tables
    }
};
```

### Composite Index Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $polymorphicTables = [
        'entity_sources',
        'entity_spells',
        'entity_conditions',
        'entity_languages',
        'entity_senses',
        'entity_proficiencies',
        'entity_modifiers',
        'entity_saving_throws',
        'entity_prerequisites',
        'entity_data_tables',
        'entity_items',
        'entity_traits',
    ];

    public function up(): void
    {
        foreach ($this->polymorphicTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->index(['reference_type', 'reference_id']);
            });
        }
    }
};
```

---

## Document History

| Date | Author | Changes |
|------|--------|---------|
| 2024-11-30 | Claude | Initial audit and proposal |
