# Show Endpoint Completeness Analysis

**Date:** 2025-11-23
**Purpose:** Analyze all show endpoints to ensure they expose ALL available model relationships

---

## Race Model

### Available Relationships (11 total)
1. ✅ `size` - BelongsTo
2. ✅ `parent` - BelongsTo (with full nested relationships)
3. ✅ `subraces` - HasMany
4. ✅ `proficiencies` - MorphMany (with nested: skill.abilityScore, abilityScore)
5. ✅ `traits` - MorphMany (with nested: randomTables.entries)
6. ✅ `modifiers` - MorphMany (with nested: abilityScore, skill, damageType)
7. ✅ `sources` - MorphMany (with nested: source)
8. ✅ `languages` - MorphMany (with nested: language)
9. ✅ `conditions` - MorphMany (with nested: condition)
10. ✅ `spells` - MorphMany (with nested: spell, abilityScore)
11. ✅ `tags` - HasTags (Spatie Tags)

### Currently in SHOW_RELATIONSHIPS
```php
'size',
'sources.source',
'parent.size',
'parent.sources.source',
'parent.proficiencies.skill.abilityScore',
'parent.proficiencies.abilityScore',
'parent.traits.randomTables.entries',
'parent.modifiers.abilityScore',
'parent.modifiers.skill',
'parent.modifiers.damageType',
'parent.languages.language',
'parent.conditions.condition',
'parent.spells.spell',
'parent.spells.abilityScore',
'parent.tags',
'subraces',
'proficiencies.skill.abilityScore',
'proficiencies.abilityScore',
'traits.randomTables.entries',
'modifiers.abilityScore',
'modifiers.skill',
'modifiers.damageType',
'languages.language',
'conditions.condition',
'spells.spell',
'spells.abilityScore',
'tags',
```

### Status
✅ **COMPLETE** - All 11 relationships exposed with proper nesting

---

## CharacterClass Model

### Available Relationships (10 total)
1. ✅ `spellcastingAbility` - BelongsTo
2. ✅ `parentClass` - BelongsTo (with full nested relationships)
3. ✅ `subclasses` - HasMany
4. ✅ `features` - HasMany
5. ✅ `levelProgression` - HasMany
6. ✅ `counters` - HasMany
7. ✅ `proficiencies` - MorphMany (with nested: proficiencyType, skill.abilityScore, abilityScore)
8. ✅ `traits` - MorphMany (with nested: randomTables.entries)
9. ✅ `spells` - BelongsToMany
10. ✅ `sources` - MorphMany (with nested: source)
11. ✅ `equipment` - MorphMany
12. ✅ `tags` - HasTags

### Currently in SHOW_RELATIONSHIPS
```php
'spellcastingAbility',
'parentClass.spellcastingAbility',
'parentClass.proficiencies.proficiencyType',
'parentClass.proficiencies.skill.abilityScore',
'parentClass.proficiencies.abilityScore',
'parentClass.traits.randomTables.entries',
'parentClass.sources.source',
'parentClass.features',
'parentClass.levelProgression',
'parentClass.counters',
'parentClass.equipment',
'parentClass.tags',
'subclasses',
'proficiencies.proficiencyType',
'proficiencies.skill.abilityScore',
'proficiencies.abilityScore',
'traits.randomTables.entries',
'sources.source',
'features',
'levelProgression',
'counters',
'equipment',
'subclasses.features',
'subclasses.counters',
'tags',
```

### Missing Relationships
❌ **MISSING**: `spells` - BelongsToMany (class spell list)

### Recommended Addition
```php
'spells', // Add to SHOW_RELATIONSHIPS
```

---

## Spell Model

### Available Relationships (8 total)
1. ✅ `spellSchool` - BelongsTo
2. ✅ `classes` - BelongsToMany
3. ✅ `effects` - HasMany (with nested: damageType)
4. ✅ `sources` - MorphMany (with nested: source)
5. ✅ `tags` - HasTags
6. ✅ `savingThrows` - HasMany
7. ✅ `randomTables` - MorphMany (with nested: entries)
8. ❌ `monsters` - MorphToMany (monsters that know this spell)
9. ❌ `items` - MorphToMany (items that grant this spell)
10. ❌ `races` - MorphToMany (races that know this spell innately)

### Currently in SHOW_RELATIONSHIPS
```php
'spellSchool',
'sources.source',
'effects.damageType',
'classes',
'tags',
'savingThrows',
'randomTables.entries',
```

### Missing Relationships
❌ **MISSING**:
- `monsters` - MorphToMany (reverse relationship - which monsters know this spell)
- `items` - MorphToMany (reverse relationship - which items grant this spell)
- `races` - MorphToMany (reverse relationship - which races know this spell)

### Recommended Additions
```php
'monsters',  // Monsters that can cast this spell
'items',     // Items that grant this spell (scrolls, wands, etc.)
'races',     // Races with innate access to this spell
```

**Note:** These are "reverse" relationships useful for "Who can use Fireball?" queries.

---

## Monster Model

### Available Relationships (11 total)
1. ✅ `size` - BelongsTo
2. ✅ `traits` - HasMany
3. ✅ `actions` - HasMany
4. ❌ `legendaryActions` - HasMany
5. ✅ `spellcasting` - HasOne
6. ✅ `spells` - MorphToMany (via entitySpells)
7. ✅ `modifiers` - MorphMany (with nested: abilityScore, skill, damageType)
8. ✅ `conditions` - MorphToMany
9. ✅ `sources` - MorphMany (with nested: source)
10. ✅ `tags` - HasTags
11. ❌ `reactions` - HasMany (if exists)

### Currently in SHOW_RELATIONSHIPS
```php
'size',
'traits',
'actions',
'legendaryActions',
'spellcasting',
'entitySpells',  // Using pivot table name instead of relationship
'sources.source',
'modifiers.abilityScore',
'modifiers.skill',
'modifiers.damageType',
'tags',
```

### Missing Relationships
❌ **POTENTIALLY MISSING**: `conditions` - MorphToMany

### Issues to Fix
⚠️ **ISSUE**: Using `'entitySpells'` instead of `'spells'` - should use the actual relationship name

### Recommended Changes
```php
'spells',      // Use relationship name, not pivot table
'conditions',  // Add conditions relationship
```

---

## Item Model

### Available Relationships (10 total)
1. ✅ `itemType` - BelongsTo
2. ✅ `damageType` - BelongsTo
3. ✅ `properties` - BelongsToMany
4. ✅ `abilities` - HasMany
5. ✅ `sources` - MorphMany (with nested: source)
6. ✅ `proficiencies` - MorphMany (with nested: proficiencyType)
7. ✅ `modifiers` - MorphMany (with nested: abilityScore, skill, damageType)
8. ✅ `prerequisites` - MorphMany (with nested: prerequisite)
9. ✅ `spells` - MorphToMany (via entitySpells)
10. ❌ `randomTables` - MorphMany (with nested: entries)
11. ❌ `savingThrows` - HasMany
12. ✅ `tags` - HasTags

### Currently in SHOW_RELATIONSHIPS
```php
'itemType',
'damageType',
'properties',
'abilities',
'randomTables.entries',
'sources.source',
'proficiencies.proficiencyType',
'modifiers.abilityScore',
'modifiers.skill',
'modifiers.damageType',
'prerequisites.prerequisite',
'entitySpells',  // Using pivot table name
'tags',
```

### Issues to Fix
⚠️ **ISSUE**: Using `'entitySpells'` instead of `'spells'` - should use the actual relationship name

### Missing Relationships
❌ **MISSING**: `savingThrows` - HasMany (items that require saves, like grenades)

### Recommended Changes
```php
'spells',         // Use relationship name, not pivot table
'savingThrows',   // Add saving throws
```

---

## Background Model

### Available Relationships (6 total)
1. ✅ `traits` - MorphMany (with nested: randomTables.entries)
2. ✅ `proficiencies` - MorphMany (with nested: skill.abilityScore, proficiencyType)
3. ✅ `sources` - MorphMany (with nested: source)
4. ❌ `equipment` - MorphMany
5. ✅ `languages` - MorphMany (with nested: language)
6. ✅ `tags` - HasTags

### Currently in SHOW_RELATIONSHIPS
```php
'sources.source',
'traits.randomTables.entries',
'proficiencies.skill.abilityScore',
'proficiencies.proficiencyType',
'languages.language',
'tags',
```

### Missing Relationships
❌ **MISSING**: `equipment` - MorphMany (starting equipment for backgrounds)

### Recommended Addition
```php
'equipment',  // Starting equipment
```

---

## Feat Model

### Available Relationships (5 total)
1. ✅ `sources` - MorphMany (with nested: source)
2. ✅ `modifiers` - MorphMany (with nested: abilityScore, skill, damageType)
3. ✅ `proficiencies` - MorphMany (with nested: skill.abilityScore, proficiencyType)
4. ✅ `conditions` - MorphMany
5. ✅ `prerequisites` - MorphMany (with nested: prerequisite)
6. ✅ `tags` - HasTags

### Currently in SHOW_RELATIONSHIPS
```php
'sources.source',
'modifiers.abilityScore',
'modifiers.skill',
'modifiers.damageType',
'proficiencies.skill.abilityScore',
'proficiencies.proficiencyType',
'conditions',
'prerequisites.prerequisite',
'tags',
```

### Status
✅ **COMPLETE** - All 6 relationships exposed with proper nesting

---

## Summary Table

| Entity | Total Relationships | Currently Exposed | Missing | Status |
|--------|-------------------|-------------------|---------|--------|
| **Race** | 11 | 11 | 0 | ✅ Complete |
| **Class** | 12 | 11 | 1 (`spells`) | ⚠️ Missing 1 |
| **Spell** | 10 | 7 | 3 (`monsters`, `items`, `races`) | ⚠️ Missing 3 reverse |
| **Monster** | 11 | 10 | 1 (`conditions`) | ⚠️ Missing 1 |
| **Item** | 12 | 11 | 1 (`savingThrows`) | ⚠️ Missing 1 |
| **Background** | 6 | 5 | 1 (`equipment`) | ⚠️ Missing 1 |
| **Feat** | 6 | 6 | 0 | ✅ Complete |

---

## Issues to Fix

### 1. Incorrect Relationship Names
**Problem:** Using pivot table names instead of relationship method names

**Affected:**
- `MonsterSearchService`: Using `'entitySpells'` → Should be `'spells'`
- `ItemSearchService`: Using `'entitySpells'` → Should be `'spells'`

**Fix:**
```php
// MonsterSearchService SHOW_RELATIONSHIPS
'spells',  // Instead of 'entitySpells'

// ItemSearchService SHOW_RELATIONSHIPS
'spells',  // Instead of 'entitySpells'
```

### 2. Missing Direct Relationships

**CharacterClass:**
- Add `'spells'` - Class spell list (important for character builders)

**Spell:**
- Add `'monsters'` - Which monsters can cast this spell
- Add `'items'` - Which items grant this spell
- Add `'races'` - Which races have innate access

**Monster:**
- Add `'conditions'` - Monster immunities/resistances

**Item:**
- Add `'savingThrows'` - Items requiring saves (grenades, etc.)

**Background:**
- Add `'equipment'` - Starting equipment

---

## Recommendations

### Priority 1: Fix Incorrect Names (Breaking if relationships don't work)
1. Change `'entitySpells'` → `'spells'` in Monster and Item services

### Priority 2: Add Missing Direct Relationships (High value)
1. Add `'spells'` to CharacterClass (spell lists)
2. Add `'equipment'` to Background (starting gear)
3. Add `'conditions'` to Monster (immunities/resistances)
4. Add `'savingThrows'` to Item (save-requiring items)

### Priority 3: Add Reverse Relationships (Optional, for "Who has X?" queries)
1. Add `'monsters'`, `'items'`, `'races'` to Spell
   - **Use Case:** "Which monsters can cast Fireball?"
   - **Use Case:** "Which items grant Wish?"
   - **Use Case:** "Which races get Misty Step innately?"

---

## Circular Relationship Handling

**No circular issues identified** - Parent relationships properly handle recursion:
- Race parent doesn't load subraces (would be circular)
- Class parent doesn't load subclasses (would be circular)

**Current approach is correct:** Load parent with all its data, but don't recurse back to children.

---

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
