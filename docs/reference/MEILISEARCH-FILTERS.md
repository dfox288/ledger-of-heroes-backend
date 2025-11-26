# Meilisearch Filter Guide

Complete guide to using advanced filtering in the D&D Compendium API.

---

## Overview

The API supports powerful filtering using [Meilisearch's native filter syntax](https://www.meilisearch.com/docs/reference/api/search#filter). This allows you to build complex queries with comparison operators, logical operations, and ranges.

**Key Benefits:**
- ⚡ **Fast** - Queries execute in < 100ms even with complex filters
- 🔍 **Flexible** - Combine search with filtering
- 📊 **Powerful** - Range queries, logical operators, array matching
- 🎯 **Precise** - SQL-like syntax developers already know

---

## Basic Syntax

### Filter Parameter

Add the `filter` query parameter to any entity endpoint:

```
GET /api/v1/spells?filter=<expression>
GET /api/v1/items?filter=<expression>
GET /api/v1/races?filter=<expression>
```

**Example:**
```bash
curl "http://localhost:8080/api/v1/spells?filter=level <= 3"
```

---

## Supported Operators

### Comparison Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `=` | Equals | `level = 3` |
| `!=` | Not equals | `concentration != true` |
| `>` | Greater than | `level > 5` |
| `<` | Less than | `level < 3` |
| `>=` | Greater than or equal | `level >= 1` |
| `<=` | Less than or equal | `level <= 9` |
| `TO` | Range (inclusive) | `level 1 TO 3` |

### Logical Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `AND` | Both conditions true | `level >= 1 AND level <= 3` |
| `OR` | Either condition true | `school_code = EV OR school_code = C` |
| `NOT` | Negation | `NOT concentration = true` |

### Array Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `IN` | Value in array | `class_slugs IN [wizard, sorcerer]` |
| `NOT IN` | Value not in array | `source_codes NOT IN [DMG, MM]` |

---

## Filter Examples by Entity

### Spells

**Filterable Attributes:**
- `id` - Spell ID
- `level` - Spell level (0-9)
- `school_code` - School code (EV, C, A, etc.)
- `school_name` - School name (Evocation, Conjuration, etc.)
- `concentration` - Requires concentration (true/false)
- `ritual` - Can be cast as ritual (true/false)
- `source_codes` - Source book codes (array)
- `class_slugs` - Class slugs (array)

**Examples:**

```bash
# Cantrips only
GET /api/v1/spells?filter=level = 0

# Level 1-3 spells
GET /api/v1/spells?filter=level >= 1 AND level <= 3
GET /api/v1/spells?filter=level 1 TO 3

# Evocation spells
GET /api/v1/spells?filter=school_code = EV

# Non-concentration spells
GET /api/v1/spells?filter=concentration = false

# Ritual spells
GET /api/v1/spells?filter=ritual = true

# Wizard or Sorcerer spells
GET /api/v1/spells?filter=class_slugs IN [wizard, sorcerer]

# Low-level Evocation or Conjuration spells without concentration
GET /api/v1/spells?filter=(school_code = EV OR school_code = C) AND level <= 3 AND concentration = false

# Spells from Player's Handbook
GET /api/v1/spells?filter=source_codes IN [PHB]
```

### Items

**Filterable Attributes:**
- `type_code` - Item type code
- `rarity` - Rarity (common, uncommon, rare, etc.)
- `is_magic` - Is magical item (true/false)
- `requires_attunement` - Requires attunement (true/false)
- `source_codes` - Source book codes (array)

**Examples:**

```bash
# Magic items only
GET /api/v1/items?filter=is_magic = true

# Uncommon or rare items
GET /api/v1/items?filter=rarity IN [uncommon, rare]

# Magic items that don't require attunement
GET /api/v1/items?filter=is_magic = true AND requires_attunement = false

# Weapons (type code W)
GET /api/v1/items?filter=type_code = W
```

### Races

**Filterable Attributes:**
- `size_code` - Size code (S, M, L, etc.)
- `speed` - Base walking speed
- `source_codes` - Source book codes (array)
- `is_subrace` - Is a subrace (true/false)

**Examples:**

```bash
# Medium-sized races
GET /api/v1/races?filter=size_code = M

# Fast races (speed >= 35)
GET /api/v1/races?filter=speed >= 35

# Base races only (not subraces)
GET /api/v1/races?filter=is_subrace = false
```

### Classes

**Filterable Attributes:**
- `hit_die` - Hit die size (6, 8, 10, 12)
- `spellcasting_ability` - Spellcasting ability code
- `source_codes` - Source book codes (array)
- `is_subclass` - Is a subclass (true/false)

**Examples:**

```bash
# High hit die classes (d10 or d12)
GET /api/v1/classes?filter=hit_die >= 10

# Base classes only (not subclasses)
GET /api/v1/classes?filter=is_subclass = false

# Spellcasting classes
GET /api/v1/classes?filter=spellcasting_ability != null
```

### Monsters

**Filterable Attributes:**
- `challenge_rating` - Challenge rating (string: "0", "1/8", "1/4", "1/2", "1"-"30")
- `type` - Monster type (dragon, fiend, undead, etc.)
- `size_code` - Size code (T, S, M, L, H, G)
- `alignment` - Alignment string
- `armor_class` - Armor class (integer)
- `hit_points_average` - Average hit points (integer)
- `experience_points` - XP value (integer)
- `spell_slugs` - Array of spell slugs the monster can cast
- `tag_slugs` - Array of tag slugs (creature_type, immunities, etc.)
- `source_codes` - Source book codes (array)

**Examples:**

```bash
# Dragons only
GET /api/v1/monsters?filter=type = dragon

# Challenge Rating 10-15
GET /api/v1/monsters?filter=challenge_rating IN [10, 11, 12, 13, 14, 15]

# High AC tanks (AC 18+, HP 100+)
GET /api/v1/monsters?filter=armor_class >= 18 AND hit_points_average >= 100

# Boss monsters (CR 20+)
GET /api/v1/monsters?filter=challenge_rating IN [20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30]
```

**Tag-Based Filtering (Advanced):**

```bash
# All fiends
GET /api/v1/monsters?filter=tag_slugs IN [fiend]

# Fire-immune creatures
GET /api/v1/monsters?filter=tag_slugs IN [fire-immune]

# Fiends OR undead (multiple tags, OR logic)
GET /api/v1/monsters?filter=tag_slugs IN [fiend, undead]

# Fire-immune dragons (combining tag + type)
GET /api/v1/monsters?filter=tag_slugs IN [fire-immune] AND type = dragon

# High CR fiends (combining tag + CR)
GET /api/v1/monsters?filter=tag_slugs IN [fiend] AND challenge_rating IN [15, 16, 17, 18, 19, 20]

# Poison-immune AND magic-resistant creatures
GET /api/v1/monsters?filter=tag_slugs IN [poison-immune] AND tag_slugs IN [magic-resistance]
```

**Spell-Based Filtering (Advanced):**

```bash
# Monsters that can cast Fireball
GET /api/v1/monsters?filter=spell_slugs IN [fireball]

# Monsters with Fireball OR Lightning Bolt
GET /api/v1/monsters?filter=spell_slugs IN [fireball, lightning-bolt]

# Spellcasting dragons
GET /api/v1/monsters?filter=type = dragon AND spell_slugs IN [fireball, polymorph, teleport]

# High CR spellcasters with crowd control
GET /api/v1/monsters?filter=challenge_rating >= 10 AND spell_slugs IN [hold-person, dominate-person]
```

**Use Cases:**
- **Encounter Building:** Find balanced enemies for your party's level
- **Themed Campaigns:** All fire-themed enemies, all undead necromancers
- **Spell Tracking:** Identify monsters that can counterspell, teleport, or summon
- **DM Preparation:** Find monsters with specific abilities or immunities

### Backgrounds

**Filterable Attributes:**
- `source_codes` - Source book codes (array)
- `tag_slugs` - Array of tag slugs for categorization

**Examples:**

```bash
# Backgrounds from Player's Handbook
GET /api/v1/backgrounds?filter=source_codes IN [PHB]

# Urban-themed backgrounds
GET /api/v1/backgrounds?filter=tag_slugs IN [urban]
```

### Feats

**Filterable Attributes:**
- `source_codes` - Source book codes (array)
- `tag_slugs` - Array of tag slugs (combat, spellcasting, etc.)

**Examples:**

```bash
# Combat-focused feats
GET /api/v1/feats?filter=tag_slugs IN [combat]

# Feats from Xanathar's Guide
GET /api/v1/feats?filter=source_codes IN [XGE]

# Spellcasting feats
GET /api/v1/feats?filter=tag_slugs IN [spellcasting]
```

---

## Combining Search with Filters

You can combine full-text search (`q` parameter) with filtering:

```bash
# Search for "fire" spells, level 3 or below
GET /api/v1/spells?q=fire&filter=level <= 3

# Search for "longsword" in items, magic only
GET /api/v1/items?q=longsword&filter=is_magic = true

# Search for "dwarf" races, medium size only
GET /api/v1/races?q=dwarf&filter=size_code = M
```

**How it works:**
1. Meilisearch performs typo-tolerant search on `q` parameter
2. Results are filtered by `filter` expression
3. Combined results returned in < 100ms

---

## Advanced Patterns

### Range Queries

```bash
# Spells level 1-3 (inclusive)
GET /api/v1/spells?filter=level 1 TO 3
GET /api/v1/spells?filter=level >= 1 AND level <= 3  # Equivalent

# High-level spells (7+)
GET /api/v1/spells?filter=level >= 7

# Races with speed 30-40
GET /api/v1/races?filter=speed 30 TO 40
```

### Complex Logical Expressions

Use parentheses to group conditions:

```bash
# (Evocation OR Conjuration) AND low-level AND no concentration
GET /api/v1/spells?filter=(school_code = EV OR school_code = C) AND level <= 3 AND concentration = false

# Magic items that are either uncommon OR (rare AND don't require attunement)
GET /api/v1/items?filter=rarity = uncommon OR (rarity = rare AND requires_attunement = false)
```

### Array Matching

```bash
# Spells available to multiple classes
GET /api/v1/spells?filter=class_slugs IN [wizard, sorcerer, warlock]

# Items from specific sourcebooks
GET /api/v1/items?filter=source_codes IN [PHB, DMG, XGE]

# Exclude certain sources
GET /api/v1/spells?filter=source_codes NOT IN [UA]
```

---

## Pagination & Sorting

Filters work seamlessly with pagination and sorting:

```bash
# First page of low-level spells
GET /api/v1/spells?filter=level <= 3&page=1&per_page=20

# Sort filtered results by level descending
GET /api/v1/spells?filter=school_code = EV&sort_by=level&sort_direction=desc

# Paginate and sort
GET /api/v1/spells?filter=concentration = false&sort_by=name&sort_direction=asc&page=2&per_page=15
```

---

## Error Handling

### Invalid Filter Syntax

If the filter expression is malformed, you'll receive a 422 error:

```json
{
  "message": "Invalid filter syntax",
  "error": "Attribute `invalid_field` is not filterable. Available filterable attributes are: `level, school_code, ...`"
}
```

### Validation Errors

If the filter exceeds max length (1000 chars):

```json
{
  "message": "The filter field must not be greater than 1000 characters.",
  "errors": {
    "filter": ["The filter field must not be greater than 1000 characters."]
  }
}
```

---

## Performance Tips

1. **Use specific filters** - More specific = faster results
   ```bash
   # Good - very specific
   GET /api/v1/spells?filter=level = 3 AND school_code = EV

   # Less optimal - returns many results
   GET /api/v1/spells?filter=level <= 9
   ```

2. **Filter before searching** when possible
   ```bash
   # Better - filter first, then search
   GET /api/v1/spells?filter=level <= 3&q=fire
   ```

3. **Use pagination** for large result sets
   ```bash
   GET /api/v1/spells?filter=concentration = false&per_page=20
   ```

4. **Combine filters** instead of multiple requests
   ```bash
   # One request with complex filter
   GET /api/v1/spells?filter=level <= 3 AND school_code = EV

   # Instead of two separate requests
   ```

---

## Backwards Compatibility

**Old filter parameters still work:**

```bash
# Old way (still supported for backwards compatibility)
GET /api/v1/spells?level=3&school=1&concentration=false

# New way (more powerful)
GET /api/v1/spells?filter=level = 3 AND school_code = EV AND concentration = false
```

**When to use which:**
- **Old parameters** - Simple, single-condition filters
- **New `filter` parameter** - Complex queries, ranges, logical operations

---

## Reference

### Meilisearch Documentation
- [Filter Syntax](https://www.meilisearch.com/docs/reference/api/search#filter)
- [Filter Expression Reference](https://www.meilisearch.com/docs/learn/filtering_and_sorting/filter_expression_reference)

### API Documentation
- Live API Docs: `http://localhost:8080/docs/api`
- OpenAPI Spec: `http://localhost:8080/docs/api.json`

---

## Examples by Use Case

### Character Creator Scenarios

**1. Show spells I can learn at my level**
```bash
# Level 5 Wizard - show level 1-3 spells available to wizard
GET /api/v1/spells?filter=level <= 3 AND class_slugs IN [wizard]
```

**2. Find feats I qualify for**
```bash
# [Future enhancement - once feat prerequisites are filterable]
GET /api/v1/feats?filter=...
```

**3. Browse equipment I can afford**
```bash
# [Future enhancement - once cost filtering is added]
GET /api/v1/items?filter=cost_cp <= 5000
```

### Compendium Browsing

**1. Browse high-level combat spells**
```bash
GET /api/v1/spells?filter=level >= 6 AND school_code = EV&sort_by=level&sort_direction=desc
```

**2. Find all ritual spells for my class**
```bash
GET /api/v1/spells?filter=ritual = true AND class_slugs IN [cleric]
```

**3. Explore uncommon magic items**
```bash
GET /api/v1/items?filter=is_magic = true AND rarity = uncommon&sort_by=name
```

---

**Last Updated:** 2025-11-23
**API Version:** v1
