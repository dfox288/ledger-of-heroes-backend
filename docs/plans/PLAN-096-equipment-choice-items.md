# Implementation Plan: Structured Item Type References for Equipment Choices

**Issue:** #96 - Add structured item type references for equipment choices
**Branch:** `feature/issue-96-equipment-choice-items`
**Runner:** Sail (`docker compose exec php`)

## Overview

Transform equipment choices from text-only descriptions to structured data that links to either specific items or proficiency type categories. This enables the frontend character builder to offer item selection within categories (e.g., "pick any martial weapon").

## Current State

```
entity_items
├── item_id (nullable) → items.id
├── quantity
├── is_choice, choice_group, choice_option
├── description (text like "a martial weapon and a shield")
└── proficiency_subcategory (unused, always null)
```

## Target State

```
entity_items (container only)
├── is_choice, choice_group, choice_option
├── description (display text)
└── choice_items (hasMany → equipment_choice_items)

equipment_choice_items
├── entity_item_id → entity_items.id
├── proficiency_type_id → proficiency_types.id (category reference)
├── item_id → items.id (specific item)
├── quantity
└── sort_order
```

---

## Phase 1: Database Schema

### Task 1.1: Create equipment_choice_items migration

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_equipment_choice_items_table.php`

```php
Schema::create('equipment_choice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entity_item_id')->constrained('entity_items')->cascadeOnDelete();
    $table->foreignId('proficiency_type_id')->nullable()->constrained('proficiency_types')->nullOnDelete();
    $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
    $table->unsignedTinyInteger('quantity')->default(1);
    $table->unsignedTinyInteger('sort_order')->default(0);

    $table->index('entity_item_id');
});
```

**Command:** `docker compose exec php php artisan make:migration create_equipment_choice_items_table`

**Verification:**
- [ ] Migration created
- [ ] `docker compose exec php php artisan migrate` succeeds
- [ ] Table exists in database

### Task 1.2: Create EquipmentChoiceItem model

**File:** `app/Models/EquipmentChoiceItem.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentChoiceItem extends BaseModel
{
    protected $fillable = [
        'entity_item_id',
        'proficiency_type_id',
        'item_id',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function entityItem(): BelongsTo
    {
        return $this->belongsTo(EntityItem::class);
    }

    public function proficiencyType(): BelongsTo
    {
        return $this->belongsTo(ProficiencyType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
```

**Verification:**
- [ ] Model created
- [ ] Relationships defined

### Task 1.3: Add choiceItems relationship to EntityItem

**File:** `app/Models/EntityItem.php`

Add relationship method:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function choiceItems(): HasMany
{
    return $this->hasMany(EquipmentChoiceItem::class)->orderBy('sort_order');
}
```

**Verification:**
- [ ] Relationship added
- [ ] Tinker test: `EntityItem::first()->choiceItems` returns Collection

### Task 1.4: Create EquipmentChoiceItem factory

**File:** `database/factories/EquipmentChoiceItemFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\EntityItem;
use App\Models\EquipmentChoiceItem;
use App\Models\Item;
use App\Models\ProficiencyType;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentChoiceItemFactory extends Factory
{
    protected $model = EquipmentChoiceItem::class;

    public function definition(): array
    {
        return [
            'entity_item_id' => EntityItem::factory(),
            'proficiency_type_id' => null,
            'item_id' => null,
            'quantity' => 1,
            'sort_order' => 0,
        ];
    }

    public function withCategory(string $slug = 'martial-weapons'): static
    {
        return $this->state(fn () => [
            'proficiency_type_id' => ProficiencyType::where('slug', $slug)->first()?->id,
            'item_id' => null,
        ]);
    }

    public function withItem(?Item $item = null): static
    {
        return $this->state(fn () => [
            'proficiency_type_id' => null,
            'item_id' => $item?->id ?? Item::factory(),
        ]);
    }
}
```

**Verification:**
- [ ] Factory created
- [ ] Tinker: `EquipmentChoiceItem::factory()->create()` works

---

## Phase 2: Data Migration

### Task 2.1: Create data migration to move existing item_id/quantity

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_migrate_entity_items_to_choice_items.php`

```php
public function up(): void
{
    // Move existing item_id + quantity to equipment_choice_items
    DB::statement("
        INSERT INTO equipment_choice_items (entity_item_id, item_id, quantity, sort_order)
        SELECT id, item_id, quantity, 0
        FROM entity_items
        WHERE item_id IS NOT NULL
    ");
}

public function down(): void
{
    // Restore item_id/quantity from first choice_item
    DB::statement("
        UPDATE entity_items ei
        INNER JOIN (
            SELECT entity_item_id, item_id, quantity
            FROM equipment_choice_items
            WHERE sort_order = 0
        ) eci ON ei.id = eci.entity_item_id
        SET ei.item_id = eci.item_id,
            ei.quantity = eci.quantity
    ");

    DB::table('equipment_choice_items')->truncate();
}
```

**Note:** Do NOT drop `item_id`/`quantity` columns yet - keep for rollback safety. Remove in later cleanup migration.

**Verification:**
- [ ] Migration created
- [ ] `docker compose exec php php artisan migrate` succeeds
- [ ] Data migrated: `SELECT COUNT(*) FROM equipment_choice_items` matches count of non-null item_ids

---

## Phase 3: Parser Updates

### Task 3.1: Update ClassXmlParser to extract compound items

**File:** `app/Services/Parsers/ClassXmlParser.php`

Update `parseEquipmentChoices()` to return structured choice_items:

```php
// Current return:
[
    'description' => 'a martial weapon and a shield',
    'is_choice' => true,
    ...
]

// New return:
[
    'description' => 'a martial weapon and a shield',
    'is_choice' => true,
    'choice_items' => [
        ['type' => 'category', 'value' => 'martial', 'quantity' => 1],
        ['type' => 'item', 'value' => 'shield', 'quantity' => 1],
    ],
    ...
]
```

**Patterns to detect:**
- `any martial weapon` → category: martial
- `any simple weapon` → category: simple
- `a martial weapon` → category: martial
- `two martial weapons` → category: martial, quantity: 2
- `a shield` → item: shield
- `shortbow and quiver of arrows (20)` → item: shortbow + item: arrows (qty 20)

**Add new method:**
```php
private function parseCompoundItem(string $text): array
{
    $items = [];

    // Split on " and " for compound items
    $parts = preg_split('/\s+and\s+/i', $text);

    foreach ($parts as $part) {
        $part = trim($part);

        // Extract quantity
        $quantity = 1;
        if (preg_match('/^(two|three|four|five|six|seven|eight|nine|ten|twenty)\s+/i', $part, $m)) {
            $quantity = $this->wordToNumber($m[1]);
            $part = preg_replace('/^(two|three|four|five|six|seven|eight|nine|ten|twenty)\s+/i', '', $part);
        }

        // Check for category references
        if (preg_match('/^(?:any\s+)?(martial|simple)\s+(?:melee\s+)?weapons?$/i', $part, $m)) {
            $items[] = ['type' => 'category', 'value' => strtolower($m[1]), 'quantity' => $quantity];
        } elseif (preg_match('/^(?:any\s+)?(martial|simple)\s+(melee|ranged)\s+weapons?$/i', $part, $m)) {
            $items[] = ['type' => 'category', 'value' => strtolower($m[1]) . '_' . strtolower($m[2]), 'quantity' => $quantity];
        } else {
            // Specific item - clean up for matching
            $itemName = preg_replace('/^(a|an|the)\s+/i', '', $part);
            // Handle "quiver of arrows (20)" → arrows, qty 20
            if (preg_match('/quiver\s+of\s+(\w+)\s*\((\d+)\)/i', $itemName, $m)) {
                $items[] = ['type' => 'item', 'value' => $m[1], 'quantity' => (int)$m[2]];
            } else {
                $items[] = ['type' => 'item', 'value' => $itemName, 'quantity' => $quantity];
            }
        }
    }

    return $items;
}
```

**Verification:**
- [ ] Unit test for compound item parsing
- [ ] Test: "a martial weapon and a shield" → 2 choice_items
- [ ] Test: "two martial weapons" → 1 choice_item with quantity=2

### Task 3.2: Write unit tests for parser changes

**File:** `tests/Unit/Parsers/ClassXmlParserEquipmentTest.php`

```php
#[Test]
public function it_parses_compound_equipment_choices(): void
{
    $xml = '<compendium>
        <class>
            <name>Fighter</name>
            <hd>10</hd>
            <autolevel level="1">
                <feature>
                    <name>Starting Fighter</name>
                    <text>You begin play with the following equipment:
• (a) a martial weapon and a shield or (b) two martial weapons
                    </text>
                </feature>
            </autolevel>
        </class>
    </compendium>';

    $result = (new ClassXmlParser())->parse($xml);
    $items = $result[0]['equipment']['items'];

    // Option A: martial weapon + shield
    $optionA = collect($items)->firstWhere('choice_option', 1);
    $this->assertCount(2, $optionA['choice_items']);
    $this->assertEquals('category', $optionA['choice_items'][0]['type']);
    $this->assertEquals('martial', $optionA['choice_items'][0]['value']);

    // Option B: two martial weapons
    $optionB = collect($items)->firstWhere('choice_option', 2);
    $this->assertCount(1, $optionB['choice_items']);
    $this->assertEquals(2, $optionB['choice_items'][0]['quantity']);
}
```

**Verification:**
- [ ] Test file created
- [ ] Tests pass: `docker compose exec php php artisan test --filter=ClassXmlParserEquipmentTest`

---

## Phase 4: Importer Updates

### Task 4.1: Create MatchesProficiencyCategories trait

**File:** `app/Services/Importers/Concerns/MatchesProficiencyCategories.php`

```php
<?php

namespace App\Services\Importers\Concerns;

use App\Models\ProficiencyType;

trait MatchesProficiencyCategories
{
    protected function matchProficiencyCategory(string $category): ?ProficiencyType
    {
        // Map category values to proficiency_type slugs
        $slugMap = [
            'martial' => 'martial-weapons',
            'simple' => 'simple-weapons',
            'martial_melee' => 'martial-weapons', // Use parent category
            'martial_ranged' => 'martial-weapons',
            'simple_melee' => 'simple-weapons',
            'simple_ranged' => 'simple-weapons',
            'light' => 'light-armor',
            'medium' => 'medium-armor',
            'heavy' => 'heavy-armor',
            'shields' => 'shields',
        ];

        $slug = $slugMap[$category] ?? $category;

        return ProficiencyType::where('slug', $slug)->first();
    }
}
```

**Verification:**
- [ ] Trait created
- [ ] Can match 'martial' to 'Martial Weapons' proficiency type

### Task 4.2: Update ImportsEntityItems trait

**File:** `app/Services/Importers/Concerns/ImportsEntityItems.php`

Add method to import choice_items:

```php
use App\Models\EquipmentChoiceItem;

protected function importChoiceItems(EntityItem $entityItem, array $choiceItems): void
{
    foreach ($choiceItems as $index => $choiceItem) {
        $data = [
            'quantity' => $choiceItem['quantity'] ?? 1,
            'sort_order' => $index,
        ];

        if ($choiceItem['type'] === 'category') {
            $profType = $this->matchProficiencyCategory($choiceItem['value']);
            $data['proficiency_type_id'] = $profType?->id;
        } else {
            // type === 'item'
            $item = $this->matchItemByDescription($choiceItem['value']);
            $data['item_id'] = $item?->id;
        }

        $entityItem->choiceItems()->create($data);
    }
}
```

**Verification:**
- [ ] Method added
- [ ] Uses existing matchItemByDescription() and new matchProficiencyCategory()

### Task 4.3: Update ClassImporter.importEquipment()

**File:** `app/Services/Importers/ClassImporter.php`

```php
private function importEquipment(CharacterClass $class, array $equipmentData): void
{
    if (empty($equipmentData['items'])) {
        return;
    }

    // Clear existing equipment and their choice items (cascade)
    $class->equipment()->delete();

    foreach ($equipmentData['items'] as $itemData) {
        // Create container entity_item
        $entityItem = $class->equipment()->create([
            'description' => $itemData['description'],
            'is_choice' => $itemData['is_choice'],
            'choice_group' => $itemData['choice_group'] ?? null,
            'choice_option' => $itemData['choice_option'] ?? null,
            'quantity' => $itemData['quantity'] ?? 1, // Keep for backwards compat
        ]);

        // Import structured choice_items if present
        if (!empty($itemData['choice_items'])) {
            $this->importChoiceItems($entityItem, $itemData['choice_items']);
        } else {
            // Legacy: single item, create one choice_item from item_id
            $item = $this->matchItemByDescription($itemData['description']);
            if ($item) {
                $entityItem->choiceItems()->create([
                    'item_id' => $item->id,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'sort_order' => 0,
                ]);
            }
        }
    }
}
```

**Verification:**
- [ ] Method updated
- [ ] Import creates equipment_choice_items records

### Task 4.4: Write feature test for importer

**File:** `tests/Feature/Importers/ClassImporterEquipmentTest.php`

```php
#[Test]
public function it_imports_compound_equipment_choices(): void
{
    // Setup: ensure proficiency types and items exist
    $martialWeapons = ProficiencyType::where('slug', 'martial-weapons')->first();
    $shield = Item::where('slug', 'shield')->first();

    // Run import
    $importer = new ClassImporter();
    $importer->import($this->getFighterXml());

    // Verify
    $fighter = CharacterClass::where('slug', 'fighter')->first();
    $equipment = $fighter->equipment()->with('choiceItems.proficiencyType', 'choiceItems.item')->get();

    $choice2Option1 = $equipment->firstWhere(fn($e) =>
        $e->choice_group === 'choice_2' && $e->choice_option === 1
    );

    $this->assertCount(2, $choice2Option1->choiceItems);
    $this->assertEquals($martialWeapons->id, $choice2Option1->choiceItems[0]->proficiency_type_id);
    $this->assertEquals($shield->id, $choice2Option1->choiceItems[1]->item_id);
}
```

**Verification:**
- [ ] Test file created
- [ ] Test passes with actual import

---

## Phase 5: API Resource Updates

### Task 5.1: Create EquipmentChoiceItemResource

**File:** `app/Http/Resources/EquipmentChoiceItemResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentChoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'proficiency_type' => new ProficiencyTypeResource($this->whenLoaded('proficiencyType')),
            'item' => new ItemResource($this->whenLoaded('item')),
            'quantity' => $this->quantity,
        ];
    }
}
```

### Task 5.2: Update EntityItemResource

**File:** `app/Http/Resources/EntityItemResource.php`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'item_id' => $this->item_id, // Deprecated - keep for backwards compat
        'item' => new ItemResource($this->whenLoaded('item')), // Deprecated
        'quantity' => $this->quantity, // Deprecated
        'is_choice' => $this->is_choice,
        'choice_group' => $this->choice_group,
        'choice_option' => $this->choice_option,
        'description' => $this->description,
        // New structured field
        'choice_items' => EquipmentChoiceItemResource::collection(
            $this->whenLoaded('choiceItems')
        ),
    ];
}
```

### Task 5.3: Update ClassController eager loading

**File:** `app/Http/Controllers/Api/ClassController.php`

Find where equipment is loaded and add nested eager loading:

```php
->with([
    'equipment.choiceItems.proficiencyType',
    'equipment.choiceItems.item',
])
```

### Task 5.4: Write feature test for API response

**File:** `tests/Feature/Api/ClassEquipmentApiTest.php`

```php
#[Test]
public function it_returns_choice_items_in_equipment_response(): void
{
    $class = CharacterClass::where('slug', 'fighter')->first();

    $response = $this->getJson("/api/v1/classes/{$class->slug}");

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'equipment' => [
                '*' => [
                    'choice_items' => [
                        '*' => [
                            'proficiency_type',
                            'item',
                            'quantity',
                        ],
                    ],
                ],
            ],
        ],
    ]);
}
```

---

## Phase 6: Quality Gates

### Task 6.1: Run all test suites

```bash
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 6.2: Run Pint

```bash
docker compose exec php ./vendor/bin/pint
```

### Task 6.3: Re-import test data

```bash
docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:classes --env=testing
```

### Task 6.4: Run search tests

```bash
docker compose exec php php artisan test --testsuite=Feature-Search --filter=Class
```

---

## Phase 7: Cleanup (After Frontend Confirms)

### Task 7.1: Remove deprecated columns

**Migration:** `drop_deprecated_columns_from_entity_items_table.php`

```php
public function up(): void
{
    Schema::table('entity_items', function (Blueprint $table) {
        $table->dropForeign(['item_id']);
        $table->dropColumn(['item_id', 'quantity', 'proficiency_subcategory', 'choice_description']);
    });
}
```

**Note:** Only run after frontend has updated to use `choice_items` field.

---

## Verification Checklist

- [ ] All migrations run without error
- [ ] Existing equipment data migrated to choice_items
- [ ] Parser extracts compound items correctly
- [ ] Importer creates equipment_choice_items records
- [ ] API returns choice_items with proficiency_type and item
- [ ] All test suites pass
- [ ] Code formatted with Pint
- [ ] CHANGELOG.md updated

## API Response Example

```json
{
  "data": {
    "slug": "fighter",
    "equipment": [
      {
        "id": 36,
        "is_choice": true,
        "choice_group": "choice_2",
        "choice_option": 1,
        "description": "a martial weapon and a shield",
        "choice_items": [
          {
            "proficiency_type": {
              "id": 6,
              "slug": "martial-weapons",
              "name": "Martial Weapons",
              "category": "weapon",
              "subcategory": "martial"
            },
            "item": null,
            "quantity": 1
          },
          {
            "proficiency_type": null,
            "item": {
              "id": 123,
              "slug": "shield",
              "name": "Shield"
            },
            "quantity": 1
          }
        ]
      }
    ]
  }
}
```
