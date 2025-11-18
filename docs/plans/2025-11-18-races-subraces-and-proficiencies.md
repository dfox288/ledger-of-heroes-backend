# Races: Subraces and Proficiencies Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add subrace hierarchy (parent_race_id) and proficiencies polymorphic table to races system, adhering to the design document.

**Architecture:** Two-phase approach - Phase 1 adds parent_race_id for subrace hierarchy, Phase 2 adds proficiencies polymorphic table for skills/weapons/armor. Each phase follows TDD with migration → parser → importer → API → tests.

**Tech Stack:** Laravel 11.x, PHP 8.3, PostgreSQL, PHPUnit, SimpleXML

---

## Phase 1: Add Subrace Hierarchy (parent_race_id)

### Task 1.1: Create Migration for parent_race_id

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_add_parent_race_id_to_races_table.php`

**Step 1: Create migration file**

```bash
docker compose exec php php artisan make:migration add_parent_race_id_to_races_table
```

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
        Schema::table('races', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_race_id')->nullable()->after('id');

            $table->foreign('parent_race_id')
                  ->references('id')
                  ->on('races')
                  ->onDelete('cascade');

            $table->index('parent_race_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropForeign(['parent_race_id']);
            $table->dropIndex(['parent_race_id']);
            $table->dropColumn('parent_race_id');
        });
    }
};
```

**Step 3: Run migration**

```bash
docker compose exec php php artisan migrate
```

Expected: Migration runs successfully, `parent_race_id` column added

**Step 4: Commit**

```bash
git add database/migrations/*add_parent_race_id_to_races_table.php
git commit -m "feat: add parent_race_id to races table for subrace hierarchy"
```

---

### Task 1.2: Update Race Model with Subrace Relationships

**Files:**
- Modify: `app/Models/Race.php:12-18` (fillable)
- Modify: `app/Models/Race.php:36` (add relationships)

**Step 1: Write test for parent/subrace relationships**

Edit `tests/Feature/Models/RaceModelTest.php` (create if doesn't exist):

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_race_can_have_parent_race(): void
    {
        // Arrange: Create base race and subrace
        $size = Size::where('code', 'M')->first();
        $source = Source::where('code', 'PHB')->first();

        $baseRace = Race::create([
            'name' => 'Dwarf',
            'size_id' => $size->id,
            'speed' => 25,
            'description' => 'Base dwarf description',
            'source_id' => $source->id,
            'source_pages' => '20',
            'parent_race_id' => null,
        ]);

        $subrace = Race::create([
            'name' => 'Hill',
            'size_id' => $size->id,
            'speed' => 25,
            'description' => 'Hill dwarf description',
            'source_id' => $source->id,
            'source_pages' => '20',
            'parent_race_id' => $baseRace->id,
        ]);

        // Act & Assert: Test parent relationship
        $this->assertNotNull($subrace->parent);
        $this->assertEquals('Dwarf', $subrace->parent->name);

        // Assert: Test subraces relationship
        $this->assertCount(1, $baseRace->subraces);
        $this->assertEquals('Hill', $baseRace->subraces->first()->name);
    }

    public function test_base_race_has_null_parent(): void
    {
        $size = Size::where('code', 'M')->first();
        $source = Source::where('code', 'PHB')->first();

        $baseRace = Race::create([
            'name' => 'Dragonborn',
            'size_id' => $size->id,
            'speed' => 30,
            'description' => 'Dragonborn description',
            'source_id' => $source->id,
            'source_pages' => '32',
            'parent_race_id' => null,
        ]);

        $this->assertNull($baseRace->parent);
        $this->assertCount(0, $baseRace->subraces);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceModelTest
```

Expected: FAIL - "Call to undefined relationship method parent()"

**Step 3: Update Race model with relationships**

Edit `app/Models/Race.php`:

```php
protected $fillable = [
    'name',
    'size_id',
    'speed',
    'description',
    'source_id',
    'source_pages',
    'parent_race_id', // ADD THIS
];

protected $casts = [
    'size_id' => 'integer',
    'speed' => 'integer',
    'source_id' => 'integer',
    'parent_race_id' => 'integer', // ADD THIS
];

// ADD THESE RELATIONSHIPS after source() method:

public function parent(): BelongsTo
{
    return $this->belongsTo(Race::class, 'parent_race_id');
}

public function subraces(): HasMany
{
    return $this->hasMany(Race::class, 'parent_race_id');
}
```

Don't forget to add the import at the top:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceModelTest
```

Expected: PASS (2 tests)

**Step 5: Commit**

```bash
git add app/Models/Race.php tests/Feature/Models/RaceModelTest.php
git commit -m "feat: add parent and subraces relationships to Race model"
```

---

### Task 1.3: Update RaceXmlParser to Parse Subraces

**Files:**
- Modify: `app/Services/Parsers/RaceXmlParser.php:21-68`
- Modify: `tests/Unit/Parsers/RaceXmlParserTest.php`

**Step 1: Write test for subrace parsing**

Edit `tests/Unit/Parsers/RaceXmlParserTest.php`, add this test:

```php
/** @test */
public function it_parses_base_race_and_subrace_from_name()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>As a hill dwarf, you have keen senses.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertCount(1, $races);
    $this->assertEquals('Hill', $races[0]['name']);
    $this->assertEquals('Dwarf', $races[0]['base_race_name']);
    $this->assertEquals('M', $races[0]['size_code']);
}

/** @test */
public function it_parses_race_without_subrace()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertCount(1, $races);
    $this->assertEquals('Dragonborn', $races[0]['name']);
    $this->assertNull($races[0]['base_race_name']);
}

/** @test */
public function it_handles_slash_in_subrace_names()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, Drow / Dark</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Drow description.
Source: Player's Handbook (2014) p. 24</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertEquals('Drow / Dark', $races[0]['name']);
    $this->assertEquals('Elf', $races[0]['base_race_name']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

Expected: FAIL - "Failed asserting that null matches expected 'Hill'"

**Step 3: Update parser to extract base race and subrace**

Edit `app/Services/Parsers/RaceXmlParser.php`, update the parseRace method:

```php
private function parseRace(SimpleXMLElement $element): array
{
    // Parse race name and extract base race / subrace
    $fullName = (string) $element->name;
    $baseRaceName = null;
    $raceName = $fullName;

    // Check if name contains comma (indicates subrace)
    if (str_contains($fullName, ',')) {
        [$baseRaceName, $raceName] = array_map('trim', explode(',', $fullName, 2));
    }

    // Parse description from traits with category="description" only
    $description = '';
    $sourceCode = '';
    $sourcePages = '';

    foreach ($element->trait as $trait) {
        // Only include traits with category="description" (lore/flavor text)
        // Skip mechanical traits (Age, Alignment, Size, Languages, species abilities)
        $category = isset($trait['category']) ? (string) $trait['category'] : '';

        if ($category !== 'description') {
            continue;
        }

        $traitName = (string) $trait->name;
        $traitText = (string) $trait->text;

        // Check if this trait contains a source citation
        if (preg_match('/Source:\s*([^p]+)\s*p\.\s*([\d,\s]+)/', $traitText, $matches)) {
            $sourceName = trim($matches[1]);
            $sourcePages = trim($matches[2]);
            $sourceCode = $this->getSourceCode($sourceName);

            // Remove the source line from the text
            $traitText = preg_replace('/\n*Source:\s*[^\n]+/', '', $traitText);
        }

        // Add trait to description (omit the trait name if it's just "Description")
        if (trim($traitText)) {
            if ($traitName === 'Description') {
                $description .= "{$traitText}\n\n";
            } else {
                $description .= "**{$traitName}**\n\n{$traitText}\n\n";
            }
        }
    }

    return [
        'name' => $raceName,
        'base_race_name' => $baseRaceName,
        'size_code' => (string) $element->size,
        'speed' => (int) $element->speed,
        'description' => trim($description),
        'source_code' => $sourceCode ?: 'PHB',
        'source_pages' => $sourcePages,
    ];
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

Expected: PASS (7 tests - 4 existing + 3 new)

**Step 5: Commit**

```bash
git add app/Services/Parsers/RaceXmlParser.php tests/Unit/Parsers/RaceXmlParserTest.php
git commit -m "feat: parse base race and subrace from race names"
```

---

### Task 1.4: Update RaceImporter to Create Base Races and Subraces

**Files:**
- Modify: `app/Services/Importers/RaceImporter.php`
- Modify: `tests/Feature/Importers/RaceImporterTest.php`

**Step 1: Write test for base race and subrace creation**

Edit `tests/Feature/Importers/RaceImporterTest.php`, add this test:

```php
/** @test */
public function it_creates_base_race_and_subrace()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>As a hill dwarf, you have keen senses.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

    $count = $this->importer->importFromXml($xml);

    // Should create 2 races: base "Dwarf" + subrace "Hill"
    $this->assertEquals(2, $count);

    // Check base race exists
    $baseRace = Race::where('name', 'Dwarf')
                     ->whereNull('parent_race_id')
                     ->first();
    $this->assertNotNull($baseRace);

    // Check subrace exists and is linked to base
    $subrace = Race::where('name', 'Hill')
                    ->whereNotNull('parent_race_id')
                    ->first();
    $this->assertNotNull($subrace);
    $this->assertEquals($baseRace->id, $subrace->parent_race_id);
}

/** @test */
public function it_creates_only_base_race_when_no_subrace()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

    $count = $this->importer->importFromXml($xml);

    $this->assertEquals(1, $count);

    $race = Race::where('name', 'Dragonborn')->first();
    $this->assertNotNull($race);
    $this->assertNull($race->parent_race_id);
}

/** @test */
public function it_reuses_existing_base_race_for_multiple_subraces()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>Hill dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
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

    // Should create 3 races: 1 base "Dwarf" + 2 subraces
    $this->assertEquals(3, $count);

    $baseRaces = Race::where('name', 'Dwarf')
                      ->whereNull('parent_race_id')
                      ->get();
    $this->assertCount(1, $baseRaces, 'Should only create one base Dwarf race');

    $subraces = Race::whereNotNull('parent_race_id')->get();
    $this->assertCount(2, $subraces);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest
```

Expected: FAIL - "Failed asserting that 1 matches expected 2"

**Step 3: Update RaceImporter to handle base races and subraces**

Edit `app/Services/Importers/RaceImporter.php`:

```php
<?php

namespace App\Services\Importers;

use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use App\Services\Parsers\RaceXmlParser;

class RaceImporter
{
    private RaceXmlParser $parser;

    public function __construct()
    {
        $this->parser = new RaceXmlParser();
    }

    public function importFromFile(string $filePath): int
    {
        $xmlContent = file_get_contents($filePath);
        return $this->importFromXml($xmlContent);
    }

    public function importFromXml(string $xmlContent): int
    {
        $racesData = $this->parser->parse($xmlContent);
        $count = 0;

        foreach ($racesData as $raceData) {
            // If this is a subrace, ensure base race exists first
            $parentRaceId = null;

            if ($raceData['base_race_name']) {
                // Get or create base race
                $baseRace = $this->getOrCreateBaseRace(
                    $raceData['base_race_name'],
                    $raceData['size_code'],
                    $raceData['speed'],
                    $raceData['source_code'],
                    $raceData['source_pages']
                );

                $parentRaceId = $baseRace->id;
                $count++; // Count base race if newly created
            }

            // Create or update the race (or subrace)
            $size = Size::where('code', $raceData['size_code'])->firstOrFail();
            $source = Source::where('code', $raceData['source_code'])->firstOrFail();

            Race::updateOrCreate(
                [
                    'name' => $raceData['name'],
                    'parent_race_id' => $parentRaceId,
                ],
                [
                    'size_id' => $size->id,
                    'speed' => $raceData['speed'],
                    'description' => $raceData['description'],
                    'source_id' => $source->id,
                    'source_pages' => $raceData['source_pages'],
                ]
            );

            $count++;
        }

        return $count;
    }

    private function getOrCreateBaseRace(
        string $baseRaceName,
        string $sizeCode,
        int $speed,
        string $sourceCode,
        string $sourcePages
    ): Race {
        // Check if base race already exists
        $existing = Race::where('name', $baseRaceName)
                        ->whereNull('parent_race_id')
                        ->first();

        if ($existing) {
            return $existing;
        }

        // Create base race with minimal data
        $size = Size::where('code', $sizeCode)->firstOrFail();
        $source = Source::where('code', $sourceCode)->firstOrFail();

        return Race::create([
            'name' => $baseRaceName,
            'size_id' => $size->id,
            'speed' => $speed,
            'description' => "Base {$baseRaceName} race. See subraces for details.",
            'source_id' => $source->id,
            'source_pages' => $sourcePages,
            'parent_race_id' => null,
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest
```

Expected: PASS (6 tests - 3 existing + 3 new)

**Step 5: Commit**

```bash
git add app/Services/Importers/RaceImporter.php tests/Feature/Importers/RaceImporterTest.php
git commit -m "feat: import base races and subraces with parent_race_id linking"
```

---

### Task 1.5: Update Race API to Include Parent and Subraces

**Files:**
- Modify: `app/Http/Resources/RaceResource.php`
- Modify: `app/Http/Controllers/Api/RaceController.php:20` (eager load parent and subraces)
- Modify: `tests/Feature/Api/RaceApiTest.php`

**Step 1: Write test for API returning parent and subraces**

Edit `tests/Feature/Api/RaceApiTest.php`, add these tests:

```php
/** @test */
public function it_includes_parent_race_in_response()
{
    // Create base race and subrace
    $baseRace = Race::factory()->create([
        'name' => 'Dwarf',
        'parent_race_id' => null,
    ]);

    $subrace = Race::factory()->create([
        'name' => 'Hill',
        'parent_race_id' => $baseRace->id,
    ]);

    $response = $this->getJson("/api/v1/races/{$subrace->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'name',
            'parent_race',
            'subraces',
        ]
    ]);

    $this->assertEquals('Dwarf', $response->json('data.parent_race.name'));
}

/** @test */
public function it_includes_subraces_in_response()
{
    $baseRace = Race::factory()->create([
        'name' => 'Elf',
        'parent_race_id' => null,
    ]);

    Race::factory()->create([
        'name' => 'High',
        'parent_race_id' => $baseRace->id,
    ]);

    Race::factory()->create([
        'name' => 'Wood',
        'parent_race_id' => $baseRace->id,
    ]);

    $response = $this->getJson("/api/v1/races/{$baseRace->id}");

    $response->assertStatus(200);
    $subraces = $response->json('data.subraces');
    $this->assertCount(2, $subraces);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceApiTest
```

Expected: FAIL - Factory doesn't exist yet, so first create it:

```bash
docker compose exec php php artisan make:factory RaceFactory
```

Edit `database/factories/RaceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Size;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

class RaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'size_id' => Size::where('code', 'M')->first()->id,
            'speed' => 30,
            'description' => fake()->paragraph(),
            'source_id' => Source::where('code', 'PHB')->first()->id,
            'source_pages' => '20',
            'parent_race_id' => null,
        ];
    }
}
```

Now run test again - should fail with "Undefined array key 'parent_race'"

**Step 3: Update RaceResource to include parent and subraces**

Edit `app/Http/Resources/RaceResource.php`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'size' => new SizeResource($this->whenLoaded('size')),
        'speed' => $this->speed,
        'description' => $this->description,
        'source' => new SourceResource($this->whenLoaded('source')),
        'source_pages' => $this->source_pages,
        'parent_race' => $this->when($this->parent_race_id, function () {
            return new RaceResource($this->whenLoaded('parent'));
        }),
        'subraces' => RaceResource::collection($this->whenLoaded('subraces')),
    ];
}
```

**Step 4: Update RaceController to eager load relationships**

Edit `app/Http/Controllers/Api/RaceController.php`:

In the `index` method, change:
```php
$races = Race::with(['size', 'source'])
```

In the `show` method, change:
```php
$race = Race::with(['size', 'source', 'parent', 'subraces'])
```

**Step 5: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceApiTest
```

Expected: PASS (5 tests - 3 existing + 2 new)

**Step 6: Commit**

```bash
git add app/Http/Resources/RaceResource.php app/Http/Controllers/Api/RaceController.php tests/Feature/Api/RaceApiTest.php database/factories/RaceFactory.php
git commit -m "feat: include parent race and subraces in Race API responses"
```

---

### Task 1.6: Re-import All Races with Subrace Hierarchy

**Files:**
- None (data operation)

**Step 1: Clear existing races**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\App\Models\Race::query()->delete();
echo \"Cleared all races\n\";
"
```

Expected: "Cleared all races"

**Step 2: Re-import races from PHB**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$importer = new \App\Services\Importers\RaceImporter();
\$count = \$importer->importFromFile('import-files/races-phb.xml');
echo \"Imported \$count races (includes base races + subraces)\n\";
"
```

Expected: "Imported ~21 races (includes base races + subraces)"

**Step 3: Verify race structure**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo \"Base races: \" . \App\Models\Race::whereNull('parent_race_id')->count() . \"\n\";
echo \"Subraces: \" . \App\Models\Race::whereNotNull('parent_race_id')->count() . \"\n\";
\$dwarf = \App\Models\Race::where('name', 'Dwarf')->whereNull('parent_race_id')->first();
if (\$dwarf) {
    echo \"Dwarf subraces: \" . \$dwarf->subraces()->count() . \"\n\";
}
"
```

Expected: Shows base races, subraces, and Dwarf has 2 subraces

**Step 4: Run all tests**

```bash
docker compose exec php php artisan test
```

Expected: All tests pass

**Step 5: Commit (no code changes, just verification)**

```bash
git add .
git commit -m "data: re-import races with subrace hierarchy"
```

---

## Phase 2: Add Proficiencies Polymorphic Table

### Task 2.1: Create Proficiencies Migration

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_create_proficiencies_table.php`

**Step 1: Create migration file**

```bash
docker compose exec php php artisan make:migration create_proficiencies_table
```

Expected: Migration file created

**Step 2: Write migration following design document**

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
        Schema::create('proficiencies', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->string('reference_type'); // 'race', 'background', 'class', 'feat'
            $table->unsignedBigInteger('reference_id');

            // Proficiency type
            $table->string('proficiency_type'); // 'skill', 'weapon', 'armor', 'tool', 'language', 'saving_throw'

            // Nullable FKs depending on proficiency_type
            $table->unsignedBigInteger('skill_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('ability_score_id')->nullable();
            $table->string('proficiency_name')->nullable(); // For free-form entries

            // Foreign keys
            $table->foreign('skill_id')
                  ->references('id')
                  ->on('skills')
                  ->onDelete('cascade');

            $table->foreign('item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');

            $table->foreign('ability_score_id')
                  ->references('id')
                  ->on('ability_scores')
                  ->onDelete('cascade');

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('proficiency_type');
            $table->index('skill_id');
            $table->index('item_id');

            // NO timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proficiencies');
    }
};
```

**Step 3: Run migration**

```bash
docker compose exec php php artisan migrate
```

Expected: Migration runs successfully, `proficiencies` table created

**Step 4: Commit**

```bash
git add database/migrations/*create_proficiencies_table.php
git commit -m "feat: create proficiencies polymorphic table per design document"
```

---

### Task 2.2: Create Proficiency Model

**Files:**
- Create: `app/Models/Proficiency.php`

**Step 1: Create model file**

```bash
docker compose exec php php artisan make:model Proficiency
```

**Step 2: Write Proficiency model**

Edit `app/Models/Proficiency.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Proficiency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'proficiency_type',
        'skill_id',
        'item_id',
        'ability_score_id',
        'proficiency_name',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'skill_id' => 'integer',
        'item_id' => 'integer',
        'ability_score_id' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Relationships to lookup tables
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }
}
```

**Step 3: Update Race model to include proficiencies relationship**

Edit `app/Models/Race.php`, add after the subraces() method:

```php
public function proficiencies(): MorphMany
{
    return $this->morphMany(Proficiency::class, 'reference');
}
```

Don't forget to add the import:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;
```

**Step 4: Test model relationships**

Create `tests/Feature/Models/ProficiencyModelTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Proficiency;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProficiencyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_proficiency_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();
        $skill = Skill::where('name', 'Perception')->first();

        $proficiency = Proficiency::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
        ]);

        $this->assertEquals($race->id, $proficiency->reference->id);
        $this->assertInstanceOf(Race::class, $proficiency->reference);
    }

    public function test_race_has_many_proficiencies(): void
    {
        $race = Race::factory()->create();
        $skill = Skill::where('name', 'Perception')->first();

        Proficiency::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
        ]);

        Proficiency::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Longsword',
        ]);

        $this->assertCount(2, $race->proficiencies);
    }
}
```

Run test:

```bash
docker compose exec php php artisan test --filter=ProficiencyModelTest
```

Expected: PASS (2 tests)

**Step 5: Commit**

```bash
git add app/Models/Proficiency.php app/Models/Race.php tests/Feature/Models/ProficiencyModelTest.php
git commit -m "feat: create Proficiency model with polymorphic relationships"
```

---

### Task 2.3: Update RaceXmlParser to Parse Proficiencies

**Files:**
- Modify: `app/Services/Parsers/RaceXmlParser.php`
- Modify: `tests/Unit/Parsers/RaceXmlParserTest.php`

**Step 1: Write test for proficiency parsing**

Edit `tests/Unit/Parsers/RaceXmlParserTest.php`, add this test:

```php
/** @test */
public function it_parses_skill_proficiencies()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <trait category="description">
      <name>Description</name>
      <text>High elf description.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertCount(1, $races);
    $this->assertArrayHasKey('proficiencies', $races[0]);
    $this->assertCount(1, $races[0]['proficiencies']);
    $this->assertEquals('skill', $races[0]['proficiencies'][0]['type']);
    $this->assertEquals('Perception', $races[0]['proficiencies'][0]['name']);
}

/** @test */
public function it_parses_weapon_proficiencies()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <weapons>Longsword, Shortsword, Shortbow, Longbow</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertCount(4, $races[0]['proficiencies']);
    $this->assertEquals('weapon', $races[0]['proficiencies'][0]['type']);
    $this->assertEquals('Longsword', $races[0]['proficiencies'][0]['name']);
}

/** @test */
public function it_parses_armor_proficiencies()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Mountain</name>
    <size>M</size>
    <speed>25</speed>
    <armor>Light Armor, Medium Armor</armor>
    <trait category="description">
      <name>Description</name>
      <text>Mountain dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertCount(2, $races[0]['proficiencies']);
    $this->assertEquals('armor', $races[0]['proficiencies'][0]['type']);
    $this->assertEquals('Light Armor', $races[0]['proficiencies'][0]['name']);
}

/** @test */
public function it_parses_multiple_proficiency_types()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Mountain</name>
    <size>M</size>
    <speed>25</speed>
    <proficiency>Perception</proficiency>
    <weapons>Battleaxe, Handaxe</weapons>
    <armor>Light Armor, Medium Armor</armor>
    <trait category="description">
      <name>Description</name>
      <text>Dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $proficiencies = $races[0]['proficiencies'];
    $this->assertCount(5, $proficiencies); // 1 skill + 2 weapons + 2 armor

    // Verify we have all types
    $types = array_column($proficiencies, 'type');
    $this->assertContains('skill', $types);
    $this->assertContains('weapon', $types);
    $this->assertContains('armor', $types);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

Expected: FAIL - "Undefined array key 'proficiencies'"

**Step 3: Update parser to extract proficiencies**

Edit `app/Services/Parsers/RaceXmlParser.php`, update the parseRace method:

```php
private function parseRace(SimpleXMLElement $element): array
{
    // Parse race name and extract base race / subrace
    $fullName = (string) $element->name;
    $baseRaceName = null;
    $raceName = $fullName;

    // Check if name contains comma (indicates subrace)
    if (str_contains($fullName, ',')) {
        [$baseRaceName, $raceName] = array_map('trim', explode(',', $fullName, 2));
    }

    // Parse description from traits with category="description" only
    $description = '';
    $sourceCode = '';
    $sourcePages = '';

    foreach ($element->trait as $trait) {
        // Only include traits with category="description" (lore/flavor text)
        // Skip mechanical traits (Age, Alignment, Size, Languages, species abilities)
        $category = isset($trait['category']) ? (string) $trait['category'] : '';

        if ($category !== 'description') {
            continue;
        }

        $traitName = (string) $trait->name;
        $traitText = (string) $trait->text;

        // Check if this trait contains a source citation
        if (preg_match('/Source:\s*([^p]+)\s*p\.\s*([\d,\s]+)/', $traitText, $matches)) {
            $sourceName = trim($matches[1]);
            $sourcePages = trim($matches[2]);
            $sourceCode = $this->getSourceCode($sourceName);

            // Remove the source line from the text
            $traitText = preg_replace('/\n*Source:\s*[^\n]+/', '', $traitText);
        }

        // Add trait to description (omit the trait name if it's just "Description")
        if (trim($traitText)) {
            if ($traitName === 'Description') {
                $description .= "{$traitText}\n\n";
            } else {
                $description .= "**{$traitName}**\n\n{$traitText}\n\n";
            }
        }
    }

    // Parse proficiencies
    $proficiencies = $this->parseProficiencies($element);

    return [
        'name' => $raceName,
        'base_race_name' => $baseRaceName,
        'size_code' => (string) $element->size,
        'speed' => (int) $element->speed,
        'description' => trim($description),
        'source_code' => $sourceCode ?: 'PHB',
        'source_pages' => $sourcePages,
        'proficiencies' => $proficiencies,
    ];
}

private function parseProficiencies(SimpleXMLElement $element): array
{
    $proficiencies = [];

    // Parse skill proficiencies
    if (isset($element->proficiency)) {
        $skills = array_map('trim', explode(',', (string) $element->proficiency));
        foreach ($skills as $skill) {
            $proficiencies[] = [
                'type' => 'skill',
                'name' => $skill,
            ];
        }
    }

    // Parse weapon proficiencies
    if (isset($element->weapons)) {
        $weapons = array_map('trim', explode(',', (string) $element->weapons));
        foreach ($weapons as $weapon) {
            $proficiencies[] = [
                'type' => 'weapon',
                'name' => $weapon,
            ];
        }
    }

    // Parse armor proficiencies
    if (isset($element->armor)) {
        $armors = array_map('trim', explode(',', (string) $element->armor));
        foreach ($armors as $armor) {
            $proficiencies[] = [
                'type' => 'armor',
                'name' => $armor,
            ];
        }
    }

    return $proficiencies;
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

Expected: PASS (11 tests - 7 existing + 4 new)

**Step 5: Commit**

```bash
git add app/Services/Parsers/RaceXmlParser.php tests/Unit/Parsers/RaceXmlParserTest.php
git commit -m "feat: parse skill, weapon, and armor proficiencies from race XML"
```

---

### Task 2.4: Update RaceImporter to Import Proficiencies

**Files:**
- Modify: `app/Services/Importers/RaceImporter.php`
- Modify: `tests/Feature/Importers/RaceImporterTest.php`

**Step 1: Write test for proficiency import**

Edit `tests/Feature/Importers/RaceImporterTest.php`, add this test:

```php
/** @test */
public function it_imports_skill_proficiencies()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

    $this->importer->importFromXml($xml);

    $race = Race::where('name', 'High')->first();
    $this->assertNotNull($race);

    $proficiencies = $race->proficiencies;
    $this->assertCount(1, $proficiencies);

    $proficiency = $proficiencies->first();
    $this->assertEquals('skill', $proficiency->proficiency_type);
    $this->assertNotNull($proficiency->skill_id);
    $this->assertEquals('Perception', $proficiency->skill->name);
}

/** @test */
public function it_imports_weapon_proficiencies_as_text()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <weapons>Longsword, Shortsword</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

    $this->importer->importFromXml($xml);

    $race = Race::where('name', 'High')->first();
    $proficiencies = $race->proficiencies;

    $this->assertCount(2, $proficiencies);

    $weaponProfs = $proficiencies->where('proficiency_type', 'weapon');
    $this->assertCount(2, $weaponProfs);

    // Should be stored as proficiency_name (items not imported yet)
    $names = $weaponProfs->pluck('proficiency_name')->toArray();
    $this->assertContains('Longsword', $names);
    $this->assertContains('Shortsword', $names);
}

/** @test */
public function it_clears_and_recreates_proficiencies_on_reimport()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <weapons>Longsword</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

    // First import
    $this->importer->importFromXml($xml);
    $race = Race::where('name', 'High')->first();
    $this->assertCount(2, $race->proficiencies);

    // Second import (should clear old proficiencies)
    $this->importer->importFromXml($xml);
    $race->refresh();
    $this->assertCount(2, $race->proficiencies);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest
```

Expected: FAIL - "Failed asserting that 0 matches expected 1"

**Step 3: Update RaceImporter to import proficiencies**

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
            // Get or create base race
            $baseRace = $this->getOrCreateBaseRace(
                $raceData['base_race_name'],
                $raceData['size_code'],
                $raceData['speed'],
                $raceData['source_code'],
                $raceData['source_pages']
            );

            $parentRaceId = $baseRace->id;
            $count++; // Count base race if newly created
        }

        // Create or update the race (or subrace)
        $size = Size::where('code', $raceData['size_code'])->firstOrFail();
        $source = Source::where('code', $raceData['source_code'])->firstOrFail();

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
                'source_pages' => $raceData['source_pages'],
            ]
        );

        // Import proficiencies (clear old ones first)
        $this->importProficiencies($race, $raceData['proficiencies']);

        $count++;
    }

    return $count;
}

private function importProficiencies(Race $race, array $proficienciesData): void
{
    // Clear existing proficiencies for this race
    $race->proficiencies()->delete();

    foreach ($proficienciesData as $profData) {
        $proficiency = [
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'proficiency_type' => $profData['type'],
        ];

        // Handle different proficiency types
        if ($profData['type'] === 'skill') {
            // Look up skill by name
            $skill = \App\Models\Skill::where('name', $profData['name'])->first();
            if ($skill) {
                $proficiency['skill_id'] = $skill->id;
            } else {
                // If skill not found, store as proficiency_name
                $proficiency['proficiency_name'] = $profData['name'];
            }
        } elseif ($profData['type'] === 'weapon' || $profData['type'] === 'armor') {
            // Store as proficiency_name (items not imported yet)
            $proficiency['proficiency_name'] = $profData['name'];
        }

        \App\Models\Proficiency::create($proficiency);
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest
```

Expected: PASS (9 tests - 6 existing + 3 new)

**Step 5: Commit**

```bash
git add app/Services/Importers/RaceImporter.php tests/Feature/Importers/RaceImporterTest.php
git commit -m "feat: import race proficiencies (skills, weapons, armor)"
```

---

### Task 2.5: Update Race API to Include Proficiencies

**Files:**
- Create: `app/Http/Resources/ProficiencyResource.php`
- Modify: `app/Http/Resources/RaceResource.php`
- Modify: `app/Http/Controllers/Api/RaceController.php`
- Modify: `tests/Feature/Api/RaceApiTest.php`

**Step 1: Create ProficiencyResource**

```bash
docker compose exec php php artisan make:resource ProficiencyResource
```

Edit `app/Http/Resources/ProficiencyResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProficiencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proficiency_type' => $this->proficiency_type,
            'skill' => $this->when($this->skill_id, function () {
                return [
                    'id' => $this->skill->id,
                    'name' => $this->skill->name,
                ];
            }),
            'proficiency_name' => $this->proficiency_name,
        ];
    }
}
```

**Step 2: Write test for API returning proficiencies**

Edit `tests/Feature/Api/RaceApiTest.php`, add this test:

```php
/** @test */
public function it_includes_proficiencies_in_response()
{
    $race = Race::factory()->create(['name' => 'High Elf']);
    $skill = Skill::where('name', 'Perception')->first();

    Proficiency::create([
        'reference_type' => 'race',
        'reference_id' => $race->id,
        'proficiency_type' => 'skill',
        'skill_id' => $skill->id,
    ]);

    Proficiency::create([
        'reference_type' => 'race',
        'reference_id' => $race->id,
        'proficiency_type' => 'weapon',
        'proficiency_name' => 'Longsword',
    ]);

    $response = $this->getJson("/api/v1/races/{$race->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'proficiencies' => [
                '*' => ['id', 'proficiency_type']
            ]
        ]
    ]);

    $proficiencies = $response->json('data.proficiencies');
    $this->assertCount(2, $proficiencies);
}
```

**Step 3: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceApiTest
```

Expected: FAIL - "Undefined array key 'proficiencies'"

**Step 4: Update RaceResource to include proficiencies**

Edit `app/Http/Resources/RaceResource.php`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'size' => new SizeResource($this->whenLoaded('size')),
        'speed' => $this->speed,
        'description' => $this->description,
        'source' => new SourceResource($this->whenLoaded('source')),
        'source_pages' => $this->source_pages,
        'parent_race' => $this->when($this->parent_race_id, function () {
            return new RaceResource($this->whenLoaded('parent'));
        }),
        'subraces' => RaceResource::collection($this->whenLoaded('subraces')),
        'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
    ];
}
```

Don't forget to add the import:

```php
use App\Http\Resources\ProficiencyResource;
```

**Step 5: Update RaceController to eager load proficiencies**

Edit `app/Http/Controllers/Api/RaceController.php`:

In the `show` method, change:
```php
$race = Race::with(['size', 'source', 'parent', 'subraces', 'proficiencies.skill'])
```

**Step 6: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceApiTest
```

Expected: PASS (6 tests - 5 existing + 1 new)

**Step 7: Commit**

```bash
git add app/Http/Resources/ProficiencyResource.php app/Http/Resources/RaceResource.php app/Http/Controllers/Api/RaceController.php tests/Feature/Api/RaceApiTest.php
git commit -m "feat: include proficiencies in Race API responses"
```

---

### Task 2.6: Re-import All Races with Proficiencies

**Files:**
- None (data operation)

**Step 1: Clear existing races and proficiencies**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\App\Models\Proficiency::query()->delete();
\App\Models\Race::query()->delete();
echo \"Cleared all races and proficiencies\n\";
"
```

Expected: "Cleared all races and proficiencies"

**Step 2: Re-import races from PHB with proficiencies**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$importer = new \App\Services\Importers\RaceImporter();
\$count = \$importer->importFromFile('import-files/races-phb.xml');
echo \"Imported \$count races (includes base races + subraces)\n\";
"
```

Expected: "Imported ~21 races"

**Step 3: Verify proficiency data**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo \"Total proficiencies: \" . \App\Models\Proficiency::count() . \"\n\";
echo \"Skill proficiencies: \" . \App\Models\Proficiency::where('proficiency_type', 'skill')->count() . \"\n\";
echo \"Weapon proficiencies: \" . \App\Models\Proficiency::where('proficiency_type', 'weapon')->count() . \"\n\";
echo \"Armor proficiencies: \" . \App\Models\Proficiency::where('proficiency_type', 'armor')->count() . \"\n\";
\$elf = \App\Models\Race::where('name', 'High')->first();
if (\$elf) {
    echo \"High Elf proficiencies: \" . \$elf->proficiencies()->count() . \"\n\";
}
"
```

Expected: Shows proficiency counts

**Step 4: Test API endpoint**

```bash
curl -s "http://localhost:8080/api/v1/races?search=High" | python3 -m json.tool | head -50
```

Expected: Shows High Elf with proficiencies

**Step 5: Run all tests**

```bash
docker compose exec php php artisan test
```

Expected: All tests pass

**Step 6: Commit**

```bash
git add .
git commit -m "data: re-import races with proficiencies from PHB"
```

---

## Verification & Completion

### Task 3.1: Run Full Test Suite

**Step 1: Run all tests**

```bash
docker compose exec php php artisan test
```

Expected: All tests pass (should be ~170+ tests now)

**Step 2: Verify database state**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== Database Summary ===\n\";
echo \"Base races: \" . \App\Models\Race::whereNull('parent_race_id')->count() . \"\n\";
echo \"Subraces: \" . \App\Models\Race::whereNotNull('parent_race_id')->count() . \"\n\";
echo \"Total races: \" . \App\Models\Race::count() . \"\n\";
echo \"Total proficiencies: \" . \App\Models\Proficiency::count() . \"\n\";
echo \"Skill proficiencies: \" . \App\Models\Proficiency::where('proficiency_type', 'skill')->count() . \"\n\";
echo \"Weapon proficiencies: \" . \App\Models\Proficiency::where('proficiency_type', 'weapon')->count() . \"\n\";
echo \"Armor proficiencies: \" . \App\Models\Proficiency::where('proficiency_type', 'armor')->count() . \"\n\";
"
```

**Step 3: Verify API responses**

```bash
# List all races
curl -s "http://localhost:8080/api/v1/races" | python3 -m json.tool | head -30

# Get a specific subrace with parent
curl -s "http://localhost:8080/api/v1/races/$(docker compose exec php php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap(); echo \App\Models\Race::where('name', 'Hill')->first()->id;")" | python3 -m json.tool

# Get a base race with subraces
curl -s "http://localhost:8080/api/v1/races/$(docker compose exec php php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap(); echo \App\Models\Race::where('name', 'Dwarf')->whereNull('parent_race_id')->first()->id;")" | python3 -m json.tool
```

**Step 4: Final commit**

```bash
git status
git add .
git commit -m "feat: complete races subraces and proficiencies implementation

- Added parent_race_id for subrace hierarchy
- Created proficiencies polymorphic table per design document
- Parse and import skills, weapons, armor proficiencies
- API returns parent race, subraces, and proficiencies
- All tests passing (~170+ tests)
- Re-imported 21 races with full proficiency data"
```

---

## Summary

This plan implements:

1. **Phase 1: Subrace Hierarchy**
   - Migration: `parent_race_id` column
   - Parser: Extract base race from names like "Dwarf, Hill"
   - Importer: Create base races, link subraces
   - API: Return parent and subraces relationships
   - Result: ~8 base races + ~13 subraces

2. **Phase 2: Proficiencies**
   - Migration: `proficiencies` polymorphic table (per design doc)
   - Model: Proficiency with polymorphic relationships
   - Parser: Extract skills, weapons, armor from XML
   - Importer: Create proficiency records
   - API: Return proficiencies with race data
   - Result: ~40-50 proficiency records

**Adherence to Design Document:** ✅
- Uses exact table structure from design doc Section 3 (polymorphic tables)
- Follows naming conventions
- Implements proper indexes
- Uses polymorphic relationships as specified

**TDD Approach:** ✅
- Every feature has tests written FIRST
- RED → GREEN → REFACTOR cycle
- Frequent commits after each passing test

**Files Created/Modified:**
- 2 migrations
- 1 new model (Proficiency)
- 3 resources (ProficiencyResource + updates)
- Parser, Importer, Controller updates
- 10+ new tests
