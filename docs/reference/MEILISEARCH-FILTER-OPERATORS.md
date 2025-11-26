# Meilisearch Filter Operators Reference

**Version:** 1.0 | **Last Updated:** 2025-11-25 | **Meilisearch:** v1.11

---

## Table of Contents

1. [Quick Reference: Operator Compatibility Matrix](#quick-reference-operator-compatibility-matrix)
2. [Operator Detailed Reference](#operator-detailed-reference)
3. [Complex Filter Examples](#complex-filter-examples)
4. [Entity-Specific Examples](#entity-specific-examples)
5. [Common Pitfalls & Troubleshooting](#common-pitfalls--troubleshooting)
6. [Testing Coverage](#testing-coverage)
7. [Related Documentation](#related-documentation)

---

## Quick Reference: Operator Compatibility Matrix

| Operator | Integer | String | Boolean | Array | Description |
|----------|---------|--------|---------|-------|-------------|
| `=` | ✅ | ✅ | ✅ | ❌ | Exact equality |
| `!=` | ✅ | ✅ | ✅ | ❌ | Not equal |
| `>` | ✅ | ❌ | ❌ | ❌ | Greater than |
| `>=` | ✅ | ❌ | ❌ | ❌ | Greater than or equal |
| `<` | ✅ | ❌ | ❌ | ❌ | Less than |
| `<=` | ✅ | ❌ | ❌ | ❌ | Less than or equal |
| `TO` | ✅ | ❌ | ❌ | ❌ | Range (inclusive) |
| `IN` | ❌ | ❌ | ❌ | ✅ | Array contains any |
| `NOT IN` | ❌ | ❌ | ❌ | ✅ | Array contains none |
| `IS EMPTY` | ❌ | ❌ | ❌ | ✅ | Array is empty |
| `IS NULL` | ✅ | ✅ | ✅ | ✅ | Field is null |
| `EXISTS` | ✅ | ✅ | ✅ | ✅ | Field is not null |
| `AND` | ✅ | ✅ | ✅ | ✅ | Logical AND |
| `OR` | ✅ | ✅ | ✅ | ✅ | Logical OR |

**Note:** Boolean fields accept `true` or `false` (lowercase, no quotes).

---

## Operator Detailed Reference

### Integer Operators

Integer operators work with numeric fields like `level`, `challenge_rating`, `hit_die`, `armor_class`, `cost_cp`, `speed`, etc.

#### Equality (`=`)

**Syntax:** `field = value`

**Examples:**
```bash
# Spells: 3rd level spells
GET /api/v1/spells?filter=level = 3

# Monsters: Challenge Rating 5
GET /api/v1/monsters?filter=challenge_rating = 5

# Classes: d10 Hit Die
GET /api/v1/classes?filter=hit_die = 10

# Races: 30 ft speed
GET /api/v1/races?filter=speed = 30
```

**Use Cases:**
- Find spells at a specific level
- Find monsters at an exact challenge rating
- Filter classes by hit die size
- Find races with specific movement speed

---

#### Inequality (`!=`)

**Syntax:** `field != value`

**Examples:**
```bash
# Spells: Not cantrips
GET /api/v1/spells?filter=level != 0

# Monsters: Not CR 0 creatures
GET /api/v1/monsters?filter=challenge_rating != 0

# Items: Not zero-weight items
GET /api/v1/items?filter=weight != 0
```

**Use Cases:**
- Exclude specific numeric values
- Filter out edge cases (cantrips, CR 0, weightless items)

---

#### Greater Than (`>`)

**Syntax:** `field > value`

**Examples:**
```bash
# Spells: Higher than 3rd level
GET /api/v1/spells?filter=level > 3

# Monsters: CR greater than 10
GET /api/v1/monsters?filter=challenge_rating > 10

# Races: Intelligence bonus greater than 0
GET /api/v1/races?filter=ability_int_bonus > 0

# Items: Heavy weapons (weight > 10 lbs)
GET /api/v1/items?filter=weight > 10
```

**Use Cases:**
- High-level content filters
- Powerful monsters for challenging encounters
- Races with specific ability bonuses
- Heavy equipment identification

---

#### Greater Than or Equal (`>=`)

**Syntax:** `field >= value`

**Examples:**
```bash
# Spells: 5th level or higher
GET /api/v1/spells?filter=level >= 5

# Monsters: Medium-difficulty encounters (CR >= 5)
GET /api/v1/monsters?filter=challenge_rating >= 5

# Races: Fast creatures (35+ ft speed)
GET /api/v1/races?filter=speed >= 35

# Items: Expensive items (1000+ copper pieces)
GET /api/v1/items?filter=cost_cp >= 1000
```

**Use Cases:**
- Minimum threshold filters
- Tier-based content filtering (level tiers, CR tiers)
- Cost/weight constraints

---

#### Less Than (`<`)

**Syntax:** `field < value`

**Examples:**
```bash
# Spells: Low-level spells (below 3rd)
GET /api/v1/spells?filter=level < 3

# Monsters: Weak creatures (CR < 1)
GET /api/v1/monsters?filter=challenge_rating < 1

# Items: Lightweight equipment (< 5 lbs)
GET /api/v1/items?filter=weight < 5
```

**Use Cases:**
- Low-level content for beginners
- Weak monsters for easy encounters
- Portable equipment filters

---

#### Less Than or Equal (`<=`)

**Syntax:** `field <= value`

**Examples:**
```bash
# Spells: Up to 3rd level
GET /api/v1/spells?filter=level <= 3

# Monsters: Safe for low-level parties (CR <= 3)
GET /api/v1/monsters?filter=challenge_rating <= 3

# Monsters: Low Strength (STR <= 10)
GET /api/v1/monsters?filter=strength <= 10

# Items: Cheap items (<= 100 cp)
GET /api/v1/items?filter=cost_cp <= 100
```

**Use Cases:**
- Maximum threshold filters
- Character-level appropriate content
- Budget constraints
- Physical attribute filters

---

#### Range (`TO`)

**Syntax:** `field value1 TO value2` (inclusive on both ends)

**Examples:**
```bash
# Spells: Levels 2-4 (inclusive)
GET /api/v1/spells?filter=level 2 TO 4

# Monsters: Mid-tier CR (5-10)
GET /api/v1/monsters?filter=challenge_rating 5 TO 10

# Monsters: Average hit points (50-100)
GET /api/v1/monsters?filter=hit_points_average 50 TO 100

# Items: Medium-priced items (100-500 cp)
GET /api/v1/items?filter=cost_cp 100 TO 500
```

**Use Cases:**
- Tier-based filtering (spell slots, CR ranges)
- Bounded searches (price ranges, stat ranges)
- Level-appropriate content discovery

**Note:** Both boundaries are inclusive. `level 2 TO 4` returns levels 2, 3, and 4.

---

### String Operators

String operators work with text fields like `type`, `alignment`, `school_name`, `size_code`, `rarity`, etc.

#### Equality (`=`)

**Syntax:** `field = "value"` (use quotes for multi-word strings)

**Examples:**
```bash
# Monsters: Dragon type
GET /api/v1/monsters?filter=type = dragon

# Monsters: Chaotic evil alignment (multi-word, requires quotes)
GET /api/v1/monsters?filter=alignment = "chaotic evil"

# Spells: Evocation school
GET /api/v1/spells?filter=school_name = Evocation

# Monsters: Large size
GET /api/v1/monsters?filter=size_code = L

# Items: Legendary rarity
GET /api/v1/items?filter=rarity = Legendary
```

**Use Cases:**
- Filter by creature type (dragon, undead, fiend)
- Filter by alignment (lawful good, chaotic evil)
- Filter by spell school (Evocation, Conjuration)
- Filter by size (Tiny, Small, Medium, Large)
- Filter by item rarity (Common, Uncommon, Rare)

**Quoting Rules:**
- Single-word values: No quotes needed (`type = dragon`)
- Multi-word values: Use double quotes (`alignment = "chaotic evil"`)
- Case-sensitive: `dragon` works, `Dragon` may not (depends on indexing)

---

#### Inequality (`!=`)

**Syntax:** `field != "value"`

**Examples:**
```bash
# Monsters: Non-humanoid creatures
GET /api/v1/monsters?filter=type != humanoid

# Monsters: Not Medium-sized
GET /api/v1/monsters?filter=size_code != M

# Items: Non-magic items
GET /api/v1/items?filter=rarity != Common
```

**Use Cases:**
- Exclude specific categories
- Negative filters for content curation

---

### Boolean Operators

Boolean operators work with true/false fields like `concentration`, `ritual`, `requires_attunement`, `is_magic`, `has_prerequisites`, etc.

**Syntax:** `field = true` or `field = false` (lowercase, no quotes)

#### Examples

```bash
# Spells: Concentration spells
GET /api/v1/spells?filter=concentration = true

# Spells: Non-concentration spells
GET /api/v1/spells?filter=concentration = false

# Spells: Ritual spells
GET /api/v1/spells?filter=ritual = true

# Spells: No verbal component
GET /api/v1/spells?filter=requires_verbal = false

# Spells: No somatic component
GET /api/v1/spells?filter=requires_somatic = false

# Spells: No material component
GET /api/v1/spells?filter=requires_material = false

# Items: Requires attunement
GET /api/v1/items?filter=requires_attunement = true

# Items: Magic items
GET /api/v1/items?filter=is_magic = true

# Feats: Has prerequisites
GET /api/v1/feats?filter=has_prerequisites = true

# Feats: Grants proficiencies
GET /api/v1/feats?filter=grants_proficiencies = true

# Monsters: Is NPC
GET /api/v1/monsters?filter=is_npc = true

# Monsters: Has legendary actions
GET /api/v1/monsters?filter=has_legendary_actions = true

# Monsters: Is spellcaster
GET /api/v1/monsters?filter=is_spellcaster = true

# Classes: Is spellcaster
GET /api/v1/classes?filter=is_spellcaster = true

# Classes: Is subclass
GET /api/v1/classes?filter=is_subclass = true

# Classes: Is base class (not subclass)
GET /api/v1/classes?filter=is_base_class = true

# Races: Is subrace
GET /api/v1/races?filter=is_subrace = true

# Races: Has innate spells
GET /api/v1/races?filter=has_innate_spells = true

# Backgrounds: Grants language choice
GET /api/v1/backgrounds?filter=grants_language_choice = true
```

**Use Cases:**
- Filter spells by casting requirements (concentration, ritual, components)
- Find magic items requiring attunement
- Identify feats with prerequisites
- Find spellcasting monsters/classes
- Distinguish base classes from subclasses
- Identify races with innate spellcasting

**Common Mistake:** Do NOT use quotes: `concentration = "true"` is WRONG, use `concentration = true`

---

### Array Operators

Array operators work with multi-value fields like `class_slugs`, `tag_slugs`, `damage_types`, `saving_throws`, `spell_slugs`, etc.

#### Contains Any (`IN`)

**Syntax:** `field IN [value1, value2, ...]`

**Examples:**
```bash
# Spells: Available to Wizard
GET /api/v1/spells?filter=class_slugs IN [wizard]

# Spells: Available to Wizard OR Bard
GET /api/v1/spells?filter=class_slugs IN [wizard, bard]

# Spells: Fire damage
GET /api/v1/spells?filter=damage_types IN [F]

# Spells: Fire OR Cold damage
GET /api/v1/spells?filter=damage_types IN [F, C]

# Spells: Dexterity saving throw
GET /api/v1/spells?filter=saving_throws IN [DEX]

# Spells: Dexterity OR Constitution save
GET /api/v1/spells?filter=saving_throws IN [DEX, CON]

# Monsters: Knows Fireball
GET /api/v1/monsters?filter=spell_slugs IN [fireball]

# Monsters: Knows Fireball OR Lightning Bolt
GET /api/v1/monsters?filter=spell_slugs IN [fireball, lightning-bolt]

# Races: Has Darkvision tag
GET /api/v1/races?filter=tag_slugs IN [darkvision]

# Items: Has Finesse OR Light property
GET /api/v1/items?filter=property_codes IN [F, L]

# Feats: Improves Strength OR Dexterity
GET /api/v1/feats?filter=improved_abilities IN [Strength, Dexterity]

# Feats: Requires Race OR AbilityScore prerequisite
GET /api/v1/feats?filter=prerequisite_types IN [Race, AbilityScore]

# Classes: Proficient in Dexterity OR Constitution saves
GET /api/v1/classes?filter=saving_throw_proficiencies IN [Dexterity, Constitution]
```

**Use Cases:**
- Filter spells by available classes
- Find spells with specific damage types
- Find spells requiring specific saving throws
- Find monsters knowing specific spells
- Find items with specific properties
- Find feats improving specific abilities
- Find classes with specific proficiencies

**Behavior:** Returns records where the array field contains **at least one** of the specified values (logical OR within the array).

---

#### Contains None (`NOT IN`)

**Syntax:** `field NOT IN [value1, value2, ...]`

**Examples:**
```bash
# Spells: Not available to Wizard
GET /api/v1/spells?filter=class_slugs NOT IN [wizard]

# Spells: No Fire damage
GET /api/v1/spells?filter=damage_types NOT IN [F]

# Monsters: Doesn't know Fireball
GET /api/v1/monsters?filter=spell_slugs NOT IN [fireball]
```

**Use Cases:**
- Exclude specific classes from spell lists
- Find spells without specific damage types
- Find monsters without specific spells

**Behavior:** Returns records where the array field contains **none** of the specified values.

---

#### Is Empty (`IS EMPTY`)

**Syntax:** `field IS EMPTY`

**Examples:**
```bash
# Spells: No damage types (utility/buff spells)
GET /api/v1/spells?filter=damage_types IS EMPTY

# Spells: No saving throws
GET /api/v1/spells?filter=saving_throws IS EMPTY

# Monsters: No spells
GET /api/v1/monsters?filter=spell_slugs IS EMPTY

# Classes: No spell proficiencies
GET /api/v1/classes?filter=spell_count = 0
```

**Use Cases:**
- Find utility spells without damage
- Find non-damaging spells
- Find non-spellcasting monsters
- Identify records without specific associations

**Behavior:** Returns records where the array field is empty (length = 0).

---

### Null Operators

Null operators check for field existence/non-existence.

#### Is Null (`IS NULL`)

**Syntax:** `field IS NULL`

**Examples:**
```bash
# Monsters: No armor type specified
GET /api/v1/monsters?filter=armor_type IS NULL

# Items: No damage dice (non-weapons)
GET /api/v1/items?filter=damage_dice IS NULL

# Classes: No spellcasting ability
GET /api/v1/classes?filter=spellcasting_ability IS NULL
```

**Use Cases:**
- Find records with missing optional data
- Identify non-weapon items
- Find non-spellcasting classes

---

#### Exists (`EXISTS`)

**Syntax:** `field EXISTS`

**Examples:**
```bash
# Monsters: Has armor type
GET /api/v1/monsters?filter=armor_type EXISTS

# Items: Has damage dice (weapons)
GET /api/v1/items?filter=damage_dice EXISTS

# Classes: Has spellcasting ability
GET /api/v1/classes?filter=spellcasting_ability EXISTS
```

**Use Cases:**
- Find records with optional data populated
- Identify weapons (has damage dice)
- Find spellcasting classes

---

### Logical Operators

Combine multiple filters using `AND` and `OR`.

#### AND

**Syntax:** `filter1 AND filter2 AND filter3 ...`

**Examples:**
```bash
# Spells: Fire damage + 3rd level
GET /api/v1/spells?filter=damage_types IN [F] AND level = 3

# Spells: Wizard spell + concentration
GET /api/v1/spells?filter=class_slugs IN [wizard] AND concentration = true

# Spells: No verbal + no somatic components
GET /api/v1/spells?filter=requires_verbal = false AND requires_somatic = false

# Monsters: Dragon + CR 10+
GET /api/v1/monsters?filter=type = dragon AND challenge_rating >= 10

# Races: Darkvision + Int bonus
GET /api/v1/races?filter=tag_slugs IN [darkvision] AND ability_int_bonus > 0

# Items: Magic + requires attunement
GET /api/v1/items?filter=is_magic = true AND requires_attunement = true
```

**Behavior:** All conditions must be true (logical AND).

---

#### OR

**Syntax:** `filter1 OR filter2 OR filter3 ...`

**Examples:**
```bash
# Spells: 1st OR 2nd level
GET /api/v1/spells?filter=level = 1 OR level = 2

# Monsters: CR 5 OR CR 10
GET /api/v1/monsters?filter=challenge_rating = 5 OR challenge_rating = 10

# Monsters: Dragon OR Fiend type
GET /api/v1/monsters?filter=type = dragon OR type = fiend
```

**Behavior:** At least one condition must be true (logical OR).

---

#### Combined AND/OR

**Syntax:** Use parentheses for grouping (when supported)

**Examples:**
```bash
# Spells: (Fire damage OR Cold damage) AND Wizard spell
GET /api/v1/spells?filter=damage_types IN [F, C] AND class_slugs IN [wizard]

# Monsters: (Dragon OR Fiend) AND CR >= 10
GET /api/v1/monsters?filter=(type = dragon OR type = fiend) AND challenge_rating >= 10
```

**Note:** Meilisearch has limited parentheses support. Test complex expressions carefully.

---

## Complex Filter Examples

### Multi-Condition Spells

```bash
# Fire damage + Dexterity save + low level (1-3)
GET /api/v1/spells?filter=damage_types IN [F] AND saving_throws IN [DEX] AND level >= 1 AND level <= 3

# Wizard spell + concentration + 5th level or higher
GET /api/v1/spells?filter=class_slugs IN [wizard] AND concentration = true AND level >= 5

# No components + ritual
GET /api/v1/spells?filter=requires_verbal = false AND requires_somatic = false AND requires_material = false AND ritual = true

# Fire OR Cold damage + no saving throw + cantrip
GET /api/v1/spells?filter=damage_types IN [F, C] AND saving_throws IS EMPTY AND level = 0
```

---

### Multi-Condition Monsters

```bash
# Dragon + CR 10-20 + spellcaster
GET /api/v1/monsters?filter=type = dragon AND challenge_rating 10 TO 20 AND is_spellcaster = true

# Large or Huge + has legendary actions + CR 15+
GET /api/v1/monsters?filter=(size_code = L OR size_code = H) AND has_legendary_actions = true AND challenge_rating >= 15

# Knows Fireball + Chaotic Evil + CR 5+
GET /api/v1/monsters?filter=spell_slugs IN [fireball] AND alignment = "chaotic evil" AND challenge_rating >= 5

# High Intelligence (15+) + spellcaster + not NPC
GET /api/v1/monsters?filter=intelligence >= 15 AND is_spellcaster = true AND is_npc = false
```

---

### Multi-Condition Classes

```bash
# Spellcaster + d8 Hit Die + has spells
GET /api/v1/classes?filter=is_spellcaster = true AND hit_die = 8 AND has_spells = true

# Subclass + parent is Fighter + 10+ spells
GET /api/v1/classes?filter=is_subclass = true AND parent_class_name = Fighter AND spell_count >= 10

# Dexterity saving throw proficiency + armor proficiency
GET /api/v1/classes?filter=saving_throw_proficiencies IN [Dexterity] AND armor_proficiencies IN [Light, Medium]
```

---

### Multi-Condition Races

```bash
# Subrace + Darkvision + Int bonus
GET /api/v1/races?filter=is_subrace = true AND tag_slugs IN [darkvision] AND ability_int_bonus > 0

# Fast (35+ ft) + Small size + innate spells
GET /api/v1/races?filter=speed >= 35 AND size_code = S AND has_innate_spells = true

# Multiple ability bonuses (Str AND Dex)
GET /api/v1/races?filter=ability_str_bonus > 0 AND ability_dex_bonus > 0
```

---

### Multi-Condition Items

```bash
# Magic weapon + legendary rarity + attunement required
GET /api/v1/items?filter=is_magic = true AND type_code = W AND rarity = Legendary AND requires_attunement = true

# Heavy armor + no stealth disadvantage + affordable (<5000 cp)
GET /api/v1/items?filter=type_code = HA AND stealth_disadvantage = false AND cost_cp < 5000

# Weapon + Finesse property + 1d6 or 1d8 damage
GET /api/v1/items?filter=type_code = W AND property_codes IN [F] AND (damage_dice = 1d6 OR damage_dice = 1d8)

# Has charges + rechargeable + contains spells
GET /api/v1/items?filter=has_charges = true AND recharge_timing EXISTS AND spell_slugs IN [fireball, lightning-bolt]
```

---

### Multi-Condition Feats

```bash
# Has prerequisites + grants proficiencies + improves Strength
GET /api/v1/feats?filter=has_prerequisites = true AND grants_proficiencies = true AND improved_abilities IN [Strength]

# Requires Race prerequisite + doesn't grant proficiencies
GET /api/v1/feats?filter=prerequisite_types IN [Race] AND grants_proficiencies = false

# Improves multiple abilities (Str OR Dex) + no prerequisites
GET /api/v1/feats?filter=improved_abilities IN [Strength, Dexterity] AND has_prerequisites = false
```

---

### Multi-Condition Backgrounds

```bash
# Grants language choice + skill proficiencies
GET /api/v1/backgrounds?filter=grants_language_choice = true AND skill_proficiencies IN [Persuasion, Insight]

# Has tool proficiency + specific source
GET /api/v1/backgrounds?filter=tool_proficiency_types IN [Artisan] AND source_codes IN [PHB]
```

---

## Entity-Specific Examples

### Spells (477 records)

**Filterable Fields:**
```
id, level, school_name, school_code, casting_time, range, duration, concentration,
ritual, sources, source_codes, class_slugs, tag_slugs, damage_types, saving_throws,
requires_verbal, requires_somatic, requires_material, effect_types
```

**Common Patterns:**
```bash
# Wizard cantrips
GET /api/v1/spells?filter=class_slugs IN [wizard] AND level = 0

# High-level damage spells (5+) with fire damage
GET /api/v1/spells?filter=level >= 5 AND damage_types IN [F]

# Ritual spells without concentration
GET /api/v1/spells?filter=ritual = true AND concentration = false

# Healing spells (effect type)
GET /api/v1/spells?filter=effect_types IN [healing]

# Silent spells (no verbal component)
GET /api/v1/spells?filter=requires_verbal = false

# Subtle spells (no verbal OR somatic)
GET /api/v1/spells?filter=requires_verbal = false OR requires_somatic = false

# Buff spells (no damage, concentration)
GET /api/v1/spells?filter=damage_types IS EMPTY AND concentration = true

# Quick-cast spells (bonus action or reaction)
GET /api/v1/spells?filter=casting_time = "1 bonus action" OR casting_time = "1 reaction"
```

---

### Monsters (598 records)

**Filterable Fields:**
```
id, slug, size_code, size_name, type, alignment, armor_class, armor_type,
hit_points_average, challenge_rating, experience_points, source_codes, spell_slugs,
tag_slugs, speed_walk, speed_fly, speed_swim, speed_burrow, speed_climb, can_hover,
strength, dexterity, constitution, intelligence, wisdom, charisma, passive_perception,
is_npc, has_legendary_actions, has_lair_actions, is_spellcaster, has_reactions,
has_legendary_resistance, has_magic_resistance
```

**Common Patterns:**
```bash
# Boss monsters (CR 15+, legendary actions)
GET /api/v1/monsters?filter=challenge_rating >= 15 AND has_legendary_actions = true

# Flying creatures with hover
GET /api/v1/monsters?filter=speed_fly > 0 AND can_hover = true

# Smart creatures (Intelligence 10+)
GET /api/v1/monsters?filter=intelligence >= 10

# Spellcasting dragons
GET /api/v1/monsters?filter=type = dragon AND is_spellcaster = true

# Low-CR NPCs for roleplaying
GET /api/v1/monsters?filter=is_npc = true AND challenge_rating <= 2

# High AC tanks (AC 18+)
GET /api/v1/monsters?filter=armor_class >= 18

# Magic-resistant creatures
GET /api/v1/monsters?filter=has_magic_resistance = true

# Undead with legendary resistance
GET /api/v1/monsters?filter=type = undead AND has_legendary_resistance = true
```

---

### Classes (131 records)

**Filterable Fields:**
```
id, slug, hit_die, primary_ability, spellcasting_ability, is_spellcaster, source_codes,
is_subclass, is_base_class, parent_class_name, tag_slugs, has_spells, spell_count,
max_spell_level, saving_throw_proficiencies, armor_proficiencies, weapon_proficiencies,
tool_proficiencies, skill_proficiencies
```

**Common Patterns:**
```bash
# Base classes only (no subclasses)
GET /api/v1/classes?filter=is_base_class = true

# Spellcasters with 9th-level spells
GET /api/v1/classes?filter=is_spellcaster = true AND max_spell_level = 9

# Fighter subclasses
GET /api/v1/classes?filter=is_subclass = true AND parent_class_name = Fighter

# High hit die classes (d10 or d12)
GET /api/v1/classes?filter=hit_die >= 10

# Classes with Dexterity saves
GET /api/v1/classes?filter=saving_throw_proficiencies IN [Dexterity]

# Classes with martial weapon proficiency
GET /api/v1/classes?filter=weapon_proficiencies IN [Martial]

# Half-casters (max 5th level spells)
GET /api/v1/classes?filter=is_spellcaster = true AND max_spell_level = 5
```

---

### Races (115 records)

**Filterable Fields:**
```
id, slug, size_name, size_code, speed, source_codes, is_subrace, parent_race_name,
tag_slugs, spell_slugs, has_innate_spells, ability_str_bonus, ability_dex_bonus,
ability_con_bonus, ability_int_bonus, ability_wis_bonus, ability_cha_bonus
```

**Common Patterns:**
```bash
# Base races only (no subraces)
GET /api/v1/races?filter=is_subrace = false

# Elf subraces
GET /api/v1/races?filter=is_subrace = true AND parent_race_name = Elf

# Races with Darkvision
GET /api/v1/races?filter=tag_slugs IN [darkvision]

# Small races
GET /api/v1/races?filter=size_code = S

# Fast races (35+ ft speed)
GET /api/v1/races?filter=speed >= 35

# Races with Intelligence bonus
GET /api/v1/races?filter=ability_int_bonus > 0

# Races with +2 Dexterity
GET /api/v1/races?filter=ability_dex_bonus = 2

# Spellcasting races (innate spells)
GET /api/v1/races?filter=has_innate_spells = true

# Multi-stat races (Str AND Con bonuses)
GET /api/v1/races?filter=ability_str_bonus > 0 AND ability_con_bonus > 0
```

---

### Items (516 records)

**Filterable Fields:**
```
id, slug, type_name, type_code, rarity, requires_attunement, is_magic, weight, cost_cp,
source_codes, damage_dice, versatile_damage, damage_type, range_normal, range_long,
armor_class, strength_requirement, stealth_disadvantage, charges_max, has_charges,
recharge_timing, recharge_formula, spell_slugs, tag_slugs, property_codes,
modifier_categories, proficiency_names, saving_throw_abilities
```

**Common Patterns:**
```bash
# Magic weapons
GET /api/v1/items?filter=is_magic = true AND type_code = W

# Legendary items requiring attunement
GET /api/v1/items?filter=rarity = Legendary AND requires_attunement = true

# Light weapons with Finesse
GET /api/v1/items?filter=property_codes IN [L, F]

# Heavy armor without stealth disadvantage
GET /api/v1/items?filter=type_code = HA AND stealth_disadvantage = false

# Ranged weapons with 100+ ft range
GET /api/v1/items?filter=type_code = R AND range_normal >= 100

# Charged items
GET /api/v1/items?filter=has_charges = true

# Affordable equipment (<100 cp)
GET /api/v1/items?filter=cost_cp <= 100

# Magical ammunition
GET /api/v1/items?filter=type_code = A AND is_magic = true

# Items containing spells
GET /api/v1/items?filter=spell_slugs IN [fireball, lightning-bolt]
```

---

### Backgrounds (34 records)

**Filterable Fields:**
```
id, slug, source_codes, tag_slugs, grants_language_choice, skill_proficiencies,
tool_proficiency_types
```

**Common Patterns:**
```bash
# Backgrounds with language choice
GET /api/v1/backgrounds?filter=grants_language_choice = true

# Backgrounds with Persuasion proficiency
GET /api/v1/backgrounds?filter=skill_proficiencies IN [Persuasion]

# Backgrounds with tool proficiencies
GET /api/v1/backgrounds?filter=tool_proficiency_types IN [Artisan]

# Player's Handbook backgrounds
GET /api/v1/backgrounds?filter=source_codes IN [PHB]

# Backgrounds with specific tag
GET /api/v1/backgrounds?filter=tag_slugs IN [urban]
```

---

### Feats (138 records)

**Filterable Fields:**
```
id, slug, source_codes, tag_slugs, has_prerequisites, grants_proficiencies,
improved_abilities, prerequisite_types
```

**Common Patterns:**
```bash
# Feats without prerequisites
GET /api/v1/feats?filter=has_prerequisites = false

# Feats improving Strength
GET /api/v1/feats?filter=improved_abilities IN [Strength]

# Feats granting proficiencies
GET /api/v1/feats?filter=grants_proficiencies = true

# Feats requiring race prerequisite
GET /api/v1/feats?filter=prerequisite_types IN [Race]

# Feats requiring ability score prerequisite
GET /api/v1/feats?filter=prerequisite_types IN [AbilityScore]

# Combat feats (tag)
GET /api/v1/feats?filter=tag_slugs IN [combat]

# Feats improving Str OR Dex (martial)
GET /api/v1/feats?filter=improved_abilities IN [Strength, Dexterity]
```

---

## Common Pitfalls & Troubleshooting

### 1. String Quoting Issues

**Problem:** Multi-word string values without quotes fail.

```bash
# ❌ WRONG - Missing quotes for multi-word value
GET /api/v1/monsters?filter=alignment = chaotic evil

# ✅ CORRECT - Use double quotes
GET /api/v1/monsters?filter=alignment = "chaotic evil"
```

**Rule:** Use double quotes for multi-word strings, optional for single-word strings.

---

### 2. Boolean Value Format

**Problem:** Using quoted boolean values.

```bash
# ❌ WRONG - Quotes around boolean
GET /api/v1/spells?filter=concentration = "true"

# ✅ CORRECT - No quotes, lowercase
GET /api/v1/spells?filter=concentration = true
```

**Rule:** Booleans must be lowercase `true` or `false` without quotes.

---

### 3. Array Syntax

**Problem:** Using incorrect array delimiter or missing brackets.

```bash
# ❌ WRONG - Missing brackets
GET /api/v1/spells?filter=class_slugs IN wizard, bard

# ❌ WRONG - Using parentheses
GET /api/v1/spells?filter=class_slugs IN (wizard, bard)

# ✅ CORRECT - Square brackets, comma-separated
GET /api/v1/spells?filter=class_slugs IN [wizard, bard]
```

**Rule:** Array values must use square brackets `[value1, value2]`.

---

### 4. Range Operator Spacing

**Problem:** Missing spaces around `TO` operator.

```bash
# ❌ WRONG - No spaces
GET /api/v1/spells?filter=level 1TO3

# ✅ CORRECT - Spaces around TO
GET /api/v1/spells?filter=level 1 TO 3
```

**Rule:** `TO` operator requires spaces: `field value1 TO value2`.

---

### 5. Field Name Typos

**Problem:** Using non-existent or misspelled field names.

```bash
# ❌ WRONG - Field doesn't exist
GET /api/v1/spells?filter=classes IN [wizard]

# ✅ CORRECT - Use exact field name
GET /api/v1/spells?filter=class_slugs IN [wizard]
```

**Solution:** Reference controller PHPDoc or model `filterableAttributes` for valid field names.

---

### 6. URL Encoding

**Problem:** Special characters not URL-encoded.

```bash
# ❌ MAY FAIL - Unencoded spaces and quotes
GET /api/v1/monsters?filter=alignment = "chaotic evil"

# ✅ SAFE - URL-encoded
GET /api/v1/monsters?filter=alignment%20%3D%20%22chaotic%20evil%22
```

**Solution:** Most HTTP clients auto-encode, but manual encoding may be needed for special characters.

---

### 7. Non-Filterable Fields

**Problem:** Attempting to filter on non-indexed fields.

```bash
# ❌ ERROR - 'description' is searchable but not filterable
GET /api/v1/spells?filter=description = "fire damage"

# ✅ CORRECT - Use search parameter instead
GET /api/v1/spells?q=fire damage
```

**Solution:** Check model's `filterableAttributes` array. Use `?q=term` for full-text search on `searchableAttributes`.

**Error Response:**
```json
{
  "message": "Invalid filter syntax: Attribute `description` is not filterable.",
  "errors": {
    "filter": ["Invalid filter syntax: Attribute `description` is not filterable."]
  }
}
```

---

### 8. Invalid Operator for Data Type

**Problem:** Using numeric operators on string fields or vice versa.

```bash
# ❌ ERROR - Cannot use > on string field
GET /api/v1/monsters?filter=type > dragon

# ✅ CORRECT - Use equality for strings
GET /api/v1/monsters?filter=type = dragon
```

---

### 9. Case Sensitivity

**Problem:** Incorrect case for field values.

```bash
# ⚠️ MAY FAIL - Case mismatch
GET /api/v1/monsters?filter=type = Dragon

# ✅ SAFER - Match indexed case (usually lowercase)
GET /api/v1/monsters?filter=type = dragon
```

**Note:** Meilisearch indexing is case-sensitive by default. Check `toSearchableArray()` for actual indexed values.

---

### 10. Complex Nested Logic

**Problem:** Over-relying on parentheses for complex expressions.

```bash
# ⚠️ MAY NOT WORK - Complex nesting
GET /api/v1/spells?filter=(damage_types IN [F] OR damage_types IN [C]) AND (level = 1 OR level = 2)

# ✅ ALTERNATIVE - Simplify using array
GET /api/v1/spells?filter=damage_types IN [F, C] AND level 1 TO 2
```

**Note:** Meilisearch has limited parentheses support. Simplify logic when possible.

---

## Testing Coverage

### Test Files

**Comprehensive filtering tests:**

1. **`tests/Feature/Api/SpellEnhancedFilteringTest.php`** (463 lines, 24 tests)
   - Damage type filtering (`IN`, `IS EMPTY`, multiple types)
   - Saving throw filtering (`IN`, `IS EMPTY`, multiple saves)
   - Component filtering (`requires_verbal`, `requires_somatic`, `requires_material`)
   - Combined filters (damage + saves + level)
   - Pagination + sorting with filters
   - Search + filter combination

2. **`tests/Feature/Api/RaceEntitySpecificFiltersApiTest.php`** (471 lines, 21 tests)
   - Ability score bonus filtering (`ability_int_bonus > 0`, `ability_str_bonus > 0`)
   - Size filtering (`size_code = S`, `size_code = M`)
   - Speed filtering (`speed >= 35`)
   - Tag filtering (`tag_slugs IN [darkvision]`)
   - Combined filters (ability + tag)
   - Error handling (invalid syntax, pagination, sorting)

3. **`tests/Feature/Api/FeatFilterTest.php`** (146 lines, 9 tests)
   - Boolean filtering (`has_prerequisites`, `grants_proficiencies`)
   - Array filtering (`prerequisite_types IN [Race]`, `improved_abilities IN [Strength]`)

4. **`tests/Feature/Api/MonsterApiTest.php`** (627 lines, 32 tests)
   - Numeric filtering (`challenge_rating = 5`)
   - String filtering (`type = dragon`, `alignment = "chaotic evil"`)
   - Size filtering (`size_code = L`)
   - Spell filtering (`spell_slugs IN [fireball]`)
   - Combined filters (`spell_slugs IN [fireball] AND spell_slugs IN [lightning-bolt]`)

5. **`tests/Feature/Api/SpellFilterExceptionTest.php`** (64 lines, 3 tests)
   - Invalid field error handling
   - Invalid syntax error handling
   - Error response structure validation

**Coverage Statistics:**
- **1,489 total tests** passing
- **7,704 assertions**
- **50+ filter-specific tests** across 5 test files
- **99.7% pass rate**

---

### Running Filter Tests

```bash
# All filtering tests
docker compose exec php php artisan test --filter=Filter

# Spell filtering tests
docker compose exec php php artisan test --filter=SpellEnhancedFilteringTest

# Race filtering tests
docker compose exec php php artisan test --filter=RaceEntitySpecificFiltersApiTest

# Feat filtering tests
docker compose exec php php artisan test --filter=FeatFilterTest

# Monster filtering tests
docker compose exec php php artisan test tests/Feature/Api/MonsterApiTest.php

# Filter exception handling
docker compose exec php php artisan test --filter=SpellFilterExceptionTest
```

---

## Related Documentation

### Internal Documentation

1. **`CLAUDE.md`** - Project overview, TDD workflow, search architecture section
2. **`docs/PROJECT-STATUS.md`** - Project metrics, entity counts, test coverage
3. **`docs/DND-FEATURES.md`** - D&D 5e game mechanics (damage types, ability codes, tags)
4. **`docs/SESSION-HANDOVER-2025-11-24-MEILISEARCH-PHASE-1.md`** - Latest Meilisearch implementation details

### Controller PHPDoc

Each controller's index method includes PHPDoc with filterable field descriptions:

- **`app/Http/Controllers/Api/SpellController.php`** - Lines 32-57 (spell filters)
- **`app/Http/Controllers/Api/MonsterController.php`** - Lines 32-65 (monster filters)
- **`app/Http/Controllers/Api/CharacterClassController.php`** - Lines 32-58 (class filters)
- **`app/Http/Controllers/Api/RaceController.php`** - Lines 32-48 (race filters)
- **`app/Http/Controllers/Api/ItemController.php`** - Lines 32-60 (item filters)
- **`app/Http/Controllers/Api/BackgroundController.php`** - Lines 29-42 (background filters)
- **`app/Http/Controllers/Api/FeatController.php`** - Lines 32-47 (feat filters)

### Model searchableOptions()

Each model defines filterable fields in `searchableOptions()` method:

- **`app/Models/Spell.php`** - Lines 228-264
- **`app/Models/Monster.php`** - Lines 176-224
- **`app/Models/CharacterClass.php`** - Lines 223-260
- **`app/Models/Race.php`** - Lines 163-198
- **`app/Models/Item.php`** - Lines 255-300
- **`app/Models/Background.php`** - Lines 100-120
- **`app/Models/Feat.php`** - Lines 184-209

### OpenAPI Documentation

**Live Scramble Docs:** `http://localhost:8080/docs/api`

Interactive API documentation with live filtering examples.

---

## Summary Statistics

- **7 Entities:** Spells, Monsters, Classes, Races, Items, Backgrounds, Feats
- **14 Operators:** `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO`, `IN`, `NOT IN`, `IS EMPTY`, `IS NULL`, `EXISTS`, `AND`, `OR`
- **120+ Filterable Fields** across all entities
- **60+ Examples** in this document
- **50+ Test Cases** validating filter behavior
- **99.7% Test Pass Rate** (1,489/1,493 tests passing)

---

**Last Updated:** 2025-11-25 | **Maintainer:** Claude Code | **Version:** 1.0
