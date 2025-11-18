# Item Importer Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement complete Item importer following the proven Spell/Race pattern with XML reconstruction tests, multi-source architecture, and polymorphic relationships.

**Architecture:** Parser extracts XML to normalized arrays → Importer persists to database with lookups → Reconstruction tests verify completeness. Multi-source via `entity_sources` polymorphic table, proficiencies via `proficiencies` polymorphic table, properties via M2M junction.

**Tech Stack:** Laravel 12.x, PHPUnit, SimpleXML, SQLite (testing), MySQL (production)

---

## Task 1: Fix Items Migration (Multi-Source Architecture)

**Files:**
- Modify: `database/migrations/2025_11_17_204910_create_items_table.php:42-43`

**Step 1: Read current migration**

Run: Read the items migration to see current schema

**Step 2: Remove single-source columns**

Find these lines (around line 42-43):
```php
$table->unsignedBigInteger('source_id');
$table->string('source_pages', 50);
```

Replace with:
```php
// Removed: source_id and source_pages - using entity_sources polymorphic table instead
```

**Step 3: Remove foreign key constraint**

Find this line (around line 55-57):
```php
$table->foreign('source_id')->references('id')->on('sources')->onDelete('cascade');
```

Delete it completely.

**Step 4: Verify migration syntax**

Run: `docker compose exec php php artisan migrate:status`
Expected: Migration listed but not yet run on fresh DB

**Step 5: Commit**

```bash
git add database/migrations/2025_11_17_204910_create_items_table.php
git commit -m "refactor: migrate items table to multi-source architecture"
```

---

## Task 2: Create ItemTypeSeeder

**Files:**
- Create: `database/seeders/ItemTypeSeeder.php`
- Reference: `database/seeders/SourceSeeder.php` (pattern)

**Step 1: Create seeder file**

Run: `docker compose exec php php artisan make:seeder ItemTypeSeeder`

**Step 2: Write seeder implementation**

Content for `database/seeders/ItemTypeSeeder.php`:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemTypeSeeder extends Seeder
{
    public function run(): void
    {
        $itemTypes = [
            ['code' => 'A', 'name' => 'Ammunition', 'description' => 'Arrows, bolts, sling bullets, and other projectiles'],
            ['code' => 'M', 'name' => 'Melee Weapon', 'description' => 'Weapons used for close combat'],
            ['code' => 'R', 'name' => 'Ranged Weapon', 'description' => 'Weapons used for ranged combat'],
            ['code' => 'LA', 'name' => 'Light Armor', 'description' => 'Armor that allows full dexterity bonus'],
            ['code' => 'MA', 'name' => 'Medium Armor', 'description' => 'Armor that allows partial dexterity bonus'],
            ['code' => 'HA', 'name' => 'Heavy Armor', 'description' => 'Armor that provides no dexterity bonus'],
            ['code' => 'S', 'name' => 'Shield', 'description' => 'Protective shield'],
            ['code' => 'G', 'name' => 'Adventuring Gear', 'description' => 'General equipment and supplies'],
            ['code' => '$', 'name' => 'Trade Goods', 'description' => 'Gems, art objects, and valuable commodities'],
            ['code' => 'P', 'name' => 'Potion', 'description' => 'Potions, oils, and elixirs'],
            ['code' => 'RD', 'name' => 'Rod', 'description' => 'Magic rods'],
            ['code' => 'RG', 'name' => 'Ring', 'description' => 'Magic rings'],
            ['code' => 'WD', 'name' => 'Wand', 'description' => 'Magic wands'],
            ['code' => 'SC', 'name' => 'Scroll', 'description' => 'Spell scrolls'],
            ['code' => 'ST', 'name' => 'Staff', 'description' => 'Quarterstaffs and magic staffs'],
        ];

        foreach ($itemTypes as $itemType) {
            DB::table('item_types')->updateOrInsert(
                ['code' => $itemType['code']],
                $itemType
            );
        }
    }
}
```

**Step 3: Run seeder to verify**

Run: `docker compose exec php php artisan db:seed --class=ItemTypeSeeder`
Expected: "Database seeding completed successfully"

**Step 4: Verify data in database**

Run: `docker compose exec php php artisan tinker`
Then: `DB::table('item_types')->count()`
Expected: 15

**Step 5: Commit**

```bash
git add database/seeders/ItemTypeSeeder.php
git commit -m "feat: add ItemTypeSeeder with 15 item type codes"
```

---

## Task 3: Create ItemPropertySeeder

**Files:**
- Create: `database/seeders/ItemPropertySeeder.php`

**Step 1: Create seeder file**

Run: `docker compose exec php php artisan make:seeder ItemPropertySeeder`

**Step 2: Write seeder implementation**

Content for `database/seeders/ItemPropertySeeder.php`:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemPropertySeeder extends Seeder
{
    public function run(): void
    {
        $properties = [
            ['code' => 'A', 'name' => 'Ammunition', 'description' => 'Weapon requires ammunition to make a ranged attack'],
            ['code' => 'F', 'name' => 'Finesse', 'description' => 'Use DEX modifier instead of STR for attack and damage rolls'],
            ['code' => 'H', 'name' => 'Heavy', 'description' => 'Small creatures have disadvantage on attack rolls'],
            ['code' => 'L', 'name' => 'Light', 'description' => 'Can be used for two-weapon fighting'],
            ['code' => 'LD', 'name' => 'Loading', 'description' => 'Can fire only one piece of ammunition per action'],
            ['code' => 'R', 'name' => 'Reach', 'description' => 'Adds 5 feet to reach for attack'],
            ['code' => 'T', 'name' => 'Thrown', 'description' => 'Can be thrown to make a ranged attack'],
            ['code' => '2H', 'name' => 'Two-Handed', 'description' => 'Requires two hands to use'],
            ['code' => 'V', 'name' => 'Versatile', 'description' => 'Can be used with one or two hands'],
            ['code' => 'M', 'name' => 'Martial', 'description' => 'Requires martial weapon proficiency'],
            ['code' => 'S', 'name' => 'Special', 'description' => 'Has special rules described in item description'],
        ];

        foreach ($properties as $property) {
            DB::table('item_properties')->updateOrInsert(
                ['code' => $property['code']],
                $property
            );
        }
    }
}
```

**Step 3: Run seeder to verify**

Run: `docker compose exec php php artisan db:seed --class=ItemPropertySeeder`
Expected: "Database seeding completed successfully"

**Step 4: Verify data in database**

Run: `docker compose exec php php artisan tinker`
Then: `DB::table('item_properties')->count()`
Expected: 11

**Step 5: Commit**

```bash
git add database/seeders/ItemPropertySeeder.php
git commit -m "feat: add ItemPropertySeeder with 11 weapon/armor properties"
```

---

## Task 4: Create Item Model

**Files:**
- Create: `app/Models/Item.php`
- Reference: `app/Models/Spell.php`, `app/Models/Race.php` (relationship patterns)

**Step 1: Create model file**

Run: `docker compose exec php php artisan make:model Item`

**Step 2: Write model implementation**

Content for `app/Models/Item.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'item_type_id',
        'rarity',
        'requires_attunement',
        'cost_cp',
        'weight',
        'damage_dice',
        'versatile_damage',
        'damage_type_id',
        'armor_class',
        'strength_requirement',
        'stealth_disadvantage',
        'weapon_range',
        'description',
    ];

    protected $casts = [
        'requires_attunement' => 'boolean',
        'cost_cp' => 'integer',
        'weight' => 'decimal:2',
        'armor_class' => 'integer',
        'strength_requirement' => 'integer',
        'stealth_disadvantage' => 'boolean',
    ];

    // Relationships

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(ItemProperty::class, 'item_property');
    }

    public function abilities(): HasMany
    {
        return $this->hasMany(ItemAbility::class);
    }

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }
}
```

**Step 3: Verify model loads**

Run: `docker compose exec php php artisan tinker`
Then: `App\Models\Item::class`
Expected: "App\Models\Item"

**Step 4: Commit**

```bash
git add app/Models/Item.php
git commit -m "feat: add Item model with polymorphic relationships"
```

---

## Task 5: Create ItemAbility Model

**Files:**
- Create: `app/Models/ItemAbility.php`

**Step 1: Create model file**

Run: `docker compose exec php php artisan make:model ItemAbility`

**Step 2: Write model implementation**

Content for `app/Models/ItemAbility.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAbility extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'name',
        'description',
    ];

    // Relationships

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
```

**Step 3: Verify model loads**

Run: `docker compose exec php php artisan tinker`
Then: `App\Models\ItemAbility::class`
Expected: "App\Models\ItemAbility"

**Step 4: Commit**

```bash
git add app/Models/ItemAbility.php
git commit -m "feat: add ItemAbility model for magic item abilities"
```

---

## Task 6: Create ItemFactory

**Files:**
- Create: `database/factories/ItemFactory.php`
- Reference: `database/factories/SpellFactory.php` (state pattern)

**Step 1: Create factory file**

Run: `docker compose exec php php artisan make:factory ItemFactory --model=Item`

**Step 2: Write factory implementation**

Content for `database/factories/ItemFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\DamageType;
use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'item_type_id' => ItemType::where('code', 'G')->first()->id,
            'rarity' => 'common',
            'requires_attunement' => false,
            'cost_cp' => fake()->numberBetween(1, 50000),
            'weight' => fake()->randomFloat(2, 0.1, 50),
            'description' => fake()->paragraph(),
        ];
    }

    public function weapon(): static
    {
        return $this->state(function (array $attributes) {
            $damageType = DamageType::whereIn('code', ['S', 'P', 'B'])->inRandomOrder()->first();
            $weaponType = ItemType::whereIn('code', ['M', 'R'])->inRandomOrder()->first();

            return [
                'item_type_id' => $weaponType->id,
                'damage_dice' => fake()->randomElement(['1d4', '1d6', '1d8', '1d10', '1d12', '2d6']),
                'damage_type_id' => $damageType->id,
                'weapon_range' => $weaponType->code === 'R' ? '80/320' : null,
            ];
        });
    }

    public function armor(): static
    {
        return $this->state(function (array $attributes) {
            $armorType = ItemType::whereIn('code', ['LA', 'MA', 'HA'])->inRandomOrder()->first();

            return [
                'item_type_id' => $armorType->id,
                'armor_class' => fake()->numberBetween(11, 18),
                'strength_requirement' => $armorType->code === 'HA' ? 13 : null,
                'stealth_disadvantage' => $armorType->code === 'HA',
            ];
        });
    }

    public function magic(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity' => fake()->randomElement(['uncommon', 'rare', 'very rare', 'legendary']),
            'requires_attunement' => fake()->boolean(60),
        ]);
    }

    public function versatile(): static
    {
        return $this->state(fn (array $attributes) => [
            'damage_dice' => '1d8',
            'versatile_damage' => '1d10',
        ]);
    }
}
```

**Step 3: Test factory in tinker**

Run: `docker compose exec php php artisan migrate:fresh --seed`
Then: `docker compose exec php php artisan tinker`
Then: `Item::factory()->create()`
Expected: Item instance with generated data

**Step 4: Test weapon state**

In tinker: `Item::factory()->weapon()->create()`
Expected: Item with damage_dice and damage_type_id

**Step 5: Commit**

```bash
git add database/factories/ItemFactory.php
git commit -m "feat: add ItemFactory with weapon, armor, magic, versatile states"
```

---

## Task 7: Create ItemAbilityFactory

**Files:**
- Create: `database/factories/ItemAbilityFactory.php`

**Step 1: Create factory file**

Run: `docker compose exec php php artisan make:factory ItemAbilityFactory --model=ItemAbility`

**Step 2: Write factory implementation**

Content for `database/factories/ItemAbilityFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemAbility;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemAbilityFactory extends Factory
{
    protected $model = ItemAbility::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
        ];
    }

    public function forItem(Item $item): static
    {
        return $this->state(fn (array $attributes) => [
            'item_id' => $item->id,
        ]);
    }
}
```

**Step 3: Test factory in tinker**

Run: `docker compose exec php php artisan tinker`
Then: `ItemAbility::factory()->create()`
Expected: ItemAbility instance with generated data

**Step 4: Commit**

```bash
git add database/factories/ItemAbilityFactory.php
git commit -m "feat: add ItemAbilityFactory with forItem state"
```

---

## Task 8: Create ItemXmlParser

**Files:**
- Create: `app/Services/Parsers/ItemXmlParser.php`
- Reference: `app/Services/Parsers/SpellXmlParser.php` (source extraction pattern)

**Step 1: Create parser file**

Create file at `app/Services/Parsers/ItemXmlParser.php`

**Step 2: Write parser implementation**

Content for `app/Services/Parsers/ItemXmlParser.php`:
```php
<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class ItemXmlParser
{
    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $items = [];

        foreach ($xml->item as $itemElement) {
            $items[] = $this->parseItem($itemElement);
        }

        return $items;
    }

    private function parseItem(SimpleXMLElement $element): array
    {
        $text = (string) $element->text;

        return [
            'name' => (string) $element->name,
            'type_code' => (string) $element->type,
            'rarity' => (string) $element->detail ?: 'common',
            'requires_attunement' => $this->parseAttunement($text),
            'cost_cp' => $this->parseCost((string) $element->value),
            'weight' => isset($element->weight) ? (float) $element->weight : null,
            'damage_dice' => (string) $element->dmg1 ?: null,
            'versatile_damage' => (string) $element->dmg2 ?: null,
            'damage_type_code' => (string) $element->dmgType ?: null,
            'armor_class' => isset($element->ac) ? (int) $element->ac : null,
            'strength_requirement' => isset($element->strength) ? (int) $element->strength : null,
            'stealth_disadvantage' => strtoupper((string) $element->stealth) === 'YES',
            'weapon_range' => (string) $element->range ?: null,
            'description' => $text,
            'properties' => $this->parseProperties((string) $element->property),
            'sources' => $this->extractSources($text),
            'proficiencies' => $this->extractProficiencies($text),
        ];
    }

    private function parseCost(string $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        // Convert gold pieces to copper pieces (1 GP = 100 CP)
        return (int) round((float) $value * 100);
    }

    private function parseAttunement(string $text): bool
    {
        return stripos($text, 'requires attunement') !== false;
    }

    private function parseProperties(string $propertyString): array
    {
        if (empty($propertyString)) {
            return [];
        }

        return array_map('trim', explode(',', $propertyString));
    }

    private function extractSources(string $text): array
    {
        $sources = [];
        $pattern = '/Source:\s*([^(]+)\s*\((\d{4})\)\s*p\.\s*(\d+(?:,\s*\d+)*)/i';

        if (preg_match($pattern, $text, $matches)) {
            $sourceName = trim($matches[1]);
            $pages = trim($matches[3]);

            $sources[] = [
                'source_name' => $sourceName,
                'pages' => rtrim($pages, ','), // Remove trailing comma if present
            ];
        }

        return $sources;
    }

    private function extractProficiencies(string $text): array
    {
        $proficiencies = [];
        $pattern = '/Proficienc(?:y|ies):\s*([^\n]+)/i';

        if (preg_match($pattern, $text, $matches)) {
            $profList = array_map('trim', explode(',', $matches[1]));
            foreach ($profList as $profName) {
                $proficiencies[] = [
                    'name' => $profName,
                    'type' => $this->inferProficiencyType($profName),
                ];
            }
        }

        return $proficiencies;
    }

    private function inferProficiencyType(string $name): string
    {
        $name = strtolower($name);

        // Armor types
        if (in_array($name, ['light armor', 'medium armor', 'heavy armor', 'shields'])) {
            return 'armor';
        }

        // Weapon types
        if (in_array($name, ['simple', 'martial', 'simple weapons', 'martial weapons']) ||
            str_contains($name, 'weapon')) {
            return 'weapon';
        }

        // Tool types
        if (str_contains($name, 'tools') || str_contains($name, 'kit')) {
            return 'tool';
        }

        // Default to weapon for specific weapon names
        return 'weapon';
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/Parsers/ItemXmlParser.php
git commit -m "feat: add ItemXmlParser with cost conversion and proficiency extraction"
```

---

## Task 9: Create ItemImporter

**Files:**
- Create: `app/Services/Importers/ItemImporter.php`
- Reference: `app/Services/Importers/SpellImporter.php` (polymorphic source pattern)

**Step 1: Create importer file**

Create file at `app/Services/Importers/ItemImporter.php`

**Step 2: Write importer implementation**

Content for `app/Services/Importers/ItemImporter.php`:
```php
<?php

namespace App\Services\Importers;

use App\Models\DamageType;
use App\Models\EntitySource;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Proficiency;
use App\Models\Source;
use Illuminate\Support\Str;

class ItemImporter
{
    private array $itemTypeCache = [];
    private array $damageTypeCache = [];
    private array $itemPropertyCache = [];
    private array $sourceCache = [];

    public function import(array $itemData): Item
    {
        // Lookup foreign keys
        $itemTypeId = $this->getItemTypeId($itemData['type_code']);
        $damageTypeId = !empty($itemData['damage_type_code'])
            ? $this->getDamageTypeId($itemData['damage_type_code'])
            : null;

        // Create or update item
        $item = Item::updateOrCreate(
            ['slug' => Str::slug($itemData['name'])],
            [
                'name' => $itemData['name'],
                'item_type_id' => $itemTypeId,
                'rarity' => $itemData['rarity'],
                'requires_attunement' => $itemData['requires_attunement'],
                'cost_cp' => $itemData['cost_cp'],
                'weight' => $itemData['weight'],
                'damage_dice' => $itemData['damage_dice'],
                'versatile_damage' => $itemData['versatile_damage'],
                'damage_type_id' => $damageTypeId,
                'armor_class' => $itemData['armor_class'],
                'strength_requirement' => $itemData['strength_requirement'],
                'stealth_disadvantage' => $itemData['stealth_disadvantage'],
                'weapon_range' => $itemData['weapon_range'],
                'description' => $itemData['description'],
            ]
        );

        // Import sources (polymorphic)
        $this->importSources($item, $itemData['sources']);

        // Import properties (M2M)
        $this->importProperties($item, $itemData['properties']);

        // Import proficiencies (polymorphic)
        $this->importProficiencies($item, $itemData['proficiencies']);

        return $item;
    }

    private function getItemTypeId(string $code): int
    {
        if (!isset($this->itemTypeCache[$code])) {
            $itemType = ItemType::where('code', $code)->firstOrFail();
            $this->itemTypeCache[$code] = $itemType->id;
        }

        return $this->itemTypeCache[$code];
    }

    private function getDamageTypeId(string $code): int
    {
        $code = strtoupper($code);

        if (!isset($this->damageTypeCache[$code])) {
            $damageType = DamageType::where('code', $code)->firstOrFail();
            $this->damageTypeCache[$code] = $damageType->id;
        }

        return $this->damageTypeCache[$code];
    }

    private function importSources(Item $item, array $sources): void
    {
        // Clear existing sources
        $item->sources()->delete();

        foreach ($sources as $sourceData) {
            $source = $this->getSourceByName($sourceData['source_name']);

            EntitySource::create([
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'source_id' => $source->id,
                'pages' => $sourceData['pages'],
            ]);
        }
    }

    private function importProperties(Item $item, array $propertyCodes): void
    {
        // Clear existing properties
        $item->properties()->detach();

        $propertyIds = [];
        foreach ($propertyCodes as $code) {
            $propertyId = $this->getItemPropertyId($code);
            if ($propertyId) {
                $propertyIds[] = $propertyId;
            }
        }

        // Attach properties
        $item->properties()->attach($propertyIds);
    }

    private function importProficiencies(Item $item, array $proficiencies): void
    {
        // Clear existing proficiencies
        $item->proficiencies()->delete();

        foreach ($proficiencies as $profData) {
            Proficiency::create([
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'proficiency_type' => $profData['type'],
                'proficiency_name' => $profData['name'],
            ]);
        }
    }

    private function getSourceByName(string $name): Source
    {
        if (!isset($this->sourceCache[$name])) {
            $source = Source::where('name', 'like', '%' . $name . '%')->firstOrFail();
            $this->sourceCache[$name] = $source;
        }

        return $this->sourceCache[$name];
    }

    private function getItemPropertyId(string $code): ?int
    {
        if (!isset($this->itemPropertyCache[$code])) {
            $property = ItemProperty::where('code', $code)->first();
            $this->itemPropertyCache[$code] = $property?->id;
        }

        return $this->itemPropertyCache[$code];
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/Importers/ItemImporter.php
git commit -m "feat: add ItemImporter with polymorphic sources and proficiencies"
```

---

## Task 10: Create ImportItems Command

**Files:**
- Create: `app/Console/Commands/ImportItems.php`
- Reference: `app/Console/Commands/ImportSpells.php` (command pattern)

**Step 1: Create command file**

Run: `docker compose exec php php artisan make:command ImportItems`

**Step 2: Write command implementation**

Content for `app/Console/Commands/ImportItems.php`:
```php
<?php

namespace App\Console\Commands;

use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use Illuminate\Console\Command;

class ImportItems extends Command
{
    protected $signature = 'import:items {file : Path to the XML file}';
    protected $description = 'Import items from an XML file';

    public function handle(ItemXmlParser $parser, ItemImporter $importer): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Reading file: {$filePath}");
        $xmlContent = file_get_contents($filePath);

        $this->info('Parsing XML...');
        $items = $parser->parse($xmlContent);
        $this->info('Found ' . count($items) . ' items');

        $progressBar = $this->output->createProgressBar(count($items));
        $progressBar->start();

        $imported = 0;
        foreach ($items as $itemData) {
            try {
                $importer->import($itemData);
                $imported++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to import item: {$itemData['name']}");
                $this->error($e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully imported {$imported} items");

        return self::SUCCESS;
    }
}
```

**Step 3: Verify command registered**

Run: `docker compose exec php php artisan list import`
Expected: `import:items` appears in list

**Step 4: Commit**

```bash
git add app/Console/Commands/ImportItems.php
git commit -m "feat: add import:items artisan command"
```

---

## Task 11: Create API Resources

**Files:**
- Create: `app/Http/Resources/ItemResource.php`
- Create: `app/Http/Resources/ItemPropertyResource.php`
- Create: `app/Http/Resources/ItemTypeResource.php`
- Create: `app/Http/Resources/ItemAbilityResource.php`
- Reference: `app/Http/Resources/SpellResource.php` (field completeness pattern)

**Step 1: Create ItemPropertyResource**

Content for `app/Http/Resources/ItemPropertyResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemPropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

**Step 2: Create ItemTypeResource**

Content for `app/Http/Resources/ItemTypeResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

**Step 3: Create ItemAbilityResource**

Content for `app/Http/Resources/ItemAbilityResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemAbilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

**Step 4: Create ItemResource**

Content for `app/Http/Resources/ItemResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'item_type_id' => $this->item_type_id,
            'rarity' => $this->rarity,
            'requires_attunement' => $this->requires_attunement,
            'cost_cp' => $this->cost_cp,
            'weight' => $this->weight,
            'damage_dice' => $this->damage_dice,
            'versatile_damage' => $this->versatile_damage,
            'damage_type_id' => $this->damage_type_id,
            'armor_class' => $this->armor_class,
            'strength_requirement' => $this->strength_requirement,
            'stealth_disadvantage' => $this->stealth_disadvantage,
            'weapon_range' => $this->weapon_range,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'item_type' => ItemTypeResource::make($this->whenLoaded('itemType')),
            'damage_type' => DamageTypeResource::make($this->whenLoaded('damageType')),
            'properties' => ItemPropertyResource::collection($this->whenLoaded('properties')),
            'abilities' => ItemAbilityResource::collection($this->whenLoaded('abilities')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
        ];
    }
}
```

**Step 5: Commit**

```bash
git add app/Http/Resources/ItemResource.php app/Http/Resources/ItemPropertyResource.php app/Http/Resources/ItemTypeResource.php app/Http/Resources/ItemAbilityResource.php
git commit -m "feat: add Item API resources (field-complete with relationships)"
```

---

## Task 12: Write XML Reconstruction Tests (TDD)

**Files:**
- Create: `tests/Feature/Importers/ItemXmlReconstructionTest.php`
- Reference: `tests/Feature/Importers/SpellXmlReconstructionTest.php` (test pattern)

**Step 1: Create test file**

Create file at `tests/Feature/Importers/ItemXmlReconstructionTest.php`

**Step 2: Write first test case (simple weapon)**

Content for `tests/Feature/Importers/ItemXmlReconstructionTest.php`:
```php
<?php

namespace Tests\Feature\Importers;

use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use App\Models\Item;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ItemXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ItemXmlParser $parser;
    private ItemImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemXmlParser();
        $this->importer = new ItemImporter();
    }

    #[Test]
    public function it_reconstructs_simple_melee_weapon()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Battleaxe</name>
    <type>M</type>
    <weight>4</weight>
    <value>10.0</value>
    <property>V,M</property>
    <dmg1>1d8</dmg1>
    <dmg2>1d10</dmg2>
    <dmgType>S</dmgType>
    <text>A versatile martial weapon.

Proficiency: martial, battleaxe

Source: Player's Handbook (2014) p. 149</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $this->assertCount(1, $items);

        $item = $this->importer->import($items[0]);

        // Verify basic attributes
        $this->assertEquals('Battleaxe', $item->name);
        $this->assertEquals('battleaxe', $item->slug);
        $this->assertEquals('common', $item->rarity);
        $this->assertFalse($item->requires_attunement);
        $this->assertEquals(1000, $item->cost_cp); // 10.0 GP = 1000 CP
        $this->assertEquals(4.0, $item->weight);
        $this->assertEquals('1d8', $item->damage_dice);
        $this->assertEquals('1d10', $item->versatile_damage);
        $this->assertFalse($item->stealth_disadvantage);

        // Verify relationships
        $item->load(['itemType', 'damageType', 'properties', 'sources.source', 'proficiencies']);

        $this->assertEquals('M', $item->itemType->code);
        $this->assertEquals('S', $item->damageType->code);

        // Verify properties
        $this->assertCount(2, $item->properties);
        $propertyCodes = $item->properties->pluck('code')->sort()->values()->toArray();
        $this->assertEquals(['M', 'V'], $propertyCodes);

        // Verify source
        $this->assertCount(1, $item->sources);
        $this->assertEquals('Player\'s Handbook', $item->sources[0]->source->name);
        $this->assertEquals('149', $item->sources[0]->pages);

        // Verify proficiencies
        $this->assertCount(2, $item->proficiencies);
        $profNames = $item->proficiencies->pluck('proficiency_name')->sort()->values()->toArray();
        $this->assertEquals(['battleaxe', 'martial'], $profNames);
    }

    #[Test]
    public function it_reconstructs_armor_with_requirements()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Chain Mail</name>
    <type>HA</type>
    <weight>55</weight>
    <value>75.0</value>
    <ac>16</ac>
    <strength>13</strength>
    <stealth>YES</stealth>
    <text>Heavy armor that provides excellent protection but restricts movement.

Proficiency: heavy armor

Source: Player's Handbook (2014) p. 145</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify armor-specific attributes
        $this->assertEquals('Chain Mail', $item->name);
        $this->assertEquals(16, $item->armor_class);
        $this->assertEquals(13, $item->strength_requirement);
        $this->assertTrue($item->stealth_disadvantage);
        $this->assertEquals(7500, $item->cost_cp); // 75.0 GP = 7500 CP
        $this->assertEquals(55.0, $item->weight);

        // Verify item type
        $item->load('itemType');
        $this->assertEquals('HA', $item->itemType->code);

        // Verify proficiency
        $item->load('proficiencies');
        $this->assertCount(1, $item->proficiencies);
        $this->assertEquals('heavy armor', $item->proficiencies[0]->proficiency_name);
        $this->assertEquals('armor', $item->proficiencies[0]->proficiency_type);
    }

    #[Test]
    public function it_reconstructs_ranged_weapon_with_range()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Longbow</name>
    <type>R</type>
    <weight>2</weight>
    <value>50.0</value>
    <property>A,H,2H,M</property>
    <dmg1>1d8</dmg1>
    <dmgType>P</dmgType>
    <range>150/600</range>
    <text>A powerful ranged weapon requiring ammunition.

Proficiency: martial, longbow

Source: Player's Handbook (2014) p. 149</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify ranged weapon attributes
        $this->assertEquals('Longbow', $item->name);
        $this->assertEquals('1d8', $item->damage_dice);
        $this->assertEquals('150/600', $item->weapon_range);

        // Verify properties
        $item->load('properties');
        $this->assertCount(4, $item->properties);
        $propertyCodes = $item->properties->pluck('code')->sort()->values()->toArray();
        $this->assertEquals(['2H', 'A', 'H', 'M'], $propertyCodes);
    }

    #[Test]
    public function it_reconstructs_magic_item_with_attunement()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>+1 Longsword</name>
    <detail>uncommon</detail>
    <type>M</type>
    <weight>3</weight>
    <property>V,M</property>
    <dmg1>1d8</dmg1>
    <dmg2>1d10</dmg2>
    <dmgType>S</dmgType>
    <text>You have a +1 bonus to attack and damage rolls made with this magic weapon. Requires attunement.

Proficiency: martial

Source: Dungeon Master's Guide (2014) p. 213</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify magic item attributes
        $this->assertEquals('+1 Longsword', $item->name);
        $this->assertEquals('uncommon', $item->rarity);
        $this->assertTrue($item->requires_attunement);

        // Verify source
        $item->load('sources.source');
        $this->assertEquals('Dungeon Master\'s Guide', $item->sources[0]->source->name);
        $this->assertEquals('213', $item->sources[0]->pages);
    }

    #[Test]
    public function it_reconstructs_item_without_cost()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Torch</name>
    <type>G</type>
    <weight>1</weight>
    <text>A simple torch that provides light.

Source: Player's Handbook (2014) p. 153</text>
  </item>
</compendium>
XML;

        // Parse and import
        $items = $this->parser->parse($originalXml);
        $item = $this->importer->import($items[0]);

        // Verify item without cost/value
        $this->assertEquals('Torch', $item->name);
        $this->assertNull($item->cost_cp);
        $this->assertEquals(1.0, $item->weight);

        // Verify item type
        $item->load('itemType');
        $this->assertEquals('G', $item->itemType->code);
    }
}
```

**Step 3: Run tests to verify they FAIL**

Run: `docker compose exec php php artisan test --filter=ItemXmlReconstructionTest`
Expected: Multiple failures (parser/importer not complete yet)

**Step 4: Commit**

```bash
git add tests/Feature/Importers/ItemXmlReconstructionTest.php
git commit -m "test: add XML reconstruction tests for items (5 test cases)"
```

---

## Task 13: Run Migration and Import Sample Data

**Step 1: Run fresh migration with all seeders**

Run: `docker compose exec php php artisan migrate:fresh --seed`
Expected: All migrations run successfully, all seeders complete

**Step 2: Verify item_types and item_properties seeded**

Run: `docker compose exec php php artisan tinker`
Then: `DB::table('item_types')->count()`
Expected: 15
Then: `DB::table('item_properties')->count()`
Expected: 11

**Step 3: Test import command with sample file**

Run: `docker compose exec php php artisan import:items import-files/items-base-phb.xml`
Expected: Progress bar, success message with count

**Step 4: Verify items imported**

Run: `docker compose exec php php artisan tinker`
Then: `Item::count()`
Expected: > 0

**Step 5: Run reconstruction tests**

Run: `docker compose exec php php artisan test --filter=ItemXmlReconstructionTest`
Expected: All tests PASS

**Step 6: Commit if tests pass**

```bash
git add -A
git commit -m "test: verify item import pipeline with reconstruction tests"
```

---

## Task 14: Fix Any Failing Tests

**Step 1: Review test failures**

Run: `docker compose exec php php artisan test --filter=ItemXmlReconstructionTest`
Review output for any failures

**Step 2: Debug parser issues**

Common issues:
- Cost conversion math errors
- Property code case sensitivity
- Damage type lookup failures
- Source extraction regex issues

**Step 3: Debug importer issues**

Common issues:
- Foreign key lookup failures
- Missing cache initialization
- Polymorphic relationship errors

**Step 4: Fix and retest**

Make fixes, then re-run tests until all pass

**Step 5: Commit fixes**

```bash
git add -A
git commit -m "fix: resolve item import issues found by reconstruction tests"
```

---

## Task 15: Run Full Test Suite

**Step 1: Run all tests**

Run: `docker compose exec php php artisan test`
Expected: All tests pass (including new item tests)

**Step 2: Verify test count increased**

Previous: 240 tests
Expected: 245+ tests (5 new reconstruction tests)

**Step 3: Update CLAUDE.md with new statistics**

Update test counts and add Items section to documentation

**Step 4: Commit documentation**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md with Item importer completion"
```

---

## Task 16: Import All Item XML Files

**Step 1: Import base items**

Run: `docker compose exec php php artisan import:items import-files/items-base-phb.xml`

**Step 2: Import DMG items**

Run: `docker compose exec php php artisan import:items import-files/items-dmg.xml`

**Step 3: Import magic items**

Run each magic item file:
```bash
docker compose exec php php artisan import:items import-files/items-magic-dmg.xml
# Repeat for other items-magic-*.xml files
```

**Step 4: Verify total item count**

Run: `docker compose exec php php artisan tinker`
Then: `Item::count()`
Expected: 500+ items

**Step 5: Document import success**

Note successful import counts in commit message

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: import all item XML files (base, DMG, magic items)"
```

---

## Success Criteria Checklist

- [x] Items table uses multi-source architecture (no source_id column)
- [x] All 15 item types seeded in database
- [x] All 11 item properties seeded in database
- [x] Item, ItemAbility models with full relationships
- [x] 4 API Resources created and field-complete
- [x] 2 Factories created with useful states
- [x] ItemXmlParser parses all XML elements correctly
- [x] ItemImporter handles properties, proficiencies, sources polymorphically
- [x] ImportItems command works with XML files
- [x] ItemXmlReconstructionTest covers weapons, armor, magic items
- [x] ~90% attribute reconstruction coverage
- [x] All tests passing
- [x] Documentation updated

---

## Estimated Time

**Total:** 4-6 hours
- Tasks 1-7: 1.5 hours (schema, seeders, models, factories)
- Tasks 8-11: 1.5 hours (parser, importer, command, resources)
- Task 12: 1 hour (reconstruction tests)
- Tasks 13-16: 1-2 hours (testing, debugging, importing all files)

---

## Notes

**Follow TDD:** Write tests first (Task 12), watch them fail, implement (Tasks 8-11), watch them pass.

**Reference Skills:**
- @superpowers:test-driven-development - Write test, fail, implement, pass, commit
- @superpowers:verification-before-completion - Run verification commands before claiming success

**Commit Frequently:** Each task has a commit step - use them!
