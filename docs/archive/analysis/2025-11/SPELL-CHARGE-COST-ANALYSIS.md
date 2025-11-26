# Spell Charge Cost Analysis - Staff of Healing Conundrum

**Date:** 2025-11-22
**Context:** Analyzing how to model magic items that cast spells with variable charge costs

---

## 🎯 The Problem

We have **TWO overlapping systems** that need to be unified:

### System 1: Item-Level Charges (Recently Implemented)
```php
items table:
- charges_max: 10
- recharge_formula: "1d6+4"
- recharge_timing: "dawn"
```

### System 2: Spell Casting from Items (Not Yet Implemented)
```
"Staff of Healing has 10 charges...cast one of the following spells:
- cure wounds (1 charge per spell level, up to 4th)  // Variable: 1-4 charges
- lesser restoration (2 charges)                       // Fixed: 2 charges
- mass cure wounds (5 charges)"                        // Fixed: 5 charges
```

**The Conundrum:** How do we store the **per-spell charge cost** when an item can cast multiple spells with different costs?

---

## 📊 Current Database Schema

### Existing Tables

#### `items` (Item-level charge data)
```sql
charges_max          SMALLINT UNSIGNED  -- Total capacity (10)
recharge_formula     VARCHAR(50)        -- How it recharges (1d6+4)
recharge_timing      VARCHAR(50)        -- When it recharges (dawn)
```

#### `entity_spells` (Polymorphic spell associations)
```sql
id                   BIGINT UNSIGNED PRIMARY KEY
reference_type       VARCHAR(255)       -- 'App\Models\Item'
reference_id         BIGINT UNSIGNED    -- Item ID
spell_id             BIGINT UNSIGNED    -- Which spell
ability_score_id     BIGINT UNSIGNED NULL -- Casting ability
level_requirement    INT NULL           -- Min level to cast
usage_limit          VARCHAR(255) NULL  -- "3/day", "1/short rest"
is_cantrip           BOOLEAN DEFAULT FALSE
```

**MISSING:** No `charges_cost` column in `entity_spells`!

---

## 🔍 Real-World Examples from XML

### Example 1: Staff of Healing (Variable + Fixed Costs)
```xml
<text>This staff has 10 charges...cast one of the following spells:
  cure wounds (1 charge per spell level, up to 4th),
  lesser restoration (2 charges),
  mass cure wounds (5 charges).

  The staff regains 1d6 + 4 expended charges daily at dawn.
</text>
```

**Complexity:**
- `cure wounds` has **variable cost** based on spell level (1-4 charges)
- `lesser restoration` has **fixed cost** (2 charges)
- `mass cure wounds` has **fixed cost** (5 charges)

### Example 2: Staff of Fire (Fixed Costs)
```xml
<text>The staff has 10 charges...cast one of the following spells:
  burning hands (1 charge),
  fireball (3 charges),
  wall of fire (4 charges).
</text>
```

**Simple:** All fixed costs

### Example 3: Staff of the Magi (Complex)
```xml
<text>Spells: While holding the staff, you can use an action to expend some of its
charges to cast one of the following spells from it:
  conjure elemental (7 charges),
  dispel magic (3 charges),
  fireball (7th-level version, 7 charges),
  lightning bolt (7th-level version, 7 charges),
  plane shift (7 charges),
  ...
</text>
```

**Note:** Some spells are **upcast** to specific levels (fireball at 7th level)

### Example 4: Rod of Lordly Might (Spell without charges)
```xml
<text>The rod has 6 charges...While holding the rod, you can use an action
to cast one of the following spells from it:
  detect evil and good (no charges),
  detect magic (no charges),
  locate object (no charges).
</text>
```

**Rarity:** Some items grant spells WITHOUT consuming charges!

---

## 🗂️ Proposed Solutions

### Option A: Add `charges_cost` to `entity_spells` ⭐ RECOMMENDED

```sql
ALTER TABLE entity_spells ADD COLUMN charges_cost_min SMALLINT UNSIGNED NULL;
ALTER TABLE entity_spells ADD COLUMN charges_cost_max SMALLINT UNSIGNED NULL;
ALTER TABLE entity_spells ADD COLUMN charges_cost_formula VARCHAR(100) NULL;
```

**Examples:**
```php
// Fixed cost: lesser restoration (2 charges)
charges_cost_min: 2
charges_cost_max: 2
charges_cost_formula: null

// Variable cost: cure wounds (1 charge per level, up to 4th)
charges_cost_min: 1
charges_cost_max: 4
charges_cost_formula: "1 per spell level"

// Free: detect magic (no charges)
charges_cost_min: 0
charges_cost_max: 0
charges_cost_formula: null
```

**Pros:**
- Supports variable costs (cure wounds: 1-4)
- Supports fixed costs (lesser restoration: 2)
- Supports free spells (detect magic: 0)
- Searchable ("show me items that cast X for ≤3 charges")
- API-friendly (clear JSON structure)

**Cons:**
- Three columns instead of one (but more precise)
- Formula field is text (needs parsing for complex rules)

---

### Option B: Store JSON in `usage_limit`

```sql
-- Use existing usage_limit column with JSON
usage_limit: '{"charges": {"min": 1, "max": 4, "formula": "1 per spell level"}}'
```

**Pros:**
- No schema changes
- Flexible for complex rules

**Cons:**
- Hard to query ("find spells castable for ≤3 charges")
- JSON in VARCHAR is anti-pattern for structured data
- Not Eloquent-friendly
- Bad for API filtering

---

### Option C: Create `entity_spell_charges` pivot table

```sql
CREATE TABLE entity_spell_charges (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    entity_spell_id BIGINT UNSIGNED NOT NULL,
    spell_level INT NOT NULL,
    charges_cost SMALLINT UNSIGNED NOT NULL,
    FOREIGN KEY (entity_spell_id) REFERENCES entity_spells(id) ON DELETE CASCADE
);
```

**Example:** Cure Wounds from Staff of Healing
```
entity_spell_id | spell_level | charges_cost
123             | 1           | 1
123             | 2           | 2
123             | 3           | 3
123             | 4           | 4
```

**Pros:**
- Extremely precise (handles all edge cases)
- Queryable by spell level

**Cons:**
- Overkill for most items (95% have fixed costs)
- Complex to maintain
- Extra JOINs for every query
- Harder to parse from text

---

## 🎯 Recommended Approach: **Option A**

### Implementation Plan

#### Step 1: Add Columns to `entity_spells`

```php
// Migration: 2025_11_22_XXXXXX_add_charge_costs_to_entity_spells_table.php

Schema::table('entity_spells', function (Blueprint $table) {
    $table->unsignedSmallInteger('charges_cost_min')->nullable()
        ->comment('Minimum charges to cast (0 = free, 1-50 = cost)');
    $table->unsignedSmallInteger('charges_cost_max')->nullable()
        ->comment('Maximum charges to cast (same as min for fixed costs)');
    $table->string('charges_cost_formula', 100)->nullable()
        ->comment('Human-readable formula: "1 per spell level", "1-3 per use"');
});
```

#### Step 2: Create Parser Method

```php
// app/Services/Parsers/Concerns/ParsesSpellChargeCosts.php

trait ParsesSpellChargeCosts
{
    /**
     * Parse spell charge costs from item description
     *
     * Examples:
     * - "cure wounds (1 charge per spell level, up to 4th)"
     *   -> min:1, max:4, formula:"1 per spell level"
     *
     * - "lesser restoration (2 charges)"
     *   -> min:2, max:2, formula:null
     *
     * - "detect magic (no charges)"
     *   -> min:0, max:0, formula:null
     *
     * @param string $spellText The spell entry from description
     * @return array ['min' => int, 'max' => int, 'formula' => string|null]
     */
    protected function parseSpellChargeCost(string $spellText): array
    {
        $result = [
            'min' => null,
            'max' => null,
            'formula' => null,
        ];

        // Pattern 1: "X charge(s) per spell level, up to Yth"
        if (preg_match('/(\d+)\s+charges?\s+per\s+spell\s+level.*up\s+to\s+(\d+)(?:st|nd|rd|th)/i', $spellText, $matches)) {
            $result['min'] = (int)$matches[1];
            $result['max'] = (int)$matches[1] * (int)$matches[2];
            $result['formula'] = "{$matches[1]} per spell level";
            return $result;
        }

        // Pattern 2: "no charges" or "0 charges"
        if (preg_match('/\b(?:no|0)\s+charges?\b/i', $spellText)) {
            $result['min'] = 0;
            $result['max'] = 0;
            return $result;
        }

        // Pattern 3: Fixed cost "X charge(s)"
        if (preg_match('/\b(\d+)\s+charges?\b/i', $spellText, $matches)) {
            $cost = (int)$matches[1];
            $result['min'] = $cost;
            $result['max'] = $cost;
            return $result;
        }

        // Pattern 4: "expends X charge(s)"
        if (preg_match('/expends?\s+(\d+)\s+charges?/i', $spellText, $matches)) {
            $cost = (int)$matches[1];
            $result['min'] = $cost;
            $result['max'] = $cost;
            return $result;
        }

        return $result; // All null if no pattern matched
    }

    /**
     * Extract spell names and their charge costs from item description
     *
     * @param string $description Full item description
     * @return array [['spell_name' => 'Cure Wounds', 'min' => 1, 'max' => 4, ...], ...]
     */
    protected function parseItemSpells(string $description): array
    {
        $spells = [];

        // Common pattern: "cast one of the following spells: spell1 (cost), spell2 (cost)"
        if (preg_match('/cast\s+(?:one\s+of\s+)?the\s+following\s+spells[^:]*:\s*(.+?)(?:\.|The\s+\w+\s+regains)/is', $description, $matches)) {
            $spellList = $matches[1];

            // Split by commas or "or"
            $entries = preg_split('/,\s*(?:or\s+)?/', $spellList);

            foreach ($entries as $entry) {
                // Extract spell name and parenthetical cost info
                if (preg_match('/([a-z\s\']+)\s*\(([^)]+)\)/i', $entry, $spellMatch)) {
                    $spellName = trim($spellMatch[1]);
                    $costText = $spellMatch[2];

                    $costData = $this->parseSpellChargeCost($costText);

                    if ($costData['min'] !== null) {
                        $spells[] = [
                            'spell_name' => $spellName,
                            'charges_cost_min' => $costData['min'],
                            'charges_cost_max' => $costData['max'],
                            'charges_cost_formula' => $costData['formula'],
                        ];
                    }
                }
            }
        }

        return $spells;
    }
}
```

#### Step 3: Integrate into ItemImporter

```php
// app/Services/Importers/ItemImporter.php

use ParsesSpellChargeCosts;

protected function importSpells(Item $item, array $itemData): void
{
    if (!isset($itemData['spells']) || empty($itemData['spells'])) {
        return;
    }

    foreach ($itemData['spells'] as $spellData) {
        // Look up spell by name
        $spell = Spell::where('name', $spellData['spell_name'])->first();

        if (!$spell) {
            Log::warning("Spell not found: {$spellData['spell_name']} (for item: {$item->name})");
            continue;
        }

        // Create entity_spell record
        DB::table('entity_spells')->updateOrInsert(
            [
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'spell_id' => $spell->id,
            ],
            [
                'charges_cost_min' => $spellData['charges_cost_min'],
                'charges_cost_max' => $spellData['charges_cost_max'],
                'charges_cost_formula' => $spellData['charges_cost_formula'],
                'updated_at' => now(),
            ]
        );
    }
}
```

---

## 📋 API Response Example

### Request
```
GET /api/v1/items/staff-of-healing?include=spells
```

### Response
```json
{
  "id": 444,
  "name": "Staff of Healing",
  "slug": "staff-of-healing",
  "charges_max": 10,
  "recharge_formula": "1d6+4",
  "recharge_timing": "dawn",
  "spells": [
    {
      "id": 89,
      "name": "Cure Wounds",
      "level": 1,
      "charges_cost_min": 1,
      "charges_cost_max": 4,
      "charges_cost_formula": "1 per spell level",
      "usage_notes": "Can be cast at 1st-4th level"
    },
    {
      "id": 167,
      "name": "Lesser Restoration",
      "level": 2,
      "charges_cost_min": 2,
      "charges_cost_max": 2,
      "charges_cost_formula": null,
      "usage_notes": "Fixed cost"
    },
    {
      "id": 312,
      "name": "Mass Cure Wounds",
      "level": 5,
      "charges_cost_min": 5,
      "charges_cost_max": 5,
      "charges_cost_formula": null,
      "usage_notes": "Fixed cost"
    }
  ]
}
```

---

## 🧪 Testing Strategy

### Unit Tests (Parser)
```php
// tests/Unit/Parsers/SpellChargeCostParserTest.php

test('it parses variable cost per spell level')
  -> "cure wounds (1 charge per spell level, up to 4th)"
  -> min:1, max:4, formula:"1 per spell level"

test('it parses fixed cost')
  -> "lesser restoration (2 charges)"
  -> min:2, max:2, formula:null

test('it parses no charge spells')
  -> "detect magic (no charges)"
  -> min:0, max:0, formula:null

test('it parses expends syntax')
  -> "expend 3 charges to cast fireball"
  -> min:3, max:3, formula:null

test('it extracts multiple spells from description')
  -> Staff of Healing full text
  -> Returns 3 spells with correct costs
```

### Feature Tests (Import)
```php
// tests/Feature/Importers/ItemSpellChargesImportTest.php

test('it imports staff of healing with spell charge costs')
test('it associates spells with items via entity_spells')
test('it updates charge costs on reimport')
test('it handles items without spells gracefully')
```

### API Tests
```php
// tests/Feature/Api/ItemSpellsApiTest.php

test('it includes spells with charge costs in item resource')
test('it filters items by spell charge cost')
test('it includes spell charge data in search results')
```

---

## 🎯 Deliverables

1. **Migration:** Add `charges_cost_min`, `charges_cost_max`, `charges_cost_formula` to `entity_spells`
2. **Parser Trait:** `ParsesSpellChargeCosts` with 5+ regex patterns
3. **Importer Update:** Integrate spell charge parsing into ItemImporter
4. **Model Relationship:** Add `Item::spells()` morphToMany relationship
5. **API Resource:** Expose spell charge costs in ItemResource
6. **Tests:** 15+ tests (parser unit + importer feature + API)
7. **Documentation:** Update SESSION-HANDOVER.md

---

## 💡 Future Enhancements

### Phase 2: Advanced Spell Mechanics
- **Upcast detection:** "fireball (7th-level version, 7 charges)"
- **Spell DC override:** "using your spell save DC" vs "DC 18"
- **Concentration tracking:** Which spells require concentration
- **Range/duration from item:** Some items modify spell parameters

### Phase 3: Character Integration
- **Spell slot integration:** Compare item spells vs character slots
- **Charge tracking:** Current charges (user data layer)
- **Cost calculator:** "Can I afford to cast this spell?"

---

## ✅ Decision Required

**Should we proceed with Option A?**

- ✅ Add 3 columns to `entity_spells`
- ✅ Create `ParsesSpellChargeCosts` trait
- ✅ Update ItemImporter to parse and store spell costs
- ✅ Add `Item::spells()` relationship
- ✅ Expose in API

**Estimated Effort:** 6-8 hours with TDD
**Impact:** Enables spell-casting item queries, character builders, cost comparisons
**Risk:** Low (only affects items with spells, ~40-50 items)

---

**Awaiting approval to proceed with implementation.**
