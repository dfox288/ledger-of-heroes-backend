# Add Slugs to Main Entities Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add URL-friendly `slug` columns to main entity tables (spells, items, races, backgrounds, feats, classes, monsters) for SEO-friendly URLs and human-readable identifiers, then update importers to auto-generate slugs during import.

**Architecture:** Add `slug` column with unique constraint to each entity table, implement slug generation logic in models using Laravel's `Str::slug()` helper, update importers to generate slugs during import, and add slug-based lookup methods to API controllers.

**Tech Stack:** Laravel 11.x, PHP 8.4, MySQL 8.0, PHPUnit for testing, TDD methodology

**Rationale:** Slugs enable clean URLs like `/spells/fireball` instead of `/spells/123`, improve SEO, provide human-readable identifiers, and make API endpoints more intuitive.

---

## Problem Analysis

### Current State

**No slug columns exist:**
- ✅ Verified: All 7 main entity tables (spells, items, races, backgrounds, feats, classes, monsters) have NO slug column
- ✅ Current API endpoints use numeric IDs: `/api/v1/spells/123`
- ✅ No human-readable URLs available

**Issues:**
1. URLs are not SEO-friendly: `/spells/123` vs `/spells/fireball`
2. IDs expose database internals
3. No human-readable identifiers for debugging/logging
4. API consumers must track numeric IDs instead of memorable names

### Proposed Solution

**Add slug to all main entities:**
- Spells: `fireball`, `magic-missile`, `cure-wounds`
- Items: `longsword`, `ring-of-protection`, `bag-of-holding`
- Races: `dwarf`, `elf-high`, `dragonborn`
- Backgrounds: `acolyte`, `criminal`, `folk-hero`
- Feats: `great-weapon-master`, `sharpshooter`, `alert`
- Classes: `wizard`, `fighter`, `cleric-life-domain`
- Monsters: `ancient-red-dragon`, `goblin`, `tarrasque`

**Slug Rules:**
1. Lowercase
2. Alphanumeric + hyphens only
3. No special characters
4. Unique per entity type
5. Generated from `name` field
6. Handle duplicates with numeric suffix (`fireball-2`)

**Benefits:**
- SEO-friendly URLs
- Human-readable identifiers
- Better API usability
- Easier debugging/logging
- Future-proof for web frontend

---

## Phase 1: Add Slug Columns to Database

### Task 1: Create Migration to Add Slug Columns

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_add_slugs_to_entities.php`

**Step 1: Create migration file**

Run: `docker compose exec php php artisan make:migration add_slugs_to_entities`
Expected: Migration file created

**Step 2: Write migration**

Edit the generated migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Define entity tables that need slugs
        $tables = [
            'spells',
            'items',
            'races',
            'backgrounds',
            'feats',
            'classes',
            'monsters',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    // Add slug column after name
                    $table->string('slug', 255)->after('name')->nullable();

                    // Add unique index on slug
                    // Note: Will be populated in data migration, then set to unique
                    $table->index('slug');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'spells',
            'items',
            'races',
            'backgrounds',
            'feats',
            'classes',
            'monsters',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropIndex(['slug']);
                    $table->dropColumn('slug');
                });
            }
        }
    }
};
```

**Step 3: Run migration**

Run: `docker compose exec php php artisan migrate`
Expected: Migration runs successfully, slug columns added to all entity tables

**Step 4: Verify columns added**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== Slug columns verification ===\n\";
\$tables = ['spells', 'items', 'races', 'backgrounds', 'feats', 'classes', 'monsters'];
foreach (\$tables as \$table) {
    \$hasSlug = Schema::hasColumn(\$table, 'slug');
    echo \"\$table: \" . (\$hasSlug ? 'HAS slug ✓' : 'NO slug ✗') . \"\n\";
}
"`
Expected: All tables show "HAS slug ✓"

**Step 5: Commit**

```bash
git add database/migrations/*_add_slugs_to_entities.php
git commit -m "feat: add slug columns to all entity tables"
```

---

## Phase 2: Add Slug Generation to Models

### Task 2: Create SlugGenerator Trait

**Files:**
- Create: `app/Traits/HasSlug.php`
- Create: `tests/Unit/Traits/HasSlugTest.php`

**Step 1: Write failing test**

Create `tests/Unit/Traits/HasSlugTest.php`:

```php
<?php

namespace Tests\Unit\Traits;

use Illuminate\Support\Str;
use Tests\TestCase;

class HasSlugTest extends TestCase
{
    public function test_generates_slug_from_name(): void
    {
        $name = "Fireball";
        $expected = "fireball";

        $slug = Str::slug($name);

        $this->assertEquals($expected, $slug);
    }

    public function test_generates_slug_with_special_characters(): void
    {
        $name = "Tasha's Hideous Laughter";
        $expected = "tashas-hideous-laughter";

        $slug = Str::slug($name);

        $this->assertEquals($expected, $slug);
    }

    public function test_generates_slug_with_slashes(): void
    {
        $name = "Elf, Drow / Dark";
        $expected = "elf-drow-dark";

        $slug = Str::slug($name);

        $this->assertEquals($expected, $slug);
    }

    public function test_generates_slug_with_commas(): void
    {
        $name = "Dwarf, Hill";
        $expected = "dwarf-hill";

        $slug = Str::slug($name);

        $this->assertEquals($expected, $slug);
    }

    public function test_generates_slug_with_plus_signs(): void
    {
        $name = "Longsword +1";
        $expected = "longsword-1";

        $slug = Str::slug($name);

        $this->assertEquals($expected, $slug);
    }
}
```

**Step 2: Run test to verify Laravel's Str::slug() works**

Run: `docker compose exec php php artisan test --filter=HasSlugTest`
Expected: PASS (tests Laravel's built-in slug generation)

**Step 3: Create HasSlug trait**

Create `app/Traits/HasSlug.php`:

```php
<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Boot the HasSlug trait for a model.
     */
    protected static function bootHasSlug(): void
    {
        // Auto-generate slug when creating if not provided
        static::creating(function ($model) {
            if (empty($model->slug) && !empty($model->name)) {
                $model->slug = static::generateUniqueSlug($model->name, $model->getTable());
            }
        });

        // Update slug when name changes
        static::updating(function ($model) {
            if ($model->isDirty('name') && !$model->isDirty('slug')) {
                $model->slug = static::generateUniqueSlug($model->name, $model->getTable(), $model->id);
            }
        });
    }

    /**
     * Generate a unique slug for the given name.
     *
     * @param string $name The name to slugify
     * @param string $table The database table name
     * @param int|null $ignoreId ID to ignore when checking uniqueness (for updates)
     * @return string
     */
    public static function generateUniqueSlug(string $name, string $table, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);

        // Check if slug already exists
        $count = \DB::table($table)
            ->where('slug', $slug)
            ->when($ignoreId, function ($query, $ignoreId) {
                return $query->where('id', '!=', $ignoreId);
            })
            ->count();

        // If slug exists, append number
        if ($count > 0) {
            $suffix = 2;
            $originalSlug = $slug;

            do {
                $slug = "{$originalSlug}-{$suffix}";
                $count = \DB::table($table)
                    ->where('slug', $slug)
                    ->when($ignoreId, function ($query, $ignoreId) {
                        return $query->where('id', '!=', $ignoreId);
                    })
                    ->count();
                $suffix++;
            } while ($count > 0);
        }

        return $slug;
    }

    /**
     * Find a model by its slug.
     *
     * @param string $slug
     * @return static|null
     */
    public static function findBySlug(string $slug)
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Find a model by its slug or fail.
     *
     * @param string $slug
     * @return static
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findBySlugOrFail(string $slug)
    {
        return static::where('slug', $slug)->firstOrFail();
    }
}
```

**Step 4: Commit**

```bash
git add app/Traits/HasSlug.php tests/Unit/Traits/HasSlugTest.php
git commit -m "feat: add HasSlug trait for automatic slug generation"
```

---

### Task 3: Add HasSlug Trait to Spell Model

**Files:**
- Modify: `app/Models/Spell.php`
- Create: `tests/Feature/Models/SpellSlugTest.php`

**Step 1: Write failing test**

Create `tests/Feature/Models/SpellSlugTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_auto_generates_slug_on_create(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

        $spell = Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak...',
            'source_id' => $source->id,
            'source_pages' => '241',
        ]);

        $this->assertEquals('fireball', $spell->slug);
    }

    public function test_spell_handles_duplicate_slug_with_suffix(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

        // Create first spell
        $spell1 = Spell::create([
            'name' => 'Test Spell',
            'level' => 1,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'Test description 1',
            'source_id' => $source->id,
            'source_pages' => '100',
        ]);

        // Create second spell with same name
        $spell2 = Spell::create([
            'name' => 'Test Spell',
            'level' => 2,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'Test description 2',
            'source_id' => $source->id,
            'source_pages' => '101',
        ]);

        $this->assertEquals('test-spell', $spell1->slug);
        $this->assertEquals('test-spell-2', $spell2->slug);
    }

    public function test_spell_find_by_slug(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

        Spell::create([
            'name' => 'Magic Missile',
            'level' => 1,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '120 feet',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'You create three...',
            'source_id' => $source->id,
            'source_pages' => '257',
        ]);

        $spell = Spell::findBySlug('magic-missile');

        $this->assertNotNull($spell);
        $this->assertEquals('Magic Missile', $spell->name);
    }

    public function test_spell_handles_special_characters_in_name(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

        $spell = Spell::create([
            'name' => "Tasha's Hideous Laughter",
            'level' => 1,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '30 feet',
            'components' => 'V, S, M',
            'duration' => 'Concentration, up to 1 minute',
            'needs_concentration' => true,
            'is_ritual' => false,
            'description' => 'A creature...',
            'source_id' => $source->id,
            'source_pages' => '280',
        ]);

        $this->assertEquals('tashas-hideous-laughter', $spell->slug);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=SpellSlugTest`
Expected: FAIL - "Failed asserting that null matches expected 'fireball'"

**Step 3: Update Spell model to use HasSlug trait**

Edit `app/Models/Spell.php`:

Add at the top with other use statements:
```php
use App\Traits\HasSlug;
```

Add trait to class:
```php
class Spell extends Model
{
    use HasSlug;

    public $timestamps = false;
```

Add slug to fillable:
```php
protected $fillable = [
    'name',
    'slug',  // ADD THIS
    'level',
    'spell_school_id',
    // ... rest of fillable fields
];
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=SpellSlugTest`
Expected: PASS (4 tests)

**Step 5: Commit**

```bash
git add app/Models/Spell.php tests/Feature/Models/SpellSlugTest.php
git commit -m "feat: add slug auto-generation to Spell model"
```

---

### Task 4: Add HasSlug Trait to Remaining Entity Models

**Files:**
- Modify: `app/Models/Item.php` (if exists)
- Modify: `app/Models/Race.php`
- Modify: `app/Models/Background.php` (if exists)
- Modify: `app/Models/Feat.php` (if exists)
- Modify: `app/Models/CharacterClass.php`
- Modify: `app/Models/Monster.php` (if exists)

**Step 1: Update Race model**

Edit `app/Models/Race.php`:

Add trait:
```php
use App\Traits\HasSlug;

class Race extends Model
{
    use HasSlug;
```

Add slug to fillable:
```php
protected $fillable = [
    'name',
    'slug',  // ADD THIS
    'size_id',
    'speed',
    'description',
    'source_id',
    'source_pages',
    'parent_race_id',
];
```

**Step 2: Update CharacterClass model**

Edit `app/Models/CharacterClass.php`:

Add trait:
```php
use App\Traits\HasSlug;

class CharacterClass extends Model
{
    use HasSlug;
```

Add slug to fillable:
```php
protected $fillable = [
    'name',
    'slug',  // ADD THIS
    'parent_class_id',
    'hit_die',
    'description',
    // ... rest of fillable fields
];
```

**Step 3: Update Item, Background, Feat, Monster models (if they exist)**

Repeat the same pattern for each model:
1. Add `use App\Traits\HasSlug;` import
2. Add `use HasSlug;` to class
3. Add `'slug'` to `$fillable` array

**Step 4: Test slug generation on Race**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$race = \App\Models\Race::first();
if (\$race) {
    echo 'Testing slug generation on Race model...\n';
    echo 'Race name: ' . \$race->name . \"\n\";
    echo 'Current slug: ' . (\$race->slug ?? 'NULL') . \"\n\";

    // Update to trigger slug generation
    \$race->name = 'Test Dwarf';
    \$race->save();

    echo 'New slug: ' . \$race->slug . \"\n\";
    echo 'Expected: test-dwarf\n';

    // Restore original name
    \$race->name = 'Dwarf';
    \$race->save();
}
"`
Expected: Shows slug generation working

**Step 5: Commit**

```bash
git add app/Models/Race.php app/Models/CharacterClass.php
# Add other model files if they exist
git commit -m "feat: add slug auto-generation to all entity models"
```

---

## Phase 3: Generate Slugs for Existing Data

### Task 5: Create Data Migration to Populate Slugs

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_populate_slugs_for_existing_data.php`

**Step 1: Create migration file**

Run: `docker compose exec php php artisan make:migration populate_slugs_for_existing_data`
Expected: Migration file created

**Step 2: Write data migration**

Edit the generated migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'spells',
            'items',
            'races',
            'backgrounds',
            'feats',
            'classes',
            'monsters',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            echo "Generating slugs for {$table}...\n";

            // Get all records
            $records = DB::table($table)->whereNull('slug')->orWhere('slug', '')->get();

            foreach ($records as $record) {
                $slug = $this->generateUniqueSlug($record->name, $table, $record->id);

                DB::table($table)
                    ->where('id', $record->id)
                    ->update(['slug' => $slug]);
            }

            echo "  Generated " . count($records) . " slugs\n";
        }

        // Now add unique constraint to slug columns
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    // Drop existing index
                    $blueprint->dropIndex(['{$table}_slug_index']);

                    // Add unique constraint
                    $blueprint->unique('slug');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'spells',
            'items',
            'races',
            'backgrounds',
            'feats',
            'classes',
            'monsters',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropUnique(['{$table}_slug_unique']);
                    $blueprint->index('slug');
                });

                // Clear slugs
                DB::table($table)->update(['slug' => null]);
            }
        }
    }

    /**
     * Generate a unique slug for the given name.
     */
    private function generateUniqueSlug(string $name, string $table, int $ignoreId): string
    {
        $slug = Str::slug($name);

        $count = DB::table($table)
            ->where('slug', $slug)
            ->where('id', '!=', $ignoreId)
            ->count();

        if ($count > 0) {
            $suffix = 2;
            $originalSlug = $slug;

            do {
                $slug = "{$originalSlug}-{$suffix}";
                $count = DB::table($table)
                    ->where('slug', $slug)
                    ->where('id', '!=', $ignoreId)
                    ->count();
                $suffix++;
            } while ($count > 0);
        }

        return $slug;
    }
};
```

**Step 3: Run migration**

Run: `docker compose exec php php artisan migrate`
Expected: Migration runs, slugs generated for all existing data

**Step 4: Verify slugs generated**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== Slug generation verification ===\n\";
echo \"Spells with slugs: \" . \App\Models\Spell::whereNotNull('slug')->count() . \" / \" . \App\Models\Spell::count() . \"\n\";
echo \"Races with slugs: \" . \App\Models\Race::whereNotNull('slug')->count() . \" / \" . \App\Models\Race::count() . \"\n\";
echo \"Classes with slugs: \" . \App\Models\CharacterClass::whereNotNull('slug')->count() . \" / \" . \App\Models\CharacterClass::count() . \"\n\";

echo \"\n=== Sample slugs ===\n\";
\$spell = \App\Models\Spell::first();
if (\$spell) {
    echo \"Spell: \" . \$spell->name . \" → \" . \$spell->slug . \"\n\";
}

\$race = \App\Models\Race::whereNull('parent_race_id')->first();
if (\$race) {
    echo \"Race: \" . \$race->name . \" → \" . \$race->slug . \"\n\";
}

\$class = \App\Models\CharacterClass::whereNull('parent_class_id')->first();
if (\$class) {
    echo \"Class: \" . \$class->name . \" → \" . \$class->slug . \"\n\";
}
"`
Expected: All entities have slugs generated

**Step 5: Commit**

```bash
git add database/migrations/*_populate_slugs_for_existing_data.php
git commit -m "data: generate slugs for all existing entities"
```

---

## Phase 4: Update Importers to Generate Slugs

### Task 6: Update SpellImporter to Generate Slugs

**Files:**
- Modify: `app/Services/Importers/SpellImporter.php`
- Modify: `tests/Feature/Importers/SpellImporterTest.php`

**Step 1: Write failing test**

Edit `tests/Feature/Importers/SpellImporterTest.php`, add test:

```php
/** @test */
public function it_generates_slug_when_importing_spell()
{
    $spellData = [
        'name' => 'New Test Spell',
        'level' => 1,
        'school' => 'EV',
        'casting_time' => '1 action',
        'range' => 'Touch',
        'components' => 'V, S',
        'material_components' => null,
        'duration' => 'Instantaneous',
        'needs_concentration' => false,
        'is_ritual' => false,
        'description' => 'Test description',
        'higher_levels' => null,
        'classes' => ['Wizard'],
        'sources' => [
            ['code' => 'PHB', 'pages' => '100'],
        ],
    ];

    $importer = new SpellImporter();
    $spell = $importer->import($spellData);

    $this->assertNotNull($spell->slug);
    $this->assertEquals('new-test-spell', $spell->slug);
}

/** @test */
public function it_preserves_existing_slug_on_reimport()
{
    // First import
    $spellData = [
        'name' => 'Unique Spell',
        'level' => 1,
        'school' => 'EV',
        'casting_time' => '1 action',
        'range' => 'Touch',
        'components' => 'V, S',
        'duration' => 'Instantaneous',
        'needs_concentration' => false,
        'is_ritual' => false,
        'description' => 'Test description',
        'classes' => ['Wizard'],
        'sources' => [
            ['code' => 'PHB', 'pages' => '100'],
        ],
    ];

    $importer = new SpellImporter();
    $spell = $importer->import($spellData);
    $originalSlug = $spell->slug;

    // Second import (update)
    $spell = $importer->import($spellData);

    $this->assertEquals($originalSlug, $spell->slug);
}
```

**Step 2: Run test to verify it passes**

The test should already pass because the HasSlug trait automatically generates slugs on model creation. The importer doesn't need changes!

Run: `docker compose exec php php artisan test --filter=SpellImporterTest`
Expected: PASS (including 2 new tests)

**Step 3: Verify importer works**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Clear existing spells for clean test
\App\Models\Spell::whereNull('id')->orWhereNotNull('id')->delete();

// Import spells
\$importer = new \App\Services\Importers\SpellImporter();
\$count = \$importer->importFromFile('import-files/spells-phb.xml');

echo \"Imported \$count spells\n\";

// Check slugs
\$withSlugs = \App\Models\Spell::whereNotNull('slug')->count();
\$total = \App\Models\Spell::count();

echo \"Spells with slugs: \$withSlugs / \$total\n\";

// Sample slugs
\$spells = \App\Models\Spell::limit(5)->get();
foreach (\$spells as \$spell) {
    echo \"  \" . \$spell->name . \" → \" . \$spell->slug . \"\n\";
}
"`
Expected: All imported spells have slugs automatically generated

**Step 4: Commit**

```bash
git add tests/Feature/Importers/SpellImporterTest.php
git commit -m "test: verify slug generation in SpellImporter"
```

---

### Task 7: Update RaceImporter to Generate Slugs

**Files:**
- Modify: `tests/Feature/Importers/RaceImporterTest.php`

**Step 1: Write test for slug generation**

Edit `tests/Feature/Importers/RaceImporterTest.php`, add test:

```php
/** @test */
public function it_generates_slug_when_importing_race()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Test Race</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Test race description.
Source: Player's Handbook (2014) p. 100</text>
    </trait>
  </race>
</compendium>
XML;

    $count = $this->importer->importFromXml($xml);

    $race = Race::where('name', 'Test Race')->first();
    $this->assertNotNull($race);
    $this->assertNotNull($race->slug);
    $this->assertEquals('test-race', $race->slug);
}

/** @test */
public function it_generates_slug_for_subrace()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Mountain</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>Mountain dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

    $count = $this->importer->importFromXml($xml);

    // Base race should have slug
    $baseRace = Race::where('name', 'Dwarf')->whereNull('parent_race_id')->first();
    $this->assertNotNull($baseRace->slug);
    $this->assertEquals('dwarf', $baseRace->slug);

    // Subrace should have slug
    $subrace = Race::where('name', 'Mountain')->whereNotNull('parent_race_id')->first();
    $this->assertNotNull($subrace->slug);
    $this->assertEquals('mountain', $subrace->slug);
}
```

**Step 2: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=RaceImporterTest`
Expected: PASS (including 2 new tests)

**Step 3: Commit**

```bash
git add tests/Feature/Importers/RaceImporterTest.php
git commit -m "test: verify slug generation in RaceImporter"
```

---

## Phase 5: Update API to Support Slug-Based Lookups

### Task 8: Update SpellController to Support Slug Lookups

**Files:**
- Modify: `app/Http/Controllers/Api/SpellController.php`
- Modify: `tests/Feature/Api/SpellApiTest.php`

**Step 1: Write failing test**

Edit `tests/Feature/Api/SpellApiTest.php`, add test:

```php
/** @test */
public function it_can_get_spell_by_slug()
{
    $school = SpellSchool::first();
    $source = Source::first();

    $spell = Spell::create([
        'name' => 'Fireball',
        'level' => 3,
        'spell_school_id' => $school->id,
        'casting_time' => '1 action',
        'range' => '150 feet',
        'components' => 'V, S, M',
        'duration' => 'Instantaneous',
        'needs_concentration' => false,
        'is_ritual' => false,
        'description' => 'A bright streak...',
        'source_id' => $source->id,
        'source_pages' => '241',
    ]);

    // Ensure slug is generated
    $this->assertNotNull($spell->slug);

    // Test lookup by slug
    $response = $this->getJson("/api/v1/spells/{$spell->slug}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.name', 'Fireball');
    $response->assertJsonPath('data.slug', 'fireball');
}

/** @test */
public function it_includes_slug_in_spell_list()
{
    $response = $this->getJson('/api/v1/spells');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
                'slug',  // NEW: slug should be in response
                'level',
                'school',
                // ... other fields
            ]
        ]
    ]);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=SpellApiTest`
Expected: FAIL - "Response status code [404] does not match expected 200"

**Step 3: Update SpellController to support slug lookups**

Edit `app/Http/Controllers/Api/SpellController.php`:

Update the `show` method:
```php
public function show($idOrSlug)
{
    // Try to find by ID first (numeric), then by slug
    if (is_numeric($idOrSlug)) {
        $spell = Spell::with(['spellSchool', 'source', 'entitySources.source'])
            ->findOrFail($idOrSlug);
    } else {
        $spell = Spell::with(['spellSchool', 'source', 'entitySources.source'])
            ->where('slug', $idOrSlug)
            ->firstOrFail();
    }

    return new SpellResource($spell);
}
```

**Step 4: Update SpellResource to include slug**

Edit `app/Http/Resources/SpellResource.php`:

Add slug to the response array:
```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,  // ADD THIS
        'level' => $this->level,
        // ... rest of fields
    ];
}
```

**Step 5: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=SpellApiTest`
Expected: PASS (all tests including 2 new)

**Step 6: Test API manually**

Run: `curl -s "http://localhost:8080/api/v1/spells/fireball" | python3 -m json.tool | head -30`
Expected: Returns Fireball spell data

**Step 7: Commit**

```bash
git add app/Http/Controllers/Api/SpellController.php app/Http/Resources/SpellResource.php tests/Feature/Api/SpellApiTest.php
git commit -m "feat: add slug-based lookups and slug field to Spell API"
```

---

### Task 9: Update RaceController to Support Slug Lookups

**Files:**
- Modify: `app/Http/Controllers/Api/RaceController.php`
- Modify: `app/Http/Resources/RaceResource.php`
- Modify: `tests/Feature/Api/RaceApiTest.php`

**Step 1: Write failing test**

Edit `tests/Feature/Api/RaceApiTest.php`, add test:

```php
/** @test */
public function it_can_get_race_by_slug()
{
    $race = Race::factory()->create([
        'name' => 'Test Elf',
    ]);

    // Ensure slug is generated
    $this->assertNotNull($race->slug);

    $response = $this->getJson("/api/v1/races/{$race->slug}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.name', 'Test Elf');
    $response->assertJsonPath('data.slug', 'test-elf');
}

/** @test */
public function it_includes_slug_in_race_list()
{
    $response = $this->getJson('/api/v1/races');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
                'slug',  // NEW
                'size',
                'speed',
                // ... other fields
            ]
        ]
    ]);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=RaceApiTest`
Expected: FAIL

**Step 3: Update RaceController**

Edit `app/Http/Controllers/Api/RaceController.php`:

Update the `show` method:
```php
public function show($idOrSlug)
{
    if (is_numeric($idOrSlug)) {
        $race = Race::with(['size', 'source', 'parent', 'subraces', 'proficiencies.skill', 'entitySources.source'])
            ->findOrFail($idOrSlug);
    } else {
        $race = Race::with(['size', 'source', 'parent', 'subraces', 'proficiencies.skill', 'entitySources.source'])
            ->where('slug', $idOrSlug)
            ->firstOrFail();
    }

    return new RaceResource($race);
}
```

**Step 4: Update RaceResource**

Edit `app/Http/Resources/RaceResource.php`:

Add slug:
```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,  // ADD THIS
        'size' => new SizeResource($this->whenLoaded('size')),
        // ... rest of fields
    ];
}
```

**Step 5: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=RaceApiTest`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/RaceController.php app/Http/Resources/RaceResource.php tests/Feature/Api/RaceApiTest.php
git commit -m "feat: add slug-based lookups and slug field to Race API"
```

---

### Task 10: Update Remaining API Controllers (if they exist)

**Files:**
- Check: `app/Http/Controllers/Api/ClassController.php` (if exists)
- Check: `app/Http/Controllers/Api/ItemController.php` (if exists)
- Check: Other entity controllers

**Step 1: Apply same pattern to other controllers**

For each controller that exists, update:
1. `show($idOrSlug)` method to handle both ID and slug
2. Resource class to include `'slug' => $this->slug`
3. Tests to verify slug-based lookup

**Step 2: Commit each controller**

```bash
git add app/Http/Controllers/Api/ app/Http/Resources/ tests/Feature/Api/
git commit -m "feat: add slug support to all entity API endpoints"
```

---

## Phase 6: Final Verification and Documentation

### Task 11: Run Full Test Suite and Verify

**Step 1: Run all tests**

Run: `docker compose exec php php artisan test`
Expected: All tests pass (~200+ tests)

**Step 2: Verify slug data in database**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== Final Slug Verification ===\n\";

\$entities = [
    'Spell' => \App\Models\Spell::class,
    'Race' => \App\Models\Race::class,
    'CharacterClass' => \App\Models\CharacterClass::class,
];

foreach (\$entities as \$name => \$class) {
    \$total = \$class::count();
    \$withSlugs = \$class::whereNotNull('slug')->where('slug', '!=', '')->count();
    \$percentage = \$total > 0 ? round((\$withSlugs / \$total) * 100, 1) : 0;

    echo \"\$name: \$withSlugs / \$total (\$percentage%)\n\";

    // Show sample
    \$sample = \$class::whereNotNull('slug')->first();
    if (\$sample) {
        echo \"  Sample: \" . \$sample->name . \" → \" . \$sample->slug . \"\n\";
    }
}
"`
Expected: 100% of entities have slugs

**Step 3: Test API endpoints with slugs**

```bash
# Test spell by slug
curl -s "http://localhost:8080/api/v1/spells/fireball" | python3 -m json.tool | head -20

# Test race by slug
curl -s "http://localhost:8080/api/v1/races/dwarf" | python3 -m json.tool | head -20

# Test spell list includes slugs
curl -s "http://localhost:8080/api/v1/spells" | python3 -m json.tool | grep -A2 "slug"
```

Expected: All slug-based lookups work correctly

**Step 4: Check for any slug collisions**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== Checking for slug collisions ===\n\";

\$tables = ['spells', 'races', 'classes'];
foreach (\$tables as \$table) {
    \$duplicates = DB::table(\$table)
        ->select('slug', DB::raw('COUNT(*) as count'))
        ->groupBy('slug')
        ->having('count', '>', 1)
        ->get();

    if (\$duplicates->count() > 0) {
        echo \"\$table has \" . \$duplicates->count() . \" duplicate slugs:\n\";
        foreach (\$duplicates as \$dup) {
            echo \"  \" . \$dup->slug . \" (\" . \$dup->count . \" occurrences)\n\";
        }
    } else {
        echo \"\$table: No duplicate slugs ✓\n\";
    }
}
"`
Expected: No duplicate slugs

**Step 5: Update documentation**

Update `docs/HANDOVER-2025-11-18.md` or create new handover:

```markdown
## Slugs for All Entities - COMPLETE ✅

**Date:** 2025-11-18

**What Changed:**
- Added `slug` column to all main entity tables (spells, items, races, backgrounds, feats, classes, monsters)
- Created `HasSlug` trait for automatic slug generation
- Generated slugs for all existing data
- Updated API controllers to support slug-based lookups
- Updated API resources to include slug field

**Slug Format:**
- Lowercase alphanumeric + hyphens
- Generated from entity name
- Unique per entity type
- Handles duplicates with numeric suffix

**Examples:**
- Spell: "Fireball" → `fireball`
- Race: "Elf, High" → `elf-high`
- Class: "Fighter" → `fighter`
- Item: "Longsword +1" → `longsword-1`

**API Changes:**
- NEW: Slug-based lookups supported
  - `/api/v1/spells/fireball` (slug)
  - `/api/v1/spells/123` (ID, still supported)
- NEW: Slug field in all entity responses

**Database:**
- All entities have unique slugs (100% coverage)
- No slug collisions
- Automatic generation on create/update via HasSlug trait

**Testing:**
- All tests passing (~200+ tests)
- Slug generation tested in models
- Slug generation tested in importers
- Slug-based API lookups tested
```

**Step 6: Commit**

```bash
git add docs/
git commit -m "docs: document slug implementation for all entities"
```

---

## Summary

**Total Tasks:** 11

**What Was Built:**
1. ✅ Slug columns added to 7 entity tables
2. ✅ `HasSlug` trait for automatic slug generation
3. ✅ Slug generation on model create/update
4. ✅ Unique slug handling with numeric suffixes
5. ✅ Data migration to populate slugs for existing data
6. ✅ Importers automatically generate slugs (via trait)
7. ✅ API controllers support slug-based lookups
8. ✅ API resources include slug field
9. ✅ Comprehensive tests for slug functionality

**Entities with Slugs:**
- ✅ Spells (361+ spells)
- ✅ Items (when imported)
- ✅ Races (15+ races)
- ✅ Backgrounds (when imported)
- ✅ Feats (when imported)
- ✅ Classes (13 classes)
- ✅ Monsters (when imported)

**Key Benefits:**
- SEO-friendly URLs: `/spells/fireball`
- Human-readable identifiers
- Better API usability
- Easier debugging/logging
- Future-proof for web frontend

**API Backward Compatibility:**
- ✅ Numeric ID lookups still work: `/spells/123`
- ✅ New slug lookups: `/spells/fireball`
- ✅ Slug field added to all responses

**Testing:**
- TDD approach with tests first
- Model tests for slug generation
- Importer tests for automatic slugs
- API tests for slug-based lookups
- Integration tests for full pipeline

**Estimated Implementation:** 3-4 hours with TDD

**Next Steps:** Use slugs in frontend URLs, add slug-based search/filtering

---

## Execution Options

Plan complete and saved to `docs/plans/2025-11-18-add-slugs-to-entities.md`. Two execution options:

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

Which approach would you like to use?
