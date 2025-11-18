# Item Enhancements: Magic Flag, Modifiers, and Abilities Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add missing XML parsing for magic flag, modifiers, and item abilities to complete the Item importer feature set.

**Architecture:** Extend existing ItemXmlParser to extract `<magic>`, `<modifier>`, and `<roll>` elements → ItemImporter persists to database using polymorphic modifiers table and item_abilities table → Reconstruction tests verify completeness. Follows established RaceImporter pattern for modifiers.

**Tech Stack:** Laravel 12.x, PHPUnit, SimpleXML, Polymorphic relationships

---

## Background

### Current State Analysis

**What's Missing:**
1. **Magic Flag:** `<magic>YES</magic>` tag in XML not parsed (650+ occurrences in items-magic files)
2. **Attunement Parsing:** `<detail>rare (requires attunement)</detail>` not parsed from detail field (only checking description text)
3. **Modifiers:** `<modifier category="bonus">ranged attacks +1</modifier>` not parsed (800-1,200 estimated across all files)
4. **Item Abilities:** `<roll>` elements not parsed (100-200 estimated for charges, spell abilities)

**Impact:**
- `items.is_magic` column doesn't exist (need migration)
- `items.requires_attunement` mostly false (only 1 item out of 1,942 has it set correctly)
- `modifiers` table has 0 Item references (should have ~800-1,200)
- `item_abilities` table is empty (should have ~100-200)

**XML Evidence:**
```xml
<item>
  <name>Arrow +1</name>
  <detail>rare (requires attunement)</detail>
  <magic>YES</magic>
  <modifier category="bonus">ranged attacks +1</modifier>
  <modifier category="bonus">ranged damage +1</modifier>
  <roll>2d4+2</roll>
</item>
```

---

## Task 1: Add is_magic Column to Items Table

**Files:**
- Create: `database/migrations/2025_11_18_[timestamp]_add_is_magic_to_items_table.php`
- Modify: `app/Models/Item.php` (add to fillable and casts)
- Modify: `database/factories/ItemFactory.php` (add is_magic field)
- Modify: `app/Http/Resources/ItemResource.php` (add is_magic field)

**Step 1: Create migration**

Run: `docker compose exec php php artisan make:migration add_is_magic_to_items_table --table=items`

**Step 2: Write migration**

Content for migration file:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_magic')->default(false)->after('requires_attunement');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('is_magic');
        });
    }
};
```

**Step 3: Update Item model**

File: `app/Models/Item.php`

Add to fillable array (after 'requires_attunement'):
```php
'is_magic',
```

Add to casts array (after 'requires_attunement'):
```php
'is_magic' => 'boolean',
```

**Step 4: Update ItemFactory**

File: `database/factories/ItemFactory.php`

Add to definition() return array (after 'requires_attunement'):
```php
'is_magic' => false,
```

Update magic() state to set is_magic:
```php
public function magic(): static
{
    return $this->state(fn (array $attributes) => [
        'is_magic' => true,
        'rarity' => fake()->randomElement(['uncommon', 'rare', 'very rare', 'legendary']),
        'requires_attunement' => fake()->boolean(60),
    ]);
}
```

**Step 5: Update ItemResource**

File: `app/Http/Resources/ItemResource.php`

Add field after 'requires_attunement':
```php
'is_magic' => $this->is_magic,
```

**Step 6: Run migration**

Run: `docker compose exec php php artisan migrate`
Expected: "Migrated: 2025_11_18_[timestamp]_add_is_magic_to_items_table"

**Step 7: Commit**

```bash
git add database/migrations/ app/Models/Item.php database/factories/ItemFactory.php app/Http/Resources/ItemResource.php
git commit -m "feat: add is_magic boolean column to items table"
```

---

## Task 2: Parse Magic Flag in ItemXmlParser

**Files:**
- Modify: `app/Services/Parsers/ItemXmlParser.php` (add magic parsing)

**Step 1: Add is_magic to parseItem() return array**

File: `app/Services/Parsers/ItemXmlParser.php:21-43`

Add after 'requires_attunement' (line 29):
```php
'is_magic' => $this->parseMagic($element),
```

**Step 2: Add parseMagic() method**

Add method after parseAttunement() (around line 67):
```php
private function parseMagic(SimpleXMLElement $element): bool
{
    return strtoupper((string) $element->magic) === 'YES';
}
```

**Step 3: Verify ItemImporter handles is_magic**

File: `app/Services/Importers/ItemImporter.php:31-48`

Check that updateOrCreate includes 'is_magic' in the data array.
The line should already be there from ItemImporter's use of all parsed fields.
If not, add it explicitly:
```php
'is_magic' => $itemData['is_magic'],
```

**Step 4: Commit**

```bash
git add app/Services/Parsers/ItemXmlParser.php app/Services/Importers/ItemImporter.php
git commit -m "feat: parse and import magic flag from item XML"
```

---

## Task 3: Fix Attunement Parsing from Detail Field

**Files:**
- Modify: `app/Services/Parsers/ItemXmlParser.php` (fix parseAttunement method)

**Problem:** Currently only checks description text, but attunement is in `<detail>` field as "rare (requires attunement)"

**Step 1: Update parseItem() to pass detail field to parseAttunement()**

File: `app/Services/Parsers/ItemXmlParser.php:21-43`

Change line 29 from:
```php
'requires_attunement' => $this->parseAttunement($text),
```

To:
```php
'requires_attunement' => $this->parseAttunement($text, (string) $element->detail),
```

**Step 2: Update parseAttunement() method signature and logic**

File: `app/Services/Parsers/ItemXmlParser.php` (around line 77-80)

Replace:
```php
private function parseAttunement(string $text): bool
{
    return stripos($text, 'requires attunement') !== false;
}
```

With:
```php
private function parseAttunement(string $text, string $detail): bool
{
    // Check detail field first (primary location): "rare (requires attunement)"
    if (stripos($detail, 'requires attunement') !== false) {
        return true;
    }

    // Fallback: check description text (secondary location)
    return stripos($text, 'requires attunement') !== false;
}
```

**Step 3: Add reconstruction test for attunement parsing**

File: `tests/Feature/Importers/ItemXmlReconstructionTest.php`

Add test after existing tests:
```php
#[Test]
public function it_parses_attunement_from_detail_field()
{
    $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Cloak of Protection</name>
    <detail>uncommon (requires attunement)</detail>
    <type>W</type>
    <magic>YES</magic>
    <text>You gain a +1 bonus to AC and saving throws while you wear this cloak.

Source: Dungeon Master's Guide (2014) p. 159</text>
  </item>
</compendium>
XML;

    // Parse and import
    $items = $this->parser->parse($originalXml);
    $item = $this->importer->import($items[0]);

    // Verify attunement parsed from detail field
    $this->assertTrue($item->requires_attunement);
    $this->assertEquals('uncommon', $item->rarity);
    $this->assertTrue($item->is_magic);
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=it_parses_attunement_from_detail_field`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Parsers/ItemXmlParser.php tests/Feature/Importers/ItemXmlReconstructionTest.php
git commit -m "fix: parse requires_attunement from detail field instead of description"
```

---

## Task 4: Add Modifier Parsing to ItemXmlParser

**Files:**
- Modify: `app/Services/Parsers/ItemXmlParser.php` (add modifier parsing)
- Reference: `app/Services/Parsers/RaceXmlParser.php:126-141` (modifier parsing pattern)

**Step 1: Add modifiers to parseItem() return array**

File: `app/Services/Parsers/ItemXmlParser.php:21-43`

Add after 'proficiencies' (line 42):
```php
'modifiers' => $this->parseModifiers($element),
```

**Step 2: Add parseModifiers() method**

Add method after extractProficiencies() (around line 115):
```php
private function parseModifiers(SimpleXMLElement $element): array
{
    $modifiers = [];

    foreach ($element->modifier as $modifierElement) {
        $category = (string) $modifierElement['category']; // "bonus", "set", etc.
        $text = trim((string) $modifierElement);

        // Parse the modifier text: "ranged attacks +1" or "AC +2"
        $modifiers[] = [
            'category' => $category,
            'text' => $text,
        ];
    }

    return $modifiers;
}
```

**Step 3: Verify parseModifiers() extracts data correctly**

Expected output for `<modifier category="bonus">ranged attacks +1</modifier>`:
```php
[
    'category' => 'bonus',
    'text' => 'ranged attacks +1',
]
```

**Step 4: Commit**

```bash
git add app/Services/Parsers/ItemXmlParser.php
git commit -m "feat: parse modifier elements from item XML"
```

---

## Task 4: Import Modifiers in ItemImporter

**Files:**
- Modify: `app/Services/Importers/ItemImporter.php` (add importModifiers method)
- Reference: `app/Services/Importers/RaceImporter.php:115-129` (modifier import pattern)

**Step 1: Call importModifiers() in import() method**

File: `app/Services/Importers/ItemImporter.php:import()` method

Add after `$this->importProficiencies($item, $itemData['proficiencies']);` (around line 72):
```php
// Import modifiers (polymorphic)
$this->importModifiers($item, $itemData['modifiers']);
```

**Step 2: Add importModifiers() method**

Add method after importProficiencies() (around line 131):
```php
private function importModifiers(Item $item, array $modifiers): void
{
    // Clear existing modifiers
    $item->modifiers()->delete();

    foreach ($modifiers as $modData) {
        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'modifier_category' => $modData['category'],
            'modifier_text' => $modData['text'],
        ]);
    }
}
```

**Step 3: Add Modifier import to use statements**

File: `app/Services/Importers/ItemImporter.php:top of file`

Add to use statements:
```php
use App\Models\Modifier;
```

**Step 4: Verify Item model has modifiers relationship**

File: `app/Models/Item.php`

Check if modifiers() relationship exists. If not, add:
```php
public function modifiers(): MorphMany
{
    return $this->morphMany(Modifier::class, 'reference');
}
```

Add import if needed:
```php
use Illuminate\Database\Eloquent\Relations\MorphMany;
```

**Step 5: Commit**

```bash
git add app/Services/Importers/ItemImporter.php app/Models/Item.php
git commit -m "feat: import modifiers from item XML to polymorphic modifiers table"
```

---

## Task 5: Add Item Ability Parsing to ItemXmlParser

**Files:**
- Modify: `app/Services/Parsers/ItemXmlParser.php` (add ability/roll parsing)

**Step 1: Add abilities to parseItem() return array**

File: `app/Services/Parsers/ItemXmlParser.php:21-43`

Add after 'modifiers' (line 43):
```php
'abilities' => $this->parseAbilities($element),
```

**Step 2: Add parseAbilities() method**

Add method after parseModifiers() (around line 130):
```php
private function parseAbilities(SimpleXMLElement $element): array
{
    $abilities = [];

    foreach ($element->roll as $rollElement) {
        $rollText = trim((string) $rollElement);

        // Extract roll formula if present (e.g., "1d4", "2d6")
        $rollFormula = null;
        if (preg_match('/(\d+d\d+(?:\s*[+\-]\s*\d+)?)/', $rollText, $matches)) {
            $rollFormula = $matches[1];
        }

        $abilities[] = [
            'ability_type' => 'roll', // Default type for <roll> elements
            'name' => $rollText,
            'description' => $rollText,
            'roll_formula' => $rollFormula,
            'sort_order' => count($abilities),
        ];
    }

    return $abilities;
}
```

**Step 3: Commit**

```bash
git add app/Services/Parsers/ItemXmlParser.php
git commit -m "feat: parse roll/ability elements from item XML"
```

---

## Task 6: Import Abilities in ItemImporter

**Files:**
- Modify: `app/Services/Importers/ItemImporter.php` (add importAbilities method)
- Modify: `database/migrations/2025_11_17_214319_create_item_related_tables.php` (verify schema supports roll_formula)

**Step 1: Check if item_abilities table has roll_formula column**

Run: `docker compose exec php php artisan tinker`
Then: `Schema::hasColumn('item_abilities', 'roll_formula')`

If FALSE, need to add migration for roll_formula column.

**Step 2: Add migration for roll_formula if needed**

If column doesn't exist, create migration:
Run: `docker compose exec php php artisan make:migration add_roll_formula_to_item_abilities_table --table=item_abilities`

Content:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_abilities', function (Blueprint $table) {
            $table->string('roll_formula', 50)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('item_abilities', function (Blueprint $table) {
            $table->dropColumn('roll_formula');
        });
    }
};
```

Run: `docker compose exec php php artisan migrate`

**Step 3: Update ItemAbility model fillable**

File: `app/Models/ItemAbility.php`

Add to fillable array:
```php
'roll_formula',
```

**Step 4: Call importAbilities() in ItemImporter**

File: `app/Services/Importers/ItemImporter.php:import()` method

Add after `$this->importModifiers($item, $itemData['modifiers']);` (around line 75):
```php
// Import abilities
$this->importAbilities($item, $itemData['abilities']);
```

**Step 5: Add importAbilities() method**

Add method after importModifiers() (around line 145):
```php
private function importAbilities(Item $item, array $abilities): void
{
    // Clear existing abilities
    $item->abilities()->delete();

    foreach ($abilities as $abilityData) {
        ItemAbility::create([
            'item_id' => $item->id,
            'ability_type' => $abilityData['ability_type'],
            'name' => $abilityData['name'],
            'description' => $abilityData['description'],
            'roll_formula' => $abilityData['roll_formula'] ?? null,
            'sort_order' => $abilityData['sort_order'],
        ]);
    }
}
```

**Step 6: Add ItemAbility import to use statements**

File: `app/Services/Importers/ItemImporter.php:top of file`

Add to use statements:
```php
use App\Models\ItemAbility;
```

**Step 7: Commit**

```bash
git add app/Services/Importers/ItemImporter.php app/Models/ItemAbility.php database/migrations/
git commit -m "feat: import item abilities from XML roll elements"
```

---

## Task 7: Add Reconstruction Tests for New Features

**Files:**
- Modify: `tests/Feature/Importers/ItemXmlReconstructionTest.php` (add 3 new tests)

**Step 1: Add test for magic flag**

Add test method after existing tests:
```php
#[Test]
public function it_reconstructs_magic_item_with_flag()
{
    $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>+1 Arrow</name>
    <detail>uncommon</detail>
    <type>A</type>
    <magic>YES</magic>
    <weight>0.05</weight>
    <text>You have a +1 bonus to attack and damage rolls made with this magic ammunition.

Source: Dungeon Master's Guide (2014) p. 150</text>
  </item>
</compendium>
XML;

    // Parse and import
    $items = $this->parser->parse($originalXml);
    $this->assertCount(1, $items);

    $item = $this->importer->import($items[0]);

    // Verify magic flag
    $this->assertTrue($item->is_magic);
    $this->assertEquals('uncommon', $item->rarity);
}
```

**Step 2: Add test for modifiers**

Add test method:
```php
#[Test]
public function it_reconstructs_item_with_modifiers()
{
    $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Arrow +1</name>
    <detail>uncommon</detail>
    <type>A</type>
    <magic>YES</magic>
    <weight>0.05</weight>
    <text>You have a +1 bonus to attack and damage rolls.

Source: Dungeon Master's Guide (2014) p. 150</text>
    <modifier category="bonus">ranged attacks +1</modifier>
    <modifier category="bonus">ranged damage +1</modifier>
  </item>
</compendium>
XML;

    // Parse and import
    $items = $this->parser->parse($originalXml);
    $item = $this->importer->import($items[0]);

    // Verify modifiers
    $item->load('modifiers');
    $this->assertCount(2, $item->modifiers);

    $modifiers = $item->modifiers->sortBy('modifier_text')->values();
    $this->assertEquals('bonus', $modifiers[0]->modifier_category);
    $this->assertEquals('ranged attacks +1', $modifiers[0]->modifier_text);
    $this->assertEquals('bonus', $modifiers[1]->modifier_category);
    $this->assertEquals('ranged damage +1', $modifiers[1]->modifier_text);
}
```

**Step 3: Add test for item abilities**

Add test method:
```php
#[Test]
public function it_reconstructs_item_with_abilities()
{
    $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Potion of Healing</name>
    <detail>common</detail>
    <type>P</type>
    <magic>YES</magic>
    <text>You regain hit points when you drink this potion.

Source: Dungeon Master's Guide (2014) p. 187</text>
    <roll>2d4+2</roll>
  </item>
</compendium>
XML;

    // Parse and import
    $items = $this->parser->parse($originalXml);
    $item = $this->importer->import($items[0]);

    // Verify abilities
    $item->load('abilities');
    $this->assertCount(1, $item->abilities);

    $ability = $item->abilities->first();
    $this->assertEquals('roll', $ability->ability_type);
    $this->assertEquals('2d4+2', $ability->roll_formula);
    $this->assertStringContainsString('2d4+2', $ability->name);
}
```

**Step 4: Run tests to verify they FAIL**

Run: `docker compose exec php php artisan test --filter=ItemXmlReconstructionTest`
Expected: 3 new tests FAIL (parser/importer not complete yet)

**Step 5: Commit**

```bash
git add tests/Feature/Importers/ItemXmlReconstructionTest.php
git commit -m "test: add reconstruction tests for magic flag, modifiers, and abilities"
```

---

## Task 8: Run Tests and Fix Issues

**Step 1: Run all reconstruction tests**

Run: `docker compose exec php php artisan test --filter=ItemXmlReconstructionTest`
Expected: All 8 tests PASS (5 original + 3 new)

**Step 2: If tests fail, debug and fix**

Common issues:
- Parser not extracting data correctly
- Importer not calling import methods
- Missing relationships in models
- Schema issues with item_abilities table

**Step 3: Run full test suite**

Run: `docker compose exec php php artisan test`
Expected: All tests PASS

**Step 4: Commit any fixes**

```bash
git add -A
git commit -m "fix: resolve issues found by reconstruction tests"
```

---

## Task 9: Re-import Items to Populate New Data

**Step 1: Clear existing items**

Run: `docker compose exec php php artisan tinker`
Then: `DB::table('items')->truncate();`
Then: `DB::table('modifiers')->where('reference_type', 'App\Models\Item')->delete();`
Then: `DB::table('item_abilities')->truncate();`

**Step 2: Re-import all item XML files**

Run import commands for all 17 files:
```bash
docker compose exec php php artisan import:items import-files/items-base-phb.xml
docker compose exec php php artisan import:items import-files/items-dmg.xml
docker compose exec php php artisan import:items import-files/items-phb.xml
# ... (continue for all 17 files)
```

**Step 3: Verify data populated**

Run: `docker compose exec php php artisan tinker`
Then:
```php
echo "Magic items: " . DB::table('items')->where('is_magic', true)->count();
echo "Modifiers: " . DB::table('modifiers')->where('reference_type', 'App\Models\Item')->count();
echo "Item abilities: " . DB::table('item_abilities')->count();
```

Expected:
- Magic items: ~800-1,200
- Modifiers: ~800-1,200
- Item abilities: ~100-200

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: re-import all items with magic flags, modifiers, and abilities"
```

---

## Task 10: Update ItemResource to Include Related Data

**Files:**
- Modify: `app/Http/Resources/ItemResource.php` (add modifiers and abilities)

**Step 1: Add modifiers and abilities to ItemResource**

File: `app/Http/Resources/ItemResource.php`

Add to relationships section (after 'proficiencies'):
```php
'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
'abilities' => ItemAbilityResource::collection($this->whenLoaded('abilities')),
```

**Step 2: Verify ModifierResource exists**

Check if `app/Http/Resources/ModifierResource.php` exists.
If not, create it:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'modifier_category' => $this->modifier_category,
            'modifier_text' => $this->modifier_text,
        ];
    }
}
```

**Step 3: Update ItemAbilityResource to include roll_formula**

File: `app/Http/Resources/ItemAbilityResource.php`

Add field after 'description':
```php
'roll_formula' => $this->roll_formula,
```

**Step 4: Commit**

```bash
git add app/Http/Resources/ItemResource.php app/Http/Resources/ModifierResource.php app/Http/Resources/ItemAbilityResource.php
git commit -m "feat: add modifiers and abilities to Item API resource"
```

---

## Success Criteria

- [x] `items.is_magic` column exists and populated (~800-1,200 magic items)
- [x] Magic flag parsed from `<magic>YES</magic>` XML tags
- [x] `items.requires_attunement` correctly set from detail field (~400-600 items)
- [x] Attunement parsed from `<detail>rare (requires attunement)</detail>` format
- [x] `modifiers` table has Item references (~800-1,200 records)
- [x] Modifiers parsed from `<modifier>` XML elements
- [x] `item_abilities` table populated (~100-200 records)
- [x] Abilities parsed from `<roll>` XML elements
- [x] 4 new reconstruction tests passing (magic, attunement, modifiers, abilities)
- [x] All existing tests still passing
- [x] ItemResource includes new relationships
- [x] Data successfully re-imported

---

## Estimated Time

**Total:** 3.5-4.5 hours
- Tasks 1-2: 45 minutes (magic flag: migration, parser, tests)
- Task 3: 30 minutes (attunement fix: parser update, test)
- Tasks 4-5: 1 hour (modifiers: parser, importer, tests)
- Tasks 6-7: 1 hour (abilities: parser, importer, schema, tests)
- Tasks 8-9: 45 minutes (reconstruction tests, debugging)
- Tasks 10-11: 30 minutes (re-import, API resources)

---

## Notes

**Follow TDD:** Write reconstruction tests first (Task 7), watch them fail, implement features (Tasks 1-6), watch them pass.

**Reference Patterns:**
- Modifier parsing: `app/Services/Parsers/RaceXmlParser.php:126-141`
- Modifier importing: `app/Services/Importers/RaceImporter.php:115-129`
- Polymorphic relationships: Existing Item sources/proficiencies

**Data Volume:**
- Magic flag: 650+ items in items-magic-phb+dmg.xml alone
- Modifiers: 432 in items-magic-phb+dmg.xml (estimate 800-1,200 total)
- Abilities: 61 in items-magic-phb+dmg.xml (estimate 100-200 total)

**Commit Frequently:** Each task has a commit step - use them!
