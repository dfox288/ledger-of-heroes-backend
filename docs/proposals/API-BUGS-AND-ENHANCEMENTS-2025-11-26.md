# API Bugs and Enhancements Analysis

**Date:** 2025-11-26
**Source:** Frontend proposals directory analysis
**Status:** Research Complete - Ready for Implementation

---

## Executive Summary

Analysis of frontend API enhancement proposals revealed **3 critical data bugs** and **numerous enhancement opportunities**. Root cause investigation identified issues in the import pipeline rather than source XML data.

---

## Critical Bugs (Phase 1)

### Bug 1: Cleric & Paladin Missing Core Data

**Symptoms:**
- `hit_die: 0` (should be 8 for Cleric, 10 for Paladin)
- `spellcasting_ability: null` (should be WIS for Cleric, CHA for Paladin)
- Empty proficiencies array

**Database Evidence:**
```
CLERIC:  hit_die=0, spellcasting_ability_id=null
PALADIN: hit_die=0, spellcasting_ability_id=null

All other base classes have correct data.
```

**Root Cause:** Import order + incomplete merge logic

1. **XML files processed alphabetically:**
   - `class-cleric-dmg.xml` → imported FIRST
   - `class-cleric-phb.xml` → imported second with `--merge`

2. **DMG file is a supplement** - contains only Death Domain subclass, NOT base class data:
   ```xml
   <!-- class-cleric-dmg.xml -->
   <class>
     <name>Cleric</name>
     <!-- NO <hd> tag! -->
     <!-- NO <spellAbility> tag! -->
     <autolevel level="1">
       <feature optional="YES">
         <name>Divine Domain: Death Domain</name>
   ```

3. **Parser returns `hit_die: 0`** because `(int) $element->hd` on missing element = 0

4. **Merge mode doesn't update base class:**
   - `ClassImporter::mergeSupplementData()` only merges subclasses
   - Does NOT update `hit_die` or `spellcasting_ability_id`

**Affected Files:**
- `app/Services/Importers/ClassImporter.php:324-363` - `mergeSupplementData()` method
- `app/Services/Parsers/ClassXmlParser.php:44` - parser line

**Fix Options:**
1. Sort files so PHB comes first (e.g., rename or sort pattern)
2. Skip importing classes with `hit_die: 0` in parser/strategy
3. Update merge logic to also merge base class attributes when incoming data has valid values (recommended)

---

### Bug 2: Acolyte & Sage Missing Languages

**Symptoms:**
- `languages` array is empty for Acolyte and Sage backgrounds
- Both should have "Two of your choice"

**XML Source (correct):**
```
• Languages: Two of your choice   (Acolyte, line 12)
• Languages: Two of your choice   (Sage, line 700)
```

**Root Cause:** Parser regex only handles "one" choice

**Parser code at `BackgroundXmlParser.php:154`:**
```php
// Check for "one of your choice" or similar choice patterns
if (preg_match('/one.*?choice/i', $languageText)) {
    return [[
        'language_id' => null,
        'is_choice' => true,
        'quantity' => 1,
    ]];
}
```

**Problem:** "Two of your choice" doesn't match `/one.*?choice/i`

**Fallback fails:** Falls through to `extractLanguagesFromText()` which expects patterns like "two other languages" not "Two of your choice"

**Fix:** Update regex to handle number words:
```php
if (preg_match('/(one|two|three|four|any)\s+(?:of\s+your\s+choice)/i', $languageText, $matches)) {
    $quantity = $this->wordToNumber($matches[1]);
    return [[
        'language_id' => null,
        'is_choice' => true,
        'quantity' => $quantity,
    ]];
}
```

---

## Infrastructure Analysis

### Existing Reusable Components

#### 1. `ConvertsWordNumbers` Trait
**Location:** `app/Services/Parsers/Concerns/ConvertsWordNumbers.php`

```php
protected function wordToNumber(string $word, int $default = 1): int
```

**Supports:** `a`, `an`, `one`, `two`, `three`, `four`, `five`, `six`, `seven`, `eight`, `nine`, `ten`, `any`, `several`

**Usage Status:**
| Parser | Status |
|--------|--------|
| `FeatXmlParser` | ✅ Uses trait |
| `RaceXmlParser` | ✅ Uses trait |
| `BackgroundXmlParser` | ✅ Has access via `MatchesLanguages` |
| `ClassXmlParser` | ❌ Has duplicate local method |

#### 2. `MatchesLanguages` Trait
**Location:** `app/Services/Parsers/Concerns/MatchesLanguages.php`

**Key method:** `extractLanguagesFromText()`

**Pattern coverage:**
| Pattern | Handled |
|---------|---------|
| "one extra language" | ✅ |
| "two other languages" | ✅ |
| "Common and Dwarvish" | ✅ |
| "Two of your choice" | ❌ |
| "One of your choice" | ❌ |

**Gap:** Pattern requires "languages" keyword after number word:
```php
$choicePattern = '/\b(one|two|three|four|any|a|an)\s+(extra|other|additional)?\s*languages?\b/i';
```

### Tab/Whitespace Handling

**Status:** No issues found

Bullet regex patterns use `\s*` which correctly matches tabs and spaces:
```php
preg_match('/• Languages:\s*(.+?)(?:\n|$)/m', $text, $matches)
```

This handles the tab-prefixed bullets in XML (`\t• Languages:`).

---

## Technical Debt Identified

### 1. Duplicate `convertWordToNumber` Method

**Location:** `ClassXmlParser.php:861-877`

**Issue:** Local method duplicates `ConvertsWordNumbers` trait functionality

**Fix:** Add `use ConvertsWordNumbers;` and replace calls to `$this->convertWordToNumber()` with `$this->wordToNumber()`

### 2. Inconsistent Choice Pattern Handling

**Issue:** Different parsers handle "X of your choice" patterns differently:
- `FeatXmlParser` - robust pattern with quantity extraction
- `RaceXmlParser` - multiple specific patterns
- `BackgroundXmlParser` - hardcoded "one" only

**Recommendation:** Create shared trait or update `MatchesLanguages` to handle common patterns.

---

## Enhancement Priority List

### High Priority (Significant Value)

| Enhancement | Entity | Description | Effort |
|-------------|--------|-------------|--------|
| Add `/item-types` endpoint | Items | Currently returns 404, needed for filter dropdowns | Low |
| Fix `item_type_code` filter | Items | Filter returns no results | Low |
| Add `proficiency_bonus` field | Monsters | Computed from CR, saves frontend calculation | Low |
| Add `senses` structured field | Monsters | darkvision, blindsight, passive perception | Medium |
| Add `is_legendary` boolean | Monsters | Quick filter for legendary creatures | Low |
| Populate base race data | Races | Elf/Dwarf base races have empty traits/modifiers | Medium |

### Medium Priority (Nice Improvements)

| Enhancement | Entity | Description | Effort |
|-------------|--------|-------------|--------|
| Material cost/consumed fields | Spells | Parse `material_cost_gp`, `material_consumed` | Medium |
| Area of effect structure | Spells | `type`, `size`, `unit` for AoE spells | Medium |
| Casting time structure | Spells | `casting_time_type` (action/bonus/reaction) | Low |
| Add `multiclass_requirements` | Classes | Ability score prerequisites per PHB p.163 | Medium |
| Add `spellcasting_type` enum | Classes | full/half/third/pact/none | Low |
| Separate `lair_actions` array | Monsters | Currently mixed in `legendary_actions` | Medium |
| Add `languages` array | Monsters | Currently in description text | Medium |
| Add `is_subrace` flag | Races | Simplifies frontend filtering | Low |
| Add `darkvision_range` field | Races | 60 vs 120 ft for filtering | Low |
| Add `fly_speed`/`swim_speed` | Races | Aarakocra, Triton need these | Low |
| Add `feature_name` top-level | Backgrounds | Quick access without parsing traits | Low |
| Add `is_half_feat` boolean | Feats | Filter "+1 ASI" feats | Low |
| Add `parent_feat_slug` | Feats | Group Resilient variants together | Low |
| Add `proficiency_category` | Items | simple_melee, martial_melee, etc. | Medium |
| Add `price_gp` computed | Items | Convenience field from `cost_cp` | Low |

### Low Priority (Nice-to-Have)

| Enhancement | Entity | Description | Effort |
|-------------|--------|-------------|--------|
| Searchable options in meta | Spells | Return filterable/sortable fields | Low |
| Minimal response mode | Spells | `?fields=card` for list views | Medium |
| Flattened `damage_types` array | Spells | Avoid parsing nested effects | Low |
| Reaction trigger field | Spells | Extract trigger from description | Low |
| Add ability modifiers | Monsters | Pre-computed from scores | Low |
| Add `cr_numeric` | Monsters | 0.25 instead of "1/4" | Low |
| Add `creature_subtypes` | Monsters | Parse "humanoid (goblinoid)" | Medium |
| Add `environments` | Monsters | From MM Appendix B | High |
| Standardize subrace names | Races | "High Elf" vs "High" | Medium |
| Add age/lifespan fields | Races | From trait descriptions | Low |
| Tool proficiency structure | Backgrounds | Better choice modeling | Medium |
| Extract alignment from ideals | Backgrounds | Parse "(Lawful)" tags | Medium |
| Add `feat_category` | Feats | combat/spellcasting/skill/etc. | Medium |
| Add `grants_spellcasting` | Feats | Filter feats that grant spells | Medium |
| Add `magic_bonus` field | Items | +1/+2/+3 for magic weapons | Medium |

---

## Recommended Implementation Order

### Phase 1 - Fix Critical Bugs (Immediate)

1. **Fix Cleric/Paladin data** - Update `mergeSupplementData()` to merge base class attributes
2. **Fix Background languages** - Update `parseLanguagesFromTraitText()` regex

### Phase 2 - Code Cleanup

3. **Refactor `ClassXmlParser`** - Use `ConvertsWordNumbers` trait
4. **Update `MatchesLanguages`** - Add "X of your choice" pattern

### Phase 3 - Quick Wins

5. Add `/item-types` lookup endpoint
6. Fix `item_type_code` filter
7. Add `is_legendary` boolean to monsters
8. Add `proficiency_bonus` to monsters

### Phase 4 - Structural Improvements

9. Populate base race traits
10. Add structured `senses` to monsters
11. Add casting time structure to spells

---

## Summary Table

| Issue | Source Data | Parser | Importer | Root Cause |
|-------|-------------|--------|----------|------------|
| Cleric/Paladin `hit_die: 0` | ✅ Correct in PHB | ✅ Works | ❌ Merge incomplete | Import order + merge logic |
| Acolyte/Sage languages empty | ✅ "Two of your choice" | ❌ Only handles "one" | ✅ Works | Regex too narrow |

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Services/Importers/ClassImporter.php` | Update `mergeSupplementData()` |
| `app/Services/Parsers/BackgroundXmlParser.php` | Update `parseLanguagesFromTraitText()` |
| `app/Services/Parsers/ClassXmlParser.php` | Use `ConvertsWordNumbers` trait |
| `app/Services/Parsers/Concerns/MatchesLanguages.php` | Add "X of your choice" pattern |
