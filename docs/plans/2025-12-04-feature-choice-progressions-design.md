# Feature Choice Progressions Parser Design

**Issue:** #118 - Character Builder: Optional Feature Choices
**Date:** 2025-12-04
**Status:** Approved

## Problem

Optional features (Maneuvers, Metamagic, Infusions, etc.) have choice progressions that determine how many a character can know at each level. This data exists in ClassFeature descriptions as natural language but isn't being extracted.

Example: A Battle Master learns 3 maneuvers at level 3, then 2 more at levels 7, 10, and 15 (totaling 9 at level 15).

Currently:
- `class_counters` table stores level-by-level counter values
- Eldritch Invocations already has counters from XML `<counter>` elements
- Other feature types (Maneuvers, Metamagic, etc.) have NO counter data

## Solution

Parse choice progressions from ClassFeature descriptions and store them as counters in the existing `class_counters` table.

## Architecture

### New Component

**Trait:** `ParsesFeatureChoiceProgressions`
**Location:** `app/Services/Parsers/Concerns/ParsesFeatureChoiceProgressions.php`

### Pattern Types

**Type 1: Natural Language (Initial + Additional)**
```
"You learn three maneuvers... two additional at 7th, 10th, and 15th"
"You gain two Metamagic options... another one at 10th and 17th"
```

**Type 2: Embedded Tables**
```
Level | Known | Active
2nd | 4 | 2
6th | 6 | 3
```

**Type 3: "Should Know" Statements**
```
"You should know 2 elemental disciplines"
```

### Feature-to-Counter Mapping

| Feature Name Pattern | Counter Name | Subclass |
|---------------------|--------------|----------|
| `Combat Superiority (Battle Master)` | Maneuvers Known | Battle Master |
| `Additional Maneuvers (Battle Master)` | (adds to above) | Battle Master |
| `Metamagic` | Metamagic Known | null |
| `Infuse Item` | Infusions Known | null |
| `Rune Carver (Rune Knight)` | Runes Known | Rune Knight |
| `Arcane Shot (Arcane Archer)` | Arcane Shots Known | Arcane Archer |
| `Additional Arcane Shot Option` | (adds to above) | Arcane Archer |
| `Disciple of the Elements` | Elemental Disciplines Known | Way of the Four Elements |
| `Extra Elemental Discipline` | (adds to above) | Way of the Four Elements |
| `Fighting Style` | Fighting Styles Known | null |

### Integration

In `ClassXmlParser::parseClass()`:

```php
// Existing
$data['counters'] = $this->parseCounters($element);

// New - extract from feature descriptions
$choiceCounters = $this->parseFeatureChoiceProgressions($data['features']);
$data['counters'] = array_merge($data['counters'], $choiceCounters);
```

Counters flow through existing `ImportsClassCounters` trait - no importer changes needed.

### Regex Patterns

```php
// Pattern 1: Initial count
'/(?:learn|gain|choose|pick)\s+(one|two|three|four|five|six|seven|eight|four)\b/i'

// Pattern 2: Additional at levels
'/(?:additional|another)\s+(?:one|two)?\s*(?:\w+\s+)?(?:at|when you reach)\s+([\d,\s]+(?:st|nd|rd|th)[^.]*)/i'

// Pattern 3: Embedded table rows
'/(\d+)(?:st|nd|rd|th)\s*\|\s*(\d+)(?:\s*\|\s*\d+)?/m'

// Pattern 4: "Should know X"
'/should know\s+(\d+)\s+/i'
```

### Expected Output

After parsing, `class_counters` will contain:

```
Fighter (Battle Master) | Maneuvers Known | Level 3  | Value: 3
Fighter (Battle Master) | Maneuvers Known | Level 7  | Value: 5
Fighter (Battle Master) | Maneuvers Known | Level 10 | Value: 7
Fighter (Battle Master) | Maneuvers Known | Level 15 | Value: 9

Sorcerer | Metamagic Known | Level 3  | Value: 2
Sorcerer | Metamagic Known | Level 10 | Value: 3
Sorcerer | Metamagic Known | Level 17 | Value: 4

Artificer | Infusions Known | Level 2  | Value: 4
Artificer | Infusions Known | Level 6  | Value: 6
Artificer | Infusions Known | Level 10 | Value: 8
Artificer | Infusions Known | Level 14 | Value: 10
Artificer | Infusions Known | Level 18 | Value: 12
```

## Implementation Tasks

1. **Create trait** `ParsesFeatureChoiceProgressions`
   - Feature name matching logic
   - Regex patterns for each pattern type
   - Counter array generation

2. **Write unit tests** for pattern extraction
   - Test each pattern type with real feature descriptions
   - Test edge cases (missing data, malformed text)

3. **Integrate into ClassXmlParser**
   - Add trait use
   - Call after feature parsing
   - Merge with existing counters

4. **Re-import classes** to populate counters

5. **Verify data** matches expected progressions

## Testing Strategy

Unit tests in `tests/Unit/Parsers/` for:
- Each regex pattern individually
- Full feature description parsing
- Counter merging logic

Integration test: Import a class and verify counters match PHB rules.

## Future Considerations

- Homebrew support: Regex approach allows parsing custom content
- New feature types: Add mapping to feature-to-counter table
- Edge cases: Log warnings for unparseable features
