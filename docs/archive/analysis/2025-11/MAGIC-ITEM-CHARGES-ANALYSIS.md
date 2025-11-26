# Magic Item Charges - Parsing Analysis

**Date:** 2025-11-22
**Context:** Investigating structured data extraction from Wand of Smiles and similar magic items

---

## 📋 Executive Summary

**YES**, we can parse charges, recharge mechanics, and save DCs into structured data! The database schema already supports most of this through the `item_abilities` table.

**Current Support:**
- ✅ `item_abilities.charges_cost` - Cost per use (1 charge, 3 charges, etc.)
- ✅ `item_abilities.save_dc` - DC for saving throws
- ✅ `item_abilities.usage_limit` - Frequency limits ("3/day", "1/short rest")
- ✅ `entity_saving_throws` - Polymorphic saves (which ability, effect on success)

**Missing:**
- ❌ **Max charges** - Total charge capacity (3, 7, 10, 36, 50)
- ❌ **Recharge rate** - How charges regenerate ("1d6+1 at dawn", "1d3 at dawn", "1d20 at dawn")
- ❌ **Destruction condition** - What happens when last charge is used ("roll d20, on 1 it crumbles")

---

## 🔍 Wand of Smiles - Full Text Analysis

```
This wand has 3 charges. While holding it, you can use an action to expend 1 of
its charges and target a humanoid you can see within 30 feet of you. The target
must succeed on a DC 10 Charisma saving throw or be forced to smile for 1 minute.

The wand regains all expended charges daily at dawn. If you expend the wand's
last charge, roll a d20. On a 1, the wand transforms into a wand of scowls.
```

### Extractable Data

| Element | Value | Current Table | Current Column |
|---------|-------|---------------|----------------|
| **Max Charges** | 3 | ❌ None | Need: `items.charges_max` |
| **Charge Cost** | 1 | ✅ `item_abilities` | `charges_cost` |
| **Recharge Rate** | "all at dawn" | ❌ None | Need: `items.recharge_formula` |
| **Save DC** | 10 | ✅ `item_abilities` | `save_dc` |
| **Save Ability** | CHA | ✅ `entity_saving_throws` | `ability_score_id` |
| **Save Effect** | "negates" | ✅ `entity_saving_throws` | `save_effect` |
| **Destruction Trigger** | "last charge, d20=1, transforms" | ❌ None | Complex, maybe `items.destruction_condition`? |

---

## 📊 Common Patterns in Items (70+ items with charges)

### Pattern 1: Fixed Charges with Dice Recharge (Most Common - ~40 items)
```
"This [item] has 7 charges. It regains 1d6+1 expended charges daily at dawn."
"This staff has 10 charges. It regains 1d6+4 expended charges daily at dawn."
"This ring has 3 charges, and it regains 1d3 expended charges daily at dawn."
```

**Regex:** `has (\d+) charges.*regains ([\dd\+\-]+) expended charges daily at dawn`

**Examples:**
- Wand of Binding: 7 charges, regains 1d6+1
- Staff of Healing: 10 charges, regains 1d6+4
- Ring of Shooting Stars: 6 charges, regains 1d6

### Pattern 2: Full Recharge Daily (Simple - ~15 items)
```
"This wand has 3 charges. The wand regains all expended charges daily at dawn."
```

**Regex:** `has (\d+) charges.*regains all expended charges daily at dawn`

**Examples:**
- Wand of Smiles: 3 charges, all at dawn
- Wand of Scowls: 3 charges, all at dawn
- Helm of Teleportation: 3 charges, all at dawn (1d3)

### Pattern 3: Large Capacity Items (~5 items)
```
"This cube starts with 36 charges, and it regains 1d20 expended charges daily at dawn."
"This prism has 50 charges."
```

**Examples:**
- Cubic Gate: 36 charges, regains 1d20
- Ioun Stone (Reserve): 50 charges (doesn't recharge)

### Pattern 4: Destruction on Last Charge (~25 items)
```
"If you expend the wand's last charge, roll a d20. On a 1, the wand crumbles into ashes and is destroyed."
"If you expend the wand's last charge, roll a d20. On a 1, the wand transforms into a wand of [X]."
```

**Regex:** `expend.*last charge.*roll a d20.*On a 1.*(?:crumbles|destroyed|transforms|loses)`

**Examples:**
- Most Wands: Transform or crumble
- Staffs: Lose magic property
- Some Rings: Shatter

---

## 🗄️ Proposed Schema Extensions

### Option A: Add Columns to `items` Table (Recommended)

```sql
ALTER TABLE items ADD COLUMN charges_max SMALLINT UNSIGNED NULL COMMENT 'Maximum charge capacity';
ALTER TABLE items ADD COLUMN recharge_formula VARCHAR(50) NULL COMMENT '1d6+1, all, 1d3, etc.';
ALTER TABLE items ADD COLUMN recharge_timing VARCHAR(50) NULL COMMENT 'dawn, dusk, short rest, long rest';
ALTER TABLE items ADD COLUMN destruction_condition TEXT NULL COMMENT 'Flavor text about what happens on last charge';
```

**Pros:**
- Simple, straightforward
- Easy to query ("show all items with 7+ charges")
- Matches D&D item semantics (charges are item properties, not abilities)

**Cons:**
- Adds columns to main items table
- Most items (~95%) won't use these columns

### Option B: Create Separate `item_charges` Table

```sql
CREATE TABLE item_charges (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    item_id BIGINT UNSIGNED NOT NULL,
    charges_max SMALLINT UNSIGNED NOT NULL,
    recharge_formula VARCHAR(50) NULL,
    recharge_timing VARCHAR(50) DEFAULT 'dawn',
    destruction_condition TEXT NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE (item_id)  -- One charge config per item
);
```

**Pros:**
- Keeps items table clean
- Only 70 rows (~3% of items)
- Can add more complex charge mechanics later

**Cons:**
- Extra JOIN for charge queries
- More complex to maintain

### Option C: Use `item_abilities` Table (Hybrid - Current Best Fit?)

**Already exists!** We already have `item_abilities.charges_cost` and `item_abilities.usage_limit`.

Could add:
```sql
ALTER TABLE item_abilities ADD COLUMN charges_max SMALLINT UNSIGNED NULL;
ALTER TABLE item_abilities ADD COLUMN recharge_formula VARCHAR(50) NULL;
```

**Pros:**
- Abilities already tied to charge costs
- Natural grouping (each ability knows its cost + max available)
- No new tables needed

**Cons:**
- Charge max is technically item-level, not ability-level
- Wand of Binding has multiple abilities sharing same charge pool

---

## 🎯 Recommended Approach: **Option A + Enhanced Parsing**

### Step 1: Add Columns to `items` Table
```sql
-- Migration: 2025_11_22_XXXXXX_add_charges_to_items_table.php
charges_max SMALLINT UNSIGNED NULL
recharge_formula VARCHAR(50) NULL  -- "1d6+1", "all", "1d3", "1d20"
recharge_timing VARCHAR(50) NULL   -- "dawn", "dusk", "short rest", "long rest"
```

### Step 2: Create Parser Trait

```php
// app/Services/Parsers/Concerns/ParsesCharges.php
trait ParsesCharges
{
    protected function parseCharges(string $text): array
    {
        $charges = [
            'charges_max' => null,
            'recharge_formula' => null,
            'recharge_timing' => null,
        ];

        // Pattern 1: "has X charges"
        if (preg_match('/has (\d+) charges/i', $text, $matches)) {
            $charges['charges_max'] = (int)$matches[1];
        }

        // Pattern 2: "regains XdY+Z expended charges"
        if (preg_match('/regains ([\dd\+\-]+) expended charges/i', $text, $matches)) {
            $charges['recharge_formula'] = $matches[1];
        }

        // Pattern 3: "regains all expended charges"
        if (preg_match('/regains all expended charges/i', $text)) {
            $charges['recharge_formula'] = 'all';
        }

        // Pattern 4: "daily at dawn|dusk"
        if (preg_match('/daily at (dawn|dusk)/i', $text, $matches)) {
            $charges['recharge_timing'] = strtolower($matches[1]);
        }

        // Pattern 5: "after a (short|long) rest"
        if (preg_match('/after a (short|long) rest/i', $text, $matches)) {
            $charges['recharge_timing'] = strtolower($matches[1]) . ' rest';
        }

        return $charges;
    }
}
```

### Step 3: Integrate into ItemXmlParser

```php
// ItemXmlParser.php
use ParsesCharges;

private function parseItem(SimpleXMLElement $element): array
{
    $text = (string) $element->text;
    $chargeData = $this->parseCharges($text);

    return [
        // ... existing fields
        'charges_max' => $chargeData['charges_max'],
        'recharge_formula' => $chargeData['recharge_formula'],
        'recharge_timing' => $chargeData['recharge_timing'],
    ];
}
```

### Step 4: Update ItemImporter

```php
// ItemImporter.php
$item = Item::updateOrCreate(
    ['slug' => $this->generateSlug($itemData['name'])],
    [
        // ... existing fields
        'charges_max' => $itemData['charges_max'],
        'recharge_formula' => $itemData['recharge_formula'],
        'recharge_timing' => $itemData['recharge_timing'],
    ]
);
```

---

## 🧪 Testing Strategy

### Parser Tests (Unit)
```php
// tests/Unit/Parsers/ItemChargesParserTest.php
test('it parses fixed charge count')
test('it parses dice-based recharge formulas')
test('it parses all-charges recharge')
test('it parses recharge timing (dawn, dusk, rest)')
test('it handles items without charges')
test('it parses complex charges (Cubic Gate 36 charges)')
```

### Importer Tests (Feature)
```php
// tests/Feature/Importers/ItemChargesImportTest.php
test('it imports wand of smiles with 3 charges all at dawn')
test('it imports wand of binding with 7 charges 1d6+1 recharge')
test('it imports cubic gate with 36 charges 1d20 recharge')
test('it reimports items without losing charge data')
```

### API Tests (Feature)
```php
// tests/Feature/Api/ItemChargesApiTest.php
test('it exposes charge data in item resource')
test('it filters items by charges_max')
test('it filters items by recharge_timing')
```

---

## 📈 Expected Results

### Coverage Estimate
- **~70 items** with charge mechanics (3% of 2,156 items)
- **Pattern 1** (dice recharge): ~40 items (57%)
- **Pattern 2** (full recharge): ~15 items (21%)
- **Pattern 3** (large capacity): ~5 items (7%)
- **Pattern 4** (destruction): ~25 items (36% have destruction mechanic)

### Sample Parsed Items

| Item | Max | Recharge | Timing | DC | Save |
|------|-----|----------|--------|----|----|
| Wand of Smiles | 3 | all | dawn | 10 | CHA |
| Wand of Binding | 7 | 1d6+1 | dawn | - | - |
| Staff of Healing | 10 | 1d6+4 | dawn | - | - |
| Ring of Shooting Stars | 6 | 1d6 | dawn | - | - |
| Cubic Gate | 36 | 1d20 | dawn | - | - |
| Helm of Teleportation | 3 | 1d3 | dawn | - | - |

---

## 🔗 Related Tables Integration

### Saving Throws (Already Supported!)

```php
// For Wand of Smiles: "DC 10 Charisma saving throw or be forced to smile"

// item_abilities record
[
    'item_id' => $wand->id,
    'ability_type' => 'action',
    'name' => 'Force Smile',
    'description' => 'Target must succeed on a DC 10 Charisma saving throw or be forced to smile for 1 minute',
    'charges_cost' => 1,
    'save_dc' => 10,
]

// entity_saving_throws record
[
    'entity_type' => 'App\Models\Item',
    'entity_id' => $wand->id,
    'ability_score_id' => 6, // CHA
    'save_effect' => 'negates',
    'is_initial_save' => true,
]
```

### API Response Example

```json
{
  "id": 2156,
  "name": "Wand of Smiles",
  "slug": "wand-of-smiles",
  "charges_max": 3,
  "recharge_formula": "all",
  "recharge_timing": "dawn",
  "abilities": [
    {
      "name": "Force Smile",
      "charges_cost": 1,
      "save_dc": 10,
      "saving_throws": [
        {
          "ability_score": {"code": "CHA", "name": "Charisma"},
          "save_effect": "negates"
        }
      ]
    }
  ]
}
```

---

## 💡 Future Enhancements

### Phase 1: Basic Charges (This Feature)
- ✅ Max charges
- ✅ Recharge formula
- ✅ Recharge timing

### Phase 2: Advanced Mechanics
- Destruction conditions (flavor text)
- Variable charge costs (1-3 charges for Ring of the Ram)
- Shared charge pools (multiple abilities, one pool)

### Phase 3: Character Sheet Integration
- Current charges (user data - separate table)
- Charge tracking API endpoints
- Recharge automation (long rest = refill)

---

## ✅ Recommendation

**PROCEED with Option A:**

1. Add 3 columns to `items` table
2. Create `ParsesCharges` trait
3. Integrate into `ItemXmlParser` and `ItemImporter`
4. Write comprehensive tests
5. Update API Resources to expose charge data

**Estimated Effort:** 4-6 hours with TDD
**Impact:** Enables character builders, item filters, and game mechanics automation
**Risk:** Low (only affects ~70 items, easy to validate)

---

## 📚 References

- Migration: `database/migrations/2025_11_17_214319_create_item_related_tables.php`
- Table: `item_abilities` (lines 53-83)
- Column: `charges_cost` (line 60)
- Column: `usage_limit` (line 61)
- Column: `save_dc` (line 62)
- Related: `entity_saving_throws` table (polymorphic saves)

---

**Next Steps:** Await approval to implement charges parsing feature.
