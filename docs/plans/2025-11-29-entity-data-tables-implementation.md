# Entity Data Tables Refactor - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Rename `random_tables` to `entity_data_tables` and add `table_type` discriminator column.

**Architecture:** Single atomic refactor with one migration for schema changes, followed by systematic file renames and reference updates. All changes validated by existing test suites.

**Tech Stack:** Laravel 12.x, PHP 8.4, MySQL/SQLite, PHPUnit 11

---

## Pre-Flight Checklist

Before starting, verify:
```bash
# Ensure clean working directory
git status

# Ensure all tests pass
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
```

---

## Task 1: Create DataTableType Enum

**Files:**
- Create: `app/Enums/DataTableType.php`

**Step 1: Create the enum file**

```php
<?php

namespace App\Enums;

enum DataTableType: string
{
    case Random = 'random';
    case Damage = 'damage';
    case Modifier = 'modifier';
    case Lookup = 'lookup';
    case Progression = 'progression';

    /**
     * Get human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Random => 'Random Roll Table',
            self::Damage => 'Damage Dice',
            self::Modifier => 'Size/Weight Modifier',
            self::Lookup => 'Lookup Table',
            self::Progression => 'Level Progression',
        };
    }
}
```

**Step 2: Verify file created**

Run: `ls -la app/Enums/DataTableType.php`
Expected: File exists

**Step 3: Commit**

```bash
git add app/Enums/DataTableType.php
git commit -m "feat: add DataTableType enum for entity_data_tables classification"
```

---

## Task 2: Create Migration for Schema Changes

**Files:**
- Create: `database/migrations/2025_11_29_000000_rename_random_tables_to_entity_data_tables.php`

**Step 1: Create the migration**

```bash
docker compose exec php php artisan make:migration rename_random_tables_to_entity_data_tables
```

**Step 2: Write the migration content**

Edit the created migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Rename tables
        Schema::rename('random_tables', 'entity_data_tables');
        Schema::rename('random_table_entries', 'entity_data_table_entries');

        // Step 2: Add table_type column
        Schema::table('entity_data_tables', function (Blueprint $table) {
            $table->string('table_type', 20)->default('random')->after('dice_type');
            $table->index('table_type');
        });

        // Step 3: Rename foreign key column in entries table
        Schema::table('entity_data_table_entries', function (Blueprint $table) {
            $table->renameColumn('random_table_id', 'entity_data_table_id');
        });

        // Step 4: Rename foreign key column in character_traits table
        Schema::table('character_traits', function (Blueprint $table) {
            $table->renameColumn('random_table_id', 'entity_data_table_id');
        });

        // Step 5: Populate table_type based on data patterns
        DB::statement("UPDATE entity_data_tables SET table_type = 'damage' WHERE table_name LIKE '%Damage%'");
        DB::statement("UPDATE entity_data_tables SET table_type = 'modifier' WHERE table_name LIKE '%Modifier%'");
        DB::statement("UPDATE entity_data_tables SET table_type = 'progression' WHERE table_name LIKE '%Spells Known%'");
        DB::statement("UPDATE entity_data_tables SET table_type = 'progression' WHERE table_name LIKE '%Exhaustion%'");
        DB::statement("UPDATE entity_data_tables SET table_type = 'lookup' WHERE (dice_type IS NULL OR dice_type = '') AND table_type = 'random'");
    }

    public function down(): void
    {
        // Reverse foreign key renames first
        Schema::table('character_traits', function (Blueprint $table) {
            $table->renameColumn('entity_data_table_id', 'random_table_id');
        });

        Schema::table('entity_data_table_entries', function (Blueprint $table) {
            $table->renameColumn('entity_data_table_id', 'random_table_id');
        });

        // Drop table_type column
        Schema::table('entity_data_tables', function (Blueprint $table) {
            $table->dropIndex(['table_type']);
            $table->dropColumn('table_type');
        });

        // Rename tables back
        Schema::rename('entity_data_table_entries', 'random_table_entries');
        Schema::rename('entity_data_tables', 'random_tables');
    }
};
```

**Step 3: Run migration on test database**

```bash
docker compose exec php php artisan migrate --env=testing
```

Expected: Migration runs successfully

**Step 4: Verify table exists**

```bash
docker compose exec php php artisan tinker --execute="Schema::hasTable('entity_data_tables')"
```

Expected: `true`

**Step 5: Commit**

```bash
git add database/migrations/*rename_random_tables_to_entity_data_tables.php
git commit -m "feat: add migration to rename random_tables to entity_data_tables"
```

---

## Task 3: Rename Model Files

**Files:**
- Rename: `app/Models/RandomTable.php` → `app/Models/EntityDataTable.php`
- Rename: `app/Models/RandomTableEntry.php` → `app/Models/EntityDataTableEntry.php`

**Step 1: Rename RandomTable.php to EntityDataTable.php**

```bash
git mv app/Models/RandomTable.php app/Models/EntityDataTable.php
```

**Step 2: Update EntityDataTable.php content**

```php
<?php

namespace App\Models;

use App\Enums\DataTableType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityDataTable extends BaseModel
{
    protected $table = 'entity_data_tables';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'table_name',
        'dice_type',
        'table_type',
        'description',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'table_type' => DataTableType::class,
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Entries relationship
    public function entries(): HasMany
    {
        return $this->hasMany(EntityDataTableEntry::class)->orderBy('sort_order');
    }
}
```

**Step 3: Rename RandomTableEntry.php to EntityDataTableEntry.php**

```bash
git mv app/Models/RandomTableEntry.php app/Models/EntityDataTableEntry.php
```

**Step 4: Update EntityDataTableEntry.php content**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityDataTableEntry extends BaseModel
{
    protected $table = 'entity_data_table_entries';

    protected $fillable = [
        'entity_data_table_id',
        'roll_min',
        'roll_max',
        'result_text',
        'level',
        'sort_order',
    ];

    protected $casts = [
        'entity_data_table_id' => 'integer',
        'roll_min' => 'integer',
        'roll_max' => 'integer',
        'level' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationship
    public function entityDataTable(): BelongsTo
    {
        return $this->belongsTo(EntityDataTable::class);
    }
}
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: rename RandomTable models to EntityDataTable"
```

---

## Task 4: Update Dependent Models

**Files:**
- Modify: `app/Models/ClassFeature.php` (lines 59-71)
- Modify: `app/Models/Item.php` (lines 91-94)
- Modify: `app/Models/CharacterTrait.php` (lines 35-48)
- Modify: `app/Models/Spell.php` (lines 101-104)
- Modify: `app/Models/OptionalFeature.php` (lines 72-77)

**Step 1: Update ClassFeature.php**

Find and replace:
- `use App\Models\RandomTable;` → `use App\Models\EntityDataTable;`
- `randomTables()` → `dataTables()`
- `RandomTable::class` → `EntityDataTable::class`

```php
/**
 * Data tables and reference tables associated with this feature.
 * Includes <roll> elements and pipe-delimited tables from feature text.
 */
public function dataTables(): MorphMany
{
    return $this->morphMany(
        EntityDataTable::class,
        'reference',
        'reference_type',
        'reference_id'
    );
}
```

**Step 2: Update Item.php**

Find and replace:
- `use App\Models\RandomTable;` → `use App\Models\EntityDataTable;`
- `randomTables()` → `dataTables()`
- `RandomTable::class` → `EntityDataTable::class`

```php
public function dataTables(): MorphMany
{
    return $this->morphMany(EntityDataTable::class, 'reference');
}
```

**Step 3: Update CharacterTrait.php**

Find and replace:
- `use App\Models\RandomTable;` → `use App\Models\EntityDataTable;`
- `randomTables()` → `dataTables()` (MorphMany)
- `randomTable()` → `dataTable()` (BelongsTo)
- `RandomTable::class` → `EntityDataTable::class`
- `random_table_id` → `entity_data_table_id`

```php
// Bidirectional relationship to data tables
// A trait can have many data tables referencing it
public function dataTables(): MorphMany
{
    return $this->morphMany(EntityDataTable::class, 'reference');
}

// A trait can also be linked to a single data table via entity_data_table_id
public function dataTable(): BelongsTo
{
    return $this->belongsTo(EntityDataTable::class, 'entity_data_table_id');
}
```

**Step 4: Update Spell.php**

Find and replace:
- `use App\Models\RandomTable;` → `use App\Models\EntityDataTable;`
- `randomTables()` → `dataTables()`
- `RandomTable::class` → `EntityDataTable::class`

```php
public function dataTables(): MorphMany
{
    return $this->morphMany(EntityDataTable::class, 'reference');
}
```

**Step 5: Update OptionalFeature.php**

Find and replace (keep `rolls()` method name as alias):
- `use App\Models\RandomTable;` → `use App\Models\EntityDataTable;`
- `RandomTable::class` → `EntityDataTable::class`

```php
/**
 * Get damage/effect rolls for this feature.
 */
public function rolls(): MorphMany
{
    return $this->morphMany(EntityDataTable::class, 'reference');
}
```

**Step 6: Run tests to verify models work**

```bash
docker compose exec php php artisan test --testsuite=Unit-DB --filter=Model
```

Expected: Tests fail (resources/importers not yet updated)

**Step 7: Commit**

```bash
git add app/Models/ClassFeature.php app/Models/Item.php app/Models/CharacterTrait.php app/Models/Spell.php app/Models/OptionalFeature.php
git commit -m "refactor: update model relationships from randomTables to dataTables"
```

---

## Task 5: Rename Resource Files

**Files:**
- Rename: `app/Http/Resources/RandomTableResource.php` → `app/Http/Resources/EntityDataTableResource.php`
- Rename: `app/Http/Resources/RandomTableEntryResource.php` → `app/Http/Resources/EntityDataTableEntryResource.php`

**Step 1: Rename RandomTableResource.php**

```bash
git mv app/Http/Resources/RandomTableResource.php app/Http/Resources/EntityDataTableResource.php
```

**Step 2: Update EntityDataTableResource.php content**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityDataTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table_name' => $this->table_name,
            'dice_type' => $this->dice_type,
            'table_type' => $this->table_type?->value,
            'description' => $this->description,
            'entries' => EntityDataTableEntryResource::collection($this->whenLoaded('entries')),
        ];
    }
}
```

**Step 3: Rename RandomTableEntryResource.php**

```bash
git mv app/Http/Resources/RandomTableEntryResource.php app/Http/Resources/EntityDataTableEntryResource.php
```

**Step 4: Update EntityDataTableEntryResource.php content**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityDataTableEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'roll_min' => $this->roll_min,
            'roll_max' => $this->roll_max,
            'result_text' => $this->result_text,
            'level' => $this->level,
            'sort_order' => $this->sort_order,
        ];
    }
}
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: rename RandomTable resources to EntityDataTable"
```

---

## Task 6: Update Dependent Resources

**Files:**
- Modify: `app/Http/Resources/ClassFeatureResource.php`
- Modify: `app/Http/Resources/ItemResource.php`
- Modify: `app/Http/Resources/TraitResource.php`
- Modify: `app/Http/Resources/SpellResource.php`

**Step 1: Update ClassFeatureResource.php**

Find and replace:
- `use App\Http\Resources\RandomTableResource;` → `use App\Http\Resources\EntityDataTableResource;`
- `'random_tables'` → `'data_tables'`
- `RandomTableResource::collection` → `EntityDataTableResource::collection`
- `randomTables` → `dataTables`

```php
'data_tables' => EntityDataTableResource::collection(
    $this->whenLoaded('dataTables')
),
```

**Step 2: Update ItemResource.php**

Find and replace:
- `use App\Http\Resources\RandomTableResource;` → `use App\Http\Resources\EntityDataTableResource;`
- `'random_tables'` → `'data_tables'`
- `RandomTableResource::collection` → `EntityDataTableResource::collection`
- `randomTables` → `dataTables`

```php
'data_tables' => EntityDataTableResource::collection($this->whenLoaded('dataTables')),
```

**Step 3: Update TraitResource.php**

Find and replace:
- `use App\Http\Resources\RandomTableResource;` → `use App\Http\Resources\EntityDataTableResource;`
- `'random_tables'` → `'data_tables'`
- `RandomTableResource::collection` → `EntityDataTableResource::collection`
- `randomTables` → `dataTables`

```php
'data_tables' => EntityDataTableResource::collection($this->whenLoaded('dataTables')),
```

**Step 4: Update SpellResource.php**

Find and replace:
- `use App\Http\Resources\RandomTableResource;` → `use App\Http\Resources\EntityDataTableResource;`
- `'random_tables'` → `'data_tables'`
- `RandomTableResource::collection` → `EntityDataTableResource::collection`
- `randomTables` → `dataTables`

```php
'data_tables' => EntityDataTableResource::collection($this->whenLoaded('dataTables')),
```

**Step 5: Commit**

```bash
git add app/Http/Resources/ClassFeatureResource.php app/Http/Resources/ItemResource.php app/Http/Resources/TraitResource.php app/Http/Resources/SpellResource.php
git commit -m "refactor: update resources to use data_tables key and EntityDataTableResource"
```

---

## Task 7: Rename Importer Traits

**Files:**
- Rename: `app/Services/Importers/Concerns/ImportsRandomTables.php` → `app/Services/Importers/Concerns/ImportsDataTables.php`
- Rename: `app/Services/Importers/Concerns/ImportsRandomTablesFromText.php` → `app/Services/Importers/Concerns/ImportsDataTablesFromText.php`

**Step 1: Rename ImportsRandomTables.php**

```bash
git mv app/Services/Importers/Concerns/ImportsRandomTables.php app/Services/Importers/Concerns/ImportsDataTables.php
```

**Step 2: Update ImportsDataTables.php content**

```php
<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterTrait;

/**
 * Trait for importing data tables embedded in trait descriptions.
 *
 * Handles detection and parsing of pipe-delimited tables like:
 * "d8|1|Result One|2|Result Two|"
 *
 * Used by: RaceImporter, BackgroundImporter, ClassImporter (future)
 *
 * This trait now delegates to ImportsDataTablesFromText for the actual implementation.
 */
trait ImportsDataTables
{
    use ImportsDataTablesFromText;

    /**
     * Import data tables embedded in a trait's description.
     *
     * Detects pipe-delimited tables, parses them, and creates
     * EntityDataTable + EntityDataTableEntry records linked to the trait.
     *
     * @param  CharacterTrait  $trait  The trait containing the table
     * @param  string  $description  Trait description text
     */
    protected function importTraitTables(CharacterTrait $trait, string $description): void
    {
        // Delegate to the generalized trait method
        $this->importDataTablesFromText($trait, $description, clearExisting: false);
    }

    /**
     * Import data tables from all traits of an entity.
     *
     * Convenience method to import tables from multiple traits at once.
     *
     * @param  array  $createdTraits  Array of CharacterTrait models
     * @param  array  $traitsData  Original trait data with descriptions
     */
    protected function importDataTablesFromTraits(array $createdTraits, array $traitsData): void
    {
        foreach ($createdTraits as $index => $trait) {
            if (isset($traitsData[$index]['description'])) {
                $this->importTraitTables($trait, $traitsData[$index]['description']);
            }
        }
    }
}
```

**Step 3: Rename ImportsRandomTablesFromText.php**

```bash
git mv app/Services/Importers/Concerns/ImportsRandomTablesFromText.php app/Services/Importers/Concerns/ImportsDataTablesFromText.php
```

**Step 4: Update ImportsDataTablesFromText.php content**

```php
<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntityDataTable;
use App\Models\EntityDataTableEntry;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing data tables detected in text descriptions.
 *
 * Handles detection and parsing of pipe-delimited tables like:
 * "d8|1|Result One|2|Result Two|"
 *
 * Used by: ItemImporter, SpellImporter, ImportsDataTables (for traits)
 *
 * This is a generalized version that works with any polymorphic entity,
 * not just CharacterTrait models.
 */
trait ImportsDataTablesFromText
{
    /**
     * Import data tables detected in text description.
     *
     * Detects pipe-delimited tables, parses them, and creates
     * EntityDataTable + EntityDataTableEntry records linked to the entity.
     *
     * @param  Model  $entity  The polymorphic entity (Item, Spell, CharacterTrait, etc.)
     * @param  string  $text  Description text to parse for tables
     * @param  bool  $clearExisting  Whether to delete existing data tables before importing
     */
    protected function importDataTablesFromText(Model $entity, string $text, bool $clearExisting = true): void
    {
        // Detect tables in description text
        $detector = new ItemTableDetector;
        $tables = $detector->detectTables($text);

        if (empty($tables)) {
            return;
        }

        // Clear existing data tables if requested
        if ($clearExisting) {
            $entity->dataTables()->delete();
        }

        foreach ($tables as $tableData) {
            $parser = new ItemTableParser;
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type'] ?? null);

            if (empty($parsed['rows'])) {
                continue; // Skip tables with no valid rows
            }

            // Create data table linked to entity
            $table = EntityDataTable::create([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'table_name' => $parsed['table_name'],
                'dice_type' => $parsed['dice_type'],
            ]);

            // Create table entries
            foreach ($parsed['rows'] as $index => $row) {
                EntityDataTableEntry::create([
                    'entity_data_table_id' => $table->id,
                    'roll_min' => $row['roll_min'],
                    'roll_max' => $row['roll_max'],
                    'result_text' => $row['result_text'],
                    'sort_order' => $index,
                ]);
            }
        }
    }
}
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: rename ImportsRandomTables traits to ImportsDataTables"
```

---

## Task 8: Update Importers Using Traits

**Files:**
- Modify: `app/Services/Importers/BackgroundImporter.php`
- Modify: `app/Services/Importers/ClassImporter.php`
- Modify: `app/Services/Importers/RaceImporter.php`
- Modify: `app/Services/Importers/SpellImporter.php`
- Modify: `app/Services/Importers/ItemImporter.php`
- Modify: `app/Services/Importers/BaseImporter.php`
- Modify: `app/Services/Importers/Concerns/ImportsClassFeatures.php`

**Step 1: Search for all files using the old trait names**

```bash
grep -rl "ImportsRandomTables\|randomTables\|RandomTable" app/Services/Importers/
```

**Step 2: Update each file**

In each importer file, find and replace:
- `use App\Services\Importers\Concerns\ImportsRandomTables;` → `use App\Services\Importers\Concerns\ImportsDataTables;`
- `use App\Services\Importers\Concerns\ImportsRandomTablesFromText;` → `use App\Services\Importers\Concerns\ImportsDataTablesFromText;`
- `use ImportsRandomTables;` → `use ImportsDataTables;`
- `use ImportsRandomTablesFromText;` → `use ImportsDataTablesFromText;`
- `use App\Models\RandomTable;` → `use App\Models\EntityDataTable;`
- `use App\Models\RandomTableEntry;` → `use App\Models\EntityDataTableEntry;`
- `RandomTable::` → `EntityDataTable::`
- `RandomTableEntry::` → `EntityDataTableEntry::`
- `->randomTables()` → `->dataTables()`
- `importRandomTablesFromText` → `importDataTablesFromText`
- `importRandomTablesFromTraits` → `importDataTablesFromTraits`

**Step 3: Run tests to verify importers work**

```bash
docker compose exec php php artisan test --testsuite=Unit-DB --filter=Importer
```

Expected: Some tests fail (test files not yet updated)

**Step 4: Commit**

```bash
git add app/Services/Importers/
git commit -m "refactor: update importers to use ImportsDataTables trait"
```

---

## Task 9: Rename Parser Trait

**Files:**
- Rename: `app/Services/Parsers/Concerns/ParsesRandomTables.php` → `app/Services/Parsers/Concerns/ParsesDataTables.php`
- Modify: `app/Services/Parsers/SpellXmlParser.php`

**Step 1: Rename ParsesRandomTables.php**

```bash
git mv app/Services/Parsers/Concerns/ParsesRandomTables.php app/Services/Parsers/Concerns/ParsesDataTables.php
```

**Step 2: Update ParsesDataTables.php content**

```php
<?php

namespace App\Services\Parsers\Concerns;

use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;

/**
 * Trait for parsing data tables embedded in entity descriptions.
 *
 * Handles pipe-delimited tables like:
 * - d8 | Effect
 * - 1 | Red: Fire damage
 * - 2-6 | Orange: Acid damage
 *
 * Used by: Spells (Prismatic Spray, Confusion), Items, Backgrounds, etc.
 */
trait ParsesDataTables
{
    /**
     * Parse data tables embedded in spell description.
     *
     * Uses ItemTableDetector and ItemTableParser to find pipe-delimited tables
     * like those in Prismatic Spray (d8 roll tables).
     *
     * @param  string  $description  Spell description text
     * @return array<int, array{table_name: string, dice_type: string|null, entries: array}>
     */
    protected function parseDataTables(string $description): array
    {
        $detector = new ItemTableDetector;
        $detectedTables = $detector->detectTables($description);

        if (empty($detectedTables)) {
            return [];
        }

        $tables = [];
        $parser = new ItemTableParser;

        foreach ($detectedTables as $tableData) {
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type'] ?? null);

            if (empty($parsed['rows'])) {
                continue; // Skip tables with no valid rows
            }

            $tables[] = [
                'table_name' => $parsed['table_name'],
                'dice_type' => $parsed['dice_type'],
                'entries' => $parsed['rows'],
            ];
        }

        return $tables;
    }
}
```

**Step 3: Update SpellXmlParser.php**

Find and replace:
- `use App\Services\Parsers\Concerns\ParsesRandomTables;` → `use App\Services\Parsers\Concerns\ParsesDataTables;`
- `use ParsesRandomTables;` → `use ParsesDataTables;`
- `parseRandomTables` → `parseDataTables`

**Step 4: Commit**

```bash
git add -A
git commit -m "refactor: rename ParsesRandomTables trait to ParsesDataTables"
```

---

## Task 10: Rename Factory Files

**Files:**
- Rename: `database/factories/RandomTableFactory.php` → `database/factories/EntityDataTableFactory.php`
- Rename: `database/factories/RandomTableEntryFactory.php` → `database/factories/EntityDataTableEntryFactory.php`

**Step 1: Rename RandomTableFactory.php**

```bash
git mv database/factories/RandomTableFactory.php database/factories/EntityDataTableFactory.php
```

**Step 2: Update EntityDataTableFactory.php content**

```php
<?php

namespace Database\Factories;

use App\Models\CharacterTrait;
use App\Models\EntityDataTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntityDataTable>
 */
class EntityDataTableFactory extends Factory
{
    protected $model = EntityDataTable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to CharacterTrait as reference type
        $trait = CharacterTrait::factory()->create();

        return [
            'reference_type' => CharacterTrait::class,
            'reference_id' => $trait->id,
            'table_name' => fake()->words(3, true),
            'dice_type' => fake()->randomElement(['d4', 'd6', 'd8', 'd10', 'd12', 'd20']),
            'table_type' => 'random',
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Set the data table to belong to a specific entity.
     */
    public function forEntity(string $referenceType, int $referenceId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Set the table type.
     */
    public function ofType(string $tableType): static
    {
        return $this->state(fn (array $attributes) => [
            'table_type' => $tableType,
        ]);
    }
}
```

**Step 3: Rename RandomTableEntryFactory.php**

```bash
git mv database/factories/RandomTableEntryFactory.php database/factories/EntityDataTableEntryFactory.php
```

**Step 4: Update EntityDataTableEntryFactory.php content**

Find and replace:
- `RandomTableEntry` → `EntityDataTableEntry`
- `RandomTable` → `EntityDataTable`
- `random_table_id` → `entity_data_table_id`

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: rename RandomTable factories to EntityDataTable"
```

---

## Task 11: Rename Test Files

**Files to rename (6 files):**
- `tests/Feature/Models/RandomTableModelTest.php` → `EntityDataTableModelTest.php`
- `tests/Unit/Factories/RandomTableFactoriesTest.php` → `EntityDataTableFactoriesTest.php`
- `tests/Feature/Importers/SpellRandomTableImportTest.php` → `SpellDataTableImportTest.php`
- `tests/Unit/Parsers/SpellRandomTableParserTest.php` → `SpellDataTableParserTest.php`
- `tests/Feature/Importers/ClassImporterRandomTablesTest.php` → `ClassImporterDataTablesTest.php`
- `tests/Unit/Importers/Concerns/ImportsRandomTablesTest.php` → `ImportsDataTablesTest.php`

**Step 1: Rename test files**

```bash
git mv tests/Feature/Models/RandomTableModelTest.php tests/Feature/Models/EntityDataTableModelTest.php
git mv tests/Unit/Factories/RandomTableFactoriesTest.php tests/Unit/Factories/EntityDataTableFactoriesTest.php
git mv tests/Feature/Importers/SpellRandomTableImportTest.php tests/Feature/Importers/SpellDataTableImportTest.php
git mv tests/Unit/Parsers/SpellRandomTableParserTest.php tests/Unit/Parsers/SpellDataTableParserTest.php
git mv tests/Feature/Importers/ClassImporterRandomTablesTest.php tests/Feature/Importers/ClassImporterDataTablesTest.php
git mv tests/Unit/Importers/Concerns/ImportsRandomTablesTest.php tests/Unit/Importers/Concerns/ImportsDataTablesTest.php
```

**Step 2: Update class names and references in each renamed file**

In each file, update:
- Class name to match filename
- All `RandomTable` → `EntityDataTable` references
- All `RandomTableEntry` → `EntityDataTableEntry` references
- All `random_tables` → `entity_data_tables` table references
- All `randomTables` → `dataTables` method references
- All `random_table_id` → `entity_data_table_id` column references

**Step 3: Commit**

```bash
git add -A
git commit -m "refactor: rename RandomTable test files to EntityDataTable"
```

---

## Task 12: Update Remaining Test Files

**Files to update (7 files):**
- `tests/Feature/Api/BackgroundApiTest.php`
- `tests/Feature/Api/RaceApiTest.php`
- `tests/Feature/Importers/BackgroundXmlReconstructionTest.php`
- `tests/Feature/Importers/RaceImporterTest.php`
- `tests/Feature/Importers/RaceXmlReconstructionTest.php`
- `tests/Unit/Parsers/BackgroundXmlParserTest.php`
- `tests/Feature/Models/OptionalFeatureTest.php`

**Step 1: Search for all remaining references**

```bash
grep -rl "RandomTable\|random_tables\|randomTables" tests/
```

**Step 2: Update each file**

In each file, find and replace:
- `use App\Models\RandomTable;` → `use App\Models\EntityDataTable;`
- `RandomTable::` → `EntityDataTable::`
- `->randomTables` → `->dataTables`
- `'random_tables'` → `'data_tables'` (in JSON assertions)
- `assertJsonPath('*.random_tables'` → `assertJsonPath('*.data_tables'`

**Step 3: Run all tests**

```bash
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

Expected: All tests pass

**Step 4: Commit**

```bash
git add tests/
git commit -m "refactor: update remaining test files for EntityDataTable rename"
```

---

## Task 13: Create Reference Documentation

**Files:**
- Create: `docs/reference/DATA-TABLE-TYPES.md`

**Step 1: Create documentation file**

```markdown
# Entity Data Table Types

The `entity_data_tables` table uses a `table_type` column to categorize the purpose of each table.

## Table Types

| Type | Description | Has Dice | Examples |
|------|-------------|----------|----------|
| `random` | Rollable tables with discrete outcomes | Yes | Personality Trait (d8), Wild Magic Surge (d100), Bond (d6) |
| `damage` | Damage dice expressions for features/spells | Yes | Necrotic Damage (d12), Psychic Damage (d8), Force Damage (d6) |
| `modifier` | Size and weight calculation modifiers | Yes | Size Modifier (2d4), Weight Modifier (1d6) |
| `lookup` | Reference tables without dice rolls | No | Musical Instrument, Exhaustion Levels, Draconic Ancestry |
| `progression` | Level-based progression tables | No | Bard Spells Known, Eldritch Knight Spells Known |

## Usage in Code

```php
use App\Enums\DataTableType;
use App\Models\EntityDataTable;

// Query by type
$damageTables = EntityDataTable::where('table_type', DataTableType::Damage)->get();

// Check type on instance
if ($table->table_type === DataTableType::Random) {
    // This is a rollable table
}

// Use enum for filtering
$lookups = EntityDataTable::where('table_type', 'lookup')->get();
```

## API Response

The `table_type` field is included in API responses:

```json
{
  "data_tables": [
    {
      "id": 1,
      "table_name": "Wild Magic Surge",
      "dice_type": "d100",
      "table_type": "random",
      "entries": [...]
    }
  ]
}
```

## Migration History

- **2025-11-29**: Renamed from `random_tables` to `entity_data_tables`, added `table_type` column
- Original tables created: 2025-11-18
```

**Step 2: Commit**

```bash
git add docs/reference/DATA-TABLE-TYPES.md
git commit -m "docs: add DATA-TABLE-TYPES.md reference documentation"
```

---

## Task 14: Update Project Documentation

**Files:**
- Modify: `docs/TECH-DEBT.md`
- Modify: `CHANGELOG.md`

**Step 1: Update TECH-DEBT.md**

Mark the item as completed:

```markdown
### 1. ~~Rename `random_tables` to `entity_data_tables`~~ ✅ COMPLETED

**Status:** Completed (2025-11-29)
**PR:** [link if applicable]

Renamed tables and added `table_type` discriminator column. See `docs/reference/DATA-TABLE-TYPES.md`.
```

**Step 2: Update CHANGELOG.md**

Add under `[Unreleased]`:

```markdown
### Changed
- **BREAKING:** Renamed `random_tables` to `entity_data_tables` in database schema
- **BREAKING:** API response key `random_tables` renamed to `data_tables`
- Added `table_type` column to classify table purposes (random, damage, modifier, lookup, progression)
- Renamed all related PHP classes: `RandomTable` → `EntityDataTable`, `RandomTableEntry` → `EntityDataTableEntry`

### Added
- `DataTableType` enum for type-safe table classification
- `docs/reference/DATA-TABLE-TYPES.md` documentation
```

**Step 3: Commit**

```bash
git add docs/TECH-DEBT.md CHANGELOG.md
git commit -m "docs: update TECH-DEBT.md and CHANGELOG.md for entity_data_tables refactor"
```

---

## Task 15: Regenerate API Documentation

**Step 1: Run Pint for code formatting**

```bash
docker compose exec php ./vendor/bin/pint
```

**Step 2: Regenerate api.json via Scramble**

```bash
docker compose exec php php artisan scramble:export
```

**Step 3: Verify api.json updated**

```bash
grep -c "data_tables" api.json
```

Expected: Multiple matches (replacing `random_tables`)

**Step 4: Commit**

```bash
git add api.json
git commit -m "docs: regenerate api.json with data_tables schema"
```

---

## Task 16: Final Verification

**Step 1: Run all test suites**

```bash
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
docker compose exec php php artisan test --testsuite=Feature-Search
```

Expected: All tests pass

**Step 2: Verify no remaining references to old names**

```bash
grep -r "RandomTable\|random_tables\|randomTables" app/ --include="*.php" | grep -v "EntityDataTable"
grep -r "RandomTable\|random_tables\|randomTables" tests/ --include="*.php" | grep -v "EntityDataTable"
```

Expected: No matches (or only in comments/docs)

**Step 3: Run migration on production database (if applicable)**

```bash
docker compose exec php php artisan migrate
```

**Step 4: Re-import data to populate table_type**

```bash
docker compose exec php php artisan import:all
```

**Step 5: Final commit (if any formatting changes)**

```bash
git add -A
git commit -m "chore: final cleanup for entity_data_tables refactor"
```

---

## Summary

| Task | Files Changed | Commits |
|------|---------------|---------|
| 1. Create enum | 1 | 1 |
| 2. Create migration | 1 | 1 |
| 3. Rename models | 2 | 1 |
| 4. Update dependent models | 5 | 1 |
| 5. Rename resources | 2 | 1 |
| 6. Update dependent resources | 4 | 1 |
| 7. Rename importer traits | 2 | 1 |
| 8. Update importers | 7 | 1 |
| 9. Rename parser trait | 2 | 1 |
| 10. Rename factories | 2 | 1 |
| 11. Rename test files | 6 | 1 |
| 12. Update remaining tests | 7 | 1 |
| 13. Create reference docs | 1 | 1 |
| 14. Update project docs | 2 | 1 |
| 15. Regenerate API docs | 1 | 1 |
| 16. Final verification | 0 | 0-1 |
| **Total** | **~45** | **~15** |
