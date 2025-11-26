# Filter Field Type Mapping

This document provides a comprehensive mapping of all filterable fields across the 7 D&D 5e API entities, including their data types and Meilisearch filter syntax.

**Last Updated:** 2025-11-25

---

## Overview

This mapping is derived from each model's `searchableOptions()['filterableAttributes']` configuration and represents the complete set of fields available for filtering via the `?filter=` query parameter.

**Total Filterable Fields Across All Entities:** 130

---

## 1. Spells (20 fields)

| Field Name | Data Type | Example Filter | Notes |
|------------|-----------|----------------|-------|
| `id` | Integer | `id = 42` | Primary key |
| `level` | Integer | `level >= 3` | Spell level (0-9) |
| `school_name` | String | `school_name = "Evocation"` | Full school name |
| `school_code` | String | `school_code = "EV"` | Two-letter code |
| `casting_time` | String | `casting_time = "1 action"` | Free-form text |
| `range` | String | `range = "60 feet"` | Free-form text |
| `duration` | String | `duration = "Instantaneous"` | Free-form text |
| `concentration` | Boolean | `concentration = true` | Requires concentration |
| `ritual` | Boolean | `ritual = true` | Can be cast as ritual |
| `requires_verbal` | Boolean | `requires_verbal = true` | Verbal component |
| `requires_somatic` | Boolean | `requires_somatic = true` | Somatic component |
| `requires_material` | Boolean | `requires_material = true` | Material component |
| `source_codes` | Array | `source_codes IN [PHB, XGE]` | Source book codes |
| `class_slugs` | Array | `class_slugs IN [bard, wizard]` | Available to classes |
| `tag_slugs` | Array | `tag_slugs IN [fire, damage]` | Thematic tags |
| `damage_types` | Array | `damage_types IN [fire, cold]` | Damage dealt |
| `saving_throws` | Array | `saving_throws IN [dexterity, wisdom]` | Saving throw types |
| `effect_types` | Array | `effect_types IN [healing, buff]` | Spell effects |

**Type Breakdown:**
- Integer: 2
- String: 6
- Boolean: 6
- Array: 6

---

## 2. Classes (18 fields)

| Field Name | Data Type | Example Filter | Notes |
|------------|-----------|----------------|-------|
| `id` | Integer | `id = 5` | Primary key |
| `hit_die` | Integer | `hit_die >= 10` | Hit die size (d6-d12) |
| `spell_count` | Integer | `spell_count > 50` | Total spells available |
| `max_spell_level` | Integer | `max_spell_level = 9` | Highest spell level |
| `slug` | String | `slug = "wizard"` | URL-safe identifier |
| `primary_ability` | String | `primary_ability = "Intelligence"` | Main ability score |
| `spellcasting_ability` | String | `spellcasting_ability = "Charisma"` | Spellcasting modifier |
| `parent_class_name` | String | `parent_class_name = "Rogue"` | For subclasses |
| `is_spellcaster` | Boolean | `is_spellcaster = true` | Has spellcasting |
| `is_subclass` | Boolean | `is_subclass = false` | Is a subclass |
| `is_base_class` | Boolean | `is_base_class = true` | Is a base class |
| `has_spells` | Boolean | `has_spells = true` | Has any spells |
| `source_codes` | Array | `source_codes IN [PHB, SCAG]` | Source book codes |
| `tag_slugs` | Array | `tag_slugs IN [martial, arcane]` | Thematic tags |
| `saving_throw_proficiencies` | Array | `saving_throw_proficiencies IN [strength, constitution]` | Proficient saves |
| `armor_proficiencies` | Array | `armor_proficiencies IN [light-armor, medium-armor]` | Armor types |
| `weapon_proficiencies` | Array | `weapon_proficiencies IN [simple-weapons, martial-weapons]` | Weapon categories |
| `tool_proficiencies` | Array | `tool_proficiencies IN [thieves-tools]` | Tool types |
| `skill_proficiencies` | Array | `skill_proficiencies IN [stealth, perception]` | Skill options |

**Type Breakdown:**
- Integer: 4
- String: 4
- Boolean: 4
- Array: 6

---

## 3. Monsters (34 fields)

| Field Name | Data Type | Example Filter | Notes |
|------------|-----------|----------------|-------|
| `id` | Integer | `id = 123` | Primary key |
| `armor_class` | Integer | `armor_class >= 15` | AC value |
| `hit_points_average` | Integer | `hit_points_average > 100` | Average HP |
| `challenge_rating` | Integer | `challenge_rating <= 5` | CR (0-30) |
| `experience_points` | Integer | `experience_points >= 1000` | XP reward |
| `speed_walk` | Integer | `speed_walk >= 30` | Walking speed (ft) |
| `speed_fly` | Integer | `speed_fly > 0` | Flying speed (ft) |
| `speed_swim` | Integer | `speed_swim > 0` | Swimming speed (ft) |
| `speed_burrow` | Integer | `speed_burrow > 0` | Burrowing speed (ft) |
| `speed_climb` | Integer | `speed_climb > 0` | Climbing speed (ft) |
| `strength` | Integer | `strength >= 18` | STR score (1-30) |
| `dexterity` | Integer | `dexterity >= 18` | DEX score (1-30) |
| `constitution` | Integer | `constitution >= 18` | CON score (1-30) |
| `intelligence` | Integer | `intelligence >= 18` | INT score (1-30) |
| `wisdom` | Integer | `wisdom >= 18` | WIS score (1-30) |
| `charisma` | Integer | `charisma >= 18` | CHA score (1-30) |
| `passive_perception` | Integer | `passive_perception >= 15` | Passive Perception |
| `slug` | String | `slug = "ancient-red-dragon"` | URL-safe identifier |
| `size_code` | String | `size_code = "L"` | Size code (T/S/M/L/H/G) |
| `size_name` | String | `size_name = "Large"` | Full size name |
| `type` | String | `type = "dragon"` | Creature type |
| `alignment` | String | `alignment = "chaotic evil"` | Alignment text |
| `armor_type` | String | `armor_type = "natural armor"` | AC source |
| `can_hover` | Boolean | `can_hover = true` | Has hover ability |
| `is_npc` | Boolean | `is_npc = true` | Is an NPC |
| `has_legendary_actions` | Boolean | `has_legendary_actions = true` | Has legendary actions |
| `has_lair_actions` | Boolean | `has_lair_actions = true` | Has lair actions |
| `is_spellcaster` | Boolean | `is_spellcaster = true` | Can cast spells |
| `has_reactions` | Boolean | `has_reactions = true` | Has reactions |
| `has_legendary_resistance` | Boolean | `has_legendary_resistance = true` | Has legendary resistance |
| `has_magic_resistance` | Boolean | `has_magic_resistance = true` | Has magic resistance |
| `source_codes` | Array | `source_codes IN [MM, VGTM]` | Source book codes |
| `spell_slugs` | Array | `spell_slugs IN [fireball, fly]` | Known spells |
| `tag_slugs` | Array | `tag_slugs IN [dragon, undead]` | Thematic tags |

**Type Breakdown:**
- Integer: 17
- String: 6
- Boolean: 8
- Array: 3

---

## 4. Races (16 fields)

| Field Name | Data Type | Example Filter | Notes |
|------------|-----------|----------------|-------|
| `id` | Integer | `id = 7` | Primary key |
| `speed` | Integer | `speed >= 30` | Base walking speed (ft) |
| `ability_str_bonus` | Integer | `ability_str_bonus > 0` | STR modifier |
| `ability_dex_bonus` | Integer | `ability_dex_bonus > 0` | DEX modifier |
| `ability_con_bonus` | Integer | `ability_con_bonus > 0` | CON modifier |
| `ability_int_bonus` | Integer | `ability_int_bonus > 0` | INT modifier |
| `ability_wis_bonus` | Integer | `ability_wis_bonus > 0` | WIS modifier |
| `ability_cha_bonus` | Integer | `ability_cha_bonus > 0` | CHA modifier |
| `slug` | String | `slug = "half-elf"` | URL-safe identifier |
| `size_name` | String | `size_name = "Medium"` | Full size name |
| `size_code` | String | `size_code = "M"` | Size code (S/M/L) |
| `parent_race_name` | String | `parent_race_name = "Elf"` | For subraces |
| `is_subrace` | Boolean | `is_subrace = true` | Is a subrace |
| `has_innate_spells` | Boolean | `has_innate_spells = true` | Has racial spells |
| `source_codes` | Array | `source_codes IN [PHB, MTOF]` | Source book codes |
| `tag_slugs` | Array | `tag_slugs IN [darkvision, fey]` | Thematic tags |
| `spell_slugs` | Array | `spell_slugs IN [dancing-lights]` | Innate spells |

**Type Breakdown:**
- Integer: 8
- String: 4
- Boolean: 2
- Array: 3

---

## 5. Items (27 fields)

| Field Name | Data Type | Example Filter | Notes |
|------------|-----------|----------------|-------|
| `id` | Integer | `id = 456` | Primary key |
| `weight` | Integer | `weight <= 10` | Weight in pounds |
| `cost_cp` | Integer | `cost_cp >= 10000` | Cost in copper pieces |
| `range_normal` | Integer | `range_normal >= 80` | Normal range (ft) |
| `range_long` | Integer | `range_long >= 320` | Long range (ft) |
| `armor_class` | Integer | `armor_class >= 14` | Base AC (for armor) |
| `strength_requirement` | Integer | `strength_requirement = 0` | STR required |
| `charges_max` | Integer | `charges_max > 0` | Max charges |
| `slug` | String | `slug = "longsword"` | URL-safe identifier |
| `type_name` | String | `type_name = "Weapon"` | Item category |
| `type_code` | String | `type_code = "W"` | Category code |
| `rarity` | String | `rarity = "rare"` | Rarity tier |
| `damage_dice` | String | `damage_dice = "1d8"` | Damage roll |
| `versatile_damage` | String | `versatile_damage = "1d10"` | Two-handed damage |
| `damage_type` | String | `damage_type = "slashing"` | Damage type |
| `recharge_timing` | String | `recharge_timing = "dawn"` | When charges restore |
| `recharge_formula` | String | `recharge_formula = "1d6+4"` | Charge restoration |
| `requires_attunement` | Boolean | `requires_attunement = true` | Needs attunement |
| `is_magic` | Boolean | `is_magic = true` | Is magical |
| `stealth_disadvantage` | Boolean | `stealth_disadvantage = true` | Imposes stealth penalty |
| `has_charges` | Boolean | `has_charges = true` | Has charge system |
| `source_codes` | Array | `source_codes IN [DMG, XGE]` | Source book codes |
| `spell_slugs` | Array | `spell_slugs IN [fireball, fly]` | Spells granted |
| `tag_slugs` | Array | `tag_slugs IN [weapon, magic]` | Thematic tags |
| `property_codes` | Array | `property_codes IN [F, L, V]` | Weapon properties |
| `modifier_categories` | Array | `modifier_categories IN [bonus, ability]` | Stat modifiers |
| `proficiency_names` | Array | `proficiency_names IN [Longswords]` | Required proficiencies |
| `saving_throw_abilities` | Array | `saving_throw_abilities IN [dexterity]` | Save types triggered |

**Type Breakdown:**
- Integer: 8
- String: 9
- Boolean: 4
- Array: 6

---

## 6. Backgrounds (7 fields)

| Field Name | Data Type | Example Filter | Notes |
|------------|-----------|----------------|-------|
| `id` | Integer | `id = 12` | Primary key |
| `slug` | String | `slug = "acolyte"` | URL-safe identifier |
| `grants_language_choice` | Boolean | `grants_language_choice = true` | Offers language selection |
| `source_codes` | Array | `source_codes IN [PHB, SCAG]` | Source book codes |
| `tag_slugs` | Array | `tag_slugs IN [religious, urban]` | Thematic tags |
| `skill_proficiencies` | Array | `skill_proficiencies IN [insight, religion]` | Granted skills |
| `tool_proficiency_types` | Array | `tool_proficiency_types IN [artisans-tools]` | Tool categories |

**Type Breakdown:**
- Integer: 1
- String: 1
- Boolean: 1
- Array: 4

---

## 7. Feats (8 fields)

| Field Name | Data Type | Example Filter | Notes |
|------------|-----------|----------------|-------|
| `id` | Integer | `id = 23` | Primary key |
| `slug` | String | `slug = "great-weapon-master"` | URL-safe identifier |
| `has_prerequisites` | Boolean | `has_prerequisites = true` | Requires prerequisites |
| `grants_proficiencies` | Boolean | `grants_proficiencies = true` | Grants proficiencies |
| `source_codes` | Array | `source_codes IN [PHB, XGE]` | Source book codes |
| `tag_slugs` | Array | `tag_slugs IN [combat, magic]` | Thematic tags |
| `improved_abilities` | Array | `improved_abilities IN [strength, constitution]` | Ability score increases |
| `prerequisite_types` | Array | `prerequisite_types IN [ability, race, spell]` | Prerequisite categories |

**Type Breakdown:**
- Integer: 1
- String: 1
- Boolean: 2
- Array: 4

---

## Summary Statistics

### Fields by Entity

| Entity | Total Fields | Integer | String | Boolean | Array |
|--------|-------------|---------|--------|---------|-------|
| Spells | 20 | 2 | 6 | 6 | 6 |
| Classes | 18 | 4 | 4 | 4 | 6 |
| Monsters | 34 | 17 | 6 | 8 | 3 |
| Races | 16 | 8 | 4 | 2 | 3 |
| Items | 27 | 8 | 9 | 4 | 6 |
| Backgrounds | 7 | 1 | 1 | 1 | 4 |
| Feats | 8 | 1 | 1 | 2 | 4 |
| **TOTAL** | **130** | **41** | **31** | **27** | **32** |

### Aggregate Type Distribution

- **Integer (41 fields - 31.5%):** Numeric values (IDs, stats, counts, scores)
- **String (31 fields - 23.8%):** Text values (names, codes, descriptions)
- **Boolean (27 fields - 20.8%):** True/false flags (capabilities, requirements)
- **Array (32 fields - 24.6%):** Multi-value fields (sources, tags, lists)

### Common Patterns

**Universal Fields (All 7 Entities):**
- `id` (Integer)
- `slug` (String)
- `source_codes` (Array)
- `tag_slugs` (Array)

**Most Complex Entity:**
- Monsters: 34 filterable fields (26% of all fields)

**Simplest Entity:**
- Backgrounds: 7 filterable fields (5% of all fields)

**Most Integer-Heavy:**
- Monsters: 17 integer fields (50% of monster fields)

**Most Boolean-Heavy:**
- Monsters: 8 boolean fields (24% of monster fields)

---

## Filter Syntax Reference

### Integer Filters
```
field = 5              # Exact match
field != 5             # Not equal
field > 5              # Greater than
field >= 5             # Greater than or equal
field < 5              # Less than
field <= 5             # Less than or equal
field 1 TO 10          # Range (inclusive)
```

### String Filters
```
field = "value"        # Exact match
field != "value"       # Not equal
```

### Boolean Filters
```
field = true           # Is true
field = false          # Is false
field != true          # Is not true
```

### Array Filters
```
field IN [value1, value2]           # Any of
field NOT IN [value1, value2]       # None of
field IN [value1] AND field IN [value2]  # All of (intersection)
```

### Combining Filters
```
field1 = value1 AND field2 > 10     # Both conditions
field1 = value1 OR field2 = value2  # Either condition
(field1 = value1 OR field1 = value2) AND field2 > 10  # Grouping
```

---

## Related Documentation

- **API Documentation:** `/docs/api` (Scramble OpenAPI)
- **Search Architecture:** `CLAUDE.md` (Meilisearch section)
- **Model Definitions:** `app/Models/` (each model's `searchableOptions()`)
- **D&D Features:** `docs/DND-FEATURES.md` (game mechanics explained)

---

**Generated:** 2025-11-25
**Source:** Model `searchableOptions()['filterableAttributes']` audit
**Status:** Production-Ready
