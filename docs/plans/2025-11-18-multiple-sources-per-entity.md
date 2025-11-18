# Multiple Sources Per Entity Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace single `source_id` + `source_pages` with many-to-many relationship via `entity_sources` junction table, enabling entities to reference multiple sourcebooks (e.g., PHB p. 150 AND DMG p. 75).

**Architecture:** Create polymorphic `entity_sources` junction table to link any entity (spells, items, races, etc.) to multiple sources. Migrate existing single-source data, update parsers to handle multi-source citations, update importers to create junction records, and update API resources to return all sources per entity.

**Tech Stack:** Laravel 11.x, PHP 8.4, MySQL 8.0, PHPUnit for testing, TDD methodology

**Design Document Reference:** `docs/plans/2025-11-17-dnd-compendium-database-design.md` (Section on sources)

---

## Problem Analysis

### Current State

**Schema (per entity table):**
```sql
source_id integer FK → sources
source_pages text nullable  -- "148, 150", "211-213"
```

**XML Format (items-magic-phb+dmg.xml):**
```xml
<text>Item description...

Source:	Dungeon Master's Guide (2014) p. 150,
		Player's Handbook (2014) p. 150</text>
```

**Issues:**
1. Single `source_id` cannot represent items published in multiple books
2. `source_pages` text field mixes multiple page numbers without book attribution
3. Cannot query "show all spells from PHB" if spell also appears in TCE
4. Cannot display "This spell appears in PHB p. 150 and TCE p. 75"

### Proposed Solution

**New junction table:**
```sql
entity_sources (
  entity_type text,      -- 'spell', 'item', 'race', 'feat', etc.
  entity_id integer,     -- FK to entity table
  source_id integer,     -- FK to sources table
  pages text nullable    -- "148, 150" for this specific source
)
```

**Benefits:**
- Entities can reference multiple sourcebooks
- Each source has its own page references
- Queries like "all content from PHB" become simple joins
- Preserves full citation information from XML

---

## Phase 1: Create entity_sources Junction Table

### Task 1: Create Migration for entity_sources Table

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_create_entity_sources_table.php`

**Step 1: Create migration file**

Run: `docker compose exec php php artisan make:migration create_entity_sources_table`
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
        Schema::create('entity_sources', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference to any entity (spell, item, race, etc.)
            $table->string('entity_type', 50); // 'spell', 'item', 'race', 'feat', 'background', 'class', 'monster'
            $table->unsignedBigInteger('entity_id');

            // Reference to sources table
            $table->unsignedBigInteger('source_id');

            // Page numbers specific to this source (e.g., "148, 150", "211-213")
            $table->string('pages', 100)->nullable();

            // Foreign key to sources
            $table->foreign('source_id')
                  ->references('id')
                  ->on('sources')
                  ->onDelete('cascade');

            // Indexes for efficient querying
            $table->index(['entity_type', 'entity_id']); // Find all sources for an entity
            $table->index('source_id'); // Find all entities from a source

            // Unique constraint - same entity can't reference same source twice
            $table->unique(['entity_type', 'entity_id', 'source_id']);

            // NO timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_sources');
    }
};
```

**Step 3: Run migration**

Run: `docker compose exec php php artisan migrate`
Expected: Migration runs successfully, `entity_sources` table created

**Step 4: Verify migration**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo 'entity_sources table exists: ' . (Schema::hasTable('entity_sources') ? 'YES' : 'NO') . \"\n\";
"`
Expected: "entity_sources table exists: YES"

**Step 5: Commit**

```bash
git add database/migrations/*_create_entity_sources_table.php
git commit -m "feat: create entity_sources polymorphic junction table"
```

---

### Task 2: Create EntitySource Model

**Files:**
- Create: `app/Models/EntitySource.php`
- Create: `tests/Feature/Models/EntitySourceTest.php`

**Step 1: Write failing test**

Create `tests/Feature/Models/EntitySourceTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\EntitySource;
use App\Models\Source;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitySourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_entity_source_belongs_to_source(): void
    {
        $source = Source::where('code', 'PHB')->first();
        $spell = Spell::first();

        $entitySource = EntitySource::create([
            'entity_type' => 'spell',
            'entity_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => '150',
        ]);

        $this->assertInstanceOf(Source::class, $entitySource->source);
        $this->assertEquals('PHB', $entitySource->source->code);
    }

    public function test_entity_source_has_polymorphic_reference(): void
    {
        $source = Source::where('code', 'PHB')->first();
        $spell = Spell::first();

        $entitySource = EntitySource::create([
            'entity_type' => 'spell',
            'entity_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => '150',
        ]);

        // Polymorphic relationship (we'll implement this in model)
        $this->assertEquals('spell', $entitySource->entity_type);
        $this->assertEquals($spell->id, $entitySource->entity_id);
    }

    public function test_entity_source_does_not_use_timestamps(): void
    {
        $entitySource = new EntitySource();
        $this->assertFalse($entitySource->timestamps);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=EntitySourceTest`
Expected: FAIL (class doesn't exist)

**Step 3: Create EntitySource model**

Create `app/Models/EntitySource.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntitySource extends Model
{
    public $timestamps = false; // CRITICAL: No timestamps on static data

    protected $fillable = [
        'entity_type',
        'entity_id',
        'source_id',
        'pages',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'source_id' => 'integer',
    ];

    // Relationship to sources table
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    // Polymorphic relationship to any entity
    // Note: Laravel expects 'entity' method name for 'entity_type'/'entity_id' columns
    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=EntitySourceTest`
Expected: PASS (3 tests)

**Step 5: Commit**

```bash
git add app/Models/EntitySource.php tests/Feature/Models/EntitySourceTest.php
git commit -m "feat: create EntitySource model with polymorphic relationships"
```

---

## Phase 2: Add entitySources Relationship to Entity Models

### Task 3: Update Spell Model with entitySources Relationship

**Files:**
- Modify: `app/Models/Spell.php:54` (add relationship after source())
- Modify: `tests/Feature/Models/SpellModelTest.php` (create if doesn't exist)

**Step 1: Write failing test**

Create or edit `tests/Feature/Models/SpellModelTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\EntitySource;
use App\Models\Source;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_has_many_entity_sources(): void
    {
        $spell = Spell::first();
        $phb = Source::where('code', 'PHB')->first();
        $dmg = Source::where('code', 'DMG')->first();

        // Create two entity_sources for this spell
        EntitySource::create([
            'entity_type' => 'spell',
            'entity_id' => $spell->id,
            'source_id' => $phb->id,
            'pages' => '150',
        ]);

        EntitySource::create([
            'entity_type' => 'spell',
            'entity_id' => $spell->id,
            'source_id' => $dmg->id,
            'pages' => '75',
        ]);

        $spell->refresh();

        $this->assertCount(2, $spell->entitySources);
        $this->assertInstanceOf(EntitySource::class, $spell->entitySources->first());
    }

    public function test_spell_can_access_sources_through_entity_sources(): void
    {
        $spell = Spell::first();
        $phb = Source::where('code', 'PHB')->first();

        EntitySource::create([
            'entity_type' => 'spell',
            'entity_id' => $spell->id,
            'source_id' => $phb->id,
            'pages' => '150',
        ]);

        $spell->load('entitySources.source');

        $this->assertEquals('PHB', $spell->entitySources->first()->source->code);
        $this->assertEquals('150', $spell->entitySources->first()->pages);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=SpellModelTest`
Expected: FAIL - "Call to undefined relationship method entitySources()"

**Step 3: Update Spell model**

Edit `app/Models/Spell.php`, add after the `source()` method:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;

// ... existing relationships ...

public function entitySources(): MorphMany
{
    return $this->morphMany(EntitySource::class, 'entity', 'entity_type', 'entity_id');
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=SpellModelTest`
Expected: PASS (2 tests)

**Step 5: Commit**

```bash
git add app/Models/Spell.php tests/Feature/Models/SpellModelTest.php
git commit -m "feat: add entitySources relationship to Spell model"
```

---

### Task 4: Update Remaining Entity Models (Item, Race, Feat, etc.)

**Files:**
- Modify: `app/Models/Item.php` (if exists)
- Modify: `app/Models/Race.php`
- Modify: `app/Models/Feat.php` (if exists)
- Modify: `app/Models/Background.php` (if exists)
- Modify: `app/Models/ClassModel.php`
- Modify: `app/Models/Monster.php` (if exists)

**Step 1: Add entitySources relationship to Race model**

Edit `app/Models/Race.php`:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;

// ... existing relationships ...

public function entitySources(): MorphMany
{
    return $this->morphMany(EntitySource::class, 'entity', 'entity_type', 'entity_id');
}
```

**Step 2: Verify Race model works**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$race = \App\Models\Race::first();
echo 'Race entitySources relationship exists: ' . (method_exists(\$race, 'entitySources') ? 'YES' : 'NO') . \"\n\";
"`
Expected: "Race entitySources relationship exists: YES"

**Step 3: Add entitySources to other entity models**

Repeat the same pattern for:
- `app/Models/Item.php` (if exists)
- `app/Models/Feat.php` (if exists)
- `app/Models/Background.php` (if exists)
- `app/Models/ClassModel.php`
- `app/Models/Monster.php` (if exists)

Each model gets:
```php
use Illuminate\Database\Eloquent\Relations\MorphMany;

public function entitySources(): MorphMany
{
    return $this->morphMany(EntitySource::class, 'entity', 'entity_type', 'entity_id');
}
```

**Step 4: Commit**

```bash
git add app/Models/Race.php app/Models/ClassModel.php
# Add other model files if they exist
git commit -m "feat: add entitySources relationship to all entity models"
```

---

## Phase 3: Migrate Existing Single-Source Data

### Task 5: Create Data Migration to Populate entity_sources

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_migrate_single_source_to_entity_sources.php`

**Step 1: Create migration file**

Run: `docker compose exec php php artisan make:migration migrate_single_source_to_entity_sources`
Expected: Migration file created

**Step 2: Write data migration**

Edit the generated migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate spells
        DB::statement("
            INSERT INTO entity_sources (entity_type, entity_id, source_id, pages)
            SELECT 'spell', id, source_id, source_pages
            FROM spells
            WHERE source_id IS NOT NULL
        ");

        // Migrate races
        DB::statement("
            INSERT INTO entity_sources (entity_type, entity_id, source_id, pages)
            SELECT 'race', id, source_id, source_pages
            FROM races
            WHERE source_id IS NOT NULL
        ");

        // Migrate classes (if table exists and has source_id column)
        if (Schema::hasTable('classes') && Schema::hasColumn('classes', 'source_id')) {
            DB::statement("
                INSERT INTO entity_sources (entity_type, entity_id, source_id, pages)
                SELECT 'class', id, source_id, source_pages
                FROM classes
                WHERE source_id IS NOT NULL
            ");
        }

        // Add other entities as needed (items, feats, backgrounds, monsters)
        // Only if those tables exist
        $entitiesToMigrate = [
            'items' => 'item',
            'feats' => 'feat',
            'backgrounds' => 'background',
            'monsters' => 'monster',
        ];

        foreach ($entitiesToMigrate as $tableName => $entityType) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'source_id')) {
                DB::statement("
                    INSERT INTO entity_sources (entity_type, entity_id, source_id, pages)
                    SELECT '{$entityType}', id, source_id, source_pages
                    FROM {$tableName}
                    WHERE source_id IS NOT NULL
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear all entity_sources
        DB::table('entity_sources')->truncate();
    }
};
```

**Step 3: Run migration**

Run: `docker compose exec php php artisan migrate`
Expected: Migration runs successfully, data migrated

**Step 4: Verify data migration**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== entity_sources migration results ===\n\";
echo \"Total entity_sources: \" . \App\Models\EntitySource::count() . \"\n\";
echo \"Spells: \" . \App\Models\EntitySource::where('entity_type', 'spell')->count() . \"\n\";
echo \"Races: \" . \App\Models\EntitySource::where('entity_type', 'race')->count() . \"\n\";
echo \"Classes: \" . \App\Models\EntitySource::where('entity_type', 'class')->count() . \"\n\";

\$spell = \App\Models\Spell::first();
if (\$spell) {
    echo \"Sample spell (\" . \$spell->name . \") has \" . \$spell->entitySources()->count() . \" source(s)\n\";
}
"`
Expected: Shows counts for each entity type (spells: 361, races: 15, etc.)

**Step 5: Commit**

```bash
git add database/migrations/*_migrate_single_source_to_entity_sources.php
git commit -m "data: migrate single source_id to entity_sources junction table"
```

---

## Phase 4: Update Parsers to Extract Multiple Sources

### Task 6: Update SpellXmlParser to Parse Multiple Sources

**Files:**
- Modify: `app/Services/Parsers/SpellXmlParser.php`
- Modify: `tests/Unit/Parsers/SpellXmlParserTest.php`

**Step 1: Write failing test**

Edit `tests/Unit/Parsers/SpellXmlParserTest.php`, add test:

```php
/** @test */
public function it_parses_multiple_sources_from_spell()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Fireball</name>
        <level>3</level>
        <school>EV</school>
        <time>1 action</time>
        <range>150 feet</range>
        <components>V, S, M</components>
        <duration>Instantaneous</duration>
        <classes>Wizard, Sorcerer</classes>
        <text>A bright streak flashes from your pointing finger...</text>
        <text>Source: Player's Handbook (2014) p. 241,
        Tasha's Cauldron of Everything (2020) p. 75</text>
    </spell>
</compendium>
XML;

    $parser = new SpellXmlParser();
    $spells = $parser->parse($xml);

    $this->assertCount(1, $spells);

    // Should return array of sources
    $this->assertArrayHasKey('sources', $spells[0]);
    $this->assertIsArray($spells[0]['sources']);
    $this->assertCount(2, $spells[0]['sources']);

    // First source
    $this->assertEquals('PHB', $spells[0]['sources'][0]['code']);
    $this->assertEquals('241', $spells[0]['sources'][0]['pages']);

    // Second source
    $this->assertEquals('TCE', $spells[0]['sources'][1]['code']);
    $this->assertEquals('75', $spells[0]['sources'][1]['pages']);
}

/** @test */
public function it_handles_single_source_backward_compatible()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Fireball</name>
        <level>3</level>
        <school>EV</school>
        <time>1 action</time>
        <range>150 feet</range>
        <components>V, S, M</components>
        <duration>Instantaneous</duration>
        <classes>Wizard</classes>
        <text>A bright streak flashes...</text>
        <text>Source: Player's Handbook (2014) p. 241</text>
    </spell>
</compendium>
XML;

    $parser = new SpellXmlParser();
    $spells = $parser->parse($xml);

    $this->assertArrayHasKey('sources', $spells[0]);
    $this->assertCount(1, $spells[0]['sources']);
    $this->assertEquals('PHB', $spells[0]['sources'][0]['code']);
    $this->assertEquals('241', $spells[0]['sources'][0]['pages']);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=SpellXmlParserTest`
Expected: FAIL - "Undefined array key 'sources'"

**Step 3: Update SpellXmlParser to parse multiple sources**

Edit `app/Services/Parsers/SpellXmlParser.php`, replace the source parsing section in `parseSpell()` method:

```php
private function parseSpell(SimpleXMLElement $element): array
{
    // ... existing code for components, duration, etc. ...

    // Parse description and sources from text elements
    $description = '';
    $sources = []; // NEW: Array of sources instead of single source

    foreach ($element->text as $text) {
        $textContent = (string) $text;

        // Check if this text contains source citation(s)
        if (preg_match('/Source:\s*(.+)/s', $textContent, $matches)) {
            // Extract all sources from the citation
            $sourcesText = $matches[1];
            $sources = $this->parseSourceCitations($sourcesText);

            // Remove the source line from description
            $textContent = preg_replace('/\n*Source:\s*.+/s', '', $textContent);
        }

        if (trim($textContent)) {
            $description .= $textContent . "\n\n";
        }
    }

    // ... existing code for classes, etc. ...

    return [
        'name' => (string) $element->name,
        'level' => (int) $element->level,
        'school' => (string) $element->school,
        'casting_time' => (string) $element->time,
        'range' => (string) $element->range,
        'components' => $components,
        'material_components' => $materialComponents,
        'duration' => $duration,
        'needs_concentration' => $needsConcentration,
        'is_ritual' => $isRitual,
        'description' => trim($description),
        'higher_levels' => null,
        'classes' => $classes,
        'sources' => $sources, // NEW: Return array of sources
    ];
}

/**
 * Parse source citations that may span multiple books.
 *
 * Examples:
 *   "Player's Handbook (2014) p. 241"
 *   "Dungeon Master's Guide (2014) p. 150,\n\t\tPlayer's Handbook (2014) p. 150"
 *
 * @return array{code: string, pages: string}[]
 */
private function parseSourceCitations(string $sourcesText): array
{
    $sources = [];

    // Pattern: "Book Name (Year) p. PageNumbers"
    // Handles: "p. 150" or "p. 150, 152" or "p. 150-152"
    $pattern = '/([^(]+)\s*\((\d{4})\)\s*p\.\s*([\d,\s\-]+)/';

    preg_match_all($pattern, $sourcesText, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $sourceName = trim($match[1]);
        $pages = trim($match[3]);

        $sourceCode = $this->getSourceCode($sourceName);

        $sources[] = [
            'code' => $sourceCode,
            'pages' => $pages,
        ];
    }

    // Fallback if no sources parsed (shouldn't happen with valid XML)
    if (empty($sources)) {
        $sources[] = [
            'code' => 'PHB',
            'pages' => '',
        ];
    }

    return $sources;
}

// Keep existing getSourceCode() method unchanged
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=SpellXmlParserTest`
Expected: PASS (all tests including 2 new tests)

**Step 5: Commit**

```bash
git add app/Services/Parsers/SpellXmlParser.php tests/Unit/Parsers/SpellXmlParserTest.php
git commit -m "feat: parse multiple source citations from spell XML"
```

---

### Task 7: Update RaceXmlParser to Parse Multiple Sources

**Files:**
- Modify: `app/Services/Parsers/RaceXmlParser.php`
- Modify: `tests/Unit/Parsers/RaceXmlParserTest.php`

**Step 1: Write failing test**

Edit `tests/Unit/Parsers/RaceXmlParserTest.php`, add test:

```php
/** @test */
public function it_parses_multiple_sources_from_race()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>High elf description.
Source: Player's Handbook (2014) p. 23,
        Tasha's Cauldron of Everything (2020) p. 10</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertArrayHasKey('sources', $races[0]);
    $this->assertCount(2, $races[0]['sources']);
    $this->assertEquals('PHB', $races[0]['sources'][0]['code']);
    $this->assertEquals('23', $races[0]['sources'][0]['pages']);
    $this->assertEquals('TCE', $races[0]['sources'][1]['code']);
    $this->assertEquals('10', $races[0]['sources'][1]['pages']);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=RaceXmlParserTest`
Expected: FAIL

**Step 3: Update RaceXmlParser**

Edit `app/Services/Parsers/RaceXmlParser.php`, update the source parsing section:

```php
private function parseRace(SimpleXMLElement $element): array
{
    // ... existing code for name, base_race_name, etc. ...

    $description = '';
    $sources = []; // NEW: Array instead of single source_code/pages

    foreach ($element->trait as $trait) {
        $category = isset($trait['category']) ? (string) $trait['category'] : '';

        if ($category !== 'description') {
            continue;
        }

        $traitName = (string) $trait->name;
        $traitText = (string) $trait->text;

        // Check if this trait contains source citation(s)
        if (preg_match('/Source:\s*(.+)/s', $traitText, $matches)) {
            $sourcesText = $matches[1];
            $sources = $this->parseSourceCitations($sourcesText);

            // Remove source line
            $traitText = preg_replace('/\n*Source:\s*.+/s', '', $traitText);
        }

        // Add trait to description
        if (trim($traitText)) {
            if ($traitName === 'Description') {
                $description .= "{$traitText}\n\n";
            } else {
                $description .= "**{$traitName}**\n\n{$traitText}\n\n";
            }
        }
    }

    // ... existing proficiencies parsing ...

    return [
        'name' => $raceName,
        'base_race_name' => $baseRaceName,
        'size_code' => (string) $element->size,
        'speed' => (int) $element->speed,
        'description' => trim($description),
        'sources' => $sources, // NEW: Return array of sources
        'proficiencies' => $proficiencies,
    ];
}

/**
 * Parse source citations (same as SpellXmlParser).
 */
private function parseSourceCitations(string $sourcesText): array
{
    $sources = [];

    $pattern = '/([^(]+)\s*\((\d{4})\)\s*p\.\s*([\d,\s\-]+)/';
    preg_match_all($pattern, $sourcesText, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $sourceName = trim($match[1]);
        $pages = trim($match[3]);

        $sourceCode = $this->getSourceCode($sourceName);

        $sources[] = [
            'code' => $sourceCode,
            'pages' => $pages,
        ];
    }

    if (empty($sources)) {
        $sources[] = [
            'code' => 'PHB',
            'pages' => '',
        ];
    }

    return $sources;
}

// Keep existing getSourceCode() method
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=RaceXmlParserTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Parsers/RaceXmlParser.php tests/Unit/Parsers/RaceXmlParserTest.php
git commit -m "feat: parse multiple source citations from race XML"
```

---

## Phase 5: Update Importers to Create Junction Records

### Task 8: Update SpellImporter to Create entity_sources Records

**Files:**
- Modify: `app/Services/Importers/SpellImporter.php`
- Modify: `tests/Feature/Importers/SpellImporterTest.php`

**Step 1: Write failing test**

Edit `tests/Feature/Importers/SpellImporterTest.php`, add test:

```php
/** @test */
public function it_imports_spell_with_multiple_sources()
{
    $spellData = [
        'name' => 'Fireball',
        'level' => 3,
        'school' => 'EV',
        'casting_time' => '1 action',
        'range' => '150 feet',
        'components' => 'V, S, M',
        'material_components' => 'a tiny ball of bat guano and sulfur',
        'duration' => 'Instantaneous',
        'needs_concentration' => false,
        'is_ritual' => false,
        'description' => 'A bright streak flashes...',
        'higher_levels' => null,
        'classes' => ['Wizard', 'Sorcerer'],
        'sources' => [
            ['code' => 'PHB', 'pages' => '241'],
            ['code' => 'TCE', 'pages' => '75'],
        ],
    ];

    $importer = new SpellImporter();
    $spell = $importer->import($spellData);

    // Check spell was created
    $this->assertInstanceOf(Spell::class, $spell);

    // Check entity_sources records were created
    $entitySources = $spell->entitySources;
    $this->assertCount(2, $entitySources);

    // Check PHB source
    $phbSource = $entitySources->where('source.code', 'PHB')->first();
    $this->assertNotNull($phbSource);
    $this->assertEquals('241', $phbSource->pages);

    // Check TCE source
    $tceSource = $entitySources->where('source.code', 'TCE')->first();
    $this->assertNotNull($tceSource);
    $this->assertEquals('75', $tceSource->pages);
}

/** @test */
public function it_clears_old_sources_on_reimport()
{
    // First import with PHB source
    $spellData = [
        'name' => 'Fireball',
        'level' => 3,
        'school' => 'EV',
        'casting_time' => '1 action',
        'range' => '150 feet',
        'components' => 'V, S, M',
        'duration' => 'Instantaneous',
        'needs_concentration' => false,
        'is_ritual' => false,
        'description' => 'A bright streak flashes...',
        'classes' => ['Wizard'],
        'sources' => [
            ['code' => 'PHB', 'pages' => '241'],
        ],
    ];

    $importer = new SpellImporter();
    $spell = $importer->import($spellData);

    $this->assertCount(1, $spell->entitySources);

    // Second import with PHB and TCE sources
    $spellData['sources'][] = ['code' => 'TCE', 'pages' => '75'];
    $spell = $importer->import($spellData);

    // Should have 2 sources now (old cleared, new added)
    $spell->refresh();
    $this->assertCount(2, $spell->entitySources);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=SpellImporterTest`
Expected: FAIL - "Failed asserting that 0 matches expected 2"

**Step 3: Update SpellImporter to create entity_sources**

Edit `app/Services/Importers/SpellImporter.php`:

```php
public function import(array $spellData): Spell
{
    $spellSchool = SpellSchool::where('code', $spellData['school'])->firstOrFail();

    // Get primary source (first in array) for backward compatibility
    $primarySource = Source::where('code', $spellData['sources'][0]['code'])->firstOrFail();

    // Create or update spell (keep source_id for now - will deprecate later)
    $spell = Spell::updateOrCreate(
        ['name' => $spellData['name']],
        [
            'level' => $spellData['level'],
            'spell_school_id' => $spellSchool->id,
            'casting_time' => $spellData['casting_time'],
            'range' => $spellData['range'],
            'components' => $spellData['components'],
            'material_components' => $spellData['material_components'] ?? null,
            'duration' => $spellData['duration'],
            'needs_concentration' => $spellData['needs_concentration'],
            'is_ritual' => $spellData['is_ritual'],
            'description' => $spellData['description'],
            'higher_levels' => $spellData['higher_levels'] ?? null,
            'source_id' => $primarySource->id,
            'source_pages' => $spellData['sources'][0]['pages'],
        ]
    );

    // Clear old entity_sources
    $spell->entitySources()->delete();

    // Create entity_sources for all sources
    foreach ($spellData['sources'] as $sourceData) {
        $source = Source::where('code', $sourceData['code'])->firstOrFail();

        EntitySource::create([
            'entity_type' => 'spell',
            'entity_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => $sourceData['pages'],
        ]);
    }

    // Import class associations (existing code)
    // ... existing class import code ...

    return $spell;
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=SpellImporterTest`
Expected: PASS (all tests including 2 new tests)

**Step 5: Commit**

```bash
git add app/Services/Importers/SpellImporter.php tests/Feature/Importers/SpellImporterTest.php
git commit -m "feat: import multiple sources per spell into entity_sources"
```

---

### Task 9: Update RaceImporter to Create entity_sources Records

**Files:**
- Modify: `app/Services/Importers/RaceImporter.php`
- Modify: `tests/Feature/Importers/RaceImporterTest.php`

**Step 1: Write failing test**

Edit `tests/Feature/Importers/RaceImporterTest.php`, add test:

```php
/** @test */
public function it_imports_race_with_multiple_sources()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>High elf description.
Source: Player's Handbook (2014) p. 23,
        Tasha's Cauldron of Everything (2020) p. 10</text>
    </trait>
  </race>
</compendium>
XML;

    $count = $this->importer->importFromXml($xml);

    // Should create base race + subrace = 2
    $this->assertEquals(2, $count);

    $race = Race::where('name', 'High')->first();
    $this->assertNotNull($race);

    // Check entity_sources
    $entitySources = $race->entitySources;
    $this->assertCount(2, $entitySources);

    $phb = $entitySources->where('source.code', 'PHB')->first();
    $this->assertEquals('23', $phb->pages);

    $tce = $entitySources->where('source.code', 'TCE')->first();
    $this->assertEquals('10', $tce->pages);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=RaceImporterTest`
Expected: FAIL

**Step 3: Update RaceImporter**

Edit `app/Services/Importers/RaceImporter.php`:

```php
public function importFromXml(string $xmlContent): int
{
    $racesData = $this->parser->parse($xmlContent);
    $count = 0;

    foreach ($racesData as $raceData) {
        // If this is a subrace, ensure base race exists first
        $parentRaceId = null;

        if ($raceData['base_race_name']) {
            // Get primary source for base race
            $primarySource = $raceData['sources'][0] ?? ['code' => 'PHB', 'pages' => ''];

            $baseRace = $this->getOrCreateBaseRace(
                $raceData['base_race_name'],
                $raceData['size_code'],
                $raceData['speed'],
                $primarySource['code'],
                $primarySource['pages']
            );

            $parentRaceId = $baseRace->id;
            $count++;
        }

        // Get primary source
        $primarySource = $raceData['sources'][0] ?? ['code' => 'PHB', 'pages' => ''];
        $size = Size::where('code', $raceData['size_code'])->firstOrFail();
        $source = Source::where('code', $primarySource['code'])->firstOrFail();

        $race = Race::updateOrCreate(
            [
                'name' => $raceData['name'],
                'parent_race_id' => $parentRaceId,
            ],
            [
                'size_id' => $size->id,
                'speed' => $raceData['speed'],
                'description' => $raceData['description'],
                'source_id' => $source->id,
                'source_pages' => $primarySource['pages'],
            ]
        );

        // Clear old entity_sources
        $race->entitySources()->delete();

        // Create entity_sources for all sources
        foreach ($raceData['sources'] as $sourceData) {
            $source = Source::where('code', $sourceData['code'])->firstOrFail();

            EntitySource::create([
                'entity_type' => 'race',
                'entity_id' => $race->id,
                'source_id' => $source->id,
                'pages' => $sourceData['pages'],
            ]);
        }

        // Import proficiencies (existing code)
        $this->importProficiencies($race, $raceData['proficiencies']);

        $count++;
    }

    return $count;
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=RaceImporterTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Importers/RaceImporter.php tests/Feature/Importers/RaceImporterTest.php
git commit -m "feat: import multiple sources per race into entity_sources"
```

---

## Phase 6: Update API Resources to Return All Sources

### Task 10: Update SpellResource to Include All Sources

**Files:**
- Modify: `app/Http/Resources/SpellResource.php`
- Create: `app/Http/Resources/EntitySourceResource.php`
- Modify: `app/Http/Controllers/Api/SpellController.php`
- Modify: `tests/Feature/Api/SpellApiTest.php`

**Step 1: Create EntitySourceResource**

Create `app/Http/Resources/EntitySourceResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitySourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'source' => [
                'id' => $this->source->id,
                'code' => $this->source->code,
                'name' => $this->source->name,
            ],
            'pages' => $this->pages,
        ];
    }
}
```

**Step 2: Write failing test**

Edit `tests/Feature/Api/SpellApiTest.php`, add test:

```php
/** @test */
public function it_returns_multiple_sources_in_spell_api()
{
    $school = SpellSchool::first();
    $phb = Source::where('code', 'PHB')->first();
    $tce = Source::where('code', 'TCE')->first();

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
        'description' => 'A bright streak flashes...',
        'source_id' => $phb->id,
        'source_pages' => '241',
    ]);

    // Create entity_sources
    EntitySource::create([
        'entity_type' => 'spell',
        'entity_id' => $spell->id,
        'source_id' => $phb->id,
        'pages' => '241',
    ]);

    EntitySource::create([
        'entity_type' => 'spell',
        'entity_id' => $spell->id,
        'source_id' => $tce->id,
        'pages' => '75',
    ]);

    $response = $this->getJson("/api/v1/spells/{$spell->id}");

    $response->assertStatus(200);

    $data = $response->json('data');

    // Should have 'sources' array (plural, not 'source' object)
    $this->assertArrayHasKey('sources', $data);
    $this->assertIsArray($data['sources']);
    $this->assertCount(2, $data['sources']);

    // Check PHB source
    $this->assertEquals('PHB', $data['sources'][0]['source']['code']);
    $this->assertEquals('241', $data['sources'][0]['pages']);

    // Check TCE source
    $this->assertEquals('TCE', $data['sources'][1]['source']['code']);
    $this->assertEquals('75', $data['sources'][1]['pages']);
}
```

**Step 3: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=SpellApiTest`
Expected: FAIL - "Undefined array key 'sources'"

**Step 4: Update SpellResource**

Edit `app/Http/Resources/SpellResource.php`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'level' => $this->level,
        'school' => [
            'id' => $this->spellSchool->id,
            'code' => $this->spellSchool->code,
            'name' => $this->spellSchool->name,
        ],
        'casting_time' => $this->casting_time,
        'range' => $this->range,
        'components' => $this->components,
        'material_components' => $this->material_components,
        'duration' => $this->duration,
        'needs_concentration' => $this->needs_concentration,
        'is_ritual' => $this->is_ritual,
        'description' => $this->description,
        'higher_levels' => $this->higher_levels,

        // Legacy single source (deprecated but kept for backward compatibility)
        'source' => [
            'id' => $this->source->id,
            'code' => $this->source->code,
            'name' => $this->source->name,
        ],
        'source_pages' => $this->source_pages,

        // NEW: All sources via entity_sources
        'sources' => EntitySourceResource::collection($this->whenLoaded('entitySources')),
    ];
}
```

Don't forget to add the import:
```php
use App\Http\Resources\EntitySourceResource;
```

**Step 5: Update SpellController to eager load entitySources**

Edit `app/Http/Controllers/Api/SpellController.php`:

In the `show` method:
```php
public function show(Spell $spell)
{
    $spell->load(['spellSchool', 'source', 'entitySources.source']);

    return new SpellResource($spell);
}
```

In the `index` method:
```php
public function index(Request $request)
{
    $query = Spell::with(['spellSchool', 'source', 'entitySources.source']);

    // ... existing filters and pagination ...
}
```

**Step 6: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=SpellApiTest`
Expected: PASS

**Step 7: Commit**

```bash
git add app/Http/Resources/EntitySourceResource.php app/Http/Resources/SpellResource.php app/Http/Controllers/Api/SpellController.php tests/Feature/Api/SpellApiTest.php
git commit -m "feat: include all sources in Spell API responses"
```

---

### Task 11: Update RaceResource to Include All Sources

**Files:**
- Modify: `app/Http/Resources/RaceResource.php`
- Modify: `app/Http/Controllers/Api/RaceController.php`
- Modify: `tests/Feature/Api/RaceApiTest.php`

**Step 1: Write failing test**

Edit `tests/Feature/Api/RaceApiTest.php`, add test:

```php
/** @test */
public function it_returns_multiple_sources_in_race_api()
{
    $race = Race::factory()->create(['name' => 'High Elf']);
    $phb = Source::where('code', 'PHB')->first();
    $tce = Source::where('code', 'TCE')->first();

    EntitySource::create([
        'entity_type' => 'race',
        'entity_id' => $race->id,
        'source_id' => $phb->id,
        'pages' => '23',
    ]);

    EntitySource::create([
        'entity_type' => 'race',
        'entity_id' => $race->id,
        'source_id' => $tce->id,
        'pages' => '10',
    ]);

    $response = $this->getJson("/api/v1/races/{$race->id}");

    $response->assertStatus(200);

    $data = $response->json('data');
    $this->assertArrayHasKey('sources', $data);
    $this->assertCount(2, $data['sources']);
    $this->assertEquals('PHB', $data['sources'][0]['source']['code']);
    $this->assertEquals('TCE', $data['sources'][1]['source']['code']);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=RaceApiTest`
Expected: FAIL

**Step 3: Update RaceResource**

Edit `app/Http/Resources/RaceResource.php`:

```php
use App\Http\Resources\EntitySourceResource;

public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'size' => new SizeResource($this->whenLoaded('size')),
        'speed' => $this->speed,
        'description' => $this->description,

        // Legacy single source (deprecated)
        'source' => new SourceResource($this->whenLoaded('source')),
        'source_pages' => $this->source_pages,

        // NEW: All sources
        'sources' => EntitySourceResource::collection($this->whenLoaded('entitySources')),

        'parent_race' => $this->when($this->parent_race_id, function () {
            return new RaceResource($this->whenLoaded('parent'));
        }),
        'subraces' => RaceResource::collection($this->whenLoaded('subraces')),
        'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
    ];
}
```

**Step 4: Update RaceController**

Edit `app/Http/Controllers/Api/RaceController.php`:

```php
public function show($id)
{
    $race = Race::with(['size', 'source', 'parent', 'subraces', 'proficiencies.skill', 'entitySources.source'])
        ->findOrFail($id);

    return new RaceResource($race);
}

public function index(Request $request)
{
    $races = Race::with(['size', 'source', 'entitySources.source'])
        ->paginate(15);

    return RaceResource::collection($races);
}
```

**Step 5: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=RaceApiTest`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Http/Resources/RaceResource.php app/Http/Controllers/Api/RaceController.php tests/Feature/Api/RaceApiTest.php
git commit -m "feat: include all sources in Race API responses"
```

---

## Phase 7: Re-import Data with Multiple Sources

### Task 12: Re-import All Spells and Races

**Files:**
- None (data operation)

**Step 1: Clear existing entity_sources**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\App\Models\EntitySource::query()->delete();
echo \"Cleared all entity_sources\n\";
"`
Expected: "Cleared all entity_sources"

**Step 2: Re-import spells from all XML files**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$importer = new \App\Services\Importers\SpellImporter();

\$files = [
    'import-files/spells-phb.xml',
    'import-files/spells-phb+dmg.xml',
    'import-files/spells-phb+tce.xml',
    'import-files/spells-phb+xge.xml',
];

\$totalSpells = 0;
foreach (\$files as \$file) {
    if (file_exists(\$file)) {
        \$count = \$importer->importFromFile(\$file);
        echo \"Imported \$count spells from \$file\n\";
        \$totalSpells += \$count;
    }
}
echo \"Total spells imported: \$totalSpells\n\";
"`
Expected: Spells imported from multiple files

**Step 3: Re-import races**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$importer = new \App\Services\Importers\RaceImporter();
\$count = \$importer->importFromFile('import-files/races-phb.xml');
echo \"Imported \$count races\n\";
"`
Expected: Races imported

**Step 4: Verify entity_sources data**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== entity_sources summary ===\n\";
echo \"Total entity_sources: \" . \App\Models\EntitySource::count() . \"\n\";
echo \"Spell sources: \" . \App\Models\EntitySource::where('entity_type', 'spell')->count() . \"\n\";
echo \"Race sources: \" . \App\Models\EntitySource::where('entity_type', 'race')->count() . \"\n\";

\$multiSourceSpells = \App\Models\Spell::has('entitySources', '>', 1)->count();
echo \"Spells with multiple sources: \$multiSourceSpells\n\";

\$sample = \App\Models\Spell::has('entitySources', '>', 1)->first();
if (\$sample) {
    echo \"Sample spell: \" . \$sample->name . \" has \" . \$sample->entitySources->count() . \" sources\n\";
    foreach (\$sample->entitySources as \$es) {
        echo \"  - \" . \$es->source->code . \" p. \" . \$es->pages . \"\n\";
    }
}
"`
Expected: Shows entity_sources counts and multi-source examples

**Step 5: Test API endpoints**

Run: `curl -s "http://localhost:8080/api/v1/spells/1" | python3 -m json.tool | grep -A20 "sources"`
Expected: Shows sources array with multiple entries (if spell has multiple sources)

**Step 6: Run all tests**

Run: `docker compose exec php php artisan test`
Expected: All tests pass

**Step 7: Commit**

```bash
git add .
git commit -m "data: re-import all entities with multiple source support"
```

---

## Phase 8: (Optional) Deprecate Single source_id Column

### Task 13: Create Migration to Remove source_id and source_pages Columns

**Note:** This is optional and can be deferred. Keeping the columns maintains backward compatibility.

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_remove_single_source_columns.php`

**Step 1: Create migration file**

Run: `docker compose exec php php artisan make:migration remove_single_source_columns`
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
     *
     * IMPORTANT: Only run this after verifying all entity_sources data is correct!
     */
    public function up(): void
    {
        // Remove source_id and source_pages from all entity tables
        $tables = ['spells', 'items', 'races', 'feats', 'backgrounds', 'classes', 'monsters'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'source_id')) {
                        $table->dropForeign([\'source_id\']);
                        $table->dropColumn('source_id');
                    }

                    if (Schema::hasColumn($table->getTable(), 'source_pages')) {
                        $table->dropColumn('source_pages');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot fully reverse - would need to pick a "primary" source from entity_sources
        throw new \Exception('Cannot reverse this migration - restore from backup if needed');
    }
};
```

**Step 3: Do NOT run this migration yet**

This is a destructive change. Only run after:
1. All data is successfully migrated to entity_sources
2. All API consumers are updated to use new `sources` array
3. Thorough testing in production-like environment

**Step 4: Commit (but don't run)**

```bash
git add database/migrations/*_remove_single_source_columns.php
git commit -m "migration: (do not run) remove deprecated source_id columns"
```

---

## Verification & Completion

### Task 14: Run Full Test Suite and Verify

**Step 1: Run all tests**

Run: `docker compose exec php php artisan test`
Expected: All tests pass (should be ~180+ tests now)

**Step 2: Verify database state**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== Final Database Summary ===\n\";
echo \"Spells: \" . \App\Models\Spell::count() . \"\n\";
echo \"Races: \" . \App\Models\Race::count() . \"\n\";
echo \"Total entity_sources: \" . \App\Models\EntitySource::count() . \"\n\";
echo \"Spells with multiple sources: \" . \App\Models\Spell::has('entitySources', '>', 1)->count() . \"\n\";
echo \"Races with multiple sources: \" . \App\Models\Race::has('entitySources', '>', 1)->count() . \"\n\";

echo \"\n=== Sample Multi-Source Spell ===\n\";
\$spell = \App\Models\Spell::has('entitySources', '>', 1)->first();
if (\$spell) {
    echo \"Spell: \" . \$spell->name . \"\n\";
    foreach (\$spell->entitySources as \$es) {
        echo \"  Source: \" . \$es->source->name . \" (\" . \$es->source->code . \") p. \" . \$es->pages . \"\n\";
    }
}
"`
Expected: Shows comprehensive database statistics

**Step 3: Verify API responses**

Run the following curl commands:

```bash
# Test spell with multiple sources
curl -s "http://localhost:8080/api/v1/spells" | python3 -m json.tool | head -100

# Test race with multiple sources
curl -s "http://localhost:8080/api/v1/races" | python3 -m json.tool | head -100
```

Expected: API returns `sources` array with multiple entries where applicable

**Step 4: Document the change**

Update `docs/HANDOVER-2025-11-18.md` or create new handover:

```markdown
## Multiple Sources Per Entity - COMPLETE ✅

**What Changed:**
- Created `entity_sources` polymorphic junction table
- Updated parsers to extract multiple source citations from XML
- Updated importers to create entity_sources records
- Updated API resources to return `sources` array (plural)
- Legacy `source` field (singular) maintained for backward compatibility

**Database:**
- entity_sources: ~400+ records (spells + races + more)
- Spells with multiple sources: ~XX spells
- Races with multiple sources: ~XX races

**API Changes:**
- NEW: `sources` array in all entity responses
- DEPRECATED: `source` object (still present for backward compatibility)
- Clients should migrate to using `sources` array

**Testing:**
- All tests passing (~180+ tests)
- Multi-source parsing tested
- Multi-source import tested
- Multi-source API responses tested
```

**Step 5: Final commit**

```bash
git add docs/
git commit -m "docs: document multiple sources per entity implementation"
```

---

## Summary

**Total Tasks:** 14

**What Was Built:**
1. ✅ `entity_sources` polymorphic junction table
2. ✅ `EntitySource` model with relationships
3. ✅ `entitySources` relationships on all entity models
4. ✅ Data migration from single source to junction table
5. ✅ Updated parsers to extract multiple sources from XML
6. ✅ Updated importers to create entity_sources records
7. ✅ `EntitySourceResource` for API responses
8. ✅ Updated API resources to return all sources
9. ✅ Re-imported all data with multi-source support
10. ✅ (Optional) Migration to remove deprecated columns

**Key Benefits:**
- Entities can now reference multiple sourcebooks
- Each source has its own page references
- Queries like "all content from PHB" are simple joins
- Preserves full citation information from XML
- Backward compatible (kept legacy `source` field)

**Testing:**
- TDD approach with tests written first
- Parser tests for multi-source extraction
- Importer tests for junction record creation
- API tests for multi-source responses
- Integration tests for full pipeline

**Estimated Implementation:** 4-6 hours with TDD

**Next Steps:** Continue with remaining vertical slices (Backgrounds, Feats, Items) using the same pattern

---

## Execution Options

Plan complete and saved to `docs/plans/2025-11-18-multiple-sources-per-entity.md`. Two execution options:

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

Which approach would you like to use?
