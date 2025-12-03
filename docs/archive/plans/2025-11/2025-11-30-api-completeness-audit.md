# API Completeness Audit Report

**Issue:** #58
**Date:** 2025-11-30
**Status:** In Progress

## Executive Summary

This audit reviews all main entity API responses against D&D 5e PHB requirements and character builder needs. Each entity is evaluated for completeness, computed fields, and filtering capabilities.

---

## 1. Spells API ✅ EXCELLENT

### Current Fields
| Field | Status | Notes |
|-------|--------|-------|
| id, slug, name | ✅ | Core identifiers |
| level | ✅ | 0-9 |
| school | ✅ | Full SpellSchool resource |
| casting_time | ✅ | Raw string |
| casting_time_type | ✅ | Computed: action, bonus_action, reaction, minute, hour, special |
| range | ✅ | Raw string |
| components | ✅ | "V, S, M" format |
| material_components | ✅ | Material description |
| material_cost_gp | ✅ | Computed: parsed gold cost |
| material_consumed | ✅ | Computed: boolean |
| duration | ✅ | Raw string |
| needs_concentration | ✅ | Boolean |
| is_ritual | ✅ | Boolean |
| description | ✅ | Full text |
| higher_levels | ✅ | Upcasting text |
| requires_verbal/somatic/material | ✅ | Computed booleans |
| area_of_effect | ✅ | Computed: {type, size, width?, height?} |
| sources | ✅ | With page numbers |
| effects | ✅ | Damage/healing with dice and scaling |
| classes | ✅ | Full class list |
| saving_throws | ✅ | Ability, effect, recurring, modifier |
| tags | ✅ | Custom tags |
| data_tables | ✅ | Embedded tables |

### Filtering (Meilisearch)
- level, school, concentration, ritual, classes, damage_types, saving_throws
- requires_verbal/somatic/material, effect_types
- material_cost_gp, material_consumed, aoe_type, aoe_size

### Gaps Identified
| Gap | Priority | Notes |
|-----|----------|-------|
| **Subclass spell lists** | Medium | Currently only base class, not "Circle of the Land (Arctic)" etc. |
| **Duration type** | Low | Could compute: instantaneous, rounds, minutes, hours, until_dispelled |
| **Range type** | Low | Could compute: self, touch, feet, miles, sight, unlimited |

---

## 2. Classes API ✅ EXCELLENT

### Current Fields
| Field | Status | Notes |
|-------|--------|-------|
| id, slug, name | ✅ | Core identifiers |
| hit_die | ✅ | Effective (inherits for subclasses) |
| description | ✅ | Class description |
| archetype | ✅ | Subclass type name (e.g., "Martial Archetype") |
| primary_ability | ✅ | Main stat(s) |
| spellcasting_ability | ✅ | AbilityScore resource |
| spellcasting_type | ✅ | full, half, third, pact, none |
| is_base_class | ✅ | Boolean |
| subclass_level | ✅ | Level when subclass is chosen |
| parent_class | ✅ | For subclasses |
| subclasses | ✅ | List of subclasses |
| multiclass_requirements | ✅ | Ability score requirements |
| proficiencies | ✅ | Armor, weapons, tools, saves, skills |
| traits | ✅ | Class traits |
| features | ✅ | Level-based features with inheritance |
| level_progression | ✅ | Full 1-20 progression table |
| counters | ✅ | Resource pools (rage, ki, etc.) |
| spells | ✅ | Class spell list |
| optional_features | ✅ | Optional class features (TCE) |
| equipment | ✅ | Starting equipment |
| sources | ✅ | With page numbers |
| inherited_data | ✅ | Parent class data for subclasses |
| computed | ✅ | Aggregated data on show endpoint |

### Gaps Identified
| Gap | Priority | Notes |
|-----|----------|-------|
| **Starting gold alternative** | Low | "Or Xd4 × 10 gp" option |
| **Armor/weapon proficiency categories** | Low | Could group by type |

---

## 3. Races API ✅ GOOD

### Current Fields
| Field | Status | Notes |
|-------|--------|-------|
| id, slug, name | ✅ | Core identifiers |
| size | ✅ | Full Size resource |
| speed | ✅ | Walking speed |
| fly_speed | ✅ | From Flight trait |
| swim_speed | ✅ | From Swim Speed trait |
| is_subrace | ✅ | Boolean |
| traits | ✅ | Race traits with data tables |
| modifiers | ✅ | Ability score increases, resistances |
| sources | ✅ | With page numbers |
| parent_race | ✅ | For subraces |
| subraces | ✅ | List of subraces |
| proficiencies | ✅ | Weapon/tool/armor |
| languages | ✅ | With choice support |
| conditions | ✅ | Immunities (e.g., sleep for elves) |
| spells | ✅ | Innate spellcasting |
| senses | ✅ | Darkvision, etc. |
| inherited_data | ✅ | Parent race data for subraces |

### Filtering (Meilisearch)
- size, speed ranges, ability modifiers
- has_darkvision, darkvision_range, has_fly_speed, has_swim_speed

### Gaps Identified
| Gap | Priority | Notes |
|-----|----------|-------|
| **Age/lifespan** | Low | Flavor text, rarely used mechanically |
| **Height/weight ranges** | Low | Usually flavor, random tables in PHB |
| **Climb speed** | Medium | Some races have this (Tabaxi) |
| **Burrow speed** | Low | Very rare |

---

## 4. Backgrounds API ⚠️ NEEDS IMPROVEMENT

### Current Fields
| Field | Status | Notes |
|-------|--------|-------|
| id, slug, name | ✅ | Core identifiers |
| traits | ✅ | Feature + characteristics |
| proficiencies | ✅ | Skills + tools |
| sources | ✅ | With page numbers |
| languages | ✅ | With choice support |
| equipment | ✅ | Starting items |
| tags | ✅ | Custom tags |

### Gaps Identified
| Gap | Priority | Notes |
|-----|----------|-------|
| **Feature trait extraction** | High | Background feature should be separate field |
| **Starting gold** | Medium | GP value of starting equipment |
| **Personality traits table** | ✅ | Now in data_tables |
| **Ideals table** | ✅ | Now in data_tables |
| **Bonds table** | ✅ | Now in data_tables |
| **Flaws table** | ✅ | Now in data_tables |
| **Suggested characteristics** | ✅ | In traits |
| **Variant backgrounds** | Low | Some have variants (Guild Artisan → Guild Merchant) |

### Recommended Changes
1. **Add `feature_name` and `feature_description` fields** - Extract the background feature (e.g., "Shelter of the Faithful") from traits for easier access
2. **Add `starting_gold` computed field** - Calculate total GP value of equipment

---

## 5. Feats API ✅ GOOD

### Current Fields
| Field | Status | Notes |
|-------|--------|-------|
| id, slug, name | ✅ | Core identifiers |
| prerequisites_text | ✅ | Raw prerequisite string |
| prerequisites | ✅ | Structured prerequisites |
| description | ✅ | Full text |
| is_half_feat | ✅ | Computed: grants +1 ability |
| parent_feat_slug | ✅ | For variant feats (Resilient) |
| modifiers | ✅ | Ability increases, resistances |
| proficiencies | ✅ | Granted proficiencies |
| conditions | ✅ | Granted immunities |
| sources | ✅ | With page numbers |
| tags | ✅ | Custom tags |

### Gaps Identified
| Gap | Priority | Notes |
|-----|----------|-------|
| **Granted spells** | Medium | Some feats grant spells (Magic Initiate, Ritual Caster) |
| **Repeatable flag** | Low | Can feat be taken multiple times? |
| **Category** | Low | General, racial, class-specific |

---

## 6. Items API ✅ EXCELLENT

### Current Fields
| Field | Status | Notes |
|-------|--------|-------|
| id, slug, name | ✅ | Core identifiers |
| item_type | ✅ | Full ItemType resource |
| detail | ✅ | Subtype (longsword, plate, etc.) |
| rarity | ✅ | common to legendary |
| requires_attunement | ✅ | Boolean |
| is_magic | ✅ | Boolean |
| cost_cp | ✅ | Cost in copper pieces |
| weight | ✅ | In pounds |
| damage_dice | ✅ | Weapon damage |
| versatile_damage | ✅ | Two-handed damage |
| damage_type | ✅ | Full DamageType resource |
| range_normal/range_long | ✅ | Ranged weapons |
| armor_class | ✅ | Armor AC |
| strength_requirement | ✅ | Heavy armor |
| stealth_disadvantage | ✅ | Boolean |
| description | ✅ | Full text |
| charges_max | ✅ | Magic item charges |
| recharge_formula | ✅ | e.g., "1d6+1" |
| recharge_timing | ✅ | dawn, dusk, etc. |
| proficiency_category | ✅ | Computed: simple_melee, martial_ranged, etc. |
| magic_bonus | ✅ | Computed: +1, +2, +3 |
| properties | ✅ | Weapon properties (finesse, heavy, etc.) |
| abilities | ✅ | Magic item abilities |
| data_tables | ✅ | Embedded tables |
| spells | ✅ | Spells the item can cast |
| saving_throws | ✅ | Item-imposed saves |
| modifiers | ✅ | AC bonuses, etc. |
| prerequisites | ✅ | Attunement requirements |

### Gaps Identified
| Gap | Priority | Notes |
|-----|----------|-------|
| **Ammunition flag** | Low | Is this item ammunition? |
| **Container capacity** | Low | For bags, pouches, etc. |
| **Sentient item properties** | Low | Very rare edge case |

---

## 7. Monsters API ✅ EXCELLENT

### Current Fields
| Field | Status | Notes |
|-------|--------|-------|
| id, slug, name, sort_name | ✅ | Core identifiers |
| size | ✅ | Full Size resource |
| type | ✅ | creature type |
| alignment | ✅ | alignment string |
| is_npc | ✅ | Boolean |
| armor_class, armor_type | ✅ | AC and source |
| hit_points_average, hit_dice | ✅ | HP calculation |
| speed_walk/fly/swim/burrow/climb | ✅ | All movement types |
| can_hover | ✅ | Boolean |
| All ability scores | ✅ | STR through CHA |
| challenge_rating | ✅ | CR value |
| experience_points | ✅ | XP reward |
| proficiency_bonus | ✅ | Calculated from CR |
| is_legendary | ✅ | Boolean |
| passive_perception | ✅ | Calculated |
| languages | ✅ | Languages known |
| senses | ✅ | Darkvision, etc. |
| description | ✅ | Lore text |
| traits | ✅ | Special abilities |
| actions | ✅ | Standard actions |
| legendary_actions | ✅ | Legendary actions |
| lair_actions | ✅ | Lair actions |
| spells | ✅ | Innate/prepared spells |
| modifiers | ✅ | Resistances, immunities, vulnerabilities |
| conditions | ✅ | Condition immunities |
| sources | ✅ | With page numbers |

### Gaps Identified
| Gap | Priority | Notes |
|-----|----------|-------|
| **Saving throw proficiencies** | ✅ Already Present | In modifiers as `saving_throw_dex`, etc. |
| **Skill proficiencies** | ✅ Already Present | In modifiers as `skill_perception`, etc. |
| **Reactions** | Medium | Separate from actions |
| **Multiattack details** | Low | Structured multiattack data |
| **Legendary resistance count** | Medium | How many per day (in traits, could be extracted) |
| **Lair description** | Low | Lair environment text |
| **Regional effects** | Low | Effects in lair region |

---

## Summary of Findings

### Overall Assessment
**The API is in EXCELLENT shape.** Most D&D 5e data is properly exposed with good computed fields and filtering capabilities. The few gaps identified are mostly edge cases or nice-to-haves.

### High Priority Gaps
1. **Background feature extraction** - Important for character builder (Feature trait should be a separate field)

### Medium Priority Gaps
1. **Race climb speed** - Some races have this (Tabaxi)
2. **Feat granted spells** - Magic Initiate, Ritual Caster, etc.
3. **Monster reactions** - Should be separate from actions
4. **Subclass spell lists** - Domain spells, Circle spells, etc.

### Low Priority Gaps
- Spell duration/range type computation
- Class starting gold alternative
- Race age/height/weight
- Item ammunition flag
- Monster lair/regional effects
- Background starting gold calculation

### Already Present (Verified During Audit)
- ✅ Monster saving throw proficiencies (in modifiers)
- ✅ Monster skill proficiencies (in modifiers)
- ✅ Monster legendary resistance (in traits)
- ✅ Background characteristic tables (in data_tables)

---

## Recommendations

### Quick Wins (Computed Fields)
1. Add `feature` field to BackgroundResource (extract from traits)
2. Add `climb_speed` to Race model/resource
3. Add `duration_type` computed accessor to Spell model

### Parser Changes Needed
1. Extract subclass spell lists (domain spells, etc.)
2. Parse feat-granted spells
3. Separate monster reactions from actions

### Documentation
1. Document modifier_category values for monsters (saving_throw_*, skill_*)
2. Add examples of filtering by monster saves/skills

---

## Next Steps

1. ~~Create comprehensive issues for gaps~~ → Create targeted issues as needed
2. Implement Background feature extraction (quick win)
3. Add Race climb_speed field
4. Consider subclass spell list extraction for future sprint

