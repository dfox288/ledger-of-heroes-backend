# API Bugs and Enhancements Analysis

**Date:** 2025-11-26
**Updated:** 2025-11-29
**Source:** Frontend proposals directory analysis
**Status:** ✅ ALL BUGS RESOLVED - Enhancement backlog only

---

## Executive Summary

Analysis of frontend API enhancement proposals revealed **3 critical data bugs** and **numerous enhancement opportunities**. Root cause investigation identified issues in the import pipeline rather than source XML data.

### Status Update (2025-11-26)

| Bug | Status |
|-----|--------|
| Cleric/Paladin missing core data | ✅ RESOLVED |
| Acolyte/Sage missing languages | ✅ RESOLVED |
| `/item-types` endpoint | ✅ Already exists at `/api/v1/lookups/item-types` |
| Items `type_code` filter | ✅ RESOLVED - was stale index, fixed with `import:all` |

**All bugs resolved.**

---

## Critical Bugs (Phase 1) - ALL RESOLVED ✅

### ~~Bug 1: Cleric & Paladin Missing Core Data~~ ✅ RESOLVED

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

### ~~Bug 2: Acolyte & Sage Missing Languages~~ ✅ RESOLVED

**Status:** Fixed - regex updated to handle `one|two|three|four|any` patterns.

**Verified Data:**
- Acolyte: `is_choice: true, quantity: 2` ✅
- Sage: `is_choice: true, quantity: 2` ✅

**Fix Applied:** `BackgroundXmlParser.php:155` now uses:
```php
if (preg_match('/\b(one|two|three|four|any)\b.*?\bchoice\b/i', $languageText, $choiceMatch)) {
    $quantity = $this->wordToNumber($choiceMatch[1]);
    // ...
}
```

---

## Active Bugs (Phase 2)

### Bug 3: Items `type_code` Filter Returns No Data 🔴

**Status:** OPEN - Meilisearch configuration issue

**Symptoms:**
- Filter `type_code=M` returns correct total (510) but **zero data items**
- Same pattern works correctly on Spells and Monsters endpoints

**Reproduction:**
```bash
# Items - BUG: total correct, data empty
curl "http://localhost:8080/api/v1/items?filter=type_code=M&per_page=3"
# Response: { "meta": { "total": 510 }, "data": [] }

curl "http://localhost:8080/api/v1/items?filter=type_code=R&per_page=3"
# Response: { "meta": { "total": 187 }, "data": [] }

# Spells - WORKS correctly
curl "http://localhost:8080/api/v1/spells?filter=level=3&per_page=3"
# Response: { "meta": { "total": 59 }, "data": [3 items] } ✅

# Monsters - WORKS correctly
curl "http://localhost:8080/api/v1/monsters?filter=challenge_rating=1&per_page=3"
# Response: { "meta": { "total": 54 }, "data": [3 items] } ✅
```

**Analysis:**
- Field name `type_code` IS correct (confirmed filterable, returns accurate totals)
- Count query succeeds but data retrieval fails
- Issue is specific to Items Meilisearch index
- Spells and Monsters use same filter pattern successfully

**Likely Causes:**
1. Items Meilisearch index needs rebuild (`php artisan scout:flush` + `scout:import`)
2. `type_code` not in `filterableAttributes` for data retrieval (but IS for count)
3. Index schema mismatch between count and data queries

**Suggested Fix:**
```bash
# Try reindexing Items
php artisan scout:flush "App\Models\Item"
php artisan scout:import "App\Models\Item"

# Or check Meilisearch filterable attributes
curl http://localhost:7700/indexes/items/settings/filterable-attributes
```

**Impact:** Frontend cannot filter items by type (weapons, armor, etc.)

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

| Enhancement | Entity | Description | Status |
|-------------|--------|-------------|--------|
| ~~Add `/item-types` endpoint~~ | Items | ✅ EXISTS at `/api/v1/lookups/item-types` (16 types) | ✅ Done |
| ~~Fix `type_code` filter~~ | Items | ✅ Fixed - was stale Meilisearch index (re-imported) | ✅ Done |
| ~~Add `proficiency_bonus` field~~ | Monsters | Computed from CR, saves frontend calculation | ✅ Done |
| ~~Add `senses` structured field~~ | Monsters | darkvision, blindsight, passive perception | ✅ Done |
| ~~Add `is_legendary` boolean~~ | Monsters | Quick filter for legendary creatures | ✅ Done |
| Populate base race data | Races | Elf/Dwarf base races have empty traits/modifiers | Pending |

### Medium Priority (Nice Improvements)

| Enhancement | Entity | Description | Status |
|-------------|--------|-------------|--------|
| Material cost/consumed fields | Spells | Parse `material_cost_gp`, `material_consumed` | Pending |
| Area of effect structure | Spells | `type`, `size`, `unit` for AoE spells | Pending |
| ~~Casting time structure~~ | Spells | `casting_time_type` (action/bonus/reaction) | ✅ Done |
| ~~Add `multiclass_requirements`~~ | Classes | Ability score prerequisites per PHB p.163 | ✅ Done |
| ~~Add `spellcasting_type` enum~~ | Classes | full/half/third/pact/none | ✅ Done |
| ~~Separate `lair_actions` array~~ | Monsters | Now filtered by `is_lair_action` flag | ✅ Done |
| Add `languages` array | Monsters | Currently in description text | Pending |
| ~~Add `is_subrace` flag~~ | Races | Simplifies frontend filtering | ✅ Done |
| Add `darkvision_range` field | Races | 60 vs 120 ft for filtering | Pending |
| Add `fly_speed`/`swim_speed` | Races | Aarakocra, Triton need these | Pending |
| Add `feature_name` top-level | Backgrounds | Quick access without parsing traits | Pending |
| Add `is_half_feat` boolean | Feats | Filter "+1 ASI" feats | Pending |
| Add `parent_feat_slug` | Feats | Group Resilient variants together | Pending |
| Add `proficiency_category` | Items | simple_melee, martial_melee, etc. | Pending |
| Add `price_gp` computed | Items | Convenience field from `cost_cp` | Pending |

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

### ~~Phase 1 - Fix Critical Bugs~~ ✅ COMPLETE

1. ~~**Fix Cleric/Paladin data**~~ ✅ Done
2. ~~**Fix Background languages**~~ ✅ Done

### Phase 2 - Code Cleanup (Optional)

3. **Refactor `ClassXmlParser`** - Use `ConvertsWordNumbers` trait (tech debt)
4. ~~**Update `MatchesLanguages`**~~ ✅ Already handles patterns

### Phase 3 - Quick Wins ✅ COMPLETE

5. ~~Add `/item-types` lookup endpoint~~ ✅ Already exists at `/api/v1/lookups/item-types`
6. ~~Fix `item_type_code` filter~~ ✅ Works - use `type_code` field name
7. ~~Add `is_legendary` boolean to monsters~~ ✅ Done (computed accessor)
8. ~~Add `proficiency_bonus` to monsters~~ ✅ Done (computed accessor)
9. ~~Add `casting_time_type` to spells~~ ✅ Done (computed accessor)
10. ~~Add `is_subrace` boolean to races~~ ✅ Done (computed accessor)

### Phase 4 - Structural Improvements ✅ COMPLETE

11. Populate base race traits - Pending (low priority)
12. ~~Add structured `senses` to monsters~~ ✅ Done (2025-11-29) - 519 monster senses, 45 race senses
13. ~~Add casting time structure to spells~~ ✅ Done (`casting_time_type` accessor)

---

## Summary Table

| Issue | Status | Resolution |
|-------|--------|------------|
| Cleric/Paladin `hit_die: 0` | ✅ RESOLVED | `mergeSupplementData()` updated to merge base class attributes |
| Acolyte/Sage languages empty | ✅ RESOLVED | Regex updated to handle `one\|two\|three\|four\|any` patterns |
| Items `type_code` filter | ✅ RESOLVED | Was stale Meilisearch index - fixed with `import:all` |

---

## Files Modified

| File | Change | Status |
|------|--------|--------|
| `app/Services/Importers/ClassImporter.php` | Updated `mergeSupplementData()` | ✅ Done |
| `app/Services/Parsers/BackgroundXmlParser.php` | Updated `parseLanguagesFromTraitText()` | ✅ Done |
| `app/Services/Parsers/ClassXmlParser.php` | Use `ConvertsWordNumbers` trait | 📋 Tech debt |
| `app/Services/Parsers/Concerns/MatchesLanguages.php` | Add "X of your choice" pattern | ✅ Not needed |
