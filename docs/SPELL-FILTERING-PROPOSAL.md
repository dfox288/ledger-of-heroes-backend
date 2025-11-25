# Spell Filtering Capabilities - Comprehensive Proposal

**Date:** 2025-11-25
**Status:** 📋 Proposal for Review

---

## 🎯 Goal

Maximize the usefulness of our D&D 5e spell data by providing comprehensive, intuitive filtering that leverages ALL available data.

---

## 📊 Current State Analysis

### ✅ Already Indexed in Meilisearch (Working)

| Field | Type | Example | Use Case |
|-------|------|---------|----------|
| `level` | int | `level = 0` | Find cantrips/specific spell levels |
| `school_code` | string | `school_code = EV` | Find evocation spells |
| `school_name` | string | `school_name = Evocation` | Same as above, full name |
| `concentration` | bool | `concentration = true` | Find concentration spells |
| `ritual` | bool | `ritual = true` | Find ritual spells |
| `class_slugs` | array | `class_slugs IN [bard]` | Find bard spells |
| `tag_slugs` | array | `tag_slugs IN [ritual-caster]` | Find tagged spells (22% coverage) |
| `source_codes` | array | `source_codes IN [PHB]` | Find PHB spells |
| `components` | string | `components = "V, S"` | Stored but not useful (need parsed) |
| `casting_time` | string | `casting_time = "1 action"` | Stored but not useful (too varied) |
| `range` | string | `range = "Touch"` | Stored but not useful (too varied) |
| `duration` | string | `duration = "Instantaneous"` | Stored but not useful (too varied) |

### ❌ NOT Indexed (Missing Valuable Filters)

| Data | Location | Use Case | Impact |
|------|----------|----------|--------|
| **Damage Types** | `spell_effects` table | Find fire/cold/force damage spells | **HIGH** - Core combat feature |
| **Saving Throws** | `entity_saving_throws` table | Find DEX/WIS/CON save spells | **HIGH** - Tactical gameplay |
| **Component Breakdown** | Parse `components` string | Find spells without verbal/somatic/material | **MEDIUM** - Situational (Subtle Spell, Silence, imprisonment) |

---

## 🚀 Proposal: Enhanced Meilisearch Indexing

### A. Add Missing Fields to `toSearchableArray()`

```php
public function toSearchableArray(): array
{
    $this->loadMissing([
        'spellSchool', 'sources.source', 'classes', 'tags',
        'effects.damageType', 'savingThrows'  // NEW
    ]);

    // Parse components into booleans
    $hasVerbal = str_contains($this->components, 'V');
    $hasSomatic = str_contains($this->components, 'S');
    $hasMaterial = str_contains($this->components, 'M');

    return [
        // ... existing fields ...

        // NEW: Damage types (array of codes)
        'damage_types' => $this->effects->pluck('damageType.code')->unique()->values()->all(),

        // NEW: Saving throws (array of ability codes)
        'saving_throws' => $this->savingThrows->pluck('ability_code')->unique()->values()->all(),

        // NEW: Component breakdown (booleans)
        'requires_verbal' => $hasVerbal,
        'requires_somatic' => $hasSomatic,
        'requires_material' => $hasMaterial,
    ];
}
```

### B. Update `searchableOptions()` - Add Filterable Attributes

```php
public function searchableOptions(): array
{
    return [
        'filterableAttributes' => [
            'id',
            'level',
            'school_name',
            'school_code',
            'concentration',
            'ritual',
            'source_codes',
            'class_slugs',
            'tag_slugs',
            // NEW:
            'damage_types',        // Array of damage type codes
            'saving_throws',       // Array of ability codes
            'requires_verbal',     // Boolean
            'requires_somatic',    // Boolean
            'requires_material',   // Boolean
        ],
        // ... rest ...
    ];
}
```

---

## 💡 New Filtering Capabilities

### 1. Damage Type Filtering ⭐ HIGH VALUE

**Use Cases:**
- Find all fire damage spells for a pyromancer build
- Find force damage spells (bypass most resistances)
- Find spells that deal NO damage (utility/buff spells)

**Examples:**
```bash
# Fire damage spells
GET /api/v1/spells?filter=damage_types IN [F]

# Force or radiant damage (ghost-busting)
GET /api/v1/spells?filter=damage_types IN [O, R]

# Fire damage cantrips
GET /api/v1/spells?filter=damage_types IN [F] AND level = 0

# Utility spells (no damage)
GET /api/v1/spells?filter=damage_types IS EMPTY
```

**Data Coverage:** ~40% of spells deal damage (rough estimate based on D&D distribution)

---

### 2. Saving Throw Filtering ⭐ HIGH VALUE

**Use Cases:**
- Exploit enemy weaknesses (target low DEX with DEX saves)
- Build spell lists around specific saves
- Find spells with NO saves (auto-hit spells like Magic Missile)

**Examples:**
```bash
# DEX save spells (target slow creatures)
GET /api/v1/spells?filter=saving_throws IN [DEX]

# WIS save spells (target low WIS creatures)
GET /api/v1/spells?filter=saving_throws IN [WIS]

# DEX or CON saves (common weak points)
GET /api/v1/spells?filter=saving_throws IN [DEX, CON]

# No saving throw (auto-hit spells)
GET /api/v1/spells?filter=saving_throws IS EMPTY
```

**Data Coverage:** ~30-40% of spells require saves

---

### 3. Component Filtering 🔧 MEDIUM VALUE

**Use Cases:**
- **Subtle Spell** metamagic - find spells without verbal/somatic (cast silently)
- **Silence** spell - find spells usable in Silence (no verbal)
- **Imprisonment** - find spells usable without material components
- **Grappled/Restrained** - find spells without somatic components

**Examples:**
```bash
# Spells without verbal (castable in Silence)
GET /api/v1/spells?filter=requires_verbal = false

# Spells without somatic (castable while grappled)
GET /api/v1/spells?filter=requires_somatic = false

# Spells without material (no component pouch needed)
GET /api/v1/spells?filter=requires_material = false

# Spells without V or S (Subtle Spell candidates)
GET /api/v1/spells?filter=requires_verbal = false AND requires_somatic = false

# Concentration spells without verbal (silent buff spells)
GET /api/v1/spells?filter=concentration = true AND requires_verbal = false
```

**Data Coverage:** 100% (all spells have component info)

---

## 📋 Implementation Checklist

### Phase 1: Update Model (30 min)
- [ ] Add `damage_types`, `saving_throws`, `requires_*` to `toSearchableArray()`
- [ ] Add new fields to `searchableOptions()` filterableAttributes
- [ ] Eager load `effects.damageType` and `savingThrows` in `searchableWith()`

### Phase 2: Re-index (5 min)
- [ ] Run `php artisan scout:flush "App\Models\Spell"`
- [ ] Run `php artisan scout:import "App\Models\Spell"`
- [ ] Verify index via API or Meilisearch admin

### Phase 3: Update Documentation (15 min)
- [ ] Update SpellController PHPDoc with new examples
- [ ] Add "Damage Type Filtering" section
- [ ] Add "Saving Throw Filtering" section
- [ ] Add "Component Filtering" section
- [ ] Update list of filterable fields

### Phase 4: Testing (20 min)
- [ ] Test damage type filtering
- [ ] Test saving throw filtering
- [ ] Test component filtering
- [ ] Test combined filters (e.g., fire damage + DEX save + level <= 3)
- [ ] Test empty arrays (spells with NO damage, NO saves)

---

## 🎯 Expected Impact

| Feature | Value | Rationale |
|---------|-------|-----------|
| **Damage Types** | ⭐⭐⭐⭐⭐ | Core combat mechanic, highly requested |
| **Saving Throws** | ⭐⭐⭐⭐⭐ | Tactical gameplay, essential for optimization |
| **Component Breakdown** | ⭐⭐⭐ | Situational but valuable for specific builds |

**Total Estimated Time:** ~70 minutes
**User Value:** Massive - enables tactical spell selection and build optimization

---

## 📝 API Examples After Implementation

### Combat Optimization
```bash
# Fire damage, DEX save, low level (optimal for exploiting weaknesses)
GET /api/v1/spells?filter=damage_types IN [F] AND saving_throws IN [DEX] AND level <= 3

# Force damage spells (bypass resistance)
GET /api/v1/spells?filter=damage_types IN [O]

# Auto-hit damage spells (no saves, no attack rolls)
GET /api/v1/spells?filter=damage_types NOT EMPTY AND saving_throws IS EMPTY
```

### Metamagic Planning
```bash
# Subtle Spell candidates (no V or S)
GET /api/v1/spells?filter=requires_verbal = false AND requires_somatic = false

# Twinned Spell candidates (single-target spells, typically no saves)
GET /api/v1/spells?filter=saving_throws IS EMPTY AND level <= 5
```

### Situational Spellcasting
```bash
# Spells usable in Silence (no verbal)
GET /api/v1/spells?filter=requires_verbal = false

# Spells usable while grappled (no somatic)
GET /api/v1/spells?filter=requires_somatic = false AND casting_time = "1 action"

# Spells for imprisoned casters (no components at all)
GET /api/v1/spells?filter=requires_verbal = false AND requires_somatic = false AND requires_material = false
```

### Build-Specific
```bash
# Pyromancer build (fire damage, bard spells, concentration)
GET /api/v1/spells?filter=damage_types IN [F] AND class_slugs IN [bard] AND concentration = true

# Enchanter build (WIS saves, enchantment school)
GET /api/v1/spells?filter=saving_throws IN [WIS] AND school_code = EN
```

---

## ✅ Decision Needed

**Should we implement this enhanced indexing?**

**PROS:**
- Enables tactical spell selection
- Leverages existing data we're already storing
- Relatively quick to implement (~70 min)
- Makes API dramatically more useful for D&D players

**CONS:**
- Increases index size (negligible - ~3 new fields per spell)
- Adds ~10 min to initial import time (re-indexing)
- Requires updating documentation

**Recommendation:** ✅ **YES** - The user value far outweighs the implementation cost.

---

**Prepared by:** Claude Code
**Status:** Awaiting approval
