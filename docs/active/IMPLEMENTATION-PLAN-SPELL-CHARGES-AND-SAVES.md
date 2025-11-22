# Implementation Plan: Spell Charges + Saving Throws for Items

**Date:** 2025-11-22
**Epic:** Unify item charge mechanics with spell casting and saving throws

---

## 📋 Current Status

### ✅ What's Already Done
- `items` table has `charges_max`, `recharge_formula`, `recharge_timing` columns
- `entity_saving_throws` table exists with full schema (polymorphic)
- `entity_spells` table exists (polymorphic spell associations)
- Items import and store charge pool data

### ❌ What's Missing

**For Spell Charge Costs:**
- `entity_spells` lacks `charges_cost_min`, `charges_cost_max`, `charges_cost_formula`
- No parser to extract spell names + costs from descriptions
- No importer logic to create entity_spells records
- No `Item::spells()` relationship
- Not exposed in ItemResource

**For Saving Throws:**
- No `Item::savingThrows()` relationship
- No parser to extract DC + ability from descriptions
- No importer logic to create entity_saving_throws records
- Not exposed in ItemResource

---

## 🎯 Implementation Strategy

### Phase 1: Spell Charge Costs (Priority 1)
This enables "find items that cast Fireball for ≤3 charges" queries

### Phase 2: Saving Throws (Priority 2)
This enables "find items with CHA saves" queries

Both phases follow TDD and update API resources.

---

## 📐 Phase 1: Spell Charge Costs

### Step 1.1: Migration - Add Charge Cost Columns

**File:** `database/migrations/2025_11_22_XXXXXX_add_charge_costs_to_entity_spells_table.php`

```php
Schema::table('entity_spells', function (Blueprint $table) {
    $table->unsignedSmallInteger('charges_cost_min')->nullable()
        ->comment('Minimum charges to cast (0 = free, 1-50 = cost)');
    $table->unsignedSmallInteger('charges_cost_max')->nullable()
        ->comment('Maximum charges to cast (same as min for fixed costs)');
    $table->string('charges_cost_formula', 100)->nullable()
        ->comment('Human-readable formula: "1 per spell level", "1-3 per use"');
});
```

**Test:** Run migration, verify columns exist

---

### Step 1.2: Parser Trait - Extract Spell Costs

**File:** `app/Services/Parsers/Concerns/ParsesItemSpells.php`

```php
trait ParsesItemSpells
{
    /**
     * Parse spell charge costs from spell entry text
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
     */
    protected function parseSpellChargeCost(string $spellText): array
    {
        $result = ['min' => null, 'max' => null, 'formula' => null];

        // Pattern 1: "X charge(s) per spell level, up to Yth"
        if (preg_match('/(\d+)\s+charges?\s+per\s+spell\s+level.*up\s+to\s+(\d+)(?:st|nd|rd|th)/i', $spellText, $m)) {
            $result['min'] = (int)$m[1];
            $result['max'] = (int)$m[1] * (int)$m[2];
            $result['formula'] = "{$m[1]} per spell level";
            return $result;
        }

        // Pattern 2: "no charges" or "0 charges"
        if (preg_match('/\b(?:no|0)\s+charges?\b/i', $spellText)) {
            $result['min'] = 0;
            $result['max'] = 0;
            return $result;
        }

        // Pattern 3: Fixed cost "X charge(s)"
        if (preg_match('/\((\d+)\s+charges?\)/i', $spellText, $m)) {
            $result['min'] = (int)$m[1];
            $result['max'] = (int)$m[1];
            return $result;
        }

        // Pattern 4: "expends X charge(s)"
        if (preg_match('/expends?\s+(\d+)\s+charges?/i', $spellText, $m)) {
            $result['min'] = (int)$m[1];
            $result['max'] = (int)$m[1];
            return $result;
        }

        return $result;
    }

    /**
     * Extract all spells and their costs from item description
     *
     * Returns: [['spell_name' => 'Cure Wounds', 'min' => 1, 'max' => 4, 'formula' => '...'], ...]
     */
    protected function parseItemSpells(string $description): array
    {
        $spells = [];

        // Pattern: "cast one of the following spells: spell1 (cost), spell2 (cost)"
        if (preg_match('/cast\s+(?:one\s+of\s+)?the\s+following\s+spells[^:]*:\s*(.+?)(?:\.|The\s+\w+\s+regains)/is', $description, $matches)) {
            $spellList = $matches[1];

            // Split by commas or "or"
            $entries = preg_split('/,\s*(?:or\s+)?/', $spellList);

            foreach ($entries as $entry) {
                // Extract spell name and parenthetical cost info
                if (preg_match('/([a-z\s\']+)\s*\(([^)]+)\)/i', $entry, $spellMatch)) {
                    $spellName = trim($spellMatch[1]);
                    $costText = $spellMatch[2];

                    $costData = $this->parseSpellChargeCost("($costText)");

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

**Tests:** `tests/Unit/Parsers/ItemSpellsParserTest.php`
- Parse fixed cost (lesser restoration: 2)
- Parse variable cost (cure wounds: 1-4 per level)
- Parse free spells (detect magic: 0)
- Parse expends syntax
- Extract multiple spells from Staff of Healing

---

### Step 1.3: Update Item Model - Add Relationship

**File:** `app/Models/Item.php`

```php
use Illuminate\Database\Eloquent\Relations\MorphToMany;

public function spells(): MorphToMany
{
    return $this->morphToMany(
        Spell::class,
        'reference',
        'entity_spells',
        'reference_id',
        'spell_id'
    )->withPivot([
        'charges_cost_min',
        'charges_cost_max',
        'charges_cost_formula',
        'ability_score_id',
        'level_requirement',
        'usage_limit',
        'is_cantrip',
    ]);
}
```

---

### Step 1.4: Update ItemImporter - Import Spells

**File:** `app/Services/Importers/ItemImporter.php`

Add trait:
```php
use ParsesItemSpells;
```

Add method:
```php
protected function importSpells(Item $item, array $itemData): void
{
    if (!isset($itemData['spells']) || empty($itemData['spells'])) {
        return;
    }

    foreach ($itemData['spells'] as $spellData) {
        // Look up spell by name (case-insensitive)
        $spell = Spell::whereRaw('LOWER(name) = ?', [strtolower($spellData['spell_name'])])
            ->first();

        if (!$spell) {
            Log::warning("Spell not found: {$spellData['spell_name']} (for item: {$item->name})");
            continue;
        }

        // Create or update entity_spell record
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
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }
}
```

Call in `importItem()`:
```php
private function importItem(array $itemData): void
{
    // ... existing code to create $item

    // Parse spells from description
    $itemData['spells'] = $this->parseItemSpells($itemData['description'] ?? '');

    // Import spell associations
    $this->importSpells($item, $itemData);
}
```

**Tests:** `tests/Feature/Importers/ItemSpellsImportTest.php`
- Import Staff of Healing with 3 spells
- Import spell charge costs correctly
- Handle spell not found gracefully
- Reimport updates costs

---

### Step 1.5: Update ItemResource - Expose Spells

**File:** `app/Http/Resources/ItemResource.php`

Add to `toArray()`:
```php
'spells' => EntitySpellResource::collection($this->whenLoaded('spells')),
```

Create new resource:
**File:** `app/Http/Resources/EntitySpellResource.php`

```php
class EntitySpellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'level' => $this->level,
            'charges_cost_min' => $this->pivot?->charges_cost_min,
            'charges_cost_max' => $this->pivot?->charges_cost_max,
            'charges_cost_formula' => $this->pivot?->charges_cost_formula,
            'usage_limit' => $this->pivot?->usage_limit,
        ];
    }
}
```

**Tests:** `tests/Feature/Api/ItemSpellsApiTest.php`
- GET /api/v1/items/staff-of-healing?include=spells
- Verify spell charge costs in response
- Filter items by spell: /api/v1/items?has_spell=cure-wounds

---

## 📐 Phase 2: Saving Throws

### Step 2.1: Add Relationship to Item Model

**File:** `app/Models/Item.php`

```php
public function savingThrows(): MorphMany
{
    return $this->morphMany(EntitySavingThrow::class, 'entity');
}
```

---

### Step 2.2: Create EntitySavingThrow Model (if not exists)

**File:** `app/Models/EntitySavingThrow.php`

```php
class EntitySavingThrow extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'ability_score_id',
        'save_effect',
        'is_initial_save',
        'save_modifier',
    ];

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }
}
```

---

### Step 2.3: Parser Trait - Extract Saving Throws

**File:** `app/Services/Parsers/Concerns/ParsesItemSavingThrows.php`

```php
trait ParsesItemSavingThrows
{
    /**
     * Parse saving throw from item description
     *
     * Examples:
     * - "succeed on a DC 10 Charisma saving throw"
     *   -> dc:10, ability:'CHA', effect:'negates'
     *
     * - "must make a DC 15 Wisdom saving throw or be frightened"
     *   -> dc:15, ability:'WIS', effect:'negates'
     */
    protected function parseItemSavingThrow(string $description): ?array
    {
        // Pattern: "DC X [Ability] saving throw"
        if (preg_match('/DC\s+(\d+)\s+(Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma)\s+saving\s+throw/i', $description, $matches)) {
            $dc = (int)$matches[1];
            $abilityName = $matches[2];

            // Map ability name to code
            $abilityMap = [
                'strength' => 'STR',
                'dexterity' => 'DEX',
                'constitution' => 'CON',
                'intelligence' => 'INT',
                'wisdom' => 'WIS',
                'charisma' => 'CHA',
            ];

            $abilityCode = $abilityMap[strtolower($abilityName)] ?? null;

            if ($abilityCode) {
                return [
                    'dc' => $dc,
                    'ability_code' => $abilityCode,
                    'save_effect' => 'negates', // Most items negate on success
                    'is_initial_save' => true,
                ];
            }
        }

        return null;
    }
}
```

**Tests:** `tests/Unit/Parsers/ItemSavingThrowsParserTest.php`
- Parse DC 10 Charisma save
- Parse DC 15 Wisdom save
- Handle items without saves

---

### Step 2.4: Update ItemImporter - Import Saving Throws

**File:** `app/Services/Importers/ItemImporter.php`

Add trait:
```php
use ParsesItemSavingThrows;
```

Add method:
```php
protected function importSavingThrows(Item $item, array $itemData): void
{
    $saveData = $this->parseItemSavingThrow($itemData['description'] ?? '');

    if (!$saveData) {
        return; // No saving throw found
    }

    // Look up ability score
    $abilityScore = AbilityScore::where('code', $saveData['ability_code'])->first();

    if (!$abilityScore) {
        Log::warning("Ability score not found: {$saveData['ability_code']} (for item: {$item->name})");
        return;
    }

    // Create or update saving throw
    EntitySavingThrow::updateOrCreate(
        [
            'entity_type' => Item::class,
            'entity_id' => $item->id,
            'ability_score_id' => $abilityScore->id,
            'is_initial_save' => $saveData['is_initial_save'],
        ],
        [
            'save_effect' => $saveData['save_effect'],
            'save_modifier' => 'none',
        ]
    );
}
```

Call in `importItem()`:
```php
// Import saving throws
$this->importSavingThrows($item, $itemData);
```

**Tests:** `tests/Feature/Importers/ItemSavingThrowsImportTest.php`
- Import Wand of Smiles with DC 10 CHA save
- Reimport updates save data
- Handle items without saves

---

### Step 2.5: Update ItemResource - Expose Saving Throws

**File:** `app/Http/Resources/ItemResource.php`

Add to `toArray()`:
```php
'saving_throws' => EntitySavingThrowResource::collection($this->whenLoaded('savingThrows')),
```

Create resource (if not exists):
**File:** `app/Http/Resources/EntitySavingThrowResource.php`

```php
class EntitySavingThrowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ability_score' => AbilityScoreResource::make($this->whenLoaded('abilityScore')),
            'save_effect' => $this->save_effect,
            'save_modifier' => $this->save_modifier,
            'is_initial_save' => $this->is_initial_save,
        ];
    }
}
```

**Tests:** `tests/Feature/Api/ItemSavingThrowsApiTest.php`
- GET /api/v1/items/wand-of-smiles?include=savingThrows
- Verify save data in response

---

## 📊 Final API Response Example

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
      "charges_cost_formula": "1 per spell level"
    },
    {
      "id": 167,
      "name": "Lesser Restoration",
      "level": 2,
      "charges_cost_min": 2,
      "charges_cost_max": 2,
      "charges_cost_formula": null
    }
  ],
  "saving_throws": []
}
```

```json
{
  "id": 2156,
  "name": "Wand of Smiles",
  "slug": "wand-of-smiles",
  "charges_max": 3,
  "recharge_formula": "all",
  "recharge_timing": "dawn",
  "spells": [],
  "saving_throws": [
    {
      "ability_score": {
        "code": "CHA",
        "name": "Charisma"
      },
      "save_effect": "negates",
      "save_modifier": "none",
      "is_initial_save": true
    }
  ]
}
```

---

## ✅ Testing Checklist

### Unit Tests (Parsers)
- [ ] ParsesItemSpells: Fixed costs
- [ ] ParsesItemSpells: Variable costs
- [ ] ParsesItemSpells: Free spells
- [ ] ParsesItemSpells: Multiple spells
- [ ] ParsesItemSavingThrows: DC + ability extraction

### Feature Tests (Importers)
- [ ] Import Staff of Healing spells
- [ ] Import Wand of Smiles saving throw
- [ ] Reimport updates data
- [ ] Handle missing spells gracefully

### API Tests
- [ ] Include spells in ItemResource
- [ ] Include savingThrows in ItemResource
- [ ] Filter items by spell
- [ ] Filter items by save DC

---

## 🎯 Estimated Effort

- **Phase 1 (Spell Charges):** 4-6 hours
- **Phase 2 (Saving Throws):** 2-3 hours
- **Total:** 6-9 hours with TDD

---

## 🚀 Ready to Start?

Begin with Phase 1, Step 1.1: Create migration for spell charge costs!
