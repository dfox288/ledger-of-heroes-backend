# API Quality Audit - January 25, 2025

**Audit Scope:** All 7 entity APIs (Spells, Monsters, Classes, Races, Items, Backgrounds, Feats)
**Focus Areas:** Request/Controller/Docs synchronization + Untapped filtering potential
**Method:** Parallel subagent analysis (14 concurrent audits)

---

## Executive Summary

The API is **functionally correct** but has significant technical debt from the MySQL → Meilisearch migration. The codebase is in a **transitional state** where:
- ✅ Controllers and Services correctly use Meilisearch-first filtering
- ✅ Request classes validate only Meilisearch parameters
- ❌ DTOs still extract ~50 legacy MySQL parameters (dead code)
- ❌ Some controller documentation references filters that don't exist

**Key Finding:** Found **54 high-value filtering opportunities** across all entities that would transform this from a good API to a **best-in-class D&D 5e API**.

---

## Part 1: Synchronization Audit Results

### 🔴 CRITICAL Issues (Affect All Entities)

#### 1. Dead Code in DTOs - Legacy MySQL Filters

**Problem:** All 7 entity DTOs extract filter parameters that are never validated and never used.

**Affected Files & Unused Parameter Counts:**
- `app/DTOs/SpellSearchDTO.php` → 10 parameters (level, school, concentration, ritual, damage_type, saving_throw, components, etc.)
- `app/DTOs/MonsterSearchDTO.php` → 10 parameters (challenge_rating, min_cr, max_cr, type, size, alignment, spells, etc.)
- `app/DTOs/ClassSearchDTO.php` → 11 parameters (base_only, grants_proficiency, grants_skill, spells, hit_die, etc.)
- `app/DTOs/RaceSearchDTO.php` → 15 parameters (size, grants_proficiency, speaks_language, spells, ability_bonus, etc.)
- `app/DTOs/ItemSearchDTO.php` → 11 parameters (item_type_id, type, rarity, is_magic, has_charges, spells, etc.)
- `app/DTOs/BackgroundSearchDTO.php` → 6 parameters (search, grants_proficiency, grants_skill, speaks_language, etc.)
- `app/Http/Requests/FeatIndexRequest.php` → **7 parameters still validated** (prerequisite_race, prerequisite_ability, min_value, etc.)

**Total:** ~70 unused parameters across codebase

**Root Cause:** Incomplete migration. When Service classes removed MySQL filtering logic, DTOs weren't updated.

**Evidence:**
```php
// Example: SpellSearchDTO.php lines 31-42
filters: [
    'search' => $validated['search'] ?? null,          // ❌ Not validated
    'level' => $validated['level'] ?? null,            // ❌ Not validated
    'school' => $validated['school'] ?? null,          // ❌ Not validated
    // ... 7 more unused parameters
]
```

**Service classes explicitly document removal:**
```php
// MonsterSearchService.php line 108
// MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
```

**Impact:**
- Creates false expectations for developers
- Dead code bloat (~360 lines across DTOs)
- Confusing when reading codebase

**Fix:** Remove entire `filters` arrays from DTOs. Keep only: `searchQuery`, `perPage`, `page`, `sortBy`, `sortDirection`, `meilisearchFilter`.

---

#### 2. BaseIndexRequest Has Unused `search` Parameter

**File:** `app/Http/Requests/BaseIndexRequest.php:30`

**Problem:**
```php
'search' => ['sometimes', 'string', 'max:255'],  // Validated but never used
```

All entities use `q` for full-text Meilisearch search, but `BaseIndexRequest` validates a `search` parameter that nothing uses.

**Impact:** Confusion about which parameter to use (`search` vs `q`)

**Fix:** Remove line 30 from `BaseIndexRequest`, OR document the distinction clearly.

---

#### 3. Missing `@QueryParameter` Annotations

**Problem:** Only the `filter` parameter has Scramble annotations for OpenAPI docs. Standard parameters are missing annotations across all 7 entities.

**Missing Annotations:**
- `q` - Full-text search query
- `sort_by` - Sorting field
- `sort_direction` - Sort order (asc/desc)
- `per_page` - Results per page (1-100)
- `page` - Page number

**Impact:** OpenAPI docs at `/docs/api` are incomplete

**Fix:** Add 5 `@QueryParameter` annotations to each entity's `index()` method (35 annotations total).

---

### 🟡 HIGH-PRIORITY Issues (Entity-Specific)

#### Spells API

**Missing Relationships in SpellShowRequest:**
- `tags` - Exposed in SpellResource:30, works in service, NOT in includable relationships
- `savingThrows` - Exposed in SpellResource:31, works in service, NOT in includable relationships

**Field Name Mismatches:**
- `concentration` (in validation) → `needs_concentration` (in resource)
- `ritual` (in validation) → `is_ritual` (in resource)

**Missing Selectable Fields:**
- `material_components` (SpellResource:21)
- `higher_levels` (SpellResource:26)

---

#### Monsters API

**Status:** ✅ Best synchronized of all entities

**Minor Issues:**
- Same `search` vs `q` parameter confusion as others
- Missing standard parameter annotations

---

#### Classes API

**Duplicate Logic:**
- Feature inheritance logic exists in BOTH Controller (lines 107-110, 121-124) AND Resource (line 13)
- Violates single-responsibility principle

**Unused DTO Filters:**
- 11 legacy parameters in ClassSearchDTO (lines 24-36)

---

#### Races API

**CRITICAL: Documentation Lies**

The RaceController PHPDoc (lines 48-51) documents these filters that **DO NOT EXIST**:
- `has_darkvision` (bool) - NOT in model's `filterableAttributes`
- `darkvision_range` (int) - NOT in model's `filterableAttributes`
- `spell_slugs` (array) - NOT in model's `filterableAttributes`

**23 Example Queries Reference Fake Filters:**
```php
// Line 29 - WILL NOT WORK
GET /api/v1/races?filter=spell_slugs IN [misty-step]

// Line 28 - WILL NOT WORK
GET /api/v1/races?filter=has_darkvision = true

// Lines 36-38 - WILL NOT WORK
GET /api/v1/races?filter=spell_slugs IN [dancing-lights, faerie-fire]
```

**Impact:** Developer experience nightmare - copy-paste examples from docs won't work.

**Sortable Column Issue:**
- `RaceIndexRequest:29` includes `'size'` in sortable columns
- Database column is `size_id` (integer), not `size` (string)
- Will cause SQL errors or incorrect sorting

**Unused DTO Filters:**
- 15 legacy parameters (lines 25-40)

---

#### Items API

**Missing Relationships in ItemShowRequest:**
- `tags` - Available in service (line 42), exposed in resource (line 48), NOT validated
- `savingThrows` - Available in service (line 43), exposed in resource (line 50), NOT validated

**Missing Selectable Fields (14 total):**
- `item_type_id`, `detail`, `cost_cp`, `weight`, `damage_dice`, `versatile_damage`, `damage_type_id`, `range_normal`, `range_long`, `armor_class`, `stealth_disadvantage`, `charges_max`, `recharge_formula`, `recharge_timing`

**Unused DTO Filters:**
- 11 legacy parameters (lines 27-40)

---

#### Backgrounds API

**Status:** ✅ Cleanest synchronization of all entities

**Minor Issues:**
- Missing `include` and `fields` parameter documentation in Controller PHPDoc
- Unused DTO filters (6 parameters, lines 40-46)

---

#### Feats API

**CRITICAL: Only Entity Still Validating Deprecated Parameters**

`FeatIndexRequest` still validates 7 legacy parameters (lines 26-32):
```php
'prerequisite_race' => ['sometimes', 'string', 'max:255'],
'prerequisite_ability' => ['sometimes', 'string', 'max:255'],
'min_value' => ['sometimes', 'integer', 'min:1', 'max:30'],
'prerequisite_proficiency' => ['sometimes', 'string', 'max:255'],
'has_prerequisites' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],
'grants_proficiency' => ['sometimes', 'string', 'max:255'],
'grants_skill' => ['sometimes', 'string', 'max:255'],
```

**Problem:** FeatSearchService ignores these completely (lines 96-112 are empty).

**PHPDoc Contradiction:**
- Line 64 says: "The following parameters still work but are deprecated"
- Reality: They don't work at all

**Missing Relationship:**
- `tags` - Available in service, exposed in resource, NOT in includable relationships

---

## Part 2: Untapped Filtering Potential

### Overview by Entity

| Entity | Current Filters | High-Value Opportunities | Medium-Value | Total Potential |
|--------|----------------|-------------------------|--------------|----------------|
| **Spells** | 14 | 5 | 4 | +9 fields |
| **Monsters** | 20 | 5 | 4 | +9 fields |
| **Classes** | 9 | 3 | 4 | +7 fields |
| **Races** | 9 | 4 | 3 | +7 fields |
| **Items** | 25 | 5 | 3 | +8 fields |
| **Backgrounds** | 4 | 3 | 3 | +6 fields |
| **Feats** | 4 | 4 | 4 | +8 fields |
| **TOTAL** | **85** | **29** | **25** | **+54 fields** |

---

### Spells (477 spells) - 14 current → 23 potential filters

#### High-Value Opportunities (5 fields)

**1. `casting_time` (string) - Action Economy Filter**
- **Current:** In `toSearchableArray()`, NOT in `filterableAttributes`
- **Why critical:** Action economy is THE most important D&D 5e mechanic
- **Data:**
  - 1 action: 362 spells (76%)
  - 1 bonus action: 38 spells (8%) - Build-defining for dual-casting
  - 1 reaction: 6 spells (1%) - Shield, Counterspell, Absorb Elements
  - 1+ minute: 71 spells (15%) - Out-of-combat utility
- **Use cases:**
  - `filter=casting_time = '1 bonus action'` → Healing Word, Misty Step, Hex
  - `filter=casting_time = '1 reaction'` → Defensive/counter-magic builds
  - `filter=casting_time LIKE 'minute'` → Ritual spells, summoning
- **Implementation:** Add to `filterableAttributes` (already indexed)

**2. `range` (string) - Targeting Filter**
- **Current:** In `toSearchableArray()`, NOT in `filterableAttributes`
- **Why critical:** Self-buffs vs. touch healing vs. long-range combat
- **Data:**
  - Self: 86 spells (Shield, Mage Armor, Stoneskin)
  - Touch: 68 spells (Cure Wounds, Shocking Grasp)
  - 500+ feet: Long-range artillery
  - AoE markers: "30-foot cone", "20-foot radius"
- **Use cases:**
  - `filter=range = 'Self'` → Self-buff spells
  - `filter=range = 'Touch'` → Healing/melee spells
  - `filter=range LIKE 'cone'` → Burning Hands, Cone of Cold
- **Implementation:** Add to `filterableAttributes` (already indexed)

**3. `duration` (string) - Effect Length Filter**
- **Current:** In `toSearchableArray()`, NOT in `filterableAttributes`
- **Why critical:** Instantaneous damage vs. permanent utility vs. concentration buffs
- **Data:**
  - Instantaneous: 130 spells (27%) - Fireball, Magic Missile
  - Until dispelled: 13 spells (3%) - Permanent effects
  - 8+ hours: 23 spells (5%) - Adventuring day buffs
  - 1 hour or less: 331 spells (69%) - Combat encounters
- **Use cases:**
  - `filter=duration = 'Instantaneous'` → Damage spells
  - `filter=duration LIKE 'dispelled'` → Permanent utility
  - `filter=duration LIKE 'hour' AND concentration = false` → Set-and-forget buffs
- **Implementation:** Add to `filterableAttributes` (already indexed)

**4. `effect_types` (array) - Damage/Healing/Utility Filter**
- **Current:** NOT indexed at all
- **Why critical:** Role-based spell filtering
- **Data:**
  - Damage: 170 spells (36%)
  - Healing: 8 spells (2%)
  - Other/Utility: 299 spells (63%)
- **Use cases:**
  - `filter=effect_types IN [healing]` → Support builds
  - `filter=effect_types IN [damage]` → Offensive casters
  - `filter=effect_types IS EMPTY` → Pure utility
- **Implementation:** Add to `toSearchableArray()` + `filterableAttributes`
  ```php
  'effect_types' => $this->effects->pluck('effect_type')->unique()->values()->all()
  ```

**5. `sources` (array - full names) - User-Friendly Filter**
- **Current:** In `toSearchableArray()`, NOT in `filterableAttributes`
- **Why useful:** `source_codes` works but is cryptic (PHB, XGE, TCoE)
- **Use cases:**
  - `filter=sources IN ["Xanathar's Guide to Everything"]` vs `source_codes IN [XGE]`
- **Implementation:** Add to `filterableAttributes` (already indexed)

---

### Monsters (598 monsters) - 20 current → 29 potential filters

#### High-Value Opportunities (5 fields)

**1. `has_legendary_actions` / `has_lair_actions` (boolean)**
- **Current:** NOT indexed
- **Why critical:** Boss encounter planning
- **Data:**
  - 48 monsters with legendary actions
  - 45 monsters with lair actions
- **Use cases:**
  - `filter=has_legendary_actions = true AND challenge_rating >= 15` → Epic bosses
  - `filter=has_lair_actions = true AND type = dragon` → Dragon lairs
- **Implementation:**
  ```php
  'has_legendary_actions' => $this->legendaryActions->where('is_lair_action', 0)->isNotEmpty(),
  'has_lair_actions' => $this->legendaryActions->where('is_lair_action', 1)->isNotEmpty()
  ```

**2. `is_spellcaster` (boolean)**
- **Current:** NOT indexed
- **Why critical:** Encounter variety planning
- **Data:** 129 spellcasters (22%)
- **Use cases:**
  - `filter=is_spellcaster = true AND challenge_rating <= 5` → Magic-using threats
  - `filter=is_spellcaster = false` → Non-magic encounters
- **Implementation:**
  ```php
  'is_spellcaster' => $this->entitySpells()->exists()
  ```

**3. `damage_immunities` / `damage_resistances` / `damage_vulnerabilities` (arrays)**
- **Current:** NOT indexed
- **Why critical:** Combat optimization (targeting weaknesses)
- **Data:** 593 total modifiers
- **Use cases:**
  - `filter=damage_immunities NOT IN [fire]` → Fire spell preparation
  - `filter=damage_vulnerabilities IN [fire]` → Exploit weaknesses
  - `filter=damage_resistances IN [bludgeoning, piercing, slashing]` → Tank encounters
- **Implementation:**
  ```php
  'damage_immunities' => $this->modifiers()->where('modifier_category', 'damage_immunity')->pluck('condition')->unique()->all()
  ```
- **Caveat:** Condition field contains complex strings like "bludgeoning from nonmagical attacks that aren't silvered" - may need normalization

**4. Key Trait Flags (boolean)**
- **Fields:**
  - `has_legendary_resistance` (37 monsters)
  - `has_magic_resistance` (85 monsters)
  - `has_regeneration`
  - `has_pack_tactics`
- **Why critical:** Tactical planning
- **Use cases:**
  - `filter=has_legendary_resistance = true` → Boss planning (bypass with multiple saves)
  - `filter=has_magic_resistance = true` → Avoid with martial characters
- **Implementation:**
  ```php
  'has_legendary_resistance' => $this->traits()->where('name', 'LIKE', '%Legendary Resistance%')->exists()
  ```

**5. `saving_throw_proficiencies` (array)**
- **Current:** NOT indexed
- **Why critical:** Spellcaster optimization (target weak saves)
- **Data:** 584 total modifiers
- **Use cases:**
  - `filter=saving_throw_proficiencies NOT IN [dex]` → Use Fireball!
  - `filter=saving_throw_proficiencies NOT IN [wis]` → Charm Person works
- **Implementation:**
  ```php
  'saving_throw_proficiencies' => $this->modifiers()
      ->where('modifier_category', 'LIKE', 'saving_throw_%')
      ->pluck('modifier_category')
      ->map(fn($cat) => str_replace('saving_throw_', '', $cat))
      ->unique()->all()
  ```

---

### Classes (131 classes/subclasses) - 9 current → 16 potential filters

#### High-Value Opportunities (3 fields)

**1. Proficiency Filters (HIGHEST VALUE)**
- **Fields:**
  - `saving_throw_proficiencies` (array: STR, DEX, CON, INT, WIS, CHA)
  - `armor_proficiencies` (array: Light, Medium, Heavy, Shields)
  - `weapon_proficiencies` (array: Simple, Martial, specific weapons)
- **Current:** NOT indexed
- **Why critical:** CRITICAL for multiclassing rules
- **Data:** 246 total proficiency records (22 saving throws, 35 armor, 48 weapons)
- **Use cases:**
  - `filter=saving_throw_proficiencies IN [Constitution]` → Tank multiclass builds
  - `filter=armor_proficiencies IN [Heavy Armor]` → Fighter, Paladin, War Cleric
  - `filter=weapon_proficiencies IN [Martial Weapons] AND saving_throw_proficiencies IN [Constitution]` → Optimal melee
- **Implementation:**
  ```php
  'saving_throw_proficiencies' => $this->proficiencies->where('proficiency_type', 'saving_throw')->pluck('proficiency_name')->unique()->all(),
  'armor_proficiencies' => $this->proficiencies->where('proficiency_type', 'armor')->pluck('proficiency_name')->unique()->all()
  ```

**2. Spell Availability Filters**
- **Fields:**
  - `has_spells` (boolean)
  - `spell_count` (integer)
- **Current:** NOT indexed
- **Why critical:** Full vs. half vs. non-caster distinction
- **Data:**
  - Wizard: 315 spells
  - Sorcerer: 206 spells
  - Druid: 167 spells
  - Barbarian/Fighter/Monk/Rogue: 0 spells
- **Use cases:**
  - `filter=has_spells = true` → Spellcasters only
  - `filter=spell_count >= 150` → Full casters
  - `filter=has_spells = false` → Martial builds
- **Implementation:**
  ```php
  'has_spells' => $this->spells_count > 0,
  'spell_count' => $this->spells_count  // Use withCount('spells')
  ```

**3. `max_spell_level` (integer)**
- **Current:** NOT indexed
- **Why critical:** Full-caster (9) vs. half-caster (5) vs. non-caster (0) distinction
- **Use cases:**
  - `filter=max_spell_level = 9` → Full casters
  - `filter=max_spell_level = 5` → Paladin, Ranger
- **Implementation:**
  ```php
  'max_spell_level' => $this->levelProgression()
      ->where(function($q) {
          for ($i = 9; $i >= 1; $i--) {
              $q->orWhere("spell_slots_{$i}th", '>', 0);
          }
      })
      ->max('level') ?? 0
  ```

---

### Races (89 races) - 9 current → 16 potential filters

#### High-Value Opportunities (4 fields)

**1. Ability Score Bonuses (HIGHEST PRIORITY)**
- **Fields:**
  - `ability_str_bonus` (integer)
  - `ability_dex_bonus` (integer)
  - `ability_con_bonus` (integer)
  - `ability_int_bonus` (integer)
  - `ability_wis_bonus` (integer)
  - `ability_cha_bonus` (integer)
  - `has_flexible_bonus` (boolean)
- **Current:** NOT indexed
- **Why critical:** Build foundation - THE primary race selection criterion
- **Data:** 61 races have ability modifiers (69%)
- **Use cases:**
  - `filter=ability_dex_bonus >= 2` → Races with +2 DEX or better
  - `filter=ability_cha_bonus > 0` → Races with any CHA boost
  - `filter=ability_str_bonus > 0 AND ability_con_bonus > 0` → Tank builds
- **Implementation:**
  ```php
  $abilityBonuses = $this->modifiers()->where('modifier_category', 'ability_score')->with('abilityScore')->get();
  'ability_str_bonus' => $abilityBonuses->firstWhere('abilityScore.code', 'STR')?->value ?? 0,
  // ... repeat for all 6 abilities
  ```

**2. `spell_slugs` (array) - Innate Spellcasting**
- **Current:** NOT indexed ⚠️ **BUT ALREADY DOCUMENTED IN CONTROLLER!**
- **Why critical:** Major racial feature
- **Data:** 13 races with innate spells (21 total spell relationships)
- **Examples:** Drow (Dancing Lights, Faerie Fire, Darkness), Tiefling (Thaumaturgy, Hellish Rebuke)
- **Use cases:**
  - `filter=spell_slugs IN [misty-step]` → Eladrin
  - `filter=has_innate_spells = true` → All spellcasting races
- **Implementation:**
  ```php
  'spell_slugs' => $this->spells->pluck('spell.slug')->filter()->values()->all(),
  'has_innate_spells' => $this->spells->isNotEmpty()
  ```

**3. Proficiency Types**
- **Fields:**
  - `has_skill_proficiencies` (boolean)
  - `has_weapon_proficiencies` (boolean)
  - `has_armor_proficiencies` (boolean)
  - `skill_proficiencies` (array: Perception, Stealth, etc.)
- **Current:** NOT indexed
- **Why critical:** Martial build differentiation
- **Data:** 12 races have proficiencies
- **Use cases:**
  - `filter=has_armor_proficiencies = true` → Races with armor training
  - `filter=skill_proficiencies IN [Perception]` → Keen senses
- **Implementation:**
  ```php
  'skill_proficiencies' => $this->proficiencies->where('proficiency_type', 'skill')->pluck('skill.name')->filter()->all()
  ```

**4. `damage_resistances` (array)**
- **Current:** NOT indexed
- **Why critical:** Survival feature for tanky builds
- **Use cases:**
  - `filter=damage_resistances IN [poison]` → Poison-resistant races
  - `filter=has_damage_resistances = true` → Any resistance
- **Implementation:**
  ```php
  'damage_resistances' => $this->modifiers()->where('modifier_category', 'damage_resistance')->pluck('damageType.name')->filter()->all()
  ```

---

### Items (2,232 items) - 25 current → 33 potential filters

#### High-Value Opportunities (5 fields)

**1. `property_codes` (array) - Weapon/Armor Properties**
- **Current:** NOT indexed
- **Why critical:** Build optimization (finesse, light, reach, versatile, etc.)
- **Data:** 641 items with properties
- **Available codes:** A (Ammunition), F (Finesse), H (Heavy), L (Light), LD (Loading), M (Martial), R (Reach), S (Special), T (Thrown), 2H (Two-Handed), V (Versatile)
- **Use cases:**
  - `filter=property_codes IN [F, L]` → Light finesse weapons (Rogue dual-wield)
  - `filter=property_codes IN [R]` → Reach weapons (Polearm Master)
  - `filter=property_codes IN [V]` → Versatile weapons (GWF flexibility)
- **Implementation:**
  ```php
  'property_codes' => $this->properties->pluck('code')->all()
  ```

**2. `modifier_categories` (array) - Stat Modifiers**
- **Current:** NOT indexed
- **Why critical:** Build optimization (spell attack, AC bonus, damage resistance)
- **Data:** 1,016 items with modifiers
- **Categories:** melee_attack, melee_damage, ranged_attack, ranged_damage, weapon_attack, weapon_damage, spell_attack, spell_dc, ac_base, ac_bonus, ac_magic, damage_resistance, ability_score, skill, speed, initiative, saving_throw
- **Use cases:**
  - `filter=modifier_categories IN [spell_attack]` → Wand of the War Mage, Rod of the Pact Keeper
  - `filter=modifier_categories IN [damage_resistance]` → Defensive items
  - `filter=modifier_categories IN [ability_score]` → ASI items
- **Implementation:**
  ```php
  'modifier_categories' => $this->modifiers->pluck('modifier_category')->unique()->values()->all()
  ```

**3. `proficiency_names` (array) - Proficiency Requirements**
- **Current:** NOT indexed
- **Why critical:** Class restrictions (wizards can't use martial weapons)
- **Data:** 660 items require proficiencies
- **Use cases:**
  - `filter=proficiency_names IN [Simple Weapons]` → Wizard-usable weapons
  - `filter=proficiency_names IN [Martial Weapons]` → Fighter/Barbarian/Paladin
  - `filter=proficiency_names IN [Firearms]` → DMG modern campaigns
- **Implementation:**
  ```php
  'proficiency_names' => $this->proficiencies->pluck('proficiencyType.name')->filter()->unique()->all()
  ```

**4. Recharge Mechanics**
- **Fields:**
  - `recharge_timing` (enum: dawn, dusk)
  - `recharge_formula` (string: 1d6, 1d4+1)
- **Current:** In database columns, NOT indexed
- **Why critical:** Magic item resource management
- **Data:** 100 items with charges
- **Use cases:**
  - `filter=recharge_timing = dawn` → Long rest items
  - `filter=has_charges = true AND recharge_timing = dawn` → Daily magic items
- **Implementation:** Add to `toSearchableArray()` (already in DB columns)

**5. `saving_throw_abilities` (array)**
- **Current:** NOT indexed
- **Why critical:** Offensive item tactics (target weak saves)
- **Data:** 144 items with saving throws
- **Use cases:**
  - `filter=saving_throw_abilities IN [WIS]` → Target weak Wisdom saves
  - `filter=saving_throw_abilities IN [INT, CHA]` → Mental saves
- **Implementation:**
  ```php
  'saving_throw_abilities' => $this->savingThrows->pluck('code')->unique()->all()
  ```

---

### Backgrounds (34 backgrounds) - 4 current → 10 potential filters

#### High-Value Opportunities (3 fields)

**1. `skill_proficiencies` (array) - PRIMARY SELECTION CRITERION**
- **Current:** NOT indexed
- **Why critical:** THE primary background selection criterion
- **Data:** 33/34 backgrounds have skills (97%)
- **Distribution:**
  - Persuasion: 9 backgrounds
  - Insight: 9 backgrounds
  - Athletics: 7 backgrounds
  - History: 6 backgrounds
  - Survival: 5 backgrounds
  - 18 unique skills total
- **Use cases:**
  - `filter=skill_proficiencies IN [stealth]` → Stealth-granting backgrounds
  - `filter=skill_proficiencies IN [persuasion, deception]` → Social builds
- **Implementation:**
  ```php
  'skill_proficiencies' => $this->proficiencies->where('proficiency_type', 'skill')->filter(fn($p) => $p->skill)->pluck('skill.slug')->unique()->all()
  ```

**2. `tool_proficiency_types` (array)**
- **Current:** NOT indexed
- **Why critical:** Tool proficiencies are mechanically significant (Xanathar's Guide)
- **Data:** 18/34 backgrounds have categorized tools (53%)
- **Categories:** gaming, musical, artisan
- **Use cases:**
  - `filter=tool_proficiency_types IN [musical]` → Bard-themed backgrounds
  - `filter=tool_proficiency_types IN [gaming, artisan]` → Crafting builds
- **Implementation:**
  ```php
  'tool_proficiency_types' => $this->proficiencies->where('proficiency_type', 'tool')->pluck('proficiency_subcategory')->filter()->unique()->all()
  ```

**3. `grants_language_choice` (boolean)**
- **Current:** NOT indexed
- **Why useful:** Character optimization for linguist builds
- **Data:** 14/34 backgrounds grant languages (41%)
- **Use cases:**
  - `filter=grants_language_choice = true` → Language-granting backgrounds
- **Implementation:**
  ```php
  'grants_language_choice' => $this->languages->count() > 0
  ```

---

### Feats (138 feats) - 4 current → 12 potential filters

#### High-Value Opportunities (4 fields)

**1. `improved_abilities` (array) - ASI is THE PRIMARY CRITERION**
- **Current:** NOT indexed
- **Why critical:** 62% of feats grant ASI - THE primary feat selection criterion
- **Data:**
  - STR: 20 feats
  - DEX: 17 feats
  - CON: 14 feats
  - CHA: 13 feats
  - INT: 13 feats
  - WIS: 9 feats
- **Use cases:**
  - `filter=improved_abilities IN [DEX]` → Feats boosting Dexterity
  - `filter=improved_abilities IN [STR, CON]` → Tank feats
  - `filter=improved_abilities IN [INT, WIS, CHA]` → Caster feats
- **Implementation:**
  ```php
  'improved_abilities' => $this->modifiers->where('modifier_category', 'ability_score')->whereNotNull('ability_score_id')->pluck('abilityScore.code')->unique()->all()
  ```

**2. `has_prerequisites` (boolean)**
- **Current:** NOT indexed
- **Why critical:** "Which feats can I take NOW?" - immediate eligibility
- **Data:**
  - Without prerequisites: 85 feats (62%)
  - With prerequisites: 53 feats (38%)
- **Use cases:**
  - `filter=has_prerequisites = false` → Feats for everyone
  - `filter=has_prerequisites = true` → Restricted feats
- **Implementation:**
  ```php
  'has_prerequisites' => $this->prerequisites->isNotEmpty()
  ```

**3. `grants_proficiencies` (boolean)**
- **Current:** NOT indexed
- **Why critical:** Proficiency-granting feats expand character capabilities
- **Data:** 28 feats grant proficiencies (20%)
- **Use cases:**
  - `filter=grants_proficiencies = true` → Feats expanding capabilities
- **Implementation:**
  ```php
  'grants_proficiencies' => $this->proficiencies->isNotEmpty()
  ```

**4. `prerequisite_types` (array)**
- **Current:** NOT indexed
- **Why critical:** Race-locked vs. ability-locked feats
- **Data:**
  - Race prerequisites: 29 feats
  - Ability score prerequisites: 10 feats
  - Proficiency prerequisites: 6 feats
- **Use cases:**
  - `filter=prerequisite_types IN [Race]` → Race-specific feats
  - `filter=prerequisite_types IN [AbilityScore]` → Ability requirement feats
- **Implementation:**
  ```php
  'prerequisite_types' => $this->prerequisites->whereNotNull('prerequisite_type')->pluck('prerequisite_type')->map(fn($type) => class_basename($type))->unique()->all()
  ```

---

## Prioritized Action Plan

### Phase 1: Critical Technical Debt (IMMEDIATE - 2-3 hours)

**Goal:** Eliminate false API expectations and clean up dead code

**Tasks:**
1. ✅ Remove dead DTO filters (7 files)
   - SpellSearchDTO: Remove 10 parameters
   - MonsterSearchDTO: Remove 10 parameters
   - ClassSearchDTO: Remove 11 parameters
   - RaceSearchDTO: Remove 15 parameters
   - ItemSearchDTO: Remove 11 parameters
   - BackgroundSearchDTO: Remove 6 parameters
   - Keep only: searchQuery, perPage, page, sortBy, sortDirection, meilisearchFilter

2. ✅ Fix Races controller documentation
   - Remove fake filter examples (23 examples using spell_slugs, has_darkvision)
   - Remove non-existent fields from filterable fields list
   - Fix sortable column from `size` to `size_id`

3. ✅ Remove deprecated Feats validation
   - Delete 7 legacy parameters from FeatIndexRequest (lines 26-32)
   - Update PHPDoc to say parameters removed (not "deprecated but still work")

4. ✅ Remove BaseIndexRequest `search` parameter
   - Delete line 30 from BaseIndexRequest

**Impact:** Eliminates 500+ lines of dead code, prevents developer confusion

---

### Phase 2: Missing Relationships (HIGH - 1-2 hours)

**Goal:** Complete API relationship exposure

**Tasks:**
1. ✅ Add missing `tags` and `savingThrows` to SpellShowRequest includable relationships
2. ✅ Add missing `tags` and `savingThrows` to ItemShowRequest includable relationships
3. ✅ Add missing `tags` to FeatShowRequest includable relationships
4. ✅ Fix Spell field names in SpellShowRequest: `needs_concentration`, `is_ritual`
5. ✅ Add missing fields: `material_components`, `higher_levels`
6. ✅ Add 14 missing selectable fields to ItemShowRequest
7. ✅ Remove duplicate feature inheritance logic from ClassController (keep in Resource only)

**Impact:** Complete relationship loading capabilities

---

### Phase 3: Quick Win Filters (HIGH - 2-3 hours)

**Goal:** Add 20+ high-value filters with minimal code changes

**Tasks:**
1. ✅ **Spells:** Add `casting_time`, `range`, `duration` to filterableAttributes (already in toSearchableArray)
2. ✅ **Monsters:** Add boolean flags
   - `has_legendary_actions`, `has_lair_actions`, `is_spellcaster`, `has_reactions`
3. ✅ **Classes:** Add spell counters
   - `has_spells`, `spell_count` (use withCount)
4. ✅ **Races:** Implement `spell_slugs` (already documented!)
   - Add to toSearchableArray + filterableAttributes
5. ✅ **Items:** Add recharge fields
   - `recharge_timing`, `recharge_formula` (already in DB)
6. ✅ **Backgrounds:** Add language choice
   - `grants_language_choice` (simple boolean)
7. ✅ **Feats:** Add prerequisite flags
   - `has_prerequisites`, `grants_proficiencies` (simple booleans)

**Impact:** 20+ new gameplay-critical filters in ~3 hours

---

### Phase 4: Complex Filters (MEDIUM - 4-6 hours)

**Goal:** Add relationship-based filtering arrays

**Tasks:**
1. ✅ **Spells:** Add `effect_types` array (damage/healing/utility)
2. ✅ **Monsters:** Add trait flags
   - `has_legendary_resistance`, `has_magic_resistance`
3. ✅ **Monsters:** Add `saving_throw_proficiencies` array
4. ✅ **Classes:** Add proficiency arrays
   - `saving_throw_proficiencies`, `armor_proficiencies`, `weapon_proficiencies`
5. ✅ **Races:** Add ability bonus fields
   - `ability_str_bonus`, `ability_dex_bonus`, `ability_con_bonus`, `ability_int_bonus`, `ability_wis_bonus`, `ability_cha_bonus`
6. ✅ **Races:** Add `skill_proficiencies` array
7. ✅ **Items:** Add arrays
   - `property_codes`, `modifier_categories`, `proficiency_names`, `saving_throw_abilities`
8. ✅ **Backgrounds:** Add `skill_proficiencies`, `tool_proficiency_types`
9. ✅ **Feats:** Add arrays
   - `improved_abilities`, `prerequisite_types`

**Impact:** 30+ advanced filters covering 80%+ of player queries

---

### Phase 5: Polish (LOW - 2-3 hours)

**Goal:** Complete documentation and OpenAPI coverage

**Tasks:**
1. ✅ Add 35 `@QueryParameter` annotations (5 per entity)
   - q, sort_by, sort_direction, per_page, page
2. ✅ Update Controller PHPDocs with new filter examples
3. ✅ Re-index all entities: `php artisan scout:import "App\Models\*"`
4. ✅ Update CHANGELOG.md with all changes
5. ✅ Run full test suite
6. ✅ Create session handover document

**Impact:** Production-ready, well-documented API

---

## Expected Outcomes

### Before Quality Audit
- **85 total filterable fields** across 7 entities
- **~70 dead filter parameters** in DTOs (false expectations)
- **Documentation/implementation mismatches** (Races docs reference fake filters)
- **Basic filtering only** (sources, IDs, slugs, some mechanics)
- **Incomplete OpenAPI docs** (only `filter` parameter annotated)

### After All Phases Complete
- **139 total filterable fields** (+63% increase)
- **0 dead parameters** (clean architecture)
- **Documentation aligned with reality**
- **Gameplay-optimized filtering** (action economy, proficiencies, ASI, combat stats, damage types)
- **Complete OpenAPI documentation**

### Developer Experience Improvements
- ✅ Accurate OpenAPI docs at `/docs/api`
- ✅ No false expectations from dead code
- ✅ Clear Meilisearch-first architecture
- ✅ Consistent patterns across entities
- ✅ Type-safe DTOs without unused fields

### Player/API Consumer Experience
- ✅ "Show me bonus action spells" → `filter=casting_time = '1 bonus action'`
- ✅ "Which feats boost DEX?" → `filter=improved_abilities IN [DEX]`
- ✅ "Backgrounds with Stealth proficiency" → `filter=skill_proficiencies IN [stealth]`
- ✅ "Light finesse weapons" → `filter=property_codes IN [L, F]`
- ✅ "Boss monsters with legendary actions" → `filter=has_legendary_actions = true`
- ✅ "Classes with Heavy Armor proficiency" → `filter=armor_proficiencies IN [Heavy Armor]`
- ✅ "Races with +2 DEX or better" → `filter=ability_dex_bonus >= 2`
- ✅ "Items that boost spell attacks" → `filter=modifier_categories IN [spell_attack]`

---

## Testing Strategy

### Unit Tests
- DTO construction with new reduced parameter sets
- Model `toSearchableArray()` output includes new fields
- Model `searchableOptions()` returns correct filterableAttributes

### Feature Tests
- Each new filter works correctly with Meilisearch
- Array filters support `IN` operator
- Boolean filters support `=` operator
- Integer filters support `>=`, `<=`, `=` operators
- String filters support `=`, `LIKE`, `CONTAINS` operators

### Integration Tests
- Re-indexing completes without errors
- Complex filter combinations work
- Pagination works with new filters
- Sorting works with existing sortable fields

### Regression Tests
- All existing 1,489 tests continue to pass
- No breaking changes to existing API endpoints
- Response formats unchanged

---

## Risk Assessment

### Low Risk
- Adding fields to `filterableAttributes` (non-breaking)
- Adding `@QueryParameter` annotations (documentation only)
- Removing dead DTO code (never used)
- Adding boolean flags (simple exists() checks)

### Medium Risk
- Removing deprecated Feats validation (users may be trying to use these)
  - **Mitigation:** Clear changelog, version bump if needed
- Removing BaseIndexRequest `search` parameter (inherited by all entities)
  - **Mitigation:** Verify no code references it first
- Large relationship eager loading (performance impact during indexing)
  - **Mitigation:** Use chunked indexing, run during off-hours

### High Risk
- None identified

---

## Performance Considerations

### Indexing Performance
- **Current:** ~2 seconds per entity (7 entities = 14s total)
- **Estimated after changes:** ~5-8 seconds per entity (loading more relationships)
- **Total re-index time:** ~35-60 seconds (acceptable)
- **Mitigation:** Use chunked imports: `scout:import --chunk=100`

### Query Performance
- **No impact:** Filtering happens in Meilisearch (already optimized)
- **Index size increase:** ~30-40% (85 → 139 fields)
- **Meilisearch handles this easily** for 4,607 total entities

### API Response Time
- **No impact:** Filters don't affect response serialization
- **Eager loading unchanged:** Relationships loaded on-demand via `include` parameter

---

## Maintenance Recommendations

### Immediate (After Implementation)
1. Update PROJECT-STATUS.md with new filter counts
2. Create LATEST-HANDOVER.md with implementation details
3. Update CHANGELOG.md under [Unreleased]
4. Tag as breaking change if removing deprecated Feats validation

### Short-term (Next Sprint)
1. Add integration tests for complex filter combinations
2. Create user documentation with filter examples
3. Add Postman/Insomnia collection with filter examples
4. Monitor Meilisearch index size and query performance

### Long-term (Next Quarter)
1. Consider adding more damage type filters for Monsters (complex parsing)
2. Evaluate adding `max_spell_level` to Classes (requires level progression calculation)
3. Consider tag system population for Feats (manual curation effort)
4. Explore additional sortable fields based on user feedback

---

## Files Changed Summary

### Phase 1 (Technical Debt)
- `app/DTOs/SpellSearchDTO.php`
- `app/DTOs/MonsterSearchDTO.php`
- `app/DTOs/ClassSearchDTO.php`
- `app/DTOs/RaceSearchDTO.php`
- `app/DTOs/ItemSearchDTO.php`
- `app/DTOs/BackgroundSearchDTO.php`
- `app/Http/Requests/FeatIndexRequest.php`
- `app/Http/Requests/BaseIndexRequest.php`
- `app/Http/Controllers/Api/RaceController.php`

### Phase 2 (Relationships)
- `app/Http/Requests/SpellShowRequest.php`
- `app/Http/Requests/ItemShowRequest.php`
- `app/Http/Requests/FeatShowRequest.php`
- `app/Http/Controllers/Api/ClassController.php`

### Phase 3 (Quick Wins)
- `app/Models/Spell.php`
- `app/Models/Monster.php`
- `app/Models/CharacterClass.php`
- `app/Models/Race.php`
- `app/Models/Item.php`
- `app/Models/Background.php`
- `app/Models/Feat.php`

### Phase 4 (Complex Filters)
- Same as Phase 3 (model updates)

### Phase 5 (Documentation)
- All 7 Controller files
- `CHANGELOG.md`
- `docs/SESSION-HANDOVER-*.md` (new)

**Total files:** ~30 files across 5 phases

---

## Conclusion

This audit revealed a **functionally correct but architecturally inconsistent** API in mid-migration from MySQL to Meilisearch filtering. The main issues are:

1. **Technical debt** from incomplete migration (~70 dead parameters)
2. **Documentation mismatches** (Races docs reference fake filters)
3. **Massive untapped potential** (54 high-value filters available with existing data)

The **recommended implementation path** progresses from cleanup (Phases 1-2) to value addition (Phases 3-4) to polish (Phase 5), with each phase independently deployable.

**Estimated total effort:** 12-17 hours
**Expected value:** Transform from good API → **best-in-class D&D 5e API**
**Risk level:** Low (mostly non-breaking additions)

The biggest wins are in **Phase 3** (quick filters) where 2-3 hours of work adds 20+ gameplay-critical filters that cover common player questions about action economy, proficiencies, ability improvements, and combat optimization.
