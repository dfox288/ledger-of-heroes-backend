# D&D 5e API - Advanced Usage Examples

**Last Updated:** 2025-11-22
**Base URL:** `/api/v1`

This document provides real-world examples of using the D&D 5e Compendium API's advanced filtering capabilities, especially for monster queries with spell filtering.

---

## 📚 Table of Contents

1. [Basic Monster Queries](#basic-monster-queries)
2. [Spell Filtering](#spell-filtering)
3. [Advanced Spell Filtering](#advanced-spell-filtering)
4. [Combined Filters](#combined-filters)
5. [Search + Filters](#search--filters)
6. [Building a Spell Tracker](#building-a-spell-tracker)
7. [Building an Encounter Builder](#building-an-encounter-builder)

---

## Basic Monster Queries

### Get All Monsters
```http
GET /api/v1/monsters
```

**Response:** Paginated list of all 598 monsters

### Get Specific Monster by Slug
```http
GET /api/v1/monsters/ancient-red-dragon
```

**Use Case:** Direct monster lookup by SEO-friendly slug

### Filter by Challenge Rating
```http
GET /api/v1/monsters?challenge_rating=5
GET /api/v1/monsters?min_cr=10&max_cr=15
```

**Use Case:** Find monsters appropriate for party level

### Filter by Type
```http
GET /api/v1/monsters?type=dragon
GET /api/v1/monsters?type=undead
GET /api/v1/monsters?type=humanoid
```

**Use Case:** Themed encounters (all dragons, all undead, etc.)

---

## Spell Filtering

### Monsters with Specific Spell (Single)
```http
GET /api/v1/monsters?spells=fireball
```

**Returns:** 11 monsters that know Fireball
**Use Case:** "Which monsters can cast Fireball?"

**Example Monsters:**
- Arcanaloth (CR 12)
- Lich (CR 21)
- Archmage (CR 12)
- Flameskull (CR 4)

### Monsters with Multiple Spells (AND Logic)
```http
GET /api/v1/monsters?spells=fireball,lightning-bolt
```

**Returns:** 3 monsters that know BOTH Fireball AND Lightning Bolt
**Use Case:** "Which monsters are versatile damage dealers?"

**Example Monsters:**
- Lich (CR 21)
- Archmage (CR 12)
- Arcanaloth (CR 12)

### Get All Spells for a Monster
```http
GET /api/v1/monsters/lich/spells
```

**Returns:** 26 spells known by the Lich
**Use Case:** "What can this monster do in combat?"

**Example Spells:**
- Cantrips: Mage Hand, Prestidigitation, Ray of Frost
- 3rd Level: Animate Dead, Counterspell, Dispel Magic, Fireball
- 9th Level: Power Word Kill

---

## Advanced Spell Filtering

### OR Logic - Monsters with ANY of Multiple Spells
```http
GET /api/v1/monsters?spells=fireball,lightning-bolt&spells_operator=OR
```

**Returns:** ~17 monsters that know Fireball OR Lightning Bolt (or both)
**Use Case:** "Which monsters can deal area damage?"

### Filter by Spell Level
```http
GET /api/v1/monsters?spell_level=9
```

**Returns:** High-CR spellcasters with 9th level spell slots
**Use Case:** "Which monsters are legendary archmages?"

**Example Monsters:**
- Lich (CR 21)
- Archmage (CR 12)
- Lady Illmarrow (CR 22)

### Filter by Spell Level + Specific Spell
```http
GET /api/v1/monsters?spell_level=3&spells=fireball
```

**Returns:** Monsters with 3rd level slots that know Fireball
**Use Case:** "Which mid-tier spellcasters have Fireball?"

### Filter by Spellcasting Ability
```http
GET /api/v1/monsters?spellcasting_ability=INT
GET /api/v1/monsters?spellcasting_ability=WIS
GET /api/v1/monsters?spellcasting_ability=CHA
```

**Returns:** Monsters that use INT/WIS/CHA for spellcasting
**Use Case:** "Find arcane vs divine vs charisma-based casters"

**Examples:**
- **INT:** Wizards, Archmages, Liches, Mind Flayers
- **WIS:** Clerics, Druids, Monks
- **CHA:** Sorcerers, Warlocks, Bards

---

## Combined Filters

### CR Range + Spell Filtering
```http
GET /api/v1/monsters?min_cr=5&max_cr=10&spells=fireball
```

**Returns:** Mid-tier monsters (CR 5-10) with Fireball
**Use Case:** "Balanced boss fight for level 7 party"

### Type + Spell Level
```http
GET /api/v1/monsters?type=undead&spell_level=6
```

**Returns:** Undead spellcasters with 6th level spells
**Use Case:** "Undead necromancers for themed campaign"

### Size + Alignment + Spells
```http
GET /api/v1/monsters?size=M&alignment=evil&spells=charm-person
```

**Returns:** Medium evil monsters that can charm
**Use Case:** "Deceptive villain NPCs"

### Triple Filter - CR + Type + Spells
```http
GET /api/v1/monsters?min_cr=15&type=fiend&spells=fireball,teleport&spells_operator=AND
```

**Returns:** High-CR fiends with mobility and damage
**Use Case:** "Epic boss battle with tactical options"

---

## Search + Filters

### Text Search + Spell Filter
```http
GET /api/v1/monsters?q=dragon&spells=fireball
```

**Returns:** Dragons that can cast Fireball
**Use Case:** "Dragons with spellcasting"

**Note:** Uses Meilisearch for fast (<10ms) queries

### Text Search + Spell Level
```http
GET /api/v1/monsters?q=lich&spell_level=9
```

**Returns:** Lich variants with 9th level spells
**Use Case:** "Find epic lich encounters"

### Search + OR Logic
```http
GET /api/v1/monsters?q=hag&spells=hex,hold-person,lightning-bolt&spells_operator=OR
```

**Returns:** Hags with any control/damage spells
**Use Case:** "Hag coven with varied spellcasters"

---

## Building a Spell Tracker

**Scenario:** You're running a campaign and want to track which monsters your players might encounter that can use certain spells.

### Step 1: Find All Monsters with Counterspell
```http
GET /api/v1/monsters?spells=counterspell
```

**Use Case:** "Warn players that this enemy can counter their spells"

### Step 2: Find Monsters with Healing Spells
```http
GET /api/v1/monsters?spells=cure-wounds,heal,mass-cure-wounds&spells_operator=OR
```

**Use Case:** "These enemies can heal - focus fire!"

### Step 3: Find Teleporting Enemies
```http
GET /api/v1/monsters?spells=misty-step,dimension-door,teleport&spells_operator=OR
```

**Use Case:** "Mobile enemies that can escape"

### Step 4: Find Summoners
```http
GET /api/v1/monsters?spells=conjure-animals,summon-greater-demon,gate&spells_operator=OR
```

**Use Case:** "Action economy advantage - kill the summoner first"

---

## Building an Encounter Builder

**Scenario:** Create balanced encounters for a level 10 party (4 players).

### Step 1: Find Appropriate CR Range
```http
GET /api/v1/monsters?min_cr=5&max_cr=8
```

**Use Case:** Medium difficulty enemies (CR 5-8 for level 10 party)

### Step 2: Add Spellcasting Variety
```http
GET /api/v1/monsters?min_cr=5&max_cr=8&spells=fireball&spells_operator=OR
```

**Result:** Mid-tier enemies with area damage

### Step 3: Mix Arcane and Divine
```http
# Arcane casters
GET /api/v1/monsters?min_cr=5&max_cr=8&spellcasting_ability=INT

# Divine casters
GET /api/v1/monsters?min_cr=5&max_cr=8&spellcasting_ability=WIS
```

**Use Case:** Balanced enemy composition (wizard + cleric)

### Step 4: Add Control Spells
```http
GET /api/v1/monsters?min_cr=5&max_cr=8&spells=hold-person,hypnotic-pattern,web&spells_operator=OR
```

**Use Case:** Enemies that can disable players

### Step 5: Final Encounter
**Combine results manually:**
- 1x Archmage (CR 12) - Boss with Fireball, Counterspell
- 2x Priest (CR 2) - Healers with Cure Wounds
- 1x Warlock (CR 6) - Controller with Hold Person

**XP Budget:** 8,400 + 900 + 2,300 = 11,600 XP ≈ Hard encounter for level 10 party

---

## Complex Real-World Examples

### Example 1: "Lich Hunting"
**Goal:** Find all lich-like enemies for a campaign arc

```http
# Step 1: Find liches
GET /api/v1/monsters?q=lich

# Step 2: Find undead spellcasters with 9th level spells
GET /api/v1/monsters?type=undead&spell_level=9

# Step 3: Find undead with phylactery-related spells
GET /api/v1/monsters?type=undead&spells=clone,sequester,imprisonment&spells_operator=OR
```

**Result:** 5-7 lich variants and undead archmages

### Example 2: "Elemental Damage Encounters"
**Goal:** Encounters themed around fire/lightning damage

```http
# Fire damage dealers
GET /api/v1/monsters?spells=fireball,flame-strike,wall-of-fire&spells_operator=OR

# Lightning damage dealers
GET /api/v1/monsters?spells=lightning-bolt,call-lightning,chain-lightning&spells_operator=OR

# Combined fire/lightning
GET /api/v1/monsters?min_cr=10&spells=fireball,lightning-bolt&spells_operator=OR
```

**Result:** Varied elemental casters for themed dungeon

### Example 3: "Spellcasting Dragons"
**Goal:** Find dragons with innate spellcasting

```http
# All dragons with any spells
GET /api/v1/monsters?type=dragon&spell_level=1

# Ancient dragons with legendary spells
GET /api/v1/monsters?q=ancient&type=dragon&spell_level=8

# Dragons with specific utility spells
GET /api/v1/monsters?type=dragon&spells=polymorph,teleport&spells_operator=OR
```

**Result:** Dragons that can shapeshift or teleport

### Example 4: "Boss Rush"
**Goal:** Create a series of escalating boss fights

```http
# Boss 1: CR 5-7 with control spells
GET /api/v1/monsters?min_cr=5&max_cr=7&spells=hold-person

# Boss 2: CR 10-12 with area damage
GET /api/v1/monsters?min_cr=10&max_cr=12&spells=fireball,lightning-bolt&spells_operator=OR

# Boss 3: CR 15-17 with teleportation
GET /api/v1/monsters?min_cr=15&max_cr=17&spells=dimension-door,teleport&spells_operator=OR

# Final Boss: CR 20+ with 9th level spells
GET /api/v1/monsters?min_cr=20&spell_level=9
```

**Result:** Progressive difficulty with varied mechanics

---

## Performance Notes

### Query Speed
- **Search + Spell Filter (Meilisearch):** <10ms
- **Spell Filter Only (Database):** <50ms
- **Complex Multi-Filter:** <100ms

### Response Size
- **Uncompressed:** ~92KB for 15 monsters
- **Gzip Compressed:** ~20KB (78% reduction)
- **Recommendation:** Always use `Accept-Encoding: gzip`

### Pagination
All list endpoints support pagination:
```http
GET /api/v1/monsters?per_page=50&page=2
```

Default: 15 per page

---

## Tips & Best Practices

### 1. Use OR Logic for Discovery
```http
# Good: Cast a wide net
GET /api/v1/monsters?spells=fireball,lightning-bolt,cone-of-cold&spells_operator=OR

# Less useful: Too restrictive
GET /api/v1/monsters?spells=fireball,lightning-bolt,cone-of-cold
```

### 2. Combine Search with Filters
```http
# Good: Fast and specific
GET /api/v1/monsters?q=dragon&min_cr=15

# Slower: Filter-only
GET /api/v1/monsters?type=dragon&min_cr=15
```

**Reason:** Meilisearch is faster than database filtering

### 3. Use Spell Level for Power Filtering
```http
# Find true archmages (9th level casters)
GET /api/v1/monsters?spell_level=9

# Find mid-tier casters (3rd-5th level)
GET /api/v1/monsters?spell_level=3
```

### 4. Leverage Spell Operator
```http
# Versatile: Any of these spells
?spells=charm-person,hold-person,dominate-person&spells_operator=OR

# Specific: Must have ALL these spells
?spells=fireball,counterspell&spells_operator=AND
```

### 5. Build Complex Queries Incrementally
```http
# Start broad
GET /api/v1/monsters?type=fiend

# Add CR constraint
GET /api/v1/monsters?type=fiend&min_cr=10

# Add spell requirement
GET /api/v1/monsters?type=fiend&min_cr=10&spells=fireball

# Final query
GET /api/v1/monsters?type=fiend&min_cr=10&spells=fireball,teleport&spells_operator=AND
```

---

## API Response Format

All responses include:
- `data` - Array of monster objects
- `links` - Pagination links (first, last, prev, next)
- `meta` - Metadata (current_page, total, per_page, etc.)

**Example:**
```json
{
  "data": [
    {
      "id": 258,
      "slug": "lich",
      "name": "Lich",
      "challenge_rating": "21",
      "type": "undead",
      "size": {"code": "M", "name": "Medium"},
      ...
    }
  ],
  "links": {
    "first": "/api/v1/monsters?page=1",
    "last": "/api/v1/monsters?page=5",
    "prev": null,
    "next": "/api/v1/monsters?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 63
  }
}
```

---

## Summary of Filter Parameters

| Parameter | Type | Example | Description |
|-----------|------|---------|-------------|
| `q` | string | `?q=dragon` | Full-text search (name, description, type) |
| `challenge_rating` | number | `?challenge_rating=5` | Exact CR match |
| `min_cr` | number | `?min_cr=10` | Minimum CR (inclusive) |
| `max_cr` | number | `?max_cr=15` | Maximum CR (inclusive) |
| `type` | string | `?type=dragon` | Monster type filter |
| `size` | string | `?size=M` | Size code (T, S, M, L, H, G) |
| `alignment` | string | `?alignment=evil` | Alignment filter |
| `spells` | string | `?spells=fireball,counterspell` | Comma-separated spell slugs |
| `spells_operator` | string | `?spells_operator=OR` | Spell logic (AND or OR, default: AND) |
| `spell_level` | integer | `?spell_level=9` | Filter by spell slot level (0-9) |
| `spellcasting_ability` | string | `?spellcasting_ability=INT` | Caster type (INT, WIS, or CHA) |
| `per_page` | integer | `?per_page=50` | Results per page (default: 15) |
| `page` | integer | `?page=2` | Page number (default: 1) |
| `sort_by` | string | `?sort_by=challenge_rating` | Sort column |
| `sort_direction` | string | `?sort_direction=desc` | Sort direction (asc or desc) |

---

**Full API Documentation:** `/docs/api` (Scramble-generated OpenAPI docs)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
