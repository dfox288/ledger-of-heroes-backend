# D&D 5e Specific Features

This document covers D&D 5th Edition game mechanics implemented in the API. For general development guidance, see `CLAUDE.md`.

---

## 🏷️ Universal Tag System

All 7 entities support tags with Meilisearch filtering: Spell, Monster, Item, Race, Class, Background, Feat

```php
// Model Implementation
use Spatie\Tags\HasTags;
class Spell extends Model {
    use HasTags, Searchable;

    public function toSearchableArray(): array {
        return [
            'tag_slugs' => $this->tags->pluck('slug')->all(),  // Filterable in Meilisearch
        ];
    }
}

// API Filtering (ALL 7 entities)
GET /api/v1/spells?filter=tag_slugs IN [touch-spells, verbal-only]
GET /api/v1/monsters?filter=tag_slugs IN [fiend, fire-immune]
GET /api/v1/races?filter=tag_slugs IN [darkvision, fey-ancestry]
GET /api/v1/classes?filter=tag_slugs IN [full-caster, martial]
GET /api/v1/backgrounds?filter=tag_slugs IN [criminal, noble]
GET /api/v1/feats?filter=tag_slugs IN [combat, magic]
GET /api/v1/items?filter=tag_slugs IN [magic-weapon]

// Combined Filters
?filter=tag_slugs IN [darkvision] AND speed >= 35
```

**Benefits:** Universal categorization, fast Meilisearch filtering, consistent API pattern

---

## 🎲 Language System

30 D&D languages + choice slots ("choose one extra language")

```json
{
  "languages": [
    {"language": {"name": "Common"}, "choice_group": null},
    {"language": {"name": "Elvish"}, "choice_group": null},
    {"language": null, "choice_group": "language_choice_1", "quantity": 1}
  ]
}
```

---

## 🎯 Saving Throw Modifiers

Tracks advantage/disadvantage on saving throws for spell/ability optimization

```json
{
  "saving_throws": [{
    "ability_score": {"code": "WIS", "name": "Wisdom"},
    "save_effect": "half_damage",
    "save_modifier": "none"  // 'none', 'advantage', 'disadvantage', or NULL
  }]
}
```

**Semantic Meaning:**
- `'none'` = Standard save (Fireball: "make a DEX save")
- `'advantage'` = Grants advantage on saves (Heroes' Feast: "makes WIS saves with advantage")
- `'disadvantage'` = Imposes disadvantage (Charm Monster)
- `NULL` = Parser couldn't determine (data quality indicator)

**Use Cases:**
- Filter buff spells that grant advantage on saves
- Identify spells with conditional saves
- Character builders can optimize spell selection

---

## 🛡️ AC Modifier Category System

AC modifiers categorized by type for accurate D&D 5e calculations

```json
{
  "name": "Shield +2",
  "armor_class": 2,
  "modifiers": [
    {"modifier_category": "ac_bonus", "value": "2"},  // Base shield bonus
    {"modifier_category": "ac_magic", "value": "2"}   // Magic enchantment (+2)
  ]
}
```

**AC Modifier Categories:**
- `ac_base` - Base armor AC (replaces natural AC, stores DEX modifier rules)
- `ac_bonus` - Equipment AC bonuses (shields, always additive)
- `ac_magic` - Magic enchantment bonuses (always additive)
- `ac` - Generic AC (legacy, may be deprecated)

**Why Categories?**
1. Semantic clarity - Intent is explicit (base vs bonus vs magic)
2. Query flexibility - Filter by type: magic-only, equipment-only, or total
3. D&D 5e compliance - Ready for complex AC calculations (Mage Armor, Barbarian Unarmored Defense)

**Implementation:** Light/Medium/Heavy armor auto-creates `ac_base` modifiers with DEX rules (`dex_modifier: full|max_2|none`). Shields use `ac_bonus` for base and `ac_magic` for enchantments.

---

## 🔄 Dual ID/Slug Routing

All entities support SEO-friendly slug routing

```
/api/v1/spells/123       ← Numeric ID
/api/v1/spells/fireball  ← SEO-friendly slug
```

---

## 📚 Multi-Source Citations

Entities cite multiple sourcebooks via `entity_sources` polymorphic table

```json
{
  "sources": [
    {"code": "PHB", "name": "Player's Handbook"},
    {"code": "XGE", "name": "Xanathar's Guide to Everything"}
  ]
}
```

---

## 🔗 Polymorphic Relationships

Universal patterns for shared data across multiple entity types:

- **Traits, Modifiers, Proficiencies** - Shared across races/classes/backgrounds
- **Tags** - Universal categorization system
- **Prerequisites** - Double polymorphic (entity → prerequisite type)
- **Random Tables** - d6/d8/d100 embedded in descriptions
- **Entity Spells** - Monsters/classes/races with innate/learned spells

---

## 🎲 Random Tables

Class features support embedded random tables (Wild Magic, Sneak Attack, etc.)

```json
{
  "random_tables": [
    {
      "name": "Wild Magic Surge",
      "die_type": "d100",
      "entries": [
        {"min_roll": 1, "max_roll": 2, "result": "Roll on table again..."},
        {"min_roll": 3, "max_roll": 4, "result": "..."}
      ]
    }
  ]
}
```

**Imported:** 54 tables across class features

---

## 🔮 Monster Spell Syncing

129 spellcasting monsters have 1,098 spell relationships synced via `SpellcasterStrategy`

```php
// Query monsters by spells
GET /api/v1/monsters?spells=fireball
GET /api/v1/monsters?spells=fireball,lightning-bolt  // AND logic

// Get monster spell list
GET /api/v1/monsters/{id}/spells
```

**Implementation:** Case-insensitive spell lookup with 100% match rate

---

## 🏺 Item Enhancements

**Usage Limits:** "at will", "1/day", "3/day" tracked in modifiers

**Set Ability Scores:** `set:19` notation for fixed ability scores (Gauntlets of Ogre Power)

**Potion Resistances:** 23 potions of resistance with damage type tracking

**Charged Items:** Staves/wands with spell casting, syncs to `entity_spells` table

---

## 🎭 Character Creation Data

**Proficiency Choices:** Grouped using `choice_group` pattern (skills, tools, languages)

```json
{
  "proficiencies": [
    {"proficiency": {"name": "Athletics"}, "choice_group": "skill_choice_1", "quantity": 2},
    {"proficiency": {"name": "Acrobatics"}, "choice_group": "skill_choice_1"},
    {"proficiency": {"name": "Perception"}, "choice_group": "skill_choice_1"}
  ]
}
```

**Equipment Choices:** Grouped using same pattern (character creation workflow)

**Prerequisites:** Ability score, level, spell, proficiency, race, class requirements

---

## 🧙 Spell Filtering

**Advanced Meilisearch Filtering:**
```bash
# Range queries
GET /api/v1/spells?filter=level >= 1 AND level <= 3

# Logical operators
GET /api/v1/spells?filter=school_code = EV OR school_code = C

# Combined search + filter
GET /api/v1/spells?q=fire&filter=level <= 3

# Tag filtering
GET /api/v1/spells?filter=tag_slugs IN [touch-spells, verbal-only]
```

**Lookup Endpoints:**
- `GET /api/v1/spell-schools/{id|code|slug}/spells` - Spells by school
- `GET /api/v1/damage-types/{id|code}/spells` - Spells by damage type
- `GET /api/v1/conditions/{id|slug}/spells` - Spells that inflict condition
- `GET /api/v1/ability-scores/{id|code|name}/spells` - Spells by required save

See `docs/MEILISEARCH-FILTERS.md` for full syntax

---

## 👹 Monster Filtering

**Type-Specific Strategies (12):**
- **BeastStrategy** - 102 beasts with keen senses, pack tactics, charge/pounce
- **ElementalStrategy** - 16 elementals (fire/water/earth/air subtypes)
- **ShapechangerStrategy** - 12 shapechangers (lycanthropes, mimics, doppelgangers)
- **AberrationStrategy** - 19 aberrations (mind flayers, beholders, telepathy)
- **FiendStrategy** - 28 fiends (devils, demons, yugoloths)
- **CelestialStrategy** - 2 celestials (angels, radiant damage)
- **ConstructStrategy** - 42 constructs (golems, animated objects)
- **DragonStrategy** - Breath weapons, frightful presence
- **SpellcasterStrategy** - 129 monsters with spell lists
- **UndeadStrategy** - Undead fortitude, turn resistance
- **SwarmStrategy** - Swarm type and creature count
- **DefaultStrategy** - Baseline for all monsters

**Tag-Based Filtering:**
```bash
GET /api/v1/monsters?filter=tag_slugs IN [fiend, fire-immune]
GET /api/v1/monsters?filter=tag_slugs IN [beast, pack-tactics]
GET /api/v1/monsters?filter=challenge_rating <= 5 AND tag_slugs IN [spellcaster]
```

**Lookup Endpoints:**
- `GET /api/v1/conditions/{id|slug}/monsters` - Monsters that inflict condition
- `GET /api/v1/damage-types/{id|code}/monsters` - Monsters by damage type

---

## 📖 Lookup Tables

Pre-seeded D&D reference data available via API:

- **Sources** - D&D sourcebooks (PHB, XGE, TCE, etc.)
- **Spell Schools** - 8 schools of magic
- **Damage Types** - 13 damage types (fire, cold, radiant, etc.)
- **Conditions** - 15 D&D conditions (blinded, charmed, paralyzed, etc.)
- **Proficiency Types** - 82 weapon/armor/tool types
- **Languages** - 30 languages (Common, Elvish, Draconic, etc.)
- **Sizes** - Tiny, Small, Medium, Large, Huge, Gargantuan
- **Ability Scores** - STR, DEX, CON, INT, WIS, CHA
- **Skills** - 18 skills linked to ability scores
- **Item Types/Properties** - Weapon types, armor types, magic item properties
- **Character Classes** - Base classes for reference

**Endpoints:** `GET /api/v1/sources`, `GET /api/v1/spell-schools`, etc.

---

## 🔍 Search System

**Global Search Endpoint:**
```
GET /api/v1/search?q=fire&types=spells,items,monsters,classes,races,backgrounds,feats
```

**Features:**
- 7 searchable entity types
- Typo-tolerant ("firebll" finds "Fireball")
- Performance: <50ms average, <100ms p95
- Graceful MySQL FULLTEXT fallback
- 3,600+ documents indexed

**Per-Entity Search:**
- Each entity endpoint supports `?q=` parameter
- Combined with filters: `?q=fire&filter=level <= 3`
- Meilisearch-powered with Scout integration

See `docs/SEARCH.md` for complete documentation

---

**Last Updated:** 2025-11-24
