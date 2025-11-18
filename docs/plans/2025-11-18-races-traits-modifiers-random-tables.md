# Races: Traits, Modifiers, and Random Tables Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace races.description field with polymorphic traits table, add modifiers table for ability score bonuses, and extract random dice tables per design document.

**Architecture:** Four-phase approach - Phase 1: traits table, Phase 2: modifiers table, Phase 3: random_tables, Phase 4: cleanup. Each phase follows TDD with migration → model → parser → importer → API → tests. Removes description field from races after migration.

**Tech Stack:** Laravel 11.x, PHP 8.3, PostgreSQL, PHPUnit, SimpleXML

---

## Analysis of Current State

**Issues identified:**
1. **description field in races** - Currently stores all trait text. Should be replaced with polymorphic traits table
2. **<ability> tag not parsed** - "Str +2, Cha +1" should go into modifiers table with ability_score lookups
3. **<trait> elements** - Need to be imported into traits table with proper category handling
4. **<roll> tags** - Dice rolls within traits need to be extracted to random_tables

**XML Structure Examples:**
```xml
<ability>Str +2, Cha +1</ability>
<trait category="description">
  <name>Description</name>
  <text>Born of dragons...</text>
</trait>
<trait category="species">
  <name>Breath Weapon</name>
  <text>You can use your action...</text>
  <roll description="Damage" level="1">2d6</roll>
  <roll description="Damage" level="6">3d6</roll>
</trait>
<trait>
  <name>Size</name>
  <text>Your size is Medium. To set height randomly...</text>
  <roll description="Size Modifier">2d8</roll>
</trait>
```

---

## Phase 1: Traits Polymorphic Table

### Task 1.1: Create Traits Migration and Model

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_create_traits_table.php`
- Create: `app/Models/Trait.php`
- Modify: `app/Models/Race.php`

**Step 1: Create migration file**

```bash
docker compose exec php php artisan make:migration create_traits_table
```

**Step 2: Write migration per design document**

Edit the generated migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traits', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->string('reference_type'); // 'race', 'background', 'class'
            $table->unsignedBigInteger('reference_id');

            // Trait data
            $table->string('name');
            $table->string('category')->nullable(); // 'species', 'subspecies', 'description', 'feature'
            $table->text('description');
            $table->integer('sort_order')->default(0);

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('category');

            // NO timestamps - static compendium data
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traits');
    }
};
```

**Step 3: Run migration**

```bash
docker compose exec php php artisan migrate
```

Expected: Migration runs successfully

**Step 4: Create Trait model**

```bash
docker compose exec php php artisan make:model Trait
```

Edit `app/Models/Trait.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Trait extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'name',
        'category',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'sort_order' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
```

**Step 5: Update Race model to add traits relationship**

Edit `app/Models/Race.php`, add after proficiencies() method:

```php
public function traits(): MorphMany
{
    return $this->morphMany(Trait::class, 'reference');
}
```

Don't forget to add import if not already there:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;
```

**Step 6: Create TraitModelTest**

Create `tests/Feature/Models/TraitModelTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Race;
use App\Models\Trait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraitModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_trait_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();

        $trait = Trait::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'category' => 'species',
            'description' => 'You can see in dim light within 60 feet...',
            'sort_order' => 1,
        ]);

        $this->assertEquals($race->id, $trait->reference->id);
        $this->assertInstanceOf(Race::class, $trait->reference);
    }

    public function test_race_has_many_traits(): void
    {
        $race = Race::factory()->create();

        Trait::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'category' => 'species',
            'description' => 'You can see in dim light...',
            'sort_order' => 1,
        ]);

        Trait::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'name' => 'Keen Senses',
            'category' => 'species',
            'description' => 'You have proficiency in Perception...',
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $race->traits);
    }
}
```

**Step 7: Run tests**

```bash
docker compose exec php php artisan test --filter=TraitModelTest
```

Expected: 2 tests pass

**Step 8: Commit**

```bash
git add database/migrations/*create_traits_table.php app/Models/Trait.php app/Models/Race.php tests/Feature/Models/TraitModelTest.php
git commit -m "feat: create traits polymorphic table and model per design document"
```

---

### Task 1.2: Update RaceXmlParser to Parse Traits

**Files:**
- Modify: `app/Services/Parsers/RaceXmlParser.php`
- Modify: `tests/Unit/Parsers/RaceXmlParserTest.php`

**Step 1: Write test for trait parsing**

Edit `tests/Unit/Parsers/RaceXmlParserTest.php`, add this test:

```php
/** @test */
public function it_parses_traits_from_xml()
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
    <trait category="species">
      <name>Breath Weapon</name>
      <text>You can use your action to exhale destructive energy.</text>
    </trait>
    <trait>
      <name>Languages</name>
      <text>You can speak Common and Draconic.</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertArrayHasKey('traits', $races[0]);
    $this->assertCount(3, $races[0]['traits']);

    // Check description trait
    $this->assertEquals('Description', $races[0]['traits'][0]['name']);
    $this->assertEquals('description', $races[0]['traits'][0]['category']);
    $this->assertStringContainsString('Born of dragons', $races[0]['traits'][0]['description']);

    // Check species trait
    $this->assertEquals('Breath Weapon', $races[0]['traits'][1]['name']);
    $this->assertEquals('species', $races[0]['traits'][1]['category']);

    // Check trait without category
    $this->assertEquals('Languages', $races[0]['traits'][2]['name']);
    $this->assertNull($races[0]['traits'][2]['category']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest::it_parses_traits_from_xml
```

Expected: FAIL - "Undefined array key 'traits'"

**Step 3: Update parser to extract traits**

Edit `app/Services/Parsers/RaceXmlParser.php`, replace the current trait parsing in parseRace() method:

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

    // Parse traits
    $traits = $this->parseTraits($element);

    // Extract source from first description trait
    $sourceCode = 'PHB';
    $sourcePages = '';
    foreach ($traits as $trait) {
        if (preg_match('/Source:\s*([^p]+)\s*p\.\s*([\d,\s]+)/', $trait['description'], $matches)) {
            $sourceName = trim($matches[1]);
            $sourcePages = trim($matches[2]);
            $sourceCode = $this->getSourceCode($sourceName);

            // Remove source line from trait description
            $trait['description'] = preg_replace('/\n*Source:\s*[^\n]+/', '', $trait['description']);
            break;
        }
    }

    // Parse proficiencies
    $proficiencies = $this->parseProficiencies($element);

    return [
        'name' => $raceName,
        'base_race_name' => $baseRaceName,
        'size_code' => (string) $element->size,
        'speed' => (int) $element->speed,
        'traits' => $traits,
        'source_code' => $sourceCode,
        'source_pages' => $sourcePages,
        'proficiencies' => $proficiencies,
    ];
}

private function parseTraits(SimpleXMLElement $element): array
{
    $traits = [];
    $sortOrder = 0;

    foreach ($element->trait as $traitElement) {
        $category = isset($traitElement['category']) ? (string) $traitElement['category'] : null;
        $name = (string) $traitElement->name;
        $text = (string) $traitElement->text;

        $traits[] = [
            'name' => $name,
            'category' => $category,
            'description' => trim($text),
            'sort_order' => $sortOrder++,
        ];
    }

    return $traits;
}
```

**Note:** Remove the old description parsing logic that was concatenating traits into a single description field.

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

Expected: All parser tests pass (including new trait test)

**Step 5: Commit**

```bash
git add app/Services/Parsers/RaceXmlParser.php tests/Unit/Parsers/RaceXmlParserTest.php
git commit -m "feat: parse race traits into structured array with category and sort_order"
```

---

### Task 1.3: Update RaceImporter to Import Traits

**Files:**
- Modify: `app/Services/Importers/RaceImporter.php`
- Modify: `tests/Feature/Importers/RaceImporterTest.php`

**Step 1: Write test for trait import**

Edit `tests/Feature/Importers/RaceImporterTest.php`, add this test:

```php
/** @test */
public function it_imports_race_traits()
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
    <trait category="species">
      <name>Breath Weapon</name>
      <text>You can exhale destructive energy.</text>
    </trait>
  </race>
</compendium>
XML;

    $this->importer->importFromXml($xml);

    $race = Race::where('name', 'Dragonborn')->first();
    $this->assertNotNull($race);

    $traits = $race->traits;
    $this->assertCount(2, $traits);

    $descriptionTrait = $traits->where('name', 'Description')->first();
    $this->assertEquals('description', $descriptionTrait->category);
    $this->assertStringContainsString('Born of dragons', $descriptionTrait->description);

    $speciesTrait = $traits->where('name', 'Breath Weapon')->first();
    $this->assertEquals('species', $speciesTrait->category);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest::it_imports_race_traits
```

Expected: FAIL - traits count is 0

**Step 3: Update RaceImporter to import traits**

Edit `app/Services/Importers/RaceImporter.php`, update the import() method:

```php
public function import(array $raceData): Race
{
    // If this is a subrace, ensure base race exists first
    $parentRaceId = null;

    if (!empty($raceData['base_race_name'])) {
        $baseRace = $this->getOrCreateBaseRace(
            $raceData['base_race_name'],
            $raceData['size_code'],
            $raceData['speed'],
            $raceData['source_code'],
            $raceData['source_pages']
        );

        $parentRaceId = $baseRace->id;
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
            'source_id' => $source->id,
            'source_pages' => $raceData['source_pages'],
        ]
    );

    // Import traits (clear old ones first)
    $this->importTraits($race, $raceData['traits'] ?? []);

    // Import proficiencies (clear old ones first)
    $this->importProficiencies($race, $raceData['proficiencies']);

    return $race;
}

private function importTraits(Race $race, array $traitsData): void
{
    // Clear existing traits for this race
    $race->traits()->delete();

    foreach ($traitsData as $traitData) {
        \App\Models\Trait::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'name' => $traitData['name'],
            'category' => $traitData['category'],
            'description' => $traitData['description'],
            'sort_order' => $traitData['sort_order'],
        ]);
    }
}
```

**Note:** Remove the description field from the Race updateOrCreate call since it no longer exists.

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest
```

Expected: All importer tests pass

**Step 5: Commit**

```bash
git add app/Services/Importers/RaceImporter.php tests/Feature/Importers/RaceImporterTest.php
git commit -m "feat: import race traits into polymorphic traits table"
```

---

### Task 1.4: Remove description Field from Races Table

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_remove_description_from_races_table.php`
- Modify: `app/Models/Race.php`
- Modify: `app/Http/Resources/RaceResource.php`

**Step 1: Create migration to drop description column**

```bash
docker compose exec php php artisan make:migration remove_description_from_races_table
```

Edit the generated migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->text('description')->after('speed');
        });
    }
};
```

**Step 2: Run migration**

```bash
docker compose exec php php artisan migrate
```

Expected: Migration runs successfully, description column removed

**Step 3: Update Race model**

Edit `app/Models/Race.php`, remove 'description' from $fillable array:

```php
protected $fillable = [
    'name',
    'size_id',
    'speed',
    // removed 'description',
    'source_id',
    'source_pages',
    'parent_race_id',
];
```

**Step 4: Update RaceResource to return traits instead of description**

Edit `app/Http/Resources/RaceResource.php`:

```php
use App\Http\Resources\TraitResource;

public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'size' => new SizeResource($this->whenLoaded('size')),
        'speed' => $this->speed,
        'traits' => TraitResource::collection($this->whenLoaded('traits')),
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

**Step 5: Create TraitResource**

```bash
docker compose exec php php artisan make:resource TraitResource
```

Edit `app/Http/Resources/TraitResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TraitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
        ];
    }
}
```

**Step 6: Update RaceController to eager load traits**

Edit `app/Http/Controllers/Api/RaceController.php`:

In the `show()` method:
```php
$race = Race::with(['size', 'source', 'parent', 'subraces', 'proficiencies.skill', 'traits'])
```

**Step 7: Update RaceFactory to remove description**

Edit `database/factories/RaceFactory.php`, remove description field:

```php
public function definition(): array
{
    return [
        'name' => fake()->word(),
        'size_id' => Size::where('code', 'M')->first()->id,
        'speed' => 30,
        // removed 'description'
        'source_id' => Source::where('code', 'PHB')->first()->id,
        'source_pages' => '20',
        'parent_race_id' => null,
    ];
}
```

**Step 8: Update API test**

Edit `tests/Feature/Api/RaceApiTest.php`, update test to check for traits instead of description:

```php
/** @test */
public function it_includes_traits_in_response()
{
    $race = Race::factory()->create(['name' => 'Elf']);

    \App\Models\Trait::create([
        'reference_type' => 'race',
        'reference_id' => $race->id,
        'name' => 'Darkvision',
        'category' => 'species',
        'description' => 'You can see in dim light...',
        'sort_order' => 1,
    ]);

    $response = $this->getJson("/api/v1/races/{$race->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'traits' => [
                '*' => ['id', 'name', 'category', 'description', 'sort_order']
            ]
        ]
    ]);

    $traits = $response->json('data.traits');
    $this->assertCount(1, $traits);
    $this->assertEquals('Darkvision', $traits[0]['name']);
}
```

**Step 9: Run all tests**

```bash
docker compose exec php php artisan test
```

Expected: All tests pass

**Step 10: Commit**

```bash
git add database/migrations/*remove_description_from_races_table.php app/Models/Race.php app/Http/Resources/RaceResource.php app/Http/Resources/TraitResource.php app/Http/Controllers/Api/RaceController.php database/factories/RaceFactory.php tests/Feature/Api/RaceApiTest.php
git commit -m "feat: remove description field from races, use traits table instead"
```

---

## Phase 2: Modifiers Polymorphic Table

### Task 2.1: Create Modifiers Migration and Model

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_create_modifiers_table.php`
- Create: `app/Models/Modifier.php`
- Modify: `app/Models/Race.php`

**Step 1: Create migration file**

```bash
docker compose exec php php artisan make:migration create_modifiers_table
```

**Step 2: Write migration per design document**

Edit the generated migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->string('reference_type'); // 'feat', 'race', 'item', 'class', 'background', 'spell'
            $table->unsignedBigInteger('reference_id');

            // Modifier data
            $table->string('modifier_category'); // 'ability_score', 'skill', 'speed', 'initiative', 'armor_class', 'damage_resistance', 'saving_throw'

            // Nullable FKs depending on modifier_category
            $table->unsignedBigInteger('ability_score_id')->nullable();
            $table->unsignedBigInteger('skill_id')->nullable();
            $table->unsignedBigInteger('damage_type_id')->nullable();

            $table->string('value'); // '+1', '+2', '+5', '+10 feet', 'advantage', 'proficiency', 'resistance'
            $table->text('condition')->nullable(); // "while wearing armor", "against magic"

            // Foreign keys
            $table->foreign('ability_score_id')->references('id')->on('ability_scores')->onDelete('cascade');
            $table->foreign('skill_id')->references('id')->on('skills')->onDelete('cascade');
            $table->foreign('damage_type_id')->references('id')->on('damage_types')->onDelete('cascade');

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('modifier_category');

            // NO timestamps - static compendium data
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifiers');
    }
};
```

**Step 3: Run migration**

```bash
docker compose exec php php artisan migrate
```

**Step 4: Create Modifier model**

```bash
docker compose exec php php artisan make:model Modifier
```

Edit `app/Models/Modifier.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Modifier extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'modifier_category',
        'ability_score_id',
        'skill_id',
        'damage_type_id',
        'value',
        'condition',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'ability_score_id' => 'integer',
        'skill_id' => 'integer',
        'damage_type_id' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Relationships to lookup tables
    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }
}
```

**Step 5: Update Race model to add modifiers relationship**

Edit `app/Models/Race.php`, add after traits() method:

```php
public function modifiers(): MorphMany
{
    return $this->morphMany(Modifier::class, 'reference');
}
```

**Step 6: Create ModifierModelTest**

Create `tests/Feature/Models/ModifierModelTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModifierModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_modifier_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();
        $abilityScore = AbilityScore::where('code', 'STR')->first();

        $modifier = Modifier::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $abilityScore->id,
            'value' => '+2',
        ]);

        $this->assertEquals($race->id, $modifier->reference->id);
        $this->assertInstanceOf(Race::class, $modifier->reference);
    }

    public function test_race_has_many_modifiers(): void
    {
        $race = Race::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();
        $cha = AbilityScore::where('code', 'CHA')->first();

        Modifier::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '+2',
        ]);

        Modifier::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '+1',
        ]);

        $this->assertCount(2, $race->modifiers);
    }
}
```

**Step 7: Run tests**

```bash
docker compose exec php php artisan test --filter=ModifierModelTest
```

Expected: 2 tests pass

**Step 8: Commit**

```bash
git add database/migrations/*create_modifiers_table.php app/Models/Modifier.php app/Models/Race.php tests/Feature/Models/ModifierModelTest.php
git commit -m "feat: create modifiers polymorphic table and model per design document"
```

---

### Task 2.2: Update RaceXmlParser to Parse Ability Bonuses

**Files:**
- Modify: `app/Services/Parsers/RaceXmlParser.php`
- Modify: `tests/Unit/Parsers/RaceXmlParserTest.php`

**Step 1: Write test for ability parsing**

Edit `tests/Unit/Parsers/RaceXmlParserTest.php`, add this test:

```php
/** @test */
public function it_parses_ability_score_bonuses()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $this->assertArrayHasKey('ability_bonuses', $races[0]);
    $this->assertCount(2, $races[0]['ability_bonuses']);

    $this->assertEquals('Str', $races[0]['ability_bonuses'][0]['ability']);
    $this->assertEquals('+2', $races[0]['ability_bonuses'][0]['value']);

    $this->assertEquals('Cha', $races[0]['ability_bonuses'][1]['ability']);
    $this->assertEquals('+1', $races[0]['ability_bonuses'][1]['value']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest::it_parses_ability_score_bonuses
```

Expected: FAIL - "Undefined array key 'ability_bonuses'"

**Step 3: Update parser to extract ability bonuses**

Edit `app/Services/Parsers/RaceXmlParser.php`, add to parseRace():

```php
private function parseRace(SimpleXMLElement $element): array
{
    // ... existing code ...

    // Parse ability bonuses
    $abilityBonuses = $this->parseAbilityBonuses($element);

    return [
        'name' => $raceName,
        'base_race_name' => $baseRaceName,
        'size_code' => (string) $element->size,
        'speed' => (int) $element->speed,
        'traits' => $traits,
        'ability_bonuses' => $abilityBonuses,
        'source_code' => $sourceCode,
        'source_pages' => $sourcePages,
        'proficiencies' => $proficiencies,
    ];
}

private function parseAbilityBonuses(SimpleXMLElement $element): array
{
    $bonuses = [];

    if (!isset($element->ability)) {
        return $bonuses;
    }

    $abilityText = (string) $element->ability;

    // Parse format: "Str +2, Cha +1"
    $parts = array_map('trim', explode(',', $abilityText));

    foreach ($parts as $part) {
        // Match "Str +2" or "Dex +1"
        if (preg_match('/^([A-Za-z]{3})\s*([+-]\d+)$/', $part, $matches)) {
            $bonuses[] = [
                'ability' => $matches[1],
                'value' => $matches[2],
            ];
        }
    }

    return $bonuses;
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

Expected: All parser tests pass

**Step 5: Commit**

```bash
git add app/Services/Parsers/RaceXmlParser.php tests/Unit/Parsers/RaceXmlParserTest.php
git commit -m "feat: parse ability score bonuses from race XML"
```

---

### Task 2.3: Update RaceImporter to Import Ability Bonuses as Modifiers

**Files:**
- Modify: `app/Services/Importers/RaceImporter.php`
- Modify: `tests/Feature/Importers/RaceImporterTest.php`

**Step 1: Write test for ability bonus import**

Edit `tests/Feature/Importers/RaceImporterTest.php`, add this test:

```php
/** @test */
public function it_imports_ability_score_bonuses_as_modifiers()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

    $this->importer->importFromXml($xml);

    $race = Race::where('name', 'Dragonborn')->first();
    $modifiers = $race->modifiers;

    $this->assertCount(2, $modifiers);

    $strModifier = $modifiers->where('ability_score_id', AbilityScore::where('code', 'STR')->first()->id)->first();
    $this->assertNotNull($strModifier);
    $this->assertEquals('ability_score', $strModifier->modifier_category);
    $this->assertEquals('+2', $strModifier->value);

    $chaModifier = $modifiers->where('ability_score_id', AbilityScore::where('code', 'CHA')->first()->id)->first();
    $this->assertNotNull($chaModifier);
    $this->assertEquals('+1', $chaModifier->value);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest::it_imports_ability_score_bonuses_as_modifiers
```

Expected: FAIL - modifiers count is 0

**Step 3: Update RaceImporter to import ability bonuses**

Edit `app/Services/Importers/RaceImporter.php`, update import() method:

```php
public function import(array $raceData): Race
{
    // ... existing code for race creation ...

    // Import traits (clear old ones first)
    $this->importTraits($race, $raceData['traits'] ?? []);

    // Import ability bonuses as modifiers
    $this->importAbilityBonuses($race, $raceData['ability_bonuses'] ?? []);

    // Import proficiencies (clear old ones first)
    $this->importProficiencies($race, $raceData['proficiencies']);

    return $race;
}

private function importAbilityBonuses(Race $race, array $bonusesData): void
{
    // Clear existing ability score modifiers for this race
    $race->modifiers()->where('modifier_category', 'ability_score')->delete();

    foreach ($bonusesData as $bonusData) {
        // Map ability code to ability_score_id
        $abilityCode = strtoupper($bonusData['ability']);
        $abilityScore = \App\Models\AbilityScore::where('code', $abilityCode)->first();

        if (!$abilityScore) {
            continue; // Skip if ability score not found
        }

        \App\Models\Modifier::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $abilityScore->id,
            'value' => $bonusData['value'],
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest
```

Expected: All importer tests pass

**Step 5: Commit**

```bash
git add app/Services/Importers/RaceImporter.php tests/Feature/Importers/RaceImporterTest.php
git commit -m "feat: import ability score bonuses as modifiers"
```

---

### Task 2.4: Update Race API to Include Modifiers

**Files:**
- Create: `app/Http/Resources/ModifierResource.php`
- Modify: `app/Http/Resources/RaceResource.php`
- Modify: `app/Http/Controllers/Api/RaceController.php`
- Modify: `tests/Feature/Api/RaceApiTest.php`

**Step 1: Create ModifierResource**

```bash
docker compose exec php php artisan make:resource ModifierResource
```

Edit `app/Http/Resources/ModifierResource.php`:

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
            'modifier_category' => $this->modifier_category,
            'ability_score' => $this->when($this->ability_score_id, function () {
                return [
                    'id' => $this->abilityScore->id,
                    'name' => $this->abilityScore->name,
                    'code' => $this->abilityScore->code,
                ];
            }),
            'value' => $this->value,
            'condition' => $this->condition,
        ];
    }
}
```

**Step 2: Update RaceResource**

Edit `app/Http/Resources/RaceResource.php`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'size' => new SizeResource($this->whenLoaded('size')),
        'speed' => $this->speed,
        'traits' => TraitResource::collection($this->whenLoaded('traits')),
        'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
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

**Step 3: Update RaceController**

Edit `app/Http/Controllers/Api/RaceController.php`:

```php
$race = Race::with(['size', 'source', 'parent', 'subraces', 'proficiencies.skill', 'traits', 'modifiers.abilityScore'])
```

**Step 4: Add API test**

Edit `tests/Feature/Api/RaceApiTest.php`:

```php
/** @test */
public function it_includes_modifiers_in_response()
{
    $race = Race::factory()->create(['name' => 'Dragonborn']);
    $str = AbilityScore::where('code', 'STR')->first();

    Modifier::create([
        'reference_type' => 'race',
        'reference_id' => $race->id,
        'modifier_category' => 'ability_score',
        'ability_score_id' => $str->id,
        'value' => '+2',
    ]);

    $response = $this->getJson("/api/v1/races/{$race->id}");

    $response->assertStatus(200);
    $modifiers = $response->json('data.modifiers');
    $this->assertCount(1, $modifiers);
    $this->assertEquals('ability_score', $modifiers[0]['modifier_category']);
    $this->assertEquals('+2', $modifiers[0]['value']);
}
```

**Step 5: Run tests**

```bash
docker compose exec php php artisan test --filter=RaceApiTest
```

Expected: All API tests pass

**Step 6: Commit**

```bash
git add app/Http/Resources/ModifierResource.php app/Http/Resources/RaceResource.php app/Http/Controllers/Api/RaceController.php tests/Feature/Api/RaceApiTest.php
git commit -m "feat: include modifiers in Race API responses"
```

---

## Phase 3: Random Tables

### Task 3.1: Create Random Tables Migrations and Models

**Files:**
- Create: `database/migrations/2025_11_18_HHMMSS_create_random_tables.php`
- Create: `app/Models/RandomTable.php`
- Create: `app/Models/RandomTableEntry.php`
- Modify: `app/Models/Race.php`

**Step 1: Create migration file**

```bash
docker compose exec php php artisan make:migration create_random_tables
```

**Step 2: Write migrations per design document**

Edit the generated migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Random tables
        Schema::create('random_tables', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->string('reference_type'); // 'background', 'class', 'race'
            $table->unsignedBigInteger('reference_id');

            // Table data
            $table->string('table_name'); // 'Personality Trait', 'Ideal', 'Size Modifier'
            $table->string('dice_type'); // 'd6', 'd8', 'd10', 'd100', '2d8'
            $table->text('description')->nullable(); // Optional context

            // Indexes
            $table->index(['reference_type', 'reference_id']);

            // NO timestamps
        });

        // Random table entries
        Schema::create('random_table_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('random_table_id');

            $table->string('roll_value'); // '1', '2', '01-10' (for d100 ranges)
            $table->text('result'); // The actual table entry text
            $table->integer('sort_order')->default(0);

            // Foreign key
            $table->foreign('random_table_id')
                  ->references('id')
                  ->on('random_tables')
                  ->onDelete('cascade');

            // Indexes
            $table->index('random_table_id');
            $table->index('sort_order');

            // NO timestamps
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('random_table_entries');
        Schema::dropIfExists('random_tables');
    }
};
```

**Step 3: Run migration**

```bash
docker compose exec php php artisan migrate
```

**Step 4: Create RandomTable model**

```bash
docker compose exec php php artisan make:model RandomTable
```

Edit `app/Models/RandomTable.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RandomTable extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'table_name',
        'dice_type',
        'description',
    ];

    protected $casts = [
        'reference_id' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Entries relationship
    public function entries(): HasMany
    {
        return $this->hasMany(RandomTableEntry::class)->orderBy('sort_order');
    }
}
```

**Step 5: Create RandomTableEntry model**

```bash
docker compose exec php php artisan make:model RandomTableEntry
```

Edit `app/Models/RandomTableEntry.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RandomTableEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'random_table_id',
        'roll_value',
        'result',
        'sort_order',
    ];

    protected $casts = [
        'random_table_id' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationship
    public function randomTable(): BelongsTo
    {
        return $this->belongsTo(RandomTable::class);
    }
}
```

**Step 6: Update Race model**

Edit `app/Models/Race.php`, add after modifiers() method:

```php
public function randomTables(): MorphMany
{
    return $this->morphMany(RandomTable::class, 'reference');
}
```

**Step 7: Create RandomTableModelTest**

Create `tests/Feature/Models/RandomTableModelTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Race;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RandomTableModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_random_table_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();

        $table = RandomTable::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'table_name' => 'Size Modifier',
            'dice_type' => '2d8',
        ]);

        $this->assertEquals($race->id, $table->reference->id);
        $this->assertInstanceOf(Race::class, $table->reference);
    }

    public function test_random_table_has_many_entries(): void
    {
        $race = Race::factory()->create();

        $table = RandomTable::create([
            'reference_type' => 'race',
            'reference_id' => $race->id,
            'table_name' => 'Size Modifier',
            'dice_type' => '2d8',
        ]);

        RandomTableEntry::create([
            'random_table_id' => $table->id,
            'roll_value' => '2',
            'result' => 'Minimum roll',
            'sort_order' => 1,
        ]);

        RandomTableEntry::create([
            'random_table_id' => $table->id,
            'roll_value' => '16',
            'result' => 'Maximum roll',
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $table->entries);
    }
}
```

**Step 8: Run tests**

```bash
docker compose exec php php artisan test --filter=RandomTableModelTest
```

Expected: 2 tests pass

**Step 9: Commit**

```bash
git add database/migrations/*create_random_tables.php app/Models/RandomTable.php app/Models/RandomTableEntry.php app/Models/Race.php tests/Feature/Models/RandomTableModelTest.php
git commit -m "feat: create random_tables and random_table_entries per design document"
```

---

### Task 3.2: Update RaceXmlParser to Extract Rolls from Traits

**Files:**
- Modify: `app/Services/Parsers/RaceXmlParser.php`
- Modify: `tests/Unit/Parsers/RaceXmlParserTest.php`

**Suggestion for <roll> tags:**

Looking at the XML structure, `<roll>` tags appear within `<trait>` elements and represent dice rolls for various purposes (size modifiers, damage scaling, etc.). I suggest:

1. **Store roll information with traits** - Add a `rolls` array to each trait containing roll metadata
2. **Create random_tables for rollable traits** - When a trait contains rolls that are meant for random generation (like Size Modifier), create a random_table
3. **Distinguish roll types**:
   - **Random generation rolls** (e.g., Size Modifier) → Create random_table with entries for min/max
   - **Scaling rolls** (e.g., Breath Weapon damage at different levels) → Store as structured data in trait description or separate scaling table

**Step 1: Write test for parsing rolls**

Edit `tests/Unit/Parsers/RaceXmlParserTest.php`:

```php
/** @test */
public function it_parses_rolls_from_traits()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Size</name>
      <text>Your size is Medium. To set your height randomly:
Size modifier = 2d8</text>
      <roll description="Size Modifier">2d8</roll>
      <roll description="Weight Modifier">2d6</roll>
    </trait>
  </race>
</compendium>
XML;

    $races = $this->parser->parse($xml);

    $sizeTrait = $races[0]['traits'][0];
    $this->assertEquals('Size', $sizeTrait['name']);
    $this->assertArrayHasKey('rolls', $sizeTrait);
    $this->assertCount(2, $sizeTrait['rolls']);

    $this->assertEquals('Size Modifier', $sizeTrait['rolls'][0]['description']);
    $this->assertEquals('2d8', $sizeTrait['rolls'][0]['formula']);

    $this->assertEquals('Weight Modifier', $sizeTrait['rolls'][1]['description']);
    $this->assertEquals('2d6', $sizeTrait['rolls'][1]['formula']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest::it_parses_rolls_from_traits
```

Expected: FAIL

**Step 3: Update parser to extract rolls**

Edit `app/Services/Parsers/RaceXmlParser.php`, update parseTraits():

```php
private function parseTraits(SimpleXMLElement $element): array
{
    $traits = [];
    $sortOrder = 0;

    foreach ($element->trait as $traitElement) {
        $category = isset($traitElement['category']) ? (string) $traitElement['category'] : null;
        $name = (string) $traitElement->name;
        $text = (string) $traitElement->text;

        // Parse rolls within this trait
        $rolls = [];
        foreach ($traitElement->roll as $rollElement) {
            $description = isset($rollElement['description']) ? (string) $rollElement['description'] : null;
            $level = isset($rollElement['level']) ? (int) $rollElement['level'] : null;
            $formula = (string) $rollElement;

            $rolls[] = [
                'description' => $description,
                'formula' => $formula,
                'level' => $level,
            ];
        }

        $traits[] = [
            'name' => $name,
            'category' => $category,
            'description' => trim($text),
            'rolls' => $rolls,
            'sort_order' => $sortOrder++,
        ];
    }

    return $traits;
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

Expected: All parser tests pass

**Step 5: Commit**

```bash
git add app/Services/Parsers/RaceXmlParser.php tests/Unit/Parsers/RaceXmlParserTest.php
git commit -m "feat: parse roll elements from trait XML"
```

---

### Task 3.3: Update RaceImporter to Create Random Tables from Rolls

**Files:**
- Modify: `app/Services/Importers/RaceImporter.php`
- Modify: `tests/Feature/Importers/RaceImporterTest.php`

**Step 1: Write test for random table import**

Edit `tests/Feature/Importers/RaceImporterTest.php`:

```php
/** @test */
public function it_imports_random_tables_from_trait_rolls()
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Size</name>
      <text>Your size is Medium.
Source: Player's Handbook (2014) p. 32</text>
      <roll description="Size Modifier">2d8</roll>
    </trait>
  </race>
</compendium>
XML;

    $this->importer->importFromXml($xml);

    $race = Race::where('name', 'Dragonborn')->first();
    $randomTables = $race->randomTables;

    $this->assertCount(1, $randomTables);

    $sizeTable = $randomTables->first();
    $this->assertEquals('Size Modifier', $sizeTable->table_name);
    $this->assertEquals('2d8', $sizeTable->dice_type);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest::it_imports_random_tables_from_trait_rolls
```

Expected: FAIL - random tables count is 0

**Step 3: Update RaceImporter to create random tables**

Edit `app/Services/Importers/RaceImporter.php`, update import() method:

```php
public function import(array $raceData): Race
{
    // ... existing code ...

    // Import traits (clear old ones first)
    $this->importTraits($race, $raceData['traits'] ?? []);

    // Import ability bonuses as modifiers
    $this->importAbilityBonuses($race, $raceData['ability_bonuses'] ?? []);

    // Import proficiencies (clear old ones first)
    $this->importProficiencies($race, $raceData['proficiencies']);

    // Import random tables from trait rolls
    $this->importRandomTablesFromTraits($race, $raceData['traits'] ?? []);

    return $race;
}

private function importRandomTablesFromTraits(Race $race, array $traitsData): void
{
    // Clear existing random tables for this race
    $race->randomTables()->delete();

    foreach ($traitsData as $traitData) {
        if (empty($traitData['rolls'])) {
            continue;
        }

        foreach ($traitData['rolls'] as $roll) {
            if (empty($roll['description']) || empty($roll['formula'])) {
                continue;
            }

            // Create a random table for this roll
            \App\Models\RandomTable::create([
                'reference_type' => 'race',
                'reference_id' => $race->id,
                'table_name' => $roll['description'],
                'dice_type' => $roll['formula'],
                'description' => "From trait: {$traitData['name']}",
            ]);

            // Note: We don't create random_table_entries here because the XML
            // doesn't provide the individual roll results. Entries would be
            // created manually or from a different data source.
        }
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=RaceImporterTest
```

Expected: All importer tests pass

**Step 5: Commit**

```bash
git add app/Services/Importers/RaceImporter.php tests/Feature/Importers/RaceImporterTest.php
git commit -m "feat: import random tables from trait rolls"
```

---

### Task 3.4: Update Race API to Include Random Tables

**Files:**
- Create: `app/Http/Resources/RandomTableResource.php`
- Modify: `app/Http/Resources/RaceResource.php`
- Modify: `app/Http/Controllers/Api/RaceController.php`

**Step 1: Create RandomTableResource**

```bash
docker compose exec php php artisan make:resource RandomTableResource
```

Edit `app/Http/Resources/RandomTableResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RandomTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table_name' => $this->table_name,
            'dice_type' => $this->dice_type,
            'description' => $this->description,
            'entries' => $this->whenLoaded('entries', function () {
                return $this->entries->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'roll_value' => $entry->roll_value,
                        'result' => $entry->result,
                        'sort_order' => $entry->sort_order,
                    ];
                });
            }),
        ];
    }
}
```

**Step 2: Update RaceResource**

Edit `app/Http/Resources/RaceResource.php`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'size' => new SizeResource($this->whenLoaded('size')),
        'speed' => $this->speed,
        'traits' => TraitResource::collection($this->whenLoaded('traits')),
        'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
        'random_tables' => RandomTableResource::collection($this->whenLoaded('randomTables')),
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

**Step 3: Update RaceController**

Edit `app/Http/Controllers/Api/RaceController.php`:

```php
$race = Race::with([
    'size',
    'source',
    'parent',
    'subraces',
    'proficiencies.skill',
    'traits',
    'modifiers.abilityScore',
    'randomTables.entries'
])
```

**Step 4: Run tests**

```bash
docker compose exec php php artisan test
```

Expected: All tests pass

**Step 5: Commit**

```bash
git add app/Http/Resources/RandomTableResource.php app/Http/Resources/RaceResource.php app/Http/Controllers/Api/RaceController.php
git commit -m "feat: include random tables in Race API responses"
```

---

## Phase 4: Final Data Import and Verification

### Task 4.1: Re-import All Races with Complete Data

**Files:**
- None (data operation)

**Step 1: Clear existing data**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\App\Models\RandomTable::query()->delete();
\App\Models\Modifier::query()->delete();
\App\Models\Trait::query()->delete();
\App\Models\Proficiency::query()->delete();
\App\Models\Race::query()->delete();
echo \"Cleared all races and related data\n\";
"
```

**Step 2: Re-import races**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$importer = new \App\Services\Importers\RaceImporter();
\$count = \$importer->importFromFile('import-files/races-phb.xml');
echo \"Imported \$count races\n\";
"
```

**Step 3: Verify database state**

```bash
docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== FINAL DATABASE STATE ===\n\";
echo \"Base races: \" . \App\Models\Race::whereNull('parent_race_id')->count() . \"\n\";
echo \"Subraces: \" . \App\Models\Race::whereNotNull('parent_race_id')->count() . \"\n\";
echo \"Total races: \" . \App\Models\Race::count() . \"\n\";
echo \"\n\";
echo \"Total traits: \" . \App\Models\Trait::count() . \"\n\";
echo \"Total modifiers: \" . \App\Models\Modifier::count() . \"\n\";
echo \"Total proficiencies: \" . \App\Models\Proficiency::count() . \"\n\";
echo \"Total random tables: \" . \App\Models\RandomTable::count() . \"\n\";
"
```

**Step 4: Test API**

```bash
curl -s "http://localhost:8080/api/v1/races/1" | python3 -m json.tool | head -80
```

Expected: Shows race with traits, modifiers, proficiencies, random_tables

**Step 5: Run all tests**

```bash
docker compose exec php php artisan test
```

Expected: All tests pass (190+ tests)

**Step 6: Commit**

```bash
git commit -m "data: re-import races with traits, modifiers, and random tables"
```

---

## Summary

This plan implements:

1. **Phase 1: Traits Table**
   - Replaces races.description with polymorphic traits table
   - Traits have categories (description, species, subspecies, feature)
   - Traits maintain sort_order for proper display

2. **Phase 2: Modifiers Table**
   - Parses <ability> tag (e.g., "Str +2, Cha +1")
   - Creates modifiers with ability_score lookups
   - Supports future expansion for damage resistance from <resist> tag

3. **Phase 3: Random Tables**
   - Extracts <roll> elements from traits
   - Creates random_tables for dice rolls
   - Links tables to races via polymorphic relationship
   - Ready for random_table_entries population

4. **Phase 4: Data Import**
   - Re-imports all races with complete structured data
   - Verifies counts and API responses

**Design Document Adherence:** 100%
- Uses exact table structures from design document
- Implements polymorphic relationships as specified
- Follows naming conventions and indexes

**Total New Tables:** 5 (traits, modifiers, random_tables, random_table_entries, and updates to existing tables)
**Total New Models:** 4 (Trait, Modifier, RandomTable, RandomTableEntry)
