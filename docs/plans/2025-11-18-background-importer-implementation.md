# Background Importer Implementation Plan

**Date:** 2025-11-18
**Status:** Ready for Implementation
**Estimated Time:** 3-4 hours
**Approach:** TDD with XML Reconstruction Tests

---

## Overview

Implement a complete Background importer for D&D 5e, following the established Race/Item importer patterns with **full polymorphic table usage**. Backgrounds will store only `name` in their core table, using polymorphic relationships for all other data (traits, proficiencies, sources, random tables).

**Key Decision:** Simplify `backgrounds` table to match Race pattern (minimal core fields + polymorphic relationships).

---

## Prerequisites

**Environment:**
- Sail container running: `docker compose up -d`
- Database migrations current: `docker compose exec php php artisan migrate:status`
- Tests passing: `docker compose exec php php artisan test`

**Data Available:**
- 18 backgrounds in `import-files/backgrounds-phb.xml`
- Polymorphic tables exist: `character_traits`, `proficiencies`, `entity_sources`, `random_tables`
- Source seeder populated with PHB

---

## Phase 1: Scaffolding & Environment

### Task 1.1: Confirm Runner and Branch
**Time:** 5 minutes

```bash
# Verify Sail is running
docker compose ps

# Confirm current branch
git branch --show-current  # Should be: schema-redesign

# Optional: Create feature branch
git checkout -b feature/background-importer

# Verify database connection
docker compose exec php php artisan tinker --execute="
echo 'DB: ' . config('database.default') . PHP_EOL;
echo 'Backgrounds table: ' . (Schema::hasTable('backgrounds') ? 'EXISTS' : 'MISSING') . PHP_EOL;
echo 'Character traits table: ' . (Schema::hasTable('character_traits') ? 'EXISTS' : 'MISSING') . PHP_EOL;
"
```

**Success Criteria:**
- ✅ Sail containers running
- ✅ `backgrounds` table exists
- ✅ Polymorphic tables exist

---

## Phase 2: Data Model

### Task 2.1: Simplify Backgrounds Table (Migration)
**Time:** 15 minutes
**TDD:** Write test first, verify failure, then implement

**Step 1: Write migration test**

Create `tests/Feature/Migrations/BackgroundsTableSimplifiedTest.php`:

```php
<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackgroundsTableSimplifiedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function backgrounds_table_has_minimal_schema()
    {
        $columns = Schema::getColumnListing('backgrounds');

        // Only these columns should exist
        $expectedColumns = ['id', 'name'];

        $this->assertEquals($expectedColumns, array_values(array_intersect($columns, $expectedColumns)));

        // These columns should NOT exist
        $removedColumns = [
            'description',
            'skill_proficiencies',
            'tool_proficiencies',
            'languages',
            'equipment',
            'feature_name',
            'feature_description'
        ];

        foreach ($removedColumns as $col) {
            $this->assertNotContains($col, $columns, "Column '$col' should be removed");
        }
    }

    #[Test]
    public function backgrounds_table_has_unique_name_constraint()
    {
        $indexes = Schema::getIndexes('backgrounds');

        $uniqueIndex = collect($indexes)->first(fn($idx) => $idx['columns'] === ['name'] && $idx['unique']);

        $this->assertNotNull($uniqueIndex, 'Name column should have unique constraint');
    }

    #[Test]
    public function backgrounds_table_does_not_have_timestamps()
    {
        $columns = Schema::getColumnListing('backgrounds');

        $this->assertNotContains('created_at', $columns);
        $this->assertNotContains('updated_at', $columns);
    }
}
```

**Step 2: Run test (should fail)**

```bash
docker compose exec php php artisan test --filter=BackgroundsTableSimplifiedTest
```

**Expected:** Tests fail because columns still exist

**Step 3: Create migration**

```bash
docker compose exec php php artisan make:migration simplify_backgrounds_table
```

Create `database/migrations/2025_11_18_XXXXXX_simplify_backgrounds_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backgrounds', function (Blueprint $table) {
            // Drop obsolete columns (data moves to polymorphic tables)
            $table->dropColumn([
                'description',
                'skill_proficiencies',
                'tool_proficiencies',
                'languages',
                'equipment',
                'feature_name',
                'feature_description',
            ]);

            // Add unique constraint on name
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('backgrounds', function (Blueprint $table) {
            // Restore columns for rollback
            $table->text('description')->nullable();
            $table->text('skill_proficiencies')->nullable();
            $table->text('tool_proficiencies')->nullable();
            $table->text('languages')->nullable();
            $table->text('equipment')->nullable();
            $table->text('feature_name')->nullable();
            $table->text('feature_description')->nullable();

            // Drop unique constraint
            $table->dropUnique(['name']);
        });
    }
};
```

**Step 4: Run migration**

```bash
docker compose exec php php artisan migrate
```

**Step 5: Verify tests pass**

```bash
docker compose exec php php artisan test --filter=BackgroundsTableSimplifiedTest
```

**Step 6: Commit**

```bash
git add database/migrations tests/Feature/Migrations/BackgroundsTableSimplifiedTest.php
git commit -m "refactor: simplify backgrounds table to use polymorphic relationships

- Drop description, proficiencies, equipment columns
- Add unique constraint on name
- Follows Race model pattern (minimal core fields)
- Data will use character_traits and proficiencies tables"
```

---

### Task 2.2: Create Background Model
**Time:** 15 minutes

**Step 1: Create model**

Create `app/Models/Background.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Background extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    // Polymorphic relationships
    public function traits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference', 'reference_type', 'reference_id');
    }

    // Scopes for API filtering
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('name', 'LIKE', "%{$searchTerm}%")
            ->orWhereHas('traits', fn($q) => $q->where('text', 'LIKE', "%{$searchTerm}%"));
    }
}
```

**Step 2: Write model relationship test**

Create `tests/Feature/Models/BackgroundModelTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\Proficiency;
use App\Models\EntitySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackgroundModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function background_has_traits_relationship()
    {
        $background = Background::create(['name' => 'Test Background']);

        CharacterTrait::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'name' => 'Description',
            'text' => 'Test description',
        ]);

        $this->assertCount(1, $background->fresh()->traits);
        $this->assertEquals('Description', $background->traits->first()->name);
    }

    #[Test]
    public function background_has_proficiencies_relationship()
    {
        $background = Background::create(['name' => 'Test Background']);

        Proficiency::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'proficiency_type' => 'skill',
            'name' => 'Insight',
        ]);

        $this->assertCount(1, $background->fresh()->proficiencies);
        $this->assertEquals('Insight', $background->proficiencies->first()->name);
    }

    #[Test]
    public function background_has_sources_relationship()
    {
        $background = Background::create(['name' => 'Test Background']);

        EntitySource::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'source_id' => 1, // PHB (seeded)
            'pages' => '127',
        ]);

        $this->assertCount(1, $background->fresh()->sources);
        $this->assertEquals('127', $background->sources->first()->pages);
    }
}
```

**Step 3: Run tests**

```bash
docker compose exec php php artisan test --filter=BackgroundModelTest
```

**Step 4: Commit**

```bash
git add app/Models/Background.php tests/Feature/Models/BackgroundModelTest.php
git commit -m "feat: create Background model with polymorphic relationships

- HasFactory trait for testing
- Polymorphic traits, proficiencies, sources
- Search scope for name and trait text
- No timestamps (matches schema)"
```

---

### Task 2.3: Create Background Factory
**Time:** 20 minutes

**Step 1: Create factory**

```bash
docker compose exec php php artisan make:factory BackgroundFactory
```

Create `database/factories/BackgroundFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntitySource;
use App\Models\Proficiency;
use Illuminate\Database\Eloquent\Factories\Factory;

class BackgroundFactory extends Factory
{
    protected $model = Background::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /**
     * Background with description trait
     */
    public function withDescription(): static
    {
        return $this->afterCreating(function (Background $background) {
            CharacterTrait::factory()
                ->forEntity(Background::class, $background->id)
                ->create([
                    'name' => 'Description',
                    'text' => fake()->paragraphs(3, true),
                    'category' => null,
                ]);
        });
    }

    /**
     * Background with feature trait
     */
    public function withFeature(): static
    {
        return $this->afterCreating(function (Background $background) {
            CharacterTrait::factory()
                ->forEntity(Background::class, $background->id)
                ->create([
                    'name' => 'Feature: ' . fake()->words(2, true),
                    'text' => fake()->paragraphs(2, true),
                    'category' => 'feature',
                ]);
        });
    }

    /**
     * Background with suggested characteristics trait
     */
    public function withCharacteristics(): static
    {
        return $this->afterCreating(function (Background $background) {
            CharacterTrait::factory()
                ->forEntity(Background::class, $background->id)
                ->create([
                    'name' => 'Suggested Characteristics',
                    'text' => fake()->paragraphs(2, true),
                    'category' => 'characteristics',
                ]);
        });
    }

    /**
     * Background with all standard traits
     */
    public function withTraits(): static
    {
        return $this->withDescription()
            ->withFeature()
            ->withCharacteristics();
    }

    /**
     * Background with skill proficiencies
     */
    public function withProficiencies(): static
    {
        return $this->afterCreating(function (Background $background) {
            // 2 skill proficiencies
            Proficiency::factory()
                ->count(2)
                ->forEntity(Background::class, $background->id)
                ->skill() // Existing factory state
                ->create();

            // Language proficiency
            Proficiency::factory()
                ->forEntity(Background::class, $background->id)
                ->create([
                    'proficiency_type' => 'language',
                    'name' => 'Two of your choice',
                    'skill_id' => null,
                ]);
        });
    }

    /**
     * Background with source attribution
     */
    public function withSource(string $sourceCode = 'PHB', string $pages = '127'): static
    {
        return $this->afterCreating(function (Background $background) use ($sourceCode, $pages) {
            EntitySource::factory()
                ->forEntity(Background::class, $background->id)
                ->fromSource($sourceCode)
                ->create(['pages' => $pages]);
        });
    }

    /**
     * Complete background (all traits, proficiencies, source)
     */
    public function complete(): static
    {
        return $this->withTraits()
            ->withProficiencies()
            ->withSource();
    }
}
```

**Step 2: Write factory test**

Create `tests/Unit/Factories/BackgroundFactoryTest.php`:

```php
<?php

namespace Tests\Unit\Factories;

use App\Models\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackgroundFactoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_background_with_valid_data()
    {
        $background = Background::factory()->create(['name' => 'Acolyte']);

        $this->assertDatabaseHas('backgrounds', [
            'name' => 'Acolyte',
        ]);
    }

    #[Test]
    public function it_creates_background_with_traits_state()
    {
        $background = Background::factory()->withTraits()->create();

        $this->assertCount(3, $background->traits);
        $this->assertTrue($background->traits->contains('name', 'Description'));
        $this->assertTrue($background->traits->contains('category', 'feature'));
        $this->assertTrue($background->traits->contains('category', 'characteristics'));
    }

    #[Test]
    public function it_creates_background_with_proficiencies_state()
    {
        $background = Background::factory()->withProficiencies()->create();

        $this->assertCount(3, $background->proficiencies); // 2 skills + 1 language
        $this->assertEquals(2, $background->proficiencies->where('proficiency_type', 'skill')->count());
        $this->assertEquals(1, $background->proficiencies->where('proficiency_type', 'language')->count());
    }

    #[Test]
    public function it_creates_complete_background()
    {
        $background = Background::factory()->complete()->create();

        $this->assertCount(3, $background->traits);
        $this->assertCount(3, $background->proficiencies);
        $this->assertCount(1, $background->sources);
    }
}
```

**Step 3: Run tests**

```bash
docker compose exec php php artisan test --filter=BackgroundFactoryTest
```

**Step 4: Commit**

```bash
git add database/factories/BackgroundFactory.php tests/Unit/Factories/BackgroundFactoryTest.php
git commit -m "feat: create BackgroundFactory with state methods

- withTraits(): adds description, feature, characteristics
- withProficiencies(): adds 2 skills + 1 language
- withSource(): adds PHB source attribution
- complete(): combines all states
- Follows established polymorphic factory pattern"
```

---

## Phase 3: Services & Parsers

### Task 3.1: Create BackgroundXmlParser (TDD)
**Time:** 45 minutes

**Step 1: Write parser unit tests**

Create `tests/Unit/Parsers/BackgroundXmlParserTest.php`:

```php
<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\BackgroundXmlParser;
use Tests\TestCase;

class BackgroundXmlParserTest extends TestCase
{
    private BackgroundXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BackgroundXmlParser();
    }

    #[Test]
    public function it_parses_basic_background_data()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait>
      <name>Description</name>
      <text>You have spent your life in service.

Source: Player's Handbook (2014) p. 127</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertCount(1, $backgrounds);
        $this->assertEquals('Acolyte', $backgrounds[0]['name']);
    }

    #[Test]
    public function it_parses_proficiencies_from_comma_separated_list()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight, Religion</proficiency>
    <trait><name>Desc</name><text>Test</text></trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertCount(2, $backgrounds[0]['proficiencies']);
        $this->assertEquals('Insight', $backgrounds[0]['proficiencies'][0]['name']);
        $this->assertEquals('skill', $backgrounds[0]['proficiencies'][0]['proficiency_type']);
        $this->assertEquals('Religion', $backgrounds[0]['proficiencies'][1]['name']);
    }

    #[Test]
    public function it_parses_multiple_traits()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Deception</proficiency>
    <trait>
      <name>Description</name>
      <text>First trait</text>
    </trait>
    <trait>
      <name>Feature: Test Feature</name>
      <text>Second trait</text>
    </trait>
    <trait>
      <name>Suggested Characteristics</name>
      <text>Third trait</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertCount(3, $backgrounds[0]['traits']);
        $this->assertEquals('Description', $backgrounds[0]['traits'][0]['name']);
        $this->assertNull($backgrounds[0]['traits'][0]['category']);
        $this->assertEquals('feature', $backgrounds[0]['traits'][1]['category']);
        $this->assertEquals('characteristics', $backgrounds[0]['traits'][2]['category']);
    }

    #[Test]
    public function it_extracts_source_from_trait_text()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>Some text here.

Source: Player's Handbook (2014) p. 127</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertCount(1, $backgrounds[0]['sources']);
        $this->assertEquals('PHB', $backgrounds[0]['sources'][0]['code']);
        $this->assertEquals('127', $backgrounds[0]['sources'][0]['pages']);
    }

    #[Test]
    public function it_handles_tool_proficiencies()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>One type of gaming set, thieves' tools</proficiency>
    <trait><name>Desc</name><text>Test</text></trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $profs = $backgrounds[0]['proficiencies'];
        $this->assertCount(2, $profs);
        $this->assertEquals('tool', $profs[0]['proficiency_type']);
        $this->assertEquals('tool', $profs[1]['proficiency_type']);
    }

    #[Test]
    public function it_parses_roll_elements_from_characteristics_trait()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Suggested Characteristics</name>
      <text>Some characteristics text</text>
      <roll description="Personality Trait">1d8</roll>
      <roll description="Ideal">1d6</roll>
      <roll description="Bond">1d6</roll>
      <roll description="Flaw">1d6</roll>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $charTrait = collect($backgrounds[0]['traits'])->firstWhere('category', 'characteristics');
        $this->assertCount(4, $charTrait['rolls']);
        $this->assertEquals('Personality Trait', $charTrait['rolls'][0]['description']);
        $this->assertEquals('1d8', $charTrait['rolls'][0]['formula']);
    }
}
```

**Step 2: Run tests (should fail)**

```bash
docker compose exec php php artisan test --filter=BackgroundXmlParserTest
```

**Expected:** Class not found error

**Step 3: Create parser service**

Create `app/Services/Parsers/BackgroundXmlParser.php`:

```php
<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class BackgroundXmlParser
{
    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $backgrounds = [];

        foreach ($xml->background as $bg) {
            $backgrounds[] = [
                'name' => (string) $bg->name,
                'proficiencies' => $this->parseProficiencies((string) $bg->proficiency),
                'traits' => $this->parseTraits($bg->trait),
                'sources' => $this->extractSources($bg->trait[0]->text ?? ''),
            ];
        }

        return $backgrounds;
    }

    private function parseProficiencies(string $profText): array
    {
        if (empty(trim($profText))) {
            return [];
        }

        $profs = [];
        $parts = array_map('trim', explode(',', $profText));

        foreach ($parts as $name) {
            $profs[] = [
                'name' => $name,
                'proficiency_type' => $this->inferProficiencyType($name),
                'skill_id' => $this->lookupSkillId($name),
            ];
        }

        return $profs;
    }

    private function inferProficiencyType(string $name): string
    {
        $nameLower = strtolower($name);

        // Check for tool indicators
        if (str_contains($nameLower, 'kit') ||
            str_contains($nameLower, 'tools') ||
            str_contains($nameLower, 'gaming set') ||
            str_contains($nameLower, 'instrument')) {
            return 'tool';
        }

        // Check for language indicators
        if (str_contains($nameLower, 'language')) {
            return 'language';
        }

        // Default to skill (will be validated via skill_id lookup)
        return 'skill';
    }

    private function lookupSkillId(string $name): ?int
    {
        // Query skills table for matching skill
        $skill = \App\Models\Skill::where('name', $name)->first();
        return $skill?->id;
    }

    private function parseTraits($traits): array
    {
        $parsed = [];

        foreach ($traits as $trait) {
            $name = (string) $trait->name;
            $text = (string) $trait->text;

            $parsed[] = [
                'name' => $name,
                'text' => $this->cleanTraitText($text),
                'category' => $this->inferCategory($name),
                'rolls' => $this->parseRolls($trait->roll),
            ];
        }

        return $parsed;
    }

    private function cleanTraitText(string $text): string
    {
        // Remove source citation (will be stored separately)
        $text = preg_replace('/\n\nSource:.*$/s', '', $text);

        // Trim whitespace
        return trim($text);
    }

    private function inferCategory(string $name): ?string
    {
        if ($name === 'Description') {
            return null;
        }

        if (str_starts_with($name, 'Feature:')) {
            return 'feature';
        }

        if ($name === 'Suggested Characteristics') {
            return 'characteristics';
        }

        // Other flavor traits (Favorite Schemes, etc.)
        return 'flavor';
    }

    private function parseRolls($rolls): array
    {
        $parsed = [];

        foreach ($rolls as $roll) {
            $parsed[] = [
                'description' => (string) ($roll['description'] ?? ''),
                'formula' => (string) $roll,
            ];
        }

        return $parsed;
    }

    private function extractSources(string $text): array
    {
        // Match "Source: Player's Handbook (2014) p. 127"
        if (preg_match('/Source:\s*(.+?)\s*\((\d{4})\)\s*p\.\s*([\d,\s-]+)/i', $text, $matches)) {
            $sourceName = $matches[1];
            $pages = trim($matches[3]);

            // Map source name to code
            $sourceCode = $this->mapSourceNameToCode($sourceName);

            return [
                [
                    'code' => $sourceCode,
                    'pages' => $pages,
                ],
            ];
        }

        // Fallback to PHB if no source found
        return [['code' => 'PHB', 'pages' => '']];
    }

    private function mapSourceNameToCode(string $name): string
    {
        $mappings = [
            "Player's Handbook" => 'PHB',
            'Dungeon Master\'s Guide' => 'DMG',
            'Monster Manual' => 'MM',
            'Xanathar\'s Guide to Everything' => 'XGE',
            'Tasha\'s Cauldron of Everything' => 'TCE',
            'Volo\'s Guide to Monsters' => 'VGTM',
        ];

        return $mappings[$name] ?? 'PHB';
    }
}
```

**Step 4: Run tests (should pass)**

```bash
docker compose exec php php artisan test --filter=BackgroundXmlParserTest
```

**Step 5: Commit**

```bash
git add app/Services/Parsers/BackgroundXmlParser.php tests/Unit/Parsers/BackgroundXmlParserTest.php
git commit -m "feat: create BackgroundXmlParser with unit tests

- Parse name, proficiencies, traits from XML
- Infer proficiency type (skill/tool/language)
- Extract source citations from trait text
- Parse roll elements from characteristics trait
- Clean trait text (remove source lines)
- Categorize traits (description/feature/characteristics/flavor)"
```

---

### Task 3.2: Create BackgroundImporter (TDD with Reconstruction Tests)
**Time:** 60 minutes

**Step 1: Write XML reconstruction test**

Create `tests/Feature/Importers/BackgroundXmlReconstructionTest.php`:

```php
<?php

namespace Tests\Feature\Importers;

use App\Models\Background;
use App\Services\Importers\BackgroundImporter;
use App\Services\Parsers\BackgroundXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackgroundXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    private BackgroundXmlParser $parser;
    private BackgroundImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BackgroundXmlParser();
        $this->importer = new BackgroundImporter();
    }

    #[Test]
    public function it_reconstructs_simple_background()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait>
      <name>Description</name>
      <text>You have spent your life in the service of a temple.

Source: Player's Handbook (2014) p. 127</text>
    </trait>
    <trait>
      <name>Feature: Shelter of the Faithful</name>
      <text>You command the respect of those who share your faith.</text>
    </trait>
  </background>
</compendium>
XML;

        // Act: Parse → Import → Reload
        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['traits', 'proficiencies', 'sources.source']);

        // Assert: Core data
        $this->assertEquals('Acolyte', $background->name);

        // Assert: Proficiencies
        $this->assertCount(2, $background->proficiencies);
        $this->assertTrue($background->proficiencies->contains('name', 'Insight'));
        $this->assertTrue($background->proficiencies->contains('name', 'Religion'));

        // Assert: Traits
        $this->assertCount(2, $background->traits);

        $descTrait = $background->traits->where('name', 'Description')->first();
        $this->assertNotNull($descTrait);
        $this->assertStringContainsString('temple', $descTrait->text);

        $featureTrait = $background->traits->where('category', 'feature')->first();
        $this->assertEquals('Feature: Shelter of the Faithful', $featureTrait->name);

        // Assert: Sources
        $this->assertCount(1, $background->sources);
        $this->assertEquals('PHB', $background->sources->first()->source->code);
        $this->assertEquals('127', $background->sources->first()->pages);
    }

    #[Test]
    public function it_reconstructs_background_with_tool_proficiencies()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Criminal</name>
    <proficiency>Deception, Stealth</proficiency>
    <trait>
      <name>Description</name>
      <text>You are an experienced criminal.

• Tool Proficiencies: One type of gaming set, thieves' tools

Source: Player's Handbook (2014) p. 129</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['proficiencies']);

        // Should have 2 skill proficiencies
        $skills = $background->proficiencies->where('proficiency_type', 'skill');
        $this->assertCount(2, $skills);

        // Note: Tool proficiencies are in trait text, not <proficiency> tag
        // This is intentional - XML structure varies
    }

    #[Test]
    public function it_reconstructs_background_with_characteristics_and_random_tables()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait>
      <name>Description</name>
      <text>Test description

Source: Player's Handbook (2014) p. 127</text>
    </trait>
    <trait>
      <name>Suggested Characteristics</name>
      <text>Acolytes are shaped by their experiences.

d8 | Personality Trait
1 | I idolize a hero of my faith
2 | I can find common ground
3 | I see omens everywhere

d6 | Ideal
1 | Tradition
2 | Charity

d6 | Bond
1 | I would die for a relic
2 | I seek revenge

d6 | Flaw
1 | I judge harshly
2 | I trust too much</text>
      <roll description="Personality Trait">1d8</roll>
      <roll description="Ideal">1d6</roll>
      <roll description="Bond">1d6</roll>
      <roll description="Flaw">1d6</roll>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['traits.randomTables.entries']);

        // Assert: Characteristics trait exists
        $charTrait = $background->traits->where('category', 'characteristics')->first();
        $this->assertNotNull($charTrait);

        // Assert: 4 random tables extracted
        $this->assertCount(4, $charTrait->randomTables);

        $tables = $charTrait->randomTables;
        $this->assertTrue($tables->contains('table_name', 'Personality Trait'));
        $this->assertTrue($tables->contains('table_name', 'Ideal'));
        $this->assertTrue($tables->contains('table_name', 'Bond'));
        $this->assertTrue($tables->contains('table_name', 'Flaw'));

        // Assert: Dice types correct
        $personalityTable = $tables->firstWhere('table_name', 'Personality Trait');
        $this->assertEquals('1d8', $personalityTable->dice_type);

        $idealTable = $tables->firstWhere('table_name', 'Ideal');
        $this->assertEquals('1d6', $idealTable->dice_type);

        // Assert: Entries parsed
        $this->assertGreaterThan(0, $personalityTable->entries->count());
    }

    #[Test]
    public function it_handles_backgrounds_without_feature_trait()
    {
        $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test Background</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>Just a description.

Source: Player's Handbook (2014) p. 100</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['traits']);

        $this->assertCount(1, $background->traits);
        $this->assertNull($background->traits->where('category', 'feature')->first());
    }

    #[Test]
    public function it_updates_existing_background_on_reimport()
    {
        // First import
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight</proficiency>
    <trait><name>Description</name><text>Old text</text></trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);
        $firstImport = $this->importer->import($backgrounds[0]);
        $firstId = $firstImport->id;

        // Second import with updated data
        $xml2 = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait><name>Description</name><text>New text</text></trait>
  </background>
</compendium>
XML;

        $backgrounds2 = $this->parser->parse($xml2);
        $secondImport = $this->importer->import($backgrounds2[0]);

        // Assert: Same ID (updated, not created)
        $this->assertEquals($firstId, $secondImport->id);

        // Assert: Data updated
        $this->assertCount(2, $secondImport->proficiencies);
        $this->assertStringContainsString('New text', $secondImport->traits->first()->text);
    }
}
```

**Step 2: Run tests (should fail)**

```bash
docker compose exec php php artisan test --filter=BackgroundXmlReconstructionTest
```

**Expected:** BackgroundImporter class not found

**Step 3: Create importer service**

Create `app/Services/Importers/BackgroundImporter.php`:

```php
<?php

namespace App\Services\Importers;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\Source;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;
use Illuminate\Support\Facades\DB;

class BackgroundImporter
{
    public function import(array $data): Background
    {
        return DB::transaction(function () use ($data) {
            // 1. Upsert background by name
            $background = Background::updateOrCreate(
                ['name' => $data['name']],
                []
            );

            // 2. Clear existing polymorphic relationships
            $background->traits()->delete();
            $background->proficiencies()->delete();
            $background->sources()->delete();

            // 3. Import traits
            foreach ($data['traits'] as $traitData) {
                $trait = $background->traits()->create([
                    'name' => $traitData['name'],
                    'text' => $traitData['text'],
                    'category' => $traitData['category'],
                ]);

                // 4. Import random tables for characteristics trait
                if ($traitData['category'] === 'characteristics' && !empty($traitData['rolls'])) {
                    $this->importRandomTables($trait, $traitData['text']);
                }
            }

            // 5. Import proficiencies
            foreach ($data['proficiencies'] as $profData) {
                $background->proficiencies()->create($profData);
            }

            // 6. Import sources
            foreach ($data['sources'] as $sourceData) {
                $source = Source::where('code', $sourceData['code'])->first();

                if ($source) {
                    $background->sources()->create([
                        'source_id' => $source->id,
                        'pages' => $sourceData['pages'],
                    ]);
                }
            }

            return $background;
        });
    }

    private function importRandomTables(CharacterTrait $trait, string $text): void
    {
        // Use existing ItemTableDetector to find tables
        $detector = new ItemTableDetector();
        $tables = $detector->detectTables($text);

        if (empty($tables)) {
            return;
        }

        foreach ($tables as $tableData) {
            $parser = new ItemTableParser();
            $parsed = $parser->parse($tableData['text']);

            $table = $trait->randomTables()->create([
                'table_name' => $parsed['table_name'],
                'dice_type' => $parsed['dice_type'],
            ]);

            foreach ($parsed['rows'] as $index => $row) {
                $table->entries()->create([
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

**Step 4: Run tests (should pass)**

```bash
docker compose exec php php artisan test --filter=BackgroundXmlReconstructionTest
```

**Step 5: Commit**

```bash
git add app/Services/Importers/BackgroundImporter.php tests/Feature/Importers/BackgroundXmlReconstructionTest.php
git commit -m "feat: create BackgroundImporter with XML reconstruction tests

- Upsert backgrounds by name
- Import traits to character_traits table
- Import proficiencies to proficiencies table
- Import sources via entity_sources
- Extract random tables from characteristics trait
- Reuse ItemTableDetector/Parser for table extraction
- Transaction safety per background
- 5 reconstruction tests verify completeness"
```

---

### Task 3.3: Create Import Command
**Time:** 20 minutes

**Step 1: Create artisan command**

```bash
docker compose exec php php artisan make:command ImportBackgrounds
```

Create `app/Console/Commands/ImportBackgrounds.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Importers\BackgroundImporter;
use App\Services\Parsers\BackgroundXmlParser;
use Illuminate\Console\Command;

class ImportBackgrounds extends Command
{
    protected $signature = 'import:backgrounds {file : Path to XML file}';

    protected $description = 'Import D&D backgrounds from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        // Validate file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        // Read XML content
        $this->info("Reading XML file: {$filePath}");
        $xmlContent = file_get_contents($filePath);

        try {
            // Parse XML
            $parser = new BackgroundXmlParser();
            $backgrounds = $parser->parse($xmlContent);
            $this->info("Parsed " . count($backgrounds) . " backgrounds from XML");

            // Import each background
            $importer = new BackgroundImporter();
            $importedCount = 0;
            $updatedCount = 0;

            $progressBar = $this->output->createProgressBar(count($backgrounds));
            $progressBar->start();

            foreach ($backgrounds as $backgroundData) {
                $existing = \App\Models\Background::where('name', $backgroundData['name'])->first();

                $background = $importer->import($backgroundData);

                if ($existing) {
                    $updatedCount++;
                } else {
                    $importedCount++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Report results
            $this->info("✓ Import complete!");
            $this->table(
                ['Status', 'Count'],
                [
                    ['Created', $importedCount],
                    ['Updated', $updatedCount],
                    ['Total', $importedCount + $updatedCount],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
```

**Step 2: Write command test**

Create `tests/Feature/Importers/BackgroundImporterTest.php`:

```php
<?php

namespace Tests\Feature\Importers;

use App\Models\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackgroundImporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function import_backgrounds_command_succeeds()
    {
        $exitCode = $this->artisan('import:backgrounds', [
            'file' => base_path('import-files/backgrounds-phb.xml'),
        ]);

        $this->assertEquals(0, $exitCode);

        // Verify backgrounds imported
        $this->assertGreaterThan(0, Background::count());

        // Verify Acolyte background exists with relationships
        $acolyte = Background::with(['traits', 'proficiencies', 'sources'])
            ->where('name', 'Acolyte')
            ->first();

        $this->assertNotNull($acolyte);
        $this->assertGreaterThan(0, $acolyte->traits->count());
        $this->assertGreaterThan(0, $acolyte->proficiencies->count());
        $this->assertGreaterThan(0, $acolyte->sources->count());
    }

    #[Test]
    public function import_backgrounds_command_handles_missing_file()
    {
        $exitCode = $this->artisan('import:backgrounds', [
            'file' => 'nonexistent-file.xml',
        ]);

        $this->assertEquals(1, $exitCode);
    }
}
```

**Step 3: Run tests**

```bash
docker compose exec php php artisan test --filter=BackgroundImporterTest
```

**Step 4: Test command manually**

```bash
docker compose exec php php artisan import:backgrounds import-files/backgrounds-phb.xml
```

**Expected output:**
```
Reading XML file: import-files/backgrounds-phb.xml
Parsed 18 backgrounds from XML
18/18 [============================] 100%

✓ Import complete!
+----------+-------+
| Status   | Count |
+----------+-------+
| Created  | 18    |
| Updated  | 0     |
| Total    | 18    |
+----------+-------+
```

**Step 5: Verify data in database**

```bash
docker compose exec php php artisan tinker --execute="
echo 'Backgrounds: ' . \App\Models\Background::count() . PHP_EOL;
echo 'Traits: ' . \App\Models\CharacterTrait::where('reference_type', 'Background')->count() . PHP_EOL;
echo 'Proficiencies: ' . \App\Models\Proficiency::where('reference_type', 'Background')->count() . PHP_EOL;
echo 'Random Tables: ' . \App\Models\RandomTable::whereHasMorph('reference', [\App\Models\CharacterTrait::class], function(\$q) {
    \$q->where('category', 'characteristics');
})->count() . PHP_EOL;

\$acolyte = \App\Models\Background::with(['traits', 'proficiencies'])->where('name', 'Acolyte')->first();
echo PHP_EOL . 'Acolyte Background:' . PHP_EOL;
echo '  Traits: ' . \$acolyte->traits->count() . PHP_EOL;
echo '  Proficiencies: ' . \$acolyte->proficiencies->count() . PHP_EOL;
"
```

**Step 6: Commit**

```bash
git add app/Console/Commands/ImportBackgrounds.php tests/Feature/Importers/BackgroundImporterTest.php
git commit -m "feat: create import:backgrounds artisan command

- Parse and import backgrounds from XML file
- Progress bar for user feedback
- Report created vs updated counts
- Error handling for missing files
- Transaction safety per background
- Feature test verifies command execution"
```

---

## Phase 4: API Layer (Future Enhancement)

### Task 4.1: Create BackgroundResource
**Time:** 15 minutes

Create `app/Http/Resources/BackgroundResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackgroundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            // Polymorphic relationships
            'traits' => CharacterTraitResource::collection($this->whenLoaded('traits')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),

            // Convenience accessors (computed from traits)
            'description' => $this->when(
                $this->relationLoaded('traits'),
                fn() => $this->traits->where('name', 'Description')->first()?->text
            ),
            'feature' => $this->when(
                $this->relationLoaded('traits'),
                function () {
                    $feature = $this->traits->where('category', 'feature')->first();
                    return $feature ? [
                        'name' => $feature->name,
                        'description' => $feature->text,
                    ] : null;
                }
            ),
        ];
    }
}
```

**Commit:**

```bash
git add app/Http/Resources/BackgroundResource.php
git commit -m "feat: create BackgroundResource for API serialization

- Exposes polymorphic relationships (traits, proficiencies, sources)
- Uses whenLoaded() to prevent N+1 queries
- Convenience accessors for description and feature
- Follows established resource pattern"
```

---

## Phase 5: Quality Gates

### Task 5.1: Run Full Test Suite
**Time:** 5 minutes

```bash
docker compose exec php php artisan test
```

**Success Criteria:**
- ✅ All tests pass (267+ tests including new background tests)
- ✅ No deprecation warnings
- ✅ Test duration < 3 seconds

---

### Task 5.2: Code Formatting
**Time:** 2 minutes

```bash
docker compose exec php ./vendor/bin/pint
```

**Success Criteria:**
- ✅ All files formatted according to PSR-12
- ✅ No changes needed (or auto-fixed)

---

### Task 5.3: Verify Import Data
**Time:** 5 minutes

```bash
# Count records
docker compose exec php php artisan tinker --execute="
echo 'Summary:' . PHP_EOL;
echo '  Backgrounds: ' . \App\Models\Background::count() . PHP_EOL;
echo '  Traits: ' . \App\Models\CharacterTrait::where('reference_type', 'Background')->count() . PHP_EOL;
echo '  Proficiencies: ' . \App\Models\Proficiency::where('reference_type', 'Background')->count() . PHP_EOL;
echo '  Sources: ' . \App\Models\EntitySource::where('reference_type', 'Background')->count() . PHP_EOL;
echo PHP_EOL;

// Verify specific backgrounds
\$backgrounds = ['Acolyte', 'Charlatan', 'Criminal', 'Entertainer', 'Folk Hero'];
foreach (\$backgrounds as \$name) {
    \$bg = \App\Models\Background::where('name', \$name)->first();
    echo \$name . ': ' . (\$bg ? 'EXISTS' : 'MISSING') . PHP_EOL;
}
"

# Verify random tables extracted
docker compose exec php php artisan tinker --execute="
\$acolyte = \App\Models\Background::with('traits.randomTables')->where('name', 'Acolyte')->first();
\$charTrait = \$acolyte->traits->where('category', 'characteristics')->first();
if (\$charTrait) {
    echo 'Acolyte random tables: ' . \$charTrait->randomTables->count() . ' (expected 4)' . PHP_EOL;
    foreach (\$charTrait->randomTables as \$table) {
        echo '  - ' . \$table->table_name . ' (' . \$table->dice_type . ')' . PHP_EOL;
    }
}
"
```

**Success Criteria:**
- ✅ 18 backgrounds imported
- ✅ ~54 traits created
- ✅ ~36 proficiencies created
- ✅ 18+ sources created
- ✅ ~72 random tables extracted
- ✅ Key backgrounds exist (Acolyte, Charlatan, Criminal, Entertainer, Folk Hero)

---

## Phase 6: Documentation

### Task 6.1: Update CLAUDE.md
**Time:** 10 minutes

Update `CLAUDE.md`:

1. Update "Current Status" section:
   - Change "3 importers working" to "4 importers working"
   - Add "Backgrounds: 18 imported"
   - Update pending importers count

2. Add Background format section under "XML Format Structure"

3. Update "Repository Structure" to include BackgroundImporter/Parser

4. Add Background import command to "Development Commands"

5. Update "Pending Work" section

**Commit:**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md with Background importer information"
```

---

### Task 6.2: Update PROJECT-STATUS.md
**Time:** 5 minutes

Update `docs/PROJECT-STATUS.md`:

1. Update stats (4 importers, 18 backgrounds)
2. Update "What's Working" section
3. Update pending importers list

**Commit:**

```bash
git add docs/PROJECT-STATUS.md
git commit -m "docs: update PROJECT-STATUS.md with background importer"
```

---

### Task 6.3: Update SESSION-HANDOVER.md
**Time:** 5 minutes

Add entry to `docs/SESSION-HANDOVER.md`:

```markdown
- **2025-11-18:** Background importer implemented (18 backgrounds with polymorphic data)
```

**Commit:**

```bash
git add docs/SESSION-HANDOVER.md
git commit -m "docs: add background importer to session handover"
```

---

## Phase 7: Final Verification

### Task 7.1: End-to-End Test
**Time:** 5 minutes

```bash
# Fresh import
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php bash -c 'for file in import-files/races-*.xml; do php artisan import:races "$file"; done'
docker compose exec php bash -c 'for file in import-files/items-*.xml; do php artisan import:items "$file"; done'
docker compose exec php php artisan import:backgrounds import-files/backgrounds-phb.xml

# Verify counts
docker compose exec php php artisan tinker --execute="
echo 'Entity Counts:' . PHP_EOL;
echo '  Races: ' . \App\Models\Race::count() . PHP_EOL;
echo '  Items: ' . \App\Models\Item::count() . PHP_EOL;
echo '  Backgrounds: ' . \App\Models\Background::count() . PHP_EOL;
echo '  Spells: ' . \App\Models\Spell::count() . PHP_EOL;
echo '  Total Entities: ' . (\App\Models\Race::count() + \App\Models\Item::count() + \App\Models\Background::count() + \App\Models\Spell::count()) . PHP_EOL;
"

# Run all tests
docker compose exec php php artisan test
```

**Success Criteria:**
- ✅ 56 races
- ✅ 1,942 items
- ✅ 18 backgrounds
- ✅ Total: 2,016 entities
- ✅ All tests passing

---

### Task 7.2: Create Final Summary Commit
**Time:** 2 minutes

```bash
git add .
git commit -m "feat: complete Background importer implementation

Summary:
- Simplified backgrounds table (name only, polymorphic relationships)
- BackgroundXmlParser with 8 unit tests
- BackgroundImporter with 5 reconstruction tests
- import:backgrounds artisan command
- BackgroundFactory with state methods
- BackgroundResource for API
- 18 backgrounds imported successfully

Test Coverage:
- 13 new tests (8 unit + 5 reconstruction)
- All tests passing (280+ total)

Architecture:
- Follows Race/Item importer pattern
- Full polymorphic table usage
- Random table extraction (4 tables per background)
- Proficiency type inference

Files Changed:
- 1 migration (simplify table)
- 1 model (Background)
- 1 factory (BackgroundFactory)
- 1 parser (BackgroundXmlParser)
- 1 importer (BackgroundImporter)
- 1 command (ImportBackgrounds)
- 1 resource (BackgroundResource)
- 5 test files
- 3 documentation files"
```

---

## Summary

**Total Time:** 3-4 hours
**Total Tests Added:** 13 (8 unit + 5 reconstruction)
**Total Files Created:** 13
**Migrations:** 1 (simplify table)
**Import Result:** 18 backgrounds, ~54 traits, ~36 proficiencies, ~72 random tables

**Key Achievements:**
1. ✅ Simplified schema (polymorphic-first approach)
2. ✅ Complete TDD coverage (reconstruction tests verify completeness)
3. ✅ Reusable components (ItemTableDetector/Parser for random tables)
4. ✅ Consistent with Race/Item pattern
5. ✅ Production-ready import command

**Next Recommended Steps:**
1. Implement ClassImporter (35 XML files, most complex)
2. Add Background API endpoints (controller + routes)
3. Implement MonsterImporter (5 bestiary files)
4. Add filtering/search to Background API
