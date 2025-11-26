# Backend Proposal: Classes Detail Page Optimization

**Date:** 2025-11-26
**Author:** Claude Code
**Status:** ✅ IMPLEMENTED (2025-11-26)
**Target:** `/api/v1/classes/{slug}` endpoint

## Executive Summary

The frontend classes detail page (`app/pages/classes/[slug].vue`) contains significant computed logic to handle subclass inheritance, derived values, and data transformations. This proposal recommends moving these calculations to the backend to reduce frontend complexity, improve performance, and ensure consistent behavior across all API consumers.

## Current Frontend Logic Audit

### 1. Inheritance Resolution (~80 lines)

**File:** `app/pages/classes/[slug].vue:81-206`

The frontend duplicates inheritance resolution logic 6+ times:

```typescript
// Pattern repeated for: counters, traits, level_progression, equipment, proficiencies, features
const counters = entity.value.is_base_class
  ? entity.value.counters
  : parentClass.value?.counters

const traits = entity.value.is_base_class
  ? entity.value.traits
  : parentClass.value?.traits

// etc...
```

**Problem:** Every API consumer must implement identical inheritance logic.

### 2. Proficiency Bonus Calculation

**File:** `app/components/ui/class/UiClassProgressionTable.vue:28-31`

```typescript
const getProficiencyBonus = (level: number): string => {
  const bonus = Math.ceil(level / 4) + 1
  return `+${bonus}`
}
```

**Problem:** D&D 5e formula hardcoded in frontend; should be backend concern.

### 3. Hit Points Calculation

**File:** `app/components/ui/class/UiClassHitPointsCard.vue:13-15`

```typescript
const averageHp = computed(() => {
  return Math.floor(props.hitDie / 2) + 1
})
```

**Problem:** D&D 5e HP formula duplicated in frontend.

### 4. Features Grouped By Level

**File:** `app/components/ui/class/UiClassProgressionTable.vue:36-47`

```typescript
const featuresByLevel = computed(() => {
  const grouped = new Map<number, string[]>()
  for (const feature of props.features) {
    const level = feature.level
    const names = grouped.get(level) || []
    names.push(feature.feature_name)
    grouped.set(level, names)
  }
  return grouped
})
```

**Problem:** Transforms flat array into grouped structure on every render.

### 5. Counter Value Interpolation

**File:** `app/components/ui/class/UiClassProgressionTable.vue:63-69`

```typescript
const getCounterAtLevel = (counterName: string, level: number): number | null => {
  const entries = props.counters
    .filter(c => c.counter_name === counterName && c.level <= level)
    .sort((a, b) => b.level - a.level)
  return entries[0]?.counter_value ?? null
}
```

**Problem:** Sparse counter data requires interpolation logic; should be pre-computed.

### 6. Counter Formatting (Sneak Attack Special Case)

**File:** `app/components/ui/class/UiClassProgressionTable.vue:74-84`

```typescript
const formatCounterValue = (counterName: string, value: number | null): string => {
  if (value === null) return '—'
  if (counterName === 'Sneak Attack') {
    return `${value}d6`
  }
  return String(value)
}
```

**Problem:** Counter display format is domain knowledge that should live in backend.

### 7. Spell Slot Column Visibility

**File:** `app/components/ui/accordion/UiAccordionLevelProgression.vue:21-46`

```typescript
const showSpellLevel = (level: number) => {
  const key = `spell_slots_${ordinalSuffix}` as keyof LevelProgression
  return props.levelProgression.some((prog) => {
    const value = prog[key]
    return value !== null && value !== 0
  })
}

const visibleSpellLevels = computed(() => {
  const levels = []
  for (let i = 1; i <= 9; i++) {
    if (showSpellLevel(i)) levels.push(i)
  }
  return levels
})
```

**Problem:** Computing which spell slot columns to show on every render.

### 8. Accordion Section Counts

**File:** `app/pages/classes/[slug].vue:118-175`

```typescript
items.push({
  label: `Class Traits (${traits.length})${inheritedLabel}`,
  // ...
})
```

**Problem:** Counts computed client-side for accordion labels.

---

## Backend Enhancement Proposals

### Proposal 1: Pre-Computed Progression Table

**New Field:** `ClassResource.progression_table`

```json
{
  "progression_table": {
    "columns": [
      { "key": "level", "label": "Level" },
      { "key": "proficiency_bonus", "label": "Prof. Bonus" },
      { "key": "features", "label": "Features" },
      { "key": "sneak_attack", "label": "Sneak Attack", "format": "dice_d6" }
    ],
    "rows": [
      {
        "level": 1,
        "proficiency_bonus": "+2",
        "features": "Expertise, Sneak Attack, Thieves' Cant",
        "sneak_attack": "1d6"
      },
      {
        "level": 2,
        "proficiency_bonus": "+2",
        "features": "Cunning Action",
        "sneak_attack": "1d6"
      }
      // ... levels 3-20
    ]
  }
}
```

**Benefits:**
- Eliminates 4 frontend computed properties
- Single source of truth for D&D rules
- Counter formatting handled server-side
- Sparse data pre-interpolated

**Implementation:** Add `ProgressionTableGenerator` service class.

---

### Proposal 2: Pre-Computed Hit Points Object

**New Field:** `ClassResource.hit_points`

```json
{
  "hit_points": {
    "hit_die": "d10",
    "hit_die_numeric": 10,
    "first_level": {
      "value": 10,
      "description": "10 + Constitution modifier"
    },
    "higher_levels": {
      "roll": "1d10",
      "average": 6,
      "description": "1d10 (or 6) + Constitution modifier per fighter level after 1st"
    }
  }
}
```

**Benefits:**
- Removes frontend HP calculation
- Formatted descriptions ready to display
- Class name integrated into description

**Implementation:** Add accessor `getHitPointsAttribute()` to `CharacterClass` model.

---

### Proposal 3: Effective Data for Subclasses

**New Field:** `ClassResource.effective_data` (for subclasses only)

```json
{
  "effective_data": {
    "counters": [...],      // Inherited from parent
    "traits": [...],        // Inherited from parent
    "level_progression": [...], // Inherited from parent
    "equipment": [...],     // Inherited from parent
    "proficiencies": [...], // Inherited from parent
    "hit_die": 10,         // Inherited from parent
    "hit_points": {...}    // Pre-computed from parent
  }
}
```

**Current Structure (subclass):**
```json
{
  "is_base_class": false,
  "counters": [],           // Empty - subclass specific only
  "traits": [],             // Empty - subclass specific only
  "parent_class": {         // Nested - requires frontend to extract
    "counters": [...],
    "traits": [...]
  }
}
```

**Proposed Structure (subclass):**
```json
{
  "is_base_class": false,
  "counters": [],           // Subclass-specific only (unchanged)
  "traits": [],             // Subclass-specific only (unchanged)
  "parent_class": {...},    // Still available for reference
  "effective_data": {       // NEW: Pre-resolved inherited data
    "counters": [...],      // From parent
    "traits": [...],        // From parent
    "level_progression": [...],
    "equipment": [...],
    "proficiencies": [...],
    "hit_die": 10,
    "hit_points": {...}
  }
}
```

**Benefits:**
- Frontend uses `effective_data` directly without conditionals
- Original data still available for "subclass-specific" views
- Single access pattern for both base classes and subclasses

**Implementation:** Populate `effective_data` in `ClassResource::toArray()` for subclasses.

---

### Proposal 4: Section Counts for Accordion

**New Field:** `ClassResource.section_counts`

```json
{
  "section_counts": {
    "counters": 3,
    "traits": 8,
    "features": 34,
    "proficiencies": 12,
    "equipment": 4,
    "subclasses": 7,
    "spells": 89
  }
}
```

**Benefits:**
- Accordion labels can display counts without loading full data
- Enables "X traits" display before expanding accordion
- Supports lazy loading of accordion content

**Implementation:** Add `withCount()` to relationships in controller.

---

### Proposal 5: Spell Slot Summary

**New Field:** `ClassResource.spell_slot_summary`

```json
{
  "spell_slot_summary": {
    "has_spell_slots": true,
    "caster_type": "full",  // "full" | "half" | "third" | "pact" | null
    "max_spell_level": 9,
    "available_levels": [1, 2, 3, 4, 5, 6, 7, 8, 9],
    "has_cantrips": true,
    "tracks_spells_known": false  // vs. prepared casting
  }
}
```

**Benefits:**
- Frontend knows which columns to render without scanning all rows
- Caster type enables different UI treatments
- Eliminates `visibleSpellLevels` computed property

**Implementation:** Add `getSpellSlotSummaryAttribute()` accessor.

---

## Migration Strategy

### Phase 1: Additive (Non-Breaking)

Add new fields alongside existing structure:
- `hit_points`
- `section_counts`
- `spell_slot_summary`

Frontend can adopt incrementally; existing code continues working.

### Phase 2: Effective Data

Add `effective_data` for subclasses. Frontend can:
1. Continue using `is_base_class` conditionals (legacy)
2. Or use `effective_data` directly (modern)

### Phase 3: Progression Table

Add `progression_table` with full pre-computed data:
- Remove frontend `UiClassProgressionTable` computed logic
- Direct render of `progression_table.rows`

---

## Complexity Comparison

| Metric | Current Frontend | After Backend |
|--------|-----------------|---------------|
| Computed properties | 12 | 3 |
| Conditional checks (`is_base_class`) | 8 | 1 |
| D&D formula implementations | 3 | 0 |
| Data transformation loops | 5 | 0 |
| Lines of logic | ~200 | ~50 |

---

## Backend Implementation Estimate

| Component | Effort | Complexity |
|-----------|--------|------------|
| `hit_points` accessor | 1 hour | Low |
| `section_counts` query | 1 hour | Low |
| `spell_slot_summary` accessor | 2 hours | Medium |
| `effective_data` for subclasses | 3 hours | Medium |
| `progression_table` generator | 4-6 hours | High |
| Tests for all new fields | 4 hours | Medium |
| **Total** | **15-17 hours** | - |

---

## Recommended Priority

1. **High Priority (Immediate Value)**
   - `effective_data` - Removes 80% of inheritance logic
   - `hit_points` - Simple, immediate frontend cleanup

2. **Medium Priority (Good ROI)**
   - `section_counts` - Enables lazy loading
   - `spell_slot_summary` - Clean column logic

3. **Lower Priority (Nice to Have)**
   - `progression_table` - Most complex, but biggest cleanup

---

## Questions for Backend Team

1. Should `effective_data` be opt-in via `?include=effective_data` or always included?
2. Should `progression_table` be a separate endpoint (`/classes/{slug}/progression`) for lazy loading?
3. Are there other API consumers that would benefit from these changes?
4. Do counter formats (like `Sneak Attack → 1d6`) need to be configurable per counter type?

---

## Appendix: Current API Response Structure

```json
{
  "id": 1,
  "slug": "rogue-arcane-trickster",
  "name": "Arcane Trickster",
  "hit_die": null,           // null for subclass!
  "is_base_class": false,
  "parent_class": {          // Nested parent data
    "id": 10,
    "slug": "rogue",
    "name": "Rogue",
    "hit_die": 8,
    "counters": [...],
    "traits": [...],
    "features": [...],
    "level_progression": [...],
    "equipment": [...],
    "proficiencies": [...]
  },
  "features": [...],         // Subclass-only features
  "counters": [],            // Empty
  "traits": [],              // Empty
  "level_progression": [],   // Empty
  "equipment": [],           // Empty
  "proficiencies": []        // Empty
}
```

This structure forces the frontend to:
1. Check `is_base_class`
2. Extract from `parent_class` if false
3. Use entity directly if true

Repeated for every section, across multiple components.
