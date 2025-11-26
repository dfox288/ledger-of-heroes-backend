# BLOCKED: Classes Proficiency Filters - Backend Denormalization Needed

**Date:** 2025-11-25
**Updated:** 2025-11-26
**Status:** ⚠️ **BLOCKED** - Backend denormalization needed (data EXISTS, just not indexed)
**Priority:** TIER 6 MEDIUM
**Task:** Add 5 proficiency filters to Classes page for multiclass planning
**Estimated Backend Effort:** ~4 hours (revised down from 9-14 hours)

---

## Executive Summary

**UPDATE (2025-11-26):** The proficiency data **already exists** in the `proficiencies` relationship! The blocker is now much simpler:

1. ✅ **Data exists** - Full proficiency data in `classes.proficiencies[]` relationship
2. ❌ **Not filterable** - Meilisearch can't query nested relationships efficiently
3. 🔧 **Solution** - Denormalize to flat JSON columns + add to Meilisearch index

**Only `max_spell_level` needs manual data entry (13 values).**

---

## Current State (2025-11-26)

### ✅ Data Already Exists!

The `proficiencies` relationship on classes contains **complete proficiency data**:

**Example: GET /api/v1/classes/barbarian → proficiencies[]**
```json
[
  { "proficiency_type": "armor", "proficiency_name": "Light Armor" },
  { "proficiency_type": "armor", "proficiency_name": "Medium Armor" },
  { "proficiency_type": "armor", "proficiency_name": "Shields" },
  { "proficiency_type": "weapon", "proficiency_name": "Simple Weapons" },
  { "proficiency_type": "weapon", "proficiency_name": "Martial Weapons" },
  { "proficiency_type": "saving_throw", "proficiency_name": "Strength" },
  { "proficiency_type": "saving_throw", "proficiency_name": "Constitution" },
  { "proficiency_type": "skill", "proficiency_name": "Animal Handling", "is_choice": true },
  { "proficiency_type": "skill", "proficiency_name": "Athletics", "is_choice": true },
  // ... more skills
]
```

**Example: GET /api/v1/classes/wizard → proficiencies[]**
```json
[
  { "proficiency_type": "weapon", "proficiency_name": "Daggers" },
  { "proficiency_type": "weapon", "proficiency_name": "Darts" },
  { "proficiency_type": "weapon", "proficiency_name": "Slings" },
  { "proficiency_type": "weapon", "proficiency_name": "Quarterstaffs" },
  { "proficiency_type": "weapon", "proficiency_name": "Light Crossbows" },
  { "proficiency_type": "saving_throw", "proficiency_name": "Intelligence" },
  { "proficiency_type": "saving_throw", "proficiency_name": "Wisdom" },
  { "proficiency_type": "skill", "proficiency_name": "Arcana", "is_choice": true },
  // ... more skills
]
```

### ❌ Why It's Still Blocked

**Meilisearch cannot efficiently filter on nested relationship data.** The proficiencies are in a separate table joined via relationship, not flat columns on the `classes` table.

To enable filtering like `?filter=armor_proficiencies IN ["Heavy Armor"]`, we need **denormalized columns** on the classes table that Meilisearch can index.

---

## Revised Solution (Much Simpler!)

### What Backend Needs To Do

#### Step 1: Add Denormalized Columns (~30 min)

```php
// Migration: add_proficiency_columns_to_classes.php
Schema::table('classes', function (Blueprint $table) {
    $table->json('armor_proficiency_names')->nullable();
    $table->json('weapon_proficiency_names')->nullable();
    $table->json('saving_throw_names')->nullable();
    $table->json('skill_proficiency_names')->nullable();
    $table->tinyInteger('max_spell_level')->nullable(); // Only this needs manual data
});
```

#### Step 2: Populate From Existing Relationship (~1 hour)

Create an artisan command or seeder that extracts from existing data:

```php
// Command: PopulateClassProficiencyColumns.php
CharacterClass::with('proficiencies')->each(function ($class) {
    $profs = $class->proficiencies;

    $class->update([
        'armor_proficiency_names' => $profs
            ->where('proficiency_type', 'armor')
            ->pluck('proficiency_name')
            ->values()
            ->toArray(),

        'weapon_proficiency_names' => $profs
            ->where('proficiency_type', 'weapon')
            ->pluck('proficiency_name')
            ->values()
            ->toArray(),

        'saving_throw_names' => $profs
            ->where('proficiency_type', 'saving_throw')
            ->pluck('proficiency_name')
            ->values()
            ->toArray(),

        'skill_proficiency_names' => $profs
            ->where('proficiency_type', 'skill')
            ->pluck('proficiency_name')
            ->values()
            ->toArray(),
    ]);
});
```

#### Step 3: Manually Set max_spell_level (~30 min)

Only 13 base classes need this value:

| Class | max_spell_level | Type |
|-------|-----------------|------|
| Barbarian | 0 | Non-Caster |
| Fighter | 0 | Non-Caster |
| Monk | 0 | Non-Caster |
| Rogue | 0 | Non-Caster |
| Paladin | 5 | Half Caster |
| Ranger | 5 | Half Caster |
| Artificer | 5 | Half Caster |
| Bard | 9 | Full Caster |
| Cleric | 9 | Full Caster |
| Druid | 9 | Full Caster |
| Sorcerer | 9 | Full Caster |
| Warlock | 9 | Full Caster |
| Wizard | 9 | Full Caster |

**Note:** Subclasses like Eldritch Knight (4) and Arcane Trickster (4) inherit from parent or can be set explicitly.

#### Step 4: Update Meilisearch Index (~30 min)

```php
// CharacterClass.php - toSearchableArray()
public function toSearchableArray()
{
    return [
        // ... existing fields ...
        'armor_proficiency_names' => $this->armor_proficiency_names,
        'weapon_proficiency_names' => $this->weapon_proficiency_names,
        'saving_throw_names' => $this->saving_throw_names,
        'skill_proficiency_names' => $this->skill_proficiency_names,
        'max_spell_level' => $this->max_spell_level,
    ];
}

// filterableAttributes config
'filterableAttributes' => [
    // ... existing ...
    'armor_proficiency_names',
    'weapon_proficiency_names',
    'saving_throw_names',
    'skill_proficiency_names',
    'max_spell_level',
]
```

#### Step 5: Re-index (~10 min)

```bash
php artisan scout:flush "App\Models\CharacterClass"
php artisan scout:import "App\Models\CharacterClass"
```

---

## Revised Effort Estimate

| Task | Time | Notes |
|------|------|-------|
| Migration | 30 min | Add 5 columns |
| Extraction command | 1 hour | Populate from existing relationship |
| max_spell_level data | 30 min | 13 manual values |
| Meilisearch config | 30 min | Update model + config |
| Re-index | 10 min | Flush + import |
| Testing | 1 hour | Verify API responses |
| **Total** | **~4 hours** | Down from 9-14 hours! |

---

## Original Investigation (Historical)

---

## Blocked Filters

All 5 planned filters are blocked:

### HIGH Priority (3)

1. **max_spell_level** ❌ BLOCKED
   - Column: `max_spell_level`
   - Type: Select dropdown
   - Options: 0, 4, 5, 9
   - Use: Identify caster types (full/half/third/non-caster)
   - **Backend needs:** INT column with values 0, 4, 5, or 9

2. **armor_proficiencies** ❌ BLOCKED
   - Column: `armor_proficiencies`
   - Type: Multiselect (IN filter)
   - Options: Light Armor, Medium Armor, Heavy Armor, Shields
   - Use: AC optimization, multiclass planning
   - **Backend needs:** JSON/TEXT array column

3. **weapon_proficiencies** ❌ BLOCKED
   - Column: `weapon_proficiencies`
   - Type: Multiselect (IN filter)
   - Options: Simple Weapons, Martial Weapons
   - Use: Weapon access, damage optimization
   - **Backend needs:** JSON/TEXT array column

### MEDIUM Priority (2)

4. **skill_proficiencies** ❌ BLOCKED
   - Column: `skill_proficiencies`
   - Type: Multiselect (IN filter)
   - Options: All 18 skills (from `/api/v1/skills`)
   - Use: Character skill optimization
   - **Backend needs:** JSON/TEXT array column or relationship table

5. **tool_proficiencies** ❌ BLOCKED
   - Column: `tool_proficiencies`
   - Type: Multiselect (IN filter)
   - Options: To be discovered from data
   - Use: Tool access (niche use case)
   - **Backend needs:** JSON/TEXT array column

### Already Blocked

6. **saving_throw_proficiencies** ❌ ALREADY BLOCKED
   - API returns 0 results (may also be missing from database)

---

## Backend Action Items

### Required Database Migrations

**File:** `/Users/dfox/Development/dnd/importer/database/migrations/YYYY_MM_DD_add_proficiency_columns_to_classes.php`

```php
public function up()
{
    Schema::table('classes', function (Blueprint $table) {
        // Spellcasting power (0 = non-caster, 4 = third caster, 5 = half caster, 9 = full caster)
        $table->tinyInteger('max_spell_level')->nullable()->after('spellcasting_ability_id');

        // Proficiency arrays (JSON)
        $table->json('armor_proficiencies')->nullable()->after('max_spell_level');
        $table->json('weapon_proficiencies')->nullable()->after('armor_proficiencies');
        $table->json('tool_proficiencies')->nullable()->after('weapon_proficiencies');
        $table->json('skill_proficiencies')->nullable()->after('tool_proficiencies');
        $table->json('saving_throw_proficiencies')->nullable()->after('skill_proficiencies');
    });
}
```

### Required Data Seeding

**File:** `/Users/dfox/Development/dnd/importer/database/seeders/ClassProficienciesSeeder.php`

**Data sources:**
- D&D 5e Player's Handbook
- D&D 5e Basic Rules
- Xanathar's Guide to Everything
- Tasha's Cauldron of Everything

**Example data (Barbarian):**
```php
[
    'slug' => 'barbarian',
    'max_spell_level' => 0, // Non-caster
    'armor_proficiencies' => ['Light Armor', 'Medium Armor', 'Shields'],
    'weapon_proficiencies' => ['Simple Weapons', 'Martial Weapons'],
    'skill_proficiencies' => ['Animal Handling', 'Athletics', 'Intimidation', 'Nature', 'Perception', 'Survival'], // Choose 2
    'tool_proficiencies' => null, // None
    'saving_throw_proficiencies' => ['STR', 'CON']
]
```

**Example data (Wizard):**
```php
[
    'slug' => 'wizard',
    'max_spell_level' => 9, // Full caster
    'armor_proficiencies' => null, // None
    'weapon_proficiencies' => ['Dagger', 'Dart', 'Sling', 'Quarterstaff', 'Light Crossbow'], // Specific weapons
    'skill_proficiencies' => ['Arcana', 'History', 'Insight', 'Investigation', 'Medicine', 'Religion'], // Choose 2
    'tool_proficiencies' => null, // None
    'saving_throw_proficiencies' => ['INT', 'WIS']
]
```

### Required API Resource Updates

**File:** `/Users/dfox/Development/dnd/importer/app/Http/Resources/ClassResource.php`

```php
public function toArray($request)
{
    return [
        // ... existing fields ...
        'max_spell_level' => $this->max_spell_level,
        'armor_proficiencies' => $this->armor_proficiencies,
        'weapon_proficiencies' => $this->weapon_proficiencies,
        'tool_proficiencies' => $this->tool_proficiencies,
        'skill_proficiencies' => $this->skill_proficiencies,
        'saving_throw_proficiencies' => $this->saving_throw_proficiencies,
    ];
}
```

### Required Meilisearch Index Updates

**File:** `/Users/dfox/Development/dnd/importer/app/Models/CharacterClass.php`

```php
public function toSearchableArray()
{
    return [
        // ... existing fields ...
        'max_spell_level' => $this->max_spell_level,
        'armor_proficiencies' => $this->armor_proficiencies,
        'weapon_proficiencies' => $this->weapon_proficiencies,
        'tool_proficiencies' => $this->tool_proficiencies,
        'skill_proficiencies' => $this->skill_proficiencies,
        'saving_throw_proficiencies' => $this->saving_throw_proficiencies,
    ];
}
```

**Meilisearch filterableAttributes:**
```php
'filterableAttributes' => [
    // ... existing attributes ...
    'max_spell_level',
    'armor_proficiencies',
    'weapon_proficiencies',
    'tool_proficiencies',
    'skill_proficiencies',
    'saving_throw_proficiencies',
]
```

### Re-index After Data Import

```bash
cd /Users/dfox/Development/dnd/importer
docker compose exec php php artisan scout:flush "App\Models\CharacterClass"
docker compose exec php php artisan scout:import "App\Models\CharacterClass"
```

---

## Frontend Implementation Plan (When Data Available)

Once the backend provides the data, implementation will follow this pattern:

### 1. Test API to Discover Values

```bash
# Discover unique values for each field
curl "http://localhost:8080/api/v1/classes?per_page=100" | jq '[.data[].max_spell_level] | unique'
curl "http://localhost:8080/api/v1/classes?per_page=100" | jq '[.data[].armor_proficiencies] | flatten | unique'
curl "http://localhost:8080/api/v1/classes?per_page=100" | jq '[.data[].weapon_proficiencies] | flatten | unique'
curl "http://localhost:8080/api/v1/classes?per_page=100" | jq '[.data[].tool_proficiencies] | flatten | unique'
```

### 2. Write Tests First (TDD)

**File:** `/Users/dfox/Development/dnd/frontend/tests/pages/classes-filters-proficiency.test.ts`

```typescript
import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ClassesPage from '~/pages/classes/index.vue'

describe('Classes Page - Proficiency Filters', () => {
  it('filters by max spell level', async () => {
    const wrapper = await mountSuspended(ClassesPage)

    // Test max spell level filter
    const filter = wrapper.find('[data-testid="max-spell-level-filter"]')
    await filter.setValue(9)

    // Verify filter applied to query
    // ...
  })

  it('filters by armor proficiencies', async () => {
    // Test multiselect armor filter
  })

  it('filters by weapon proficiencies', async () => {
    // Test multiselect weapon filter
  })

  it('filters by skill proficiencies', async () => {
    // Test multiselect skill filter
  })

  it('filters by tool proficiencies', async () => {
    // Test multiselect tool filter
  })

  it('displays filter chips', async () => {
    // Test chip display and removal
  })

  it('updates active filter count', async () => {
    // Test badge count updates
  })
})
```

### 3. Add Filter State

**File:** `/Users/dfox/Development/dnd/frontend/app/pages/classes/index.vue`

```typescript
// Max Spell Level filter
const selectedMaxSpellLevel = ref<number | null>(null)
const maxSpellLevelOptions = [
  { label: 'All', value: null },
  { label: '9th (Full Caster)', value: 9 },
  { label: '5th (Half Caster)', value: 5 },
  { label: '4th (Third Caster)', value: 4 },
  { label: '0 (Non-Caster)', value: 0 }
]

// Armor Proficiencies filter
const selectedArmorProficiencies = ref<string[]>([])
const armorProficiencyOptions = [
  { label: 'Light Armor', value: 'Light Armor' },
  { label: 'Medium Armor', value: 'Medium Armor' },
  { label: 'Heavy Armor', value: 'Heavy Armor' },
  { label: 'Shields', value: 'Shields' }
]

// Weapon Proficiencies filter
const selectedWeaponProficiencies = ref<string[]>([])
const weaponProficiencyOptions = [
  { label: 'Simple Weapons', value: 'Simple Weapons' },
  { label: 'Martial Weapons', value: 'Martial Weapons' }
]

// Skill Proficiencies filter
const { data: skills } = useReferenceData<Skill>('/skills')
const skillOptions = computed(() =>
  skills.value?.map(s => ({ label: s.name, value: s.name })) || []
)
const selectedSkillProficiencies = ref<string[]>([])

// Tool Proficiencies filter
const selectedToolProficiencies = ref<string[]>([])
const toolProficiencyOptions = [
  // Discover from API data
]
```

### 4. Update useMeilisearchFilters

```typescript
const { queryParams } = useMeilisearchFilters([
  // ... existing filters ...
  { ref: selectedMaxSpellLevel, field: 'max_spell_level' },
  { ref: selectedArmorProficiencies, field: 'armor_proficiencies', type: 'in' },
  { ref: selectedWeaponProficiencies, field: 'weapon_proficiencies', type: 'in' },
  { ref: selectedSkillProficiencies, field: 'skill_proficiencies', type: 'in' },
  { ref: selectedToolProficiencies, field: 'tool_proficiencies', type: 'in' }
])
```

### 5. Update UI Components

Add filter components to template:

```vue
<!-- Max Spell Level Filter -->
<USelectMenu
  v-model="selectedMaxSpellLevel"
  :items="maxSpellLevelOptions"
  value-key="value"
  placeholder="All Casters"
  data-testid="max-spell-level-filter"
/>

<!-- Armor Proficiencies Filter -->
<UiFilterMultiSelect
  v-model="selectedArmorProficiencies"
  :options="armorProficiencyOptions"
  placeholder="All Armor"
  data-testid="armor-proficiencies-filter"
/>

<!-- Weapon Proficiencies Filter -->
<UiFilterMultiSelect
  v-model="selectedWeaponProficiencies"
  :options="weaponProficiencyOptions"
  placeholder="All Weapons"
  data-testid="weapon-proficiencies-filter"
/>

<!-- Skill Proficiencies Filter -->
<UiFilterMultiSelect
  v-model="selectedSkillProficiencies"
  :options="skillOptions"
  placeholder="All Skills"
  data-testid="skill-proficiencies-filter"
/>

<!-- Tool Proficiencies Filter -->
<UiFilterMultiSelect
  v-model="selectedToolProficiencies"
  :options="toolProficiencyOptions"
  placeholder="All Tools"
  data-testid="tool-proficiencies-filter"
/>
```

### 6. Add Filter Chips

```vue
<!-- Max Spell Level Chip -->
<UButton
  v-if="selectedMaxSpellLevel !== null"
  size="xs"
  color="primary"
  variant="soft"
  @click="selectedMaxSpellLevel = null"
>
  {{ maxSpellLevelOptions.find(o => o.value === selectedMaxSpellLevel)?.label }} ✕
</UButton>

<!-- Armor Proficiency Chips -->
<UButton
  v-for="armor in selectedArmorProficiencies"
  :key="armor"
  size="xs"
  color="primary"
  variant="soft"
  @click="selectedArmorProficiencies = selectedArmorProficiencies.filter(a => a !== armor)"
>
  {{ armor }} ✕
</UButton>

<!-- Similar chips for weapons, skills, tools -->
```

### 7. Update activeFilterCount

```typescript
const activeFilterCount = useFilterCount(
  // ... existing filters ...
  selectedMaxSpellLevel,
  selectedArmorProficiencies,
  selectedWeaponProficiencies,
  selectedSkillProficiencies,
  selectedToolProficiencies
)
```

### 8. Update clearFilters

```typescript
const clearFilters = () => {
  clearBaseFilters()
  // ... existing filters ...
  selectedMaxSpellLevel.value = null
  selectedArmorProficiencies.value = []
  selectedWeaponProficiencies.value = []
  selectedSkillProficiencies.value = []
  selectedToolProficiencies.value = []
}
```

### 9. Run Tests

```bash
docker compose exec nuxt npm run test tests/pages/classes-filters-proficiency.test.ts
docker compose exec nuxt npm run test # Full suite
docker compose exec nuxt npm run typecheck
```

### 10. Manual Testing

- Navigate to `http://localhost:3000/classes`
- Test each filter individually
- Test filter combinations
- Verify chips work
- Verify clear filters works
- Test in light/dark mode

---

## Estimated Effort

### Backend Work (CRITICAL PATH)
- **Database Migration:** 1 hour
- **Data Collection:** 4-8 hours (manual data entry for 13 base classes + subclasses)
- **Seeder Creation:** 2 hours
- **API Resource Updates:** 30 minutes
- **Meilisearch Config:** 30 minutes
- **Re-indexing:** 10 minutes
- **Testing:** 1 hour

**Backend Total:** 9-14 hours

### Frontend Work (AFTER backend complete)
- **API Value Discovery:** 15 minutes
- **Test Writing (TDD):** 2 hours
- **Implementation:** 2 hours
- **Manual Testing:** 1 hour
- **Documentation:** 30 minutes

**Frontend Total:** 5.75 hours

**Grand Total:** 14.75-19.75 hours

---

## Alternative Approaches

### Approach 1: Use `proficiencies` Relationship Array (Current Architecture)

The API response shows a `proficiencies: []` array in the ClassResource. This might be a relationship to a `proficiencies` table.

**Investigate:**
```bash
cd /Users/dfox/Development/dnd/importer
docker compose exec php php artisan tinker --execute="echo \App\Models\CharacterClass::with('proficiencies')->where('slug', 'barbarian')->first()->toJson(JSON_PRETTY_PRINT);"
```

**If this relationship exists:**
- Join table might already have proficiency data
- May just need Meilisearch indexing updates
- Faster implementation (no migration needed)

**Trade-offs:**
- More complex queries
- Meilisearch indexing of nested relationships
- May require backend aggregation

### Approach 2: Defer to Low Priority

Given the significant backend work required:
- Move to TIER 8 LOW priority
- Focus on filters with existing data
- Revisit when backend data pipeline is more mature

### Approach 3: Static Filter (No Backend)

Create a static mapping file:
```typescript
// app/data/classProficiencies.ts
export const CLASS_PROFICIENCIES = {
  'barbarian': {
    maxSpellLevel: 0,
    armorProficiencies: ['Light Armor', 'Medium Armor', 'Shields'],
    // ...
  }
}
```

**Trade-offs:**
- Fast implementation (no backend wait)
- Requires manual maintenance
- Out of sync with backend data
- Not recommended for production

---

## Recommendations

1. **Report to Backend Team:** Create issue in `/Users/dfox/Development/dnd/importer` repo
2. **Update Priority:** Consider moving to TIER 8 LOW until backend data is ready
3. **Focus on Available Filters:** Work on other entity filters that have data (monsters, spells, items)
4. **Document Blockers:** Add this report to project docs
5. **Revisit After Backend Work:** When backend completes proficiency data import, use this doc as implementation guide

---

## References

- **Frontend Implementation:** `/Users/dfox/Development/dnd/frontend/app/pages/classes/index.vue`
- **Backend Models:** `/Users/dfox/Development/dnd/importer/app/Models/CharacterClass.php`
- **API Spec:** `http://localhost:8080/docs/api.json`
- **Current Status Doc:** `/Users/dfox/Development/dnd/frontend/docs/CURRENT_STATUS.md`
- **Filter Composable:** `/Users/dfox/Development/dnd/frontend/app/composables/useMeilisearchFilters.ts`

---

## Appendix: D&D 5e Class Proficiency Reference

### Max Spell Level by Class

| Class | Max Spell Level | Type |
|-------|----------------|------|
| Wizard, Cleric, Druid, Bard, Sorcerer, Warlock | 9 | Full Caster |
| Paladin, Ranger, Artificer | 5 | Half Caster |
| Eldritch Knight, Arcane Trickster | 4 | Third Caster |
| Barbarian, Fighter, Rogue, Monk | 0 | Non-Caster |

### Armor Proficiencies by Class

| Class | Light | Medium | Heavy | Shields |
|-------|-------|--------|-------|---------|
| Barbarian | ✓ | ✓ | ✗ | ✓ |
| Bard | ✓ | ✗ | ✗ | ✗ |
| Cleric | ✓ | ✓ | ✗ | ✓ |
| Druid | ✓ | ✓ | ✗ | ✓ |
| Fighter | ✓ | ✓ | ✓ | ✓ |
| Monk | ✗ | ✗ | ✗ | ✗ |
| Paladin | ✓ | ✓ | ✓ | ✓ |
| Ranger | ✓ | ✓ | ✗ | ✓ |
| Rogue | ✓ | ✗ | ✗ | ✗ |
| Sorcerer | ✗ | ✗ | ✗ | ✗ |
| Warlock | ✓ | ✗ | ✗ | ✗ |
| Wizard | ✗ | ✗ | ✗ | ✗ |

### Weapon Proficiencies by Class

| Class | Simple | Martial | Special |
|-------|--------|---------|---------|
| Barbarian | ✓ | ✓ | - |
| Bard | ✓ | ✗ | Hand crossbow, longsword, rapier, shortsword |
| Cleric | ✓ | ✗ | - |
| Druid | ✓ | ✗ | Club, dagger, dart, javelin, mace, quarterstaff, scimitar, sickle, sling, spear |
| Fighter | ✓ | ✓ | - |
| Monk | ✓ | ✗ | Shortsword |
| Paladin | ✓ | ✓ | - |
| Ranger | ✓ | ✓ | - |
| Rogue | ✓ | ✗ | Hand crossbow, longsword, rapier, shortsword |
| Sorcerer | ✗ | ✗ | Dagger, dart, sling, quarterstaff, light crossbow |
| Warlock | ✓ | ✗ | - |
| Wizard | ✗ | ✗ | Dagger, dart, sling, quarterstaff, light crossbow |

### Saving Throw Proficiencies by Class

| Class | Primary | Secondary |
|-------|---------|-----------|
| Barbarian | STR | CON |
| Bard | DEX | CHA |
| Cleric | WIS | CHA |
| Druid | INT | WIS |
| Fighter | STR | CON |
| Monk | STR | DEX |
| Paladin | WIS | CHA |
| Ranger | STR | DEX |
| Rogue | DEX | INT |
| Sorcerer | CON | CHA |
| Warlock | WIS | CHA |
| Wizard | INT | WIS |

---

**Status:** This document serves as the definitive blocker report and implementation guide for Classes proficiency filters. Update this document when backend data becomes available.
