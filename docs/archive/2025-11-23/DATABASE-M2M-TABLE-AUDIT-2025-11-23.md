# Database M2M Table Naming Audit (2025-11-23)

## Executive Summary

**Total M2M Tables:** 17
**Polymorphic Tables (with reference_type/reference_id):** 10
**Standard M2M Tables (foreign key pairs):** 7
**Tables Requiring Rename:** 7

---

## 1. Polymorphic Tables (reference_type + reference_id)

### ✅ GOOD - Already Follow `entity_*` Convention (10 tables)

| Table Name | Purpose | Primary Keys | Notes |
|-----------|---------|--------------|-------|
| `entity_conditions` | Links entities to conditions | id (auto), reference_type, reference_id, condition_id | Effect tracking |
| `entity_items` | Links entities to items | id (auto), reference_type, reference_id, item_id | Equipment/gear associations |
| `entity_languages` | Links entities to languages | id (auto), reference_type, reference_id, language_id | Language proficiencies |
| `entity_modifiers` | Links entities to modifiers | id (auto), reference_type, reference_id, modifier_category | Ability/skill/damage modifiers |
| `entity_prerequisites` | Links entities to prerequisites | id (auto), reference_type, reference_id, prerequisite_type | Double polymorphic |
| `entity_saving_throws` | Links entities to saving throws | id (auto), entity_type, entity_id, ability_score_id | **Note:** Uses `entity_type/entity_id` instead of `reference_type/reference_id` |
| `entity_sources` | Links entities to sourcebooks | id (auto), reference_type, reference_id, source_id | Citation tracking |
| `entity_spells` | Links entities to spells | id (auto), reference_type, reference_id, spell_id | Innate spells, items with spells |
| `proficiencies` | Links entities to proficiencies | id (auto), reference_type, reference_id, proficiency_type_id | Weapon/armor/tool proficiencies |
| `traits` | Links entities to character traits | id (auto), reference_type, reference_id, name, category | Racial/class traits |

**Notes:**
- All use `entity_` prefix ✅
- `entity_saving_throws` uses `entity_type/entity_id` (inconsistent but acceptable)
- `proficiencies` and `traits` lack `entity_` prefix but contain rich metadata (not pure pivot tables)

---

## 2. Standard M2M Tables (Foreign Key Pairs)

### ❌ NEEDS RENAME - Missing `entity_` Prefix (5 tables)

| Current Name | Proposed Name | Structure | Connects | Notes |
|-------------|---------------|-----------|----------|-------|
| `ability_score_bonuses` | `entity_ability_score_bonuses` | ability_score_id + race_id/class_id/background_id/feat_id | Ability scores to entities | Multi-column composite key |
| `class_spells` | `entity_class_spells` | class_id + spell_id + level_learned | Classes to spells | Standard pivot with metadata |
| `item_property` | `entity_item_properties` | item_id + property_id | Items to properties | Pure pivot (Laravel convention: alphabetical, plural) |
| `monster_spells` | `entity_monster_spells` | monster_id + spell_id + usage_type + usage_limit | Monsters to spells | Standard pivot with metadata |
| `skill_proficiencies` | `entity_skill_proficiencies` | skill_id + race_id/class_id/background_id/feat_id | Skills to entities | Multi-column composite key |

### ✅ GOOD - Spatie Tags Convention (1 table)

| Table Name | Purpose | Structure | Notes |
|-----------|---------|-----------|-------|
| `taggables` | Links tags to any taggable entity | tag_id + taggable_type + taggable_id | **DO NOT RENAME** - Spatie package requires this exact name |

### ⚠️ EDGE CASE - Not Pure M2M (1 table)

| Table Name | Purpose | Structure | Notes |
|-----------|---------|-----------|-------|
| `random_table_entries` | Stores random table roll results | id (auto), random_table_id, roll_min, roll_max, result_text | **DO NOT RENAME** - Not a pivot table, contains data |

---

## 3. Inconsistency Analysis

### Issue 1: Polymorphic Naming Inconsistency

**Problem:** `entity_saving_throws` uses `entity_type` + `entity_id` instead of `reference_type` + `reference_id`

**Impact:** Breaks convention, confuses developers expecting consistent polymorphic column names

**Recommendation:**
- **Option A (Preferred):** Rename columns to `reference_type` + `reference_id` for consistency
- **Option B (Acceptable):** Keep as-is, document exception (saving throws are entity-specific, not references)

### Issue 2: Missing `entity_` Prefix on Standard Pivots

**Problem:** 5 standard M2M tables don't follow `entity_*` prefix convention:
- `ability_score_bonuses`
- `class_spells`
- `item_property`
- `monster_spells`
- `skill_proficiencies`

**Impact:** Inconsistent naming makes schema harder to understand, breaks pattern recognition

**Recommendation:** Rename all to use `entity_` prefix

### Issue 3: Singular vs Plural Naming

**Problem:** `item_property` is singular (should be `item_properties` per Laravel convention)

**Impact:** Violates Laravel's pivot table naming convention (alphabetical, plural)

**Recommendation:** Rename to `entity_item_properties`

---

## 4. Proposed Renames

### Standard M2M Tables (5 renames)

| Current Name | New Name | Migration Priority |
|-------------|----------|-------------------|
| `ability_score_bonuses` | `entity_ability_score_bonuses` | HIGH |
| `class_spells` | `entity_class_spells` | HIGH |
| `item_property` | `entity_item_properties` | HIGH (also fixes singular→plural) |
| `monster_spells` | `entity_monster_spells` | HIGH |
| `skill_proficiencies` | `entity_skill_proficiencies` | HIGH |

### Polymorphic Column Rename (1 rename)

| Table | Current Columns | New Columns | Migration Priority |
|-------|----------------|-------------|-------------------|
| `entity_saving_throws` | `entity_type`, `entity_id` | `reference_type`, `reference_id` | MEDIUM (optional) |

---

## 5. Tables Requiring NO Changes

| Table Name | Reason |
|-----------|--------|
| `taggables` | Spatie package convention - DO NOT CHANGE |
| `random_table_entries` | Not a pivot table - stores data, not relationships |
| All 10 existing `entity_*` polymorphic tables | Already follow convention ✅ |

---

## 6. Impact Analysis

### Files Requiring Updates After Renames

#### Models (5 files)
- `app/Models/Race.php`
- `app/Models/Class.php`
- `app/Models/Background.php`
- `app/Models/Feat.php`
- `app/Models/Monster.php`
- `app/Models/Item.php`
- `app/Models/Spell.php`

#### Migrations (5 new rename migrations + 1 optional)
- `xxxx_rename_ability_score_bonuses_to_entity_ability_score_bonuses.php`
- `xxxx_rename_class_spells_to_entity_class_spells.php`
- `xxxx_rename_item_property_to_entity_item_properties.php`
- `xxxx_rename_monster_spells_to_entity_monster_spells.php`
- `xxxx_rename_skill_proficiencies_to_entity_skill_proficiencies.php`
- (Optional) `xxxx_rename_entity_saving_throws_columns.php`

#### Importers (9 files)
- `app/Services/Importers/ClassImporter.php` - class_spells table
- `app/Services/Importers/RaceImporter.php` - ability_score_bonuses, skill_proficiencies
- `app/Services/Importers/BackgroundImporter.php` - skill_proficiencies
- `app/Services/Importers/FeatImporter.php` - ability_score_bonuses, skill_proficiencies
- `app/Services/Importers/MonsterImporter.php` - monster_spells
- `app/Services/Importers/ItemImporter.php` - item_property
- `app/Services/Importers/Traits/ImportsEntitySpells.php` - entity_spells (no change)
- `app/Services/Importers/Strategies/MonsterStrategies/SpellcasterStrategy.php` - monster_spells

#### Tests (Est. 30-50 files)
- All feature tests that reference renamed tables
- Factory definitions using renamed relationships

---

## 7. Recommended Migration Order

1. **Phase 1: Rename Standard M2M Tables (HIGH priority)**
   - `ability_score_bonuses` → `entity_ability_score_bonuses`
   - `class_spells` → `entity_class_spells`
   - `item_property` → `entity_item_properties` (fixes singular issue)
   - `monster_spells` → `entity_monster_spells`
   - `skill_proficiencies` → `entity_skill_proficiencies`

2. **Phase 2: Update Models/Importers/Tests**
   - Update all `belongsToMany()` relationship definitions
   - Update raw table references in queries
   - Update factory definitions
   - Update test assertions

3. **Phase 3 (Optional): Standardize Polymorphic Columns**
   - `entity_saving_throws`: Rename `entity_type/entity_id` → `reference_type/reference_id`

---

## 8. Benefits of Renaming

1. **Consistency:** All M2M tables follow `entity_*` prefix convention
2. **Clarity:** Table name immediately indicates purpose (entity relationships)
3. **Maintenance:** Easier to identify and maintain relationship tables
4. **Documentation:** Self-documenting schema reduces cognitive load
5. **Laravel Conventions:** `entity_item_properties` follows Laravel's alphabetical plural naming

---

## 9. Breaking Changes

**None** - This is a database-level refactoring. External API consumers are NOT affected:
- API endpoints remain unchanged (`/api/v1/spells`, etc.)
- API responses remain unchanged (JSON structure preserved)
- Only internal database schema and ORM relationship definitions change

---

## 10. Final Recommendation

**Proceed with all 5 standard M2M table renames** to establish consistent naming across the entire schema. Skip the `entity_saving_throws` column rename (acceptable exception).

**Estimated Effort:** 2-3 hours
- 20 minutes: Write 5 rename migrations
- 30 minutes: Update model relationships
- 30 minutes: Update importers/strategies
- 1 hour: Run tests, fix failures
- 30 minutes: Update documentation
