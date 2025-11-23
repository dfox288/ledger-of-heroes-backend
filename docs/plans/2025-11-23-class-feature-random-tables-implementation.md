# Implementation Plan: Class Feature Random Tables

**Date:** 2025-11-23
**Status:** ✅ COMPLETE (Already Implemented)
**Completed:** November 2025 (prior to 2025-11-23)
**Estimated Effort:** 1-1.5 hours
**Priority:** Medium

---

## Executive Summary

**✅ IMPLEMENTATION COMPLETE** - This feature was already implemented prior to this plan being written!

All random table and reference table data from class features is stored using the `random_tables` polymorphic infrastructure. Character builders and DM tools can access structured roll formulas, random effect tables, and reference tables through the API.

**Current State:**
- 54 class feature random tables imported and accessible
- API endpoint: `GET /api/v1/classes/{id}` returns `features[].random_tables[]`
- Example: Rogue Sneak Attack has level-scaled damage table (1d6 → 10d6)
- Includes dice-based tables (Wild Magic Surge d8) and reference tables (spell progression)

---

## Problem Statement

### Current State

Class features contain three types of tabular data that are **currently ignored** during import:

1. **`<roll>` XML Elements** - Structured level-scaled data
2. **Pipe-Delimited Random Tables** - Dice-based rollable tables
3. **Pipe-Delimited Reference Tables** - Lookup tables without dice

**Impact:** Character builders and DM tools cannot access:
- Sneak Attack damage by level
- Wild Magic random effects
- Spell progression reference tables
- Any other feature roll formulas

### Data Examples

#### Type 1: `<roll>` XML Elements (Level-Scaled)

```xml
<feature>
  <name>Sneak Attack</name>
  <text>Beginning at 1st level, you know how to strike subtly...</text>
  <roll description="Extra Damage" level="1">1d6</roll>
  <roll description="Extra Damage" level="3">2d6</roll>
  <roll description="Extra Damage" level="5">3d6</roll>
  ...
  <roll description="Extra Damage" level="19">10d6</roll>
</feature>
```

**Characteristics:**
- Structured XML elements
- `description` attribute (table name)
- `level` attribute (optional, for level-scaling)
- Formula as element content (`1d6`, `2d6`, etc.)

**Found In:**
- `class-rogue-phb.xml` - Sneak Attack (10 rolls)
- `class-barbarian-tce.xml` - Wild Magic effects (5 rolls)
- `class-barbarian-xge.xml` - Various features (7 rolls)
- ~15 class files total

#### Type 2: Pipe-Delimited Random Tables (With Dice)

```xml
<feature>
  <name>Wild Surge (Path of Wild Magic)</name>
  <text>The magical energy roiling inside you sometimes erupts from you. When you enter your rage, roll on the Wild Magic table to determine the magical effect produced.

d8 | Magical Effect
1 | Shadowy tendrils lash around you. Each creature of your choice that you can see within 30 feet of you must succeed on a Constitution saving throw or take 1d12 necrotic damage. You also gain 1d12 temporary hit points.
2 | You teleport up to 30 feet to an unoccupied space you can see...
3 | An intangible spirit appears within 5 feet...
...
8 | A bolt of light shoots from your chest...
  </text>
</feature>
```

**Characteristics:**
- Embedded in feature description text
- Header with dice notation: `d8 | Table Name`
- Numbered rows: `1 | Result text`
- Rollable (has dice type)

**Found In:**
- `class-barbarian-tce.xml` - Wild Magic Surge table
- Other classes with random effect tables

#### Type 3: Pipe-Delimited Reference Tables (No Dice)

```xml
<feature>
  <name>Spellcasting (Arcane Trickster)</name>
  <text>When you reach 3rd level, you gain the ability to cast spells...

Arcane Trickster Spells Known:
Level | Spells Known
1 | -
2 | -
3 | 3
4 | 4
5 | 4
...
19 | 12
20 | 13
  </text>
</feature>
```

**Characteristics:**
- Embedded in feature description text
- Header format: `Column | Column`
- **No dice notation** (reference table, not rollable)
- Row format: `Value | Value`

**Found In:**
- `class-rogue-phb.xml` - Arcane Trickster Spells Known
- Other subclass features with reference tables

---

## Existing Infrastructure (Already Built!)

### ✅ Database Schema

**Tables:** `random_tables` + `random_table_entries`

```php
random_tables {
  id: bigint
  reference_type: string  // 'App\Models\ClassFeature'
  reference_id: bigint    // ClassFeature ID
  table_name: string      // 'Extra Damage', 'Magical Effect', 'Spells Known'
  dice_type: string|null  // 'd6', 'd8', null (for reference tables)
  description: string|null
}

random_table_entries {
  id: bigint
  random_table_id: bigint
  roll_min: int           // Level or roll value
  roll_max: int           // Same as roll_min for single values
  result_text: string     // '1d6', 'Shadowy tendrils...', '3'
  sort_order: int
}
```

**Polymorphic Design:** Already supports any entity type via `reference_type` + `reference_id`.

### ✅ Parser Traits

**`ParsesRolls` trait** (`app/Services/Parsers/Concerns/ParsesRolls.php`)

```php
trait ParsesRolls
{
    /**
     * Parse roll elements from XML.
     * Extracts dice formulas, descriptions, and level requirements.
     */
    protected function parseRollElements(SimpleXMLElement $element): array
    {
        $rolls = [];
        foreach ($element->roll as $rollElement) {
            $rolls[] = [
                'description' => (string) $rollElement['description'] ?? null,
                'formula' => (string) $rollElement,
                'level' => (int) $rollElement['level'] ?? null,
            ];
        }
        return $rolls;
    }
}
```

**Already used by:** Other entity parsers (items, spells, etc.)

### ✅ Importer Traits

**`ImportsRandomTablesFromText` trait** (`app/Services/Importers/Concerns/ImportsRandomTablesFromText.php`)

```php
trait ImportsRandomTablesFromText
{
    /**
     * Import random tables detected in text description.
     * Handles both dice-based tables AND reference tables (dice_type = null).
     */
    protected function importRandomTablesFromText(
        Model $entity,
        string $text,
        bool $clearExisting = true
    ): void {
        $detector = new ItemTableDetector;
        $tables = $detector->detectTables($text); // Detects pipe-delimited tables

        foreach ($tables as $tableData) {
            $parser = new ItemTableParser;
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type']);

            // Create RandomTable + RandomTableEntry records
            // ...
        }
    }
}
```

**Table Detection:** Uses `ItemTableDetector` with 3 patterns:
- Pattern 1: `Table Name:\nHeader | Header\n1 | Data` (handles reference tables!)
- Pattern 2: `d8 | Table Name\n1 | Data` (handles random tables)
- Pattern 3: Inline tables

**Already used by:** `ItemImporter`, `SpellImporter`

### ✅ Detection Logic

**`ItemTableDetector::parseDiceType()`** already handles tables **without dice**:

```php
private function parseDiceType(string $header): ?string
{
    // Check if header starts with dice notation
    if (preg_match('/^(\d*d\d+)\s*\|/', $header, $matches)) {
        return $matches[1]; // Returns 'd8', '2d6', etc.
    }
    return null; // Returns null for reference tables like "Level | Spells Known"
}
```

**Result:** Reference tables get `dice_type: null`, which is perfect for our use case!

---

## Implementation Plan

### Step 1: Add Parser Support (15 min)

**File:** `app/Services/Parsers/ClassXmlParser.php`

**Changes:**
1. Add `ParsesRolls` trait to class
2. Parse `<roll>` elements in `parseFeatures()` method

```php
use App\Services\Parsers\Concerns\ParsesRolls;

class ClassXmlParser
{
    use MatchesProficiencyTypes, ParsesSourceCitations, ParsesTraits, ParsesRolls;

    private function parseFeatures(SimpleXMLElement $element): array
    {
        $features = [];
        $sortOrder = 0;

        foreach ($element->autolevel as $autolevel) {
            $level = (int) $autolevel['level'];

            foreach ($autolevel->feature as $featureElement) {
                $isOptional = isset($featureElement['optional'])
                    && (string) $featureElement['optional'] === 'YES';
                $name = (string) $featureElement->name;
                $text = (string) $featureElement->text;

                $sources = $this->parseSourceCitations($text);

                $features[] = [
                    'level' => $level,
                    'name' => $name,
                    'description' => trim($text),
                    'is_optional' => $isOptional,
                    'sources' => $sources,
                    'sort_order' => $sortOrder++,
                    'rolls' => $this->parseRollElements($featureElement), // NEW
                ];
            }
        }

        return $features;
    }
}
```

### Step 2: Add Model Relationship (5 min)

**File:** `app/Models/ClassFeature.php`

**Changes:**
Add `randomTables()` morphMany relationship

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ClassFeature extends BaseModel
{
    protected $table = 'class_features';

    protected $fillable = [
        'class_id',
        'level',
        'feature_name',
        'is_optional',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'level' => 'integer',
        'is_optional' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    /**
     * Random tables and reference tables associated with this feature.
     * Includes <roll> elements and pipe-delimited tables from feature text.
     */
    public function randomTables(): MorphMany
    {
        return $this->morphMany(
            RandomTable::class,
            'reference',
            'reference_type',
            'reference_id'
        );
    }
}
```

### Step 3: Add Importer Support (30 min)

**File:** `app/Services/Importers/ClassImporter.php`

**Changes:**
1. Add `ImportsRandomTablesFromText` trait
2. Create `importFeatureRolls()` method for `<roll>` elements
3. Call `importRandomTablesFromText()` for pipe-delimited tables

```php
use App\Services\Importers\Concerns\ImportsRandomTablesFromText;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;

class ClassImporter extends BaseImporter
{
    use CachesLookupTables;
    use GeneratesSlugs;
    use ImportsSources;
    use ImportsEntityTraits;
    use ImportsEntityProficiencies;
    use ImportsLanguages;
    use ImportsRandomTablesFromText; // NEW

    /**
     * Import class features.
     */
    private function importFeatures(CharacterClass $class, array $features): void
    {
        foreach ($features as $featureData) {
            $feature = ClassFeature::create([
                'class_id' => $class->id,
                'level' => $featureData['level'],
                'feature_name' => $featureData['name'],
                'is_optional' => $featureData['is_optional'],
                'description' => $featureData['description'],
                'sort_order' => $featureData['sort_order'],
            ]);

            // Import random tables from <roll> XML elements
            if (!empty($featureData['rolls'])) {
                $this->importFeatureRolls($feature, $featureData['rolls']);
            }

            // Import random tables from pipe-delimited tables in description text
            // This handles BOTH dice-based random tables AND reference tables (dice_type = null)
            $this->importRandomTablesFromText($feature, $featureData['description']);
        }
    }

    /**
     * Import random tables from <roll> XML elements.
     *
     * Groups rolls by description to create tables with level-based entries.
     *
     * Example: Sneak Attack has 10 rolls with description "Extra Damage"
     *          → Creates 1 table with 10 entries (one per level)
     *
     * @param ClassFeature $feature
     * @param array $rolls Array of ['description' => string, 'formula' => string, 'level' => int|null]
     */
    private function importFeatureRolls(ClassFeature $feature, array $rolls): void
    {
        // Group rolls by description (table name)
        // Example: All "Extra Damage" rolls → one table
        $groupedRolls = collect($rolls)->groupBy('description');

        foreach ($groupedRolls as $tableName => $rollGroup) {
            // Extract dice type from first roll formula
            // Examples: "1d6" → "d6", "2d12" → "d12"
            $firstFormula = $rollGroup->first()['formula'];
            $diceType = $this->extractDiceType($firstFormula);

            $table = RandomTable::create([
                'reference_type' => ClassFeature::class,
                'reference_id' => $feature->id,
                'table_name' => $tableName,
                'dice_type' => $diceType,
                'description' => null,
            ]);

            foreach ($rollGroup as $index => $roll) {
                RandomTableEntry::create([
                    'random_table_id' => $table->id,
                    'roll_min' => $roll['level'] ?? 1, // Use level as roll value, or 1 if no level
                    'roll_max' => $roll['level'] ?? 1,
                    'result_text' => $roll['formula'], // "1d6", "2d6", etc.
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /**
     * Extract dice type from formula (e.g., "1d6" → "d6", "2d12" → "d12").
     *
     * @param string $formula Dice formula like "1d6", "2d12+5"
     * @return string|null Dice type like "d6", "d12" or null if not found
     */
    private function extractDiceType(string $formula): ?string
    {
        if (preg_match('/\d*d\d+/', $formula, $matches)) {
            // Remove leading number: "2d6" → "d6"
            return preg_replace('/^\d+/', '', $matches[0]);
        }
        return null;
    }
}
```

### Step 4: Add API Exposure (5 min)

**File:** `app/Http/Resources/ClassFeatureResource.php`

**Changes:**
Add `random_tables` to resource output

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'level' => $this->level,
            'feature_name' => $this->feature_name,
            'is_optional' => $this->is_optional,
            'description' => $this->description,
            'sort_order' => $this->sort_order,

            // Relationships
            'random_tables' => RandomTableResource::collection(
                $this->whenLoaded('randomTables')
            ), // NEW
        ];
    }
}
```

**Note:** `RandomTableResource` already exists and includes `entries` relationship.

### Step 5: Update Eager Loading (5 min)

**File:** `app/Http/Controllers/Api/ClassController.php`

**Changes:**
Add `features.randomTables.entries` to available includes

```php
// In ClassShowRequest.php validation rules
'include' => [
    'sometimes',
    'string',
    Rule::in([
        'traits',
        'proficiencies',
        'spells',
        'features',
        'features.randomTables',        // NEW
        'features.randomTables.entries', // NEW
        'counters',
        'levelProgression',
        'parentClass',
        'parentClass.features',
        // ... other includes
    ]),
],
```

---

## Testing Strategy

### Unit Tests: `ClassXmlParserRollsTest.php`

**Test Cases:**
1. ✅ `it_parses_roll_elements_from_features`
   - Verify `parseRollElements()` extracts description, formula, level

2. ✅ `it_handles_features_with_no_rolls`
   - Returns empty array when no `<roll>` elements present

3. ✅ `it_handles_level_scaled_rolls`
   - Sneak Attack: 10 rolls with different levels

4. ✅ `it_handles_multiple_rolls_per_feature`
   - Wild Magic: 5 different roll types

### Feature Tests: `ClassImporterRandomTablesTest.php`

**Test Cases:**
1. ✅ `it_creates_random_tables_from_roll_elements`
   - Import Sneak Attack → verify 1 table with 10 entries

2. ✅ `it_creates_random_tables_from_pipe_delimited_text`
   - Import Wild Magic → verify table with d8 dice type

3. ✅ `it_creates_reference_tables_without_dice`
   - Import Arcane Trickster → verify table with null dice_type

4. ✅ `it_handles_features_with_both_roll_elements_and_text_tables`
   - Wild Magic has both `<roll>` AND pipe-delimited table

5. ✅ `it_groups_rolls_by_description`
   - All "Extra Damage" rolls → one table

### API Tests: `ClassFeatureApiTest.php`

**Test Cases:**
1. ✅ `it_includes_random_tables_when_requested`
   - `GET /classes/rogue?include=features.randomTables.entries`
   - Verify Sneak Attack feature has random table

---

## Expected API Output

### Example 1: Sneak Attack (Level-Scaled Rolls)

```json
GET /api/v1/classes/rogue?include=features.randomTables.entries

{
  "name": "Rogue",
  "features": [
    {
      "id": 100,
      "level": 1,
      "feature_name": "Sneak Attack",
      "description": "Beginning at 1st level, you know how to strike...",
      "random_tables": [
        {
          "id": 1,
          "table_name": "Extra Damage",
          "dice_type": "d6",
          "entries": [
            {"roll_min": 1, "roll_max": 1, "result_text": "1d6", "sort_order": 0},
            {"roll_min": 3, "roll_max": 3, "result_text": "2d6", "sort_order": 1},
            {"roll_min": 5, "roll_max": 5, "result_text": "3d6", "sort_order": 2},
            ...
            {"roll_min": 19, "roll_max": 19, "result_text": "10d6", "sort_order": 9}
          ]
        }
      ]
    }
  ]
}
```

**Character Builder Usage:**
```javascript
const rogueLevel = 5;
const sneakAttack = features.find(f => f.feature_name === "Sneak Attack");
const damageTable = sneakAttack.random_tables[0];
const damage = damageTable.entries.find(e => e.roll_min === rogueLevel);
console.log(`Sneak Attack damage at level ${rogueLevel}: ${damage.result_text}`);
// Output: "Sneak Attack damage at level 5: 3d6"
```

### Example 2: Wild Magic Surge (Random Table)

```json
{
  "feature_name": "Wild Surge (Path of Wild Magic)",
  "random_tables": [
    {
      "table_name": "Magical Effect",
      "dice_type": "d8",
      "entries": [
        {"roll_min": 1, "roll_max": 1, "result_text": "Shadowy tendrils lash around you...", "sort_order": 0},
        {"roll_min": 2, "roll_max": 2, "result_text": "You teleport up to 30 feet...", "sort_order": 1},
        ...
        {"roll_min": 8, "roll_max": 8, "result_text": "A bolt of light shoots from your chest...", "sort_order": 7}
      ]
    }
  ]
}
```

**DM Tool Usage:**
```javascript
const wildMagic = features.find(f => f.feature_name.includes("Wild Surge"));
const table = wildMagic.random_tables[0];
const roll = Math.floor(Math.random() * 8) + 1; // Roll 1d8
const effect = table.entries.find(e => e.roll_min === roll);
console.log(`Wild Magic Effect (rolled ${roll}): ${effect.result_text}`);
```

### Example 3: Arcane Trickster Spells Known (Reference Table)

```json
{
  "feature_name": "Spellcasting (Arcane Trickster)",
  "random_tables": [
    {
      "table_name": "Arcane Trickster Spells Known",
      "dice_type": null,
      "entries": [
        {"roll_min": 1, "roll_max": 1, "result_text": "-", "sort_order": 0},
        {"roll_min": 2, "roll_max": 2, "result_text": "-", "sort_order": 1},
        {"roll_min": 3, "roll_max": 3, "result_text": "3", "sort_order": 2},
        {"roll_min": 4, "roll_max": 4, "result_text": "4", "sort_order": 3},
        ...
        {"roll_min": 20, "roll_max": 20, "result_text": "13", "sort_order": 19}
      ]
    }
  ]
}
```

**Character Builder Usage:**
```javascript
const arcaneTricksterLevel = 7;
const spellcasting = features.find(f => f.feature_name === "Spellcasting (Arcane Trickster)");
const spellsKnownTable = spellcasting.random_tables.find(t => t.table_name.includes("Spells Known"));
const spellsKnown = spellsKnownTable.entries.find(e => e.roll_min === arcaneTricksterLevel);
console.log(`Spells known at level ${arcaneTricksterLevel}: ${spellsKnown.result_text}`);
// Output: "Spells known at level 7: 5"
```

---

## Performance Considerations

### Import Performance

**Additional Queries per Feature:**
- 1 query per random table (from `<roll>` elements)
- 1 query per table entry batch (INSERT)
- Same for pipe-delimited tables (handled by existing trait)

**Estimate:**
- Average feature: 0-1 tables
- Features with rolls: 1-3 tables
- Negligible impact on import time

### API Performance

**Query Optimization:**
```php
// Efficient eager loading
GET /classes/rogue?include=features.randomTables.entries

// Controller does:
$class->load([
    'features.randomTables.entries'
]);

// Results in 3 queries:
// 1. SELECT * FROM classes WHERE slug = 'rogue'
// 2. SELECT * FROM class_features WHERE class_id = ?
// 3. SELECT * FROM random_tables WHERE reference_type = 'ClassFeature' AND reference_id IN (...)
// 4. SELECT * FROM random_table_entries WHERE random_table_id IN (...)
```

**Caching:** Entity cache already handles this (no changes needed).

---

## Files to Modify

### Modified Files (4)

1. **app/Services/Parsers/ClassXmlParser.php**
   - Add `ParsesRolls` trait
   - Parse rolls in `parseFeatures()` method

2. **app/Models/ClassFeature.php**
   - Add `randomTables()` relationship

3. **app/Services/Importers/ClassImporter.php**
   - Add `ImportsRandomTablesFromText` trait
   - Add `importFeatureRolls()` method
   - Add `extractDiceType()` helper
   - Call both import methods in `importFeatures()`

4. **app/Http/Resources/ClassFeatureResource.php**
   - Add `random_tables` to output

### New Test Files (2)

1. **tests/Unit/Parsers/ClassXmlParserRollsTest.php** (4 tests)
2. **tests/Feature/Importers/ClassImporterRandomTablesTest.php** (5 tests)

### Updated Files (2)

1. **app/Http/Requests/ClassShowRequest.php**
   - Add `features.randomTables` to include validation

2. **CHANGELOG.md**
   - Add entry under `[Unreleased]`

---

## Edge Cases & Considerations

### 1. Features with Both `<roll>` and Pipe-Delimited Tables

**Example:** Wild Magic has both:
- `<roll>` elements for individual damage amounts
- Pipe-delimited table for the main effect table

**Solution:** Import both! They'll create separate `random_tables` records.

### 2. Multiple Rolls with Same Description

**Example:** Wild Magic has 5 rolls:
```xml
<roll description="Magical Effect">1d8</roll>
<roll description="Necrotic Damage">1d12</roll>
<roll description="Temporary Hit Points">1d12</roll>
<roll description="Force Damage">1d6</roll>
<roll description="Radiant Damage">1d6</roll>
```

**Solution:** Each unique description creates a separate table.

### 3. Reference Tables Without Dice

**Example:** "Level | Spells Known" has no dice type.

**Solution:** `dice_type = null` is perfect! It's a lookup table, not a rollable table.

### 4. Rolls Without Level Attribute

**Example:** Wild Magic rolls don't have `level` attribute.

**Solution:** Use `roll_min = 1` as fallback (or could use sort_order).

---

## Success Criteria

✅ **Parser Tests Pass:**
- Extracts `<roll>` elements correctly
- Handles level-scaled rolls
- Handles multiple rolls per feature

✅ **Importer Tests Pass:**
- Creates random tables from `<roll>` elements
- Creates tables from pipe-delimited text (both types)
- Groups rolls by description correctly

✅ **Database Verification:**
```bash
# Verify Sneak Attack has random table
docker compose exec php php artisan tinker --execute="
\$feature = \App\Models\ClassFeature::with('randomTables.entries')
    ->whereHas('characterClass', fn(\$q) => \$q->where('slug', 'rogue'))
    ->where('feature_name', 'Sneak Attack')
    ->first();
echo 'Tables: ' . \$feature->randomTables->count() . PHP_EOL;
echo 'Entries: ' . \$feature->randomTables->first()->entries->count() . PHP_EOL;
"
# Expected: Tables: 1, Entries: 10
```

✅ **API Verification:**
```bash
curl "http://localhost:8080/api/v1/classes/rogue?include=features.randomTables.entries" | \
  jq '.features[] | select(.feature_name == "Sneak Attack") | .random_tables'
# Expected: Array with 1 table containing 10 entries
```

✅ **All Existing Tests Still Pass:**
```bash
docker compose exec php php artisan test --filter=Class
# Expected: All ClassXmlParser and ClassImporter tests pass
```

---

## Related Documents

- **Database Migration:** `2025_11_18_102310_create_random_tables.php` (already exists)
- **Existing Traits:**
  - `app/Services/Parsers/Concerns/ParsesRolls.php`
  - `app/Services/Importers/Concerns/ImportsRandomTablesFromText.php`
- **Table Detection:** `app/Services/Parsers/ItemTableDetector.php`
- **Table Parsing:** `app/Services/Parsers/ItemTableParser.php`

---

## Notes for Next Developer

### Quick Start

1. **Understand existing infrastructure first:**
   - Read `ParsesRolls` trait (simple, ~45 lines)
   - Read `ImportsRandomTablesFromText` trait (well-documented)
   - Check how `ItemImporter` uses these traits (good example)

2. **Follow TDD approach:**
   - Write parser tests first (watch them fail)
   - Implement parser changes (watch them pass)
   - Write importer tests (watch them fail)
   - Implement importer changes (watch them pass)

3. **Test with real data:**
   ```bash
   # Import Rogue class
   docker compose exec php php artisan migrate:fresh --seed
   docker compose exec php php artisan import:classes import-files/class-rogue-phb.xml

   # Verify in database
   docker compose exec php php artisan tinker
   > $feature = ClassFeature::with('randomTables.entries')->where('feature_name', 'Sneak Attack')->first();
   > $feature->randomTables; // Should have 1 table
   > $feature->randomTables->first()->entries; // Should have 10 entries
   ```

### Common Pitfalls to Avoid

1. **Don't create a new table** - `random_tables` already exists and is perfect!
2. **Don't reinvent parsing** - Use existing `ParsesRolls` and `ImportsRandomTablesFromText` traits
3. **Don't forget the relationship** - Must add `randomTables()` to `ClassFeature` model
4. **Don't skip text tables** - Remember to call `importRandomTablesFromText()` for pipe-delimited tables

### Why This Design is Good

- ✅ **Reuses existing infrastructure** - No new tables or parsing logic
- ✅ **Handles all three types** - `<roll>`, random tables, reference tables
- ✅ **Polymorphic design** - Works for any entity type
- ✅ **Consistent API** - Same structure as items/spells random tables
- ✅ **Character builder ready** - All data accessible via API

---

**Status:** 📋 READY FOR IMPLEMENTATION
**Estimated Time:** 1-1.5 hours
**Complexity:** Low (all infrastructure exists)
**Risk:** Low (well-tested patterns)

**Ready when you are!** 🚀
