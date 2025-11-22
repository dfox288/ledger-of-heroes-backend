# D&D 5e API: Comprehensive Usage Examples

**Last Updated:** 2025-11-22
**Base URL:** `http://localhost:8080/api/v1`
**Total Endpoints:** 40+ (7 entity APIs + 15 reverse relationships + 18 lookup endpoints)

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Entity APIs (7)](#entity-apis)
3. [Tier 1: Static Reference Relationships (6)](#tier-1-static-reference-relationships)
4. [Tier 2: Advanced Reverse Relationships (8)](#tier-2-advanced-reverse-relationships)
5. [Search & Filtering](#search--filtering)
6. [Pagination & Sorting](#pagination--sorting)
7. [Common Use Cases](#common-use-cases)

---

## Quick Start

### Basic Request
```bash
curl "http://localhost:8080/api/v1/spells?per_page=5"
```

### With Filtering
```bash
curl "http://localhost:8080/api/v1/spells?level=3&school=evocation"
```

### Single Resource
```bash
curl "http://localhost:8080/api/v1/spells/fireball"
```

---

## Entity APIs

### 1. Spells (477 total)

**List with Pagination:**
```bash
GET /api/v1/spells?per_page=10
```

**Filter by Level:**
```bash
GET /api/v1/spells?level=3
# Returns: 67 level 3 spells (Fireball, Lightning Bolt, Counterspell, etc.)
```

**Filter by School:**
```bash
GET /api/v1/spells?school=evocation
# Returns: Evocation spells (Fireball, Magic Missile, etc.)
```

**Get Single Spell (by ID or slug):**
```bash
GET /api/v1/spells/fireball
GET /api/v1/spells/123
```

**Get Spell's Classes:**
```bash
GET /api/v1/spells/fireball/classes
# Returns: Sorcerer, Wizard
```

**Get Spell's Monsters:**
```bash
GET /api/v1/spells/fireball/monsters
# Returns: 11 monsters that can cast Fireball
```

---

### 2. Monsters (598 total)

**List All:**
```bash
GET /api/v1/monsters?per_page=20
```

**Filter by Challenge Rating:**
```bash
GET /api/v1/monsters?cr=10
# Returns: CR 10 monsters
```

**Filter by Spell Access:**
```bash
GET /api/v1/monsters?spells=fireball
# Returns: 11 spellcasting monsters with Fireball (Arcanaloth, Death Slaad, etc.)
```

**Filter by Size:**
```bash
GET /api/v1/monsters?size=6
# Returns: 16 Gargantuan monsters (Ancient Dragons, Kraken, Tarrasque)
```

**Get Monster's Spells:**
```bash
GET /api/v1/monsters/arcanaloth/spells
# Returns: Fire Bolt, Mage Hand, Minor Illusion, Fireball, etc.
```

---

### 3. Races (115 total)

**List All:**
```bash
GET /api/v1/races?per_page=20
```

**Filter by Darkvision:**
```bash
GET /api/v1/races?has_darkvision=true
# Returns: 45 races with darkvision (Elf, Dwarf, Tiefling, etc.)
```

**Filter by Size:**
```bash
GET /api/v1/races?size_id=2
# Returns: 22 Small races (Halfling, Gnome, Kobold, etc.)
```

**Get Single Race:**
```bash
GET /api/v1/races/aarakocra
# Returns: Aarakocra details with size, speed, fly speed, traits, etc.
```

**Get Race's Spells:**
```bash
GET /api/v1/races/{id}/spells
# Returns: Innate spells for the race
```

---

### 4. Backgrounds (34 total)

**List All:**
```bash
GET /api/v1/backgrounds?per_page=20
```

**Get Single Background:**
```bash
GET /api/v1/backgrounds/acolyte
# Returns: Acolyte details with traits, proficiencies, equipment, etc.
```

---

### 5. Items (516 total)

**List All:**
```bash
GET /api/v1/items?per_page=20
```

**Filter by Type (Weapons):**
```bash
GET /api/v1/items?type_code=WP
# Returns: All weapons
```

**Filter by Rarity:**
```bash
GET /api/v1/items?rarity=legendary
# Returns: Legendary items
```

**Get Single Item:**
```bash
GET /api/v1/items/staff-of-power
```

---

### 6. Classes (131 total)

**List All:**
```bash
GET /api/v1/classes?per_page=20
```

**Get Single Class:**
```bash
GET /api/v1/classes/wizard
```

**Get Class Spells:**
```bash
GET /api/v1/classes/wizard/spells
# Returns: All wizard spells
```

---

### 7. Feats (138 total)

**List All:**
```bash
GET /api/v1/feats?per_page=20
```

**Get Single Feat:**
```bash
GET /api/v1/feats/alert
```

---

## Tier 1: Static Reference Relationships

### 1. Spell Schools → Spells

**List Evocation Spells:**
```bash
GET /api/v1/spell-schools/evocation/spells
GET /api/v1/spell-schools/EV/spells  # By code
GET /api/v1/spell-schools/2/spells   # By ID
```

**Example Response:**
```json
{
  "data": [
    {"name": "Fireball", "level": 3},
    {"name": "Magic Missile", "level": 1},
    {"name": "Lightning Bolt", "level": 3}
  ],
  "meta": {"total": 62}
}
```

---

### 2. Damage Types → Spells/Items

**Spells Dealing Fire Damage:**
```bash
GET /api/v1/damage-types/fire/spells
GET /api/v1/damage-types/FIRE/spells  # By code
# Returns: 101 fire damage spells (Fireball, Burning Hands, etc.)
```

**Items Dealing Fire Damage:**
```bash
GET /api/v1/damage-types/fire/items
# Returns: Flametongue, Flame Blade, etc.
```

---

### 3. Conditions → Spells/Monsters

**Spells Inflicting Frightened:**
```bash
GET /api/v1/conditions/frightened/spells
# Returns: Fear, Wrathful Smite, etc.
```

**Monsters Inflicting Frightened:**
```bash
GET /api/v1/conditions/frightened/monsters
# Returns: Monsters with frightful presence, etc.
```

---

## Tier 2: Advanced Reverse Relationships

### 1. Ability Scores → Spells (by Saving Throw)

**Dexterity Save Spells:**
```bash
GET /api/v1/ability-scores/DEX/spells
GET /api/v1/ability-scores/dexterity/spells  # Case-insensitive
GET /api/v1/ability-scores/2/spells          # By ID
# Returns: 88 spells requiring DEX saves (Fireball, Lightning Bolt, etc.)
```

**Wisdom Save Spells:**
```bash
GET /api/v1/ability-scores/WIS/spells
# Returns: ~60 spells (Charm Person, Hold Person, etc.)
```

**Intelligence Save Spells (Rarest):**
```bash
GET /api/v1/ability-scores/INT/spells
# Returns: ~15 spells (Feeblemind, Phantasmal Force, etc.)
```

---

### 2. Proficiency Types → Classes/Races/Backgrounds

**Which Classes Are Proficient with Longswords?**
```bash
GET /api/v1/proficiency-types/Longsword/classes
GET /api/v1/proficiency-types/longsword/classes  # Case-insensitive
# Returns: Bard, Rogue
```

**Which Races Speak Elvish?**
```bash
GET /api/v1/proficiency-types/Elvish/races
# Returns: 11 Elf variants (Drow, High Elf, Wood Elf, etc.)
```

**Which Backgrounds Grant Stealth?**
```bash
GET /api/v1/proficiency-types/Stealth/backgrounds
# Returns: Criminal, Urchin, Spy
```

---

### 3. Languages → Races/Backgrounds

**Which Races Speak Common?**
```bash
GET /api/v1/languages/common/races
# Returns: 64 races (nearly universal)
```

**Which Races Speak Elvish?**
```bash
GET /api/v1/languages/elvish/races
# Returns: 11 Elf variants
```

**Which Backgrounds Teach Thieves' Cant?**
```bash
GET /api/v1/languages/thieves-cant/backgrounds
# Returns: Criminal, Urchin
```

---

### 4. Sizes → Races/Monsters

**Small Races:**
```bash
GET /api/v1/sizes/2/races
# Returns: 22 Small races (Halfling, Gnome, Kobold, etc.)
```

**Gargantuan Monsters (Boss Tier):**
```bash
GET /api/v1/sizes/6/monsters
# Returns: 16 Gargantuan monsters (Ancient Dragons, Kraken, Tarrasque)
```

**Medium Monsters (Most Common):**
```bash
GET /api/v1/sizes/3/monsters
# Returns: 280 Medium monsters (47% of all monsters)
```

---

## Search & Filtering

### Global Search (Meilisearch)

**Search All Entity Types:**
```bash
GET /api/v1/search?q=fire&types=spells,items
```

**Search Spells:**
```bash
GET /api/v1/spells?q=fire
# Returns: Fireball, Fire Bolt, Fire Storm, etc.
```

### Advanced Filtering (Meilisearch)

**Range Queries:**
```bash
GET /api/v1/spells?filter=level >= 1 AND level <= 3
```

**Logical Operators:**
```bash
GET /api/v1/spells?filter=school_code = EV OR school_code = C
```

**Combined Search + Filter:**
```bash
GET /api/v1/spells?q=fire&filter=level <= 3
```

---

## Pagination & Sorting

### Pagination

**Default (50 per page):**
```bash
GET /api/v1/spells
```

**Custom Page Size:**
```bash
GET /api/v1/spells?per_page=10
```

**Navigate Pages:**
```bash
GET /api/v1/spells?page=2&per_page=20
```

### Sorting

**Sort by Name:**
```bash
GET /api/v1/races?sort_by=name
```

**Sort by Speed:**
```bash
GET /api/v1/races?sort_by=speed
```

---

## Common Use Cases

### Character Building

**1. Find Races with Darkvision + Intelligence Bonus:**
```bash
GET /api/v1/races?has_darkvision=true&ability_bonus=INT
```

**2. Find Backgrounds Granting Stealth:**
```bash
GET /api/v1/proficiency-types/Stealth/backgrounds
```

**3. Find Classes That Can Learn Fireball:**
```bash
GET /api/v1/spells/fireball/classes
```

---

### Encounter Building

**1. Find CR 10 Monsters:**
```bash
GET /api/v1/monsters?cr=10
```

**2. Find Gargantuan Boss Monsters:**
```bash
GET /api/v1/sizes/6/monsters
```

**3. Find Monsters That Cast Fireball:**
```bash
GET /api/v1/monsters?spells=fireball
```

---

### Spell Optimization

**1. Which Spells Require DEX Saves?**
```bash
GET /api/v1/ability-scores/DEX/spells
```

**2. Find All Evocation Spells:**
```bash
GET /api/v1/spell-schools/evocation/spells
```

**3. Find Fire Damage Spells:**
```bash
GET /api/v1/damage-types/fire/spells
```

---

### Multiclass Planning

**1. Which Classes Get Longsword Proficiency?**
```bash
GET /api/v1/proficiency-types/Longsword/classes
```

**2. Which Races Speak Elvish?**
```bash
GET /api/v1/languages/elvish/races
```

---

## Response Structure

All endpoints return JSON with this structure:

```json
{
  "data": [
    {
      "id": 1,
      "slug": "fireball",
      "name": "Fireball",
      // ... entity fields
    }
  ],
  "links": {
    "first": "http://localhost:8080/api/v1/spells?page=1",
    "last": "http://localhost:8080/api/v1/spells?page=10",
    "prev": null,
    "next": "http://localhost:8080/api/v1/spells?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "path": "http://localhost:8080/api/v1/spells",
    "per_page": 50,
    "to": 50,
    "total": 477
  }
}
```

---

## Error Handling

**404 Not Found:**
```bash
GET /api/v1/spells/invalid-slug
```

**422 Validation Error:**
```bash
GET /api/v1/spells?per_page=999  # Exceeds max 100
```

---

## Performance Tips

1. **Use Pagination:** Default `per_page=50` is optimized
2. **Eager-Loading:** Single resource endpoints auto-load relationships
3. **Caching:** Static reference endpoints are highly cacheable
4. **Meilisearch:** Use `?q=` for typo-tolerant search

---

## OpenAPI Documentation

Auto-generated documentation available at:
```
http://localhost:8080/docs/api
```

---

## Summary Statistics

- **Total Endpoints:** 40+
- **Total Tests:** 1,169 passing
- **Total Records:**
  - Spells: 477
  - Monsters: 598
  - Races: 115
  - Items: 516
  - Classes: 131
  - Feats: 138
  - Backgrounds: 34

**All endpoints support:**
- ✅ Pagination (50 per page default, max 100)
- ✅ Dual routing (ID + slug/code/name)
- ✅ Eager-loading (prevent N+1 queries)
- ✅ CORS enabled
- ✅ OpenAPI documented

---

**🤖 Generated with [Claude Code](https://claude.com/claude-code)**

**Co-Authored-By:** Claude <noreply@anthropic.com>
