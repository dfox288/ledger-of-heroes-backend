# Background Entity Enhancements - Implementation Plan

**Date:** 2025-11-19
**Branch:** `feature/background-enhancements`
**Estimated Duration:** 4-6 hours
**Complexity:** Medium

## Overview

Enhance the Background entity to parse and store:
1. Languages from trait text (e.g., "Languages: One of your choice")
2. Tool proficiencies with choice support (e.g., "Tool Proficiencies: One type of artisan's tools")
3. Equipment via new polymorphic `entity_items` table
4. ALL embedded random tables (not just Suggested Characteristics)

## Prerequisites

- ✅ Laravel 12.x with Sail running
- ✅ Existing `entity_languages` table (created, working)
- ✅ Existing `proficiencies` table (needs enhancement)
- ✅ Existing `random_tables` + `random_table_entries` tables
- ✅ `ItemTableDetector` + `ItemTableParser` services (working)

---

## Phase 1: Scaffolding

### Task 1.1: Confirm environment and create branch
```bash
# Verify Sail is running
docker compose ps

# Create feature branch from current branch
git checkout -b feature/background-enhancements

# Verify starting point
docker compose exec php php artisan test
```

**Acceptance Criteria:**
- ✅ Sail containers running
- ✅ New branch created
- ✅ All tests passing (baseline)

---

## Phase 2: Data Model Changes

### Task 2.1: Add choice support to proficiencies table

**Migration:** `add_choice_support_to_proficiencies_table`

```php
Schema::table('proficiencies', function (Blueprint $table) {
    $table->boolean('is_choice')->default(false)->after('grants');
    $table->integer('quantity')->default(1)->after('is_choice');
    $table->index('is_choice');
});
```

**Model Update:** `app/Models/Proficiency.php`
- Add `is_choice` and `quantity` to `$fillable`
- Add `$casts` for boolean/integer

**Factory Update:** `database/factories/ProficiencyFactory.php`
- Add state: `withChoice(int $quantity = 1)`

**Acceptance Criteria:**
- ✅ Migration runs clean
- ✅ Rollback works
- ✅ Existing proficiencies have defaults (is_choice=false, quantity=1)
- ✅ Factory can create choice proficiencies

**Test:** `tests/Feature/Migrations/ProficienciesTableTest.php`
```php
#[Test]
public function it_has_choice_support_columns(): void
{
    $this->assertTrue(Schema::hasColumn('proficiencies', 'is_choice'));
    $this->assertTrue(Schema::hasColumn('proficiencies', 'quantity'));
}

#[Test]
public function proficiency_factory_supports_choices(): void
{
    $prof = Proficiency::factory()
        ->forEntity(Background::class, 1)
        ->withChoice(1)
        ->create(['proficiency_name' => 'artisan tools']);

    $this->assertTrue($prof->is_choice);
    $this->assertEquals(1, $prof->quantity);
}
```

**Commit:** `feat: add choice support to proficiencies table`

---

### Task 2.2: Create entity_items polymorphic table

**Migration:** `create_entity_items_table`

```php
Schema::create('entity_items', function (Blueprint $table) {
    $table->id();
    $table->string('reference_type'); // Background, Race, etc.
    $table->unsignedBigInteger('reference_id');
    $table->unsignedBigInteger('item_id')->nullable(); // FK to items table
    $table->integer('quantity')->default(1);
    $table->boolean('is_choice')->default(false);
    $table->text('choice_description')->nullable(); // "one of your choice"

    // Indexes
    $table->index(['reference_type', 'reference_id']);
    $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
});
```

**Model:** `app/Models/EntityItem.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'item_id',
        'quantity',
        'is_choice',
        'choice_description',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'is_choice' => 'boolean',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
```

**Factory:** `database/factories/EntityItemFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\EntityItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityItemFactory extends Factory
{
    protected $model = EntityItem::class;

    public function definition(): array
    {
        return [
            'reference_type' => 'App\\Models\\Background',
            'reference_id' => 1,
            'item_id' => null,
            'quantity' => 1,
            'is_choice' => false,
            'choice_description' => null,
        ];
    }

    public function forEntity(string $entityType, int $entityId): self
    {
        return $this->state([
            'reference_type' => $entityType,
            'reference_id' => $entityId,
        ]);
    }

    public function withItem(int $itemId, int $quantity = 1): self
    {
        return $this->state([
            'item_id' => $itemId,
            'quantity' => $quantity,
        ]);
    }

    public function asChoice(string $description): self
    {
        return $this->state([
            'is_choice' => true,
            'choice_description' => $description,
        ]);
    }
}
```

**Update Background Model:** `app/Models/Background.php`

```php
public function equipment(): MorphMany
{
    return $this->morphMany(EntityItem::class, 'reference');
}
```

**Acceptance Criteria:**
- ✅ Migration runs clean
- ✅ EntityItem model created with relationships
- ✅ Factory supports forEntity(), withItem(), asChoice() states
- ✅ Background->equipment() relationship works

**Test:** `tests/Feature/Migrations/EntityItemsTableTest.php`

```php
#[Test]
public function it_creates_entity_items_table(): void
{
    $this->assertTrue(Schema::hasTable('entity_items'));
    $this->assertTrue(Schema::hasColumns('entity_items', [
        'id', 'reference_type', 'reference_id', 'item_id',
        'quantity', 'is_choice', 'choice_description'
    ]));
}

#[Test]
public function background_has_equipment_relationship(): void
{
    $bg = Background::factory()->create();
    $equipment = EntityItem::factory()
        ->forEntity(Background::class, $bg->id)
        ->create();

    $this->assertCount(1, $bg->equipment);
    $this->assertEquals($equipment->id, $bg->equipment->first()->id);
}
```

**Commit:** `feat: create entity_items polymorphic table for equipment`

---

## Phase 3: Parser Enhancements

### Task 3.1: Add language parsing from trait text

**Parser Method:** `app/Services/Parsers/BackgroundXmlParser.php`

```php
use App\Services\Parsers\Concerns\MatchesLanguages;

class BackgroundXmlParser
{
    use MatchesLanguages, MatchesProficiencyTypes, ParsesSourceCitations;

    /**
     * Parse languages from trait Description text.
     * Pattern: "• Languages: One of your choice"
     */
    private function parseLanguagesFromTraitText(string $text): array
    {
        if (!preg_match('/• Languages:\s*(.+?)(?:\n|$)/m', $text, $matches)) {
            return [];
        }

        $languageText = trim($matches[1]);

        // Check for "one of your choice" pattern
        if (preg_match('/one.*?choice/i', $languageText)) {
            return [[
                'language_id' => null,
                'is_choice' => true,
                'quantity' => 1,
            ]];
        }

        // Parse specific language names (e.g., "Common, Dwarvish")
        return $this->parseLanguages($languageText);
    }
}
```

**Update parse() method:**
```php
public function parse(string $xmlContent): array
{
    // ... existing code ...

    foreach ($xml->background as $bg) {
        $descriptionText = (string) ($bg->trait[0]->text ?? '');

        $backgrounds[] = [
            'name' => (string) $bg->name,
            'proficiencies' => $this->parseProficiencies((string) $bg->proficiency),
            'traits' => $this->parseTraits($bg->trait),
            'sources' => $this->extractSources($descriptionText),
            'languages' => $this->parseLanguagesFromTraitText($descriptionText), // NEW
        ];
    }
}
```

**Acceptance Criteria:**
- ✅ Parses "Languages: One of your choice" → `[{language_id: null, is_choice: true, quantity: 1}]`
- ✅ Parses "Languages: Common" → `[{language_id: X, is_choice: false}]`
- ✅ Returns empty array if no Languages: found

**Test:** `tests/Unit/Parsers/BackgroundXmlParserTest.php`

```php
#[Test]
public function it_parses_language_choice_from_trait_text(): void
{
    $xml = <<<XML
    <compendium>
        <background>
            <name>Test Background</name>
            <proficiency>Insight</proficiency>
            <trait>
                <name>Description</name>
                <text>• Languages: One of your choice</text>
            </trait>
        </background>
    </compendium>
    XML;

    $parser = new BackgroundXmlParser();
    $result = $parser->parse($xml);

    $this->assertCount(1, $result[0]['languages']);
    $this->assertNull($result[0]['languages'][0]['language_id']);
    $this->assertTrue($result[0]['languages'][0]['is_choice']);
}
```

**Commit:** `feat: parse languages from background trait text`

---

### Task 3.2: Add tool proficiency parsing from trait text

**Parser Method:** `app/Services/Parsers/BackgroundXmlParser.php`

```php
/**
 * Parse tool proficiencies from trait Description text.
 * Pattern: "• Tool Proficiencies: One type of artisan's tools"
 */
private function parseToolProficienciesFromTraitText(string $text): array
{
    if (!preg_match('/• Tool Proficiencies:\s*(.+?)(?:\n|$)/m', $text, $matches)) {
        return [];
    }

    $toolText = trim($matches[1]);

    // Check for "one type of" or choice pattern
    if (preg_match('/one.*?type.*?of\s+(.+?)$/i', $toolText, $choiceMatch)) {
        $toolName = trim($choiceMatch[1]);
        $proficiencyType = $this->matchProficiencyType($toolName);

        return [[
            'proficiency_name' => $toolName,
            'proficiency_type' => 'tool',
            'proficiency_type_id' => $proficiencyType?->id,
            'is_choice' => true,
            'quantity' => 1,
            'grants' => true,
        ]];
    }

    // Specific tool (e.g., "Navigator's tools")
    $proficiencyType = $this->matchProficiencyType($toolText);

    return [[
        'proficiency_name' => $toolText,
        'proficiency_type' => 'tool',
        'proficiency_type_id' => $proficiencyType?->id,
        'is_choice' => false,
        'quantity' => 1,
        'grants' => true,
    ]];
}
```

**Update parse() method:**
```php
foreach ($xml->background as $bg) {
    $descriptionText = (string) ($bg->trait[0]->text ?? '');

    // Merge XML proficiencies with trait-text tool proficiencies
    $xmlProfs = $this->parseProficiencies((string) $bg->proficiency);
    $toolProfs = $this->parseToolProficienciesFromTraitText($descriptionText);

    $backgrounds[] = [
        // ...
        'proficiencies' => array_merge($xmlProfs, $toolProfs), // MERGED
    ];
}
```

**Acceptance Criteria:**
- ✅ Parses "Tool Proficiencies: One type of artisan's tools" with `is_choice=true`
- ✅ Parses "Tool Proficiencies: Navigator's tools" with `is_choice=false`
- ✅ Merges with XML `<proficiency>` element proficiencies

**Test:** `tests/Unit/Parsers/BackgroundXmlParserTest.php`

```php
#[Test]
public function it_parses_tool_proficiency_choice_from_trait_text(): void
{
    $xml = <<<XML
    <compendium>
        <background>
            <name>Guild Artisan</name>
            <proficiency>Insight, Persuasion</proficiency>
            <trait>
                <name>Description</name>
                <text>• Skill Proficiencies: Insight, Persuasion
• Tool Proficiencies: One type of artisan's tools
• Languages: One of your choice</text>
            </trait>
        </background>
    </compendium>
    XML;

    $parser = new BackgroundXmlParser();
    $result = $parser->parse($xml);

    // Should have 3 proficiencies: Insight, Persuasion, artisan tools
    $this->assertCount(3, $result[0]['proficiencies']);

    $toolProf = collect($result[0]['proficiencies'])
        ->firstWhere('proficiency_type', 'tool');

    $this->assertNotNull($toolProf);
    $this->assertTrue($toolProf['is_choice']);
    $this->assertEquals("artisan's tools", $toolProf['proficiency_name']);
}
```

**Commit:** `feat: parse tool proficiencies from background trait text`

---

### Task 3.3: Add equipment parsing from trait text

**Parser Method:** `app/Services/Parsers/BackgroundXmlParser.php`

```php
/**
 * Parse equipment from trait Description text.
 * Pattern: "• Equipment: A set of artisan's tools (one of your choice), a letter..."
 */
private function parseEquipmentFromTraitText(string $text): array
{
    if (!preg_match('/• Equipment:\s*(.+?)(?:\n\n|\n[A-Z]|$)/ms', $text, $matches)) {
        return [];
    }

    $equipmentText = trim($matches[1]);

    // Split by commas (but preserve parenthetical content)
    $items = [];
    $parts = preg_split('/,\s*(?![^()]*\))/', $equipmentText);

    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part) || strtolower($part) === 'and') {
            continue;
        }

        // Check for choice pattern
        $isChoice = false;
        $choiceDescription = null;
        if (preg_match('/\(([^)]*choice[^)]*)\)/i', $part, $choiceMatch)) {
            $isChoice = true;
            $choiceDescription = trim($choiceMatch[1]);
            $part = trim(preg_replace('/\([^)]*choice[^)]*\)/i', '', $part));
        }

        // Extract quantity (e.g., "15 gp")
        $quantity = 1;
        if (preg_match('/^(\d+)\s+/', $part, $qtyMatch)) {
            $quantity = (int) $qtyMatch[1];
            $part = trim(substr($part, strlen($qtyMatch[0])));
        }

        // Try to match to items table
        $itemId = $this->findItemByName($part);

        $items[] = [
            'item_id' => $itemId,
            'quantity' => $quantity,
            'is_choice' => $isChoice,
            'choice_description' => $choiceDescription,
            'item_name' => $part, // For debug/logging
        ];
    }

    return $items;
}

private function findItemByName(string $name): ?int
{
    try {
        // Normalize: "A set of artisan's tools" → "artisan's tools"
        $normalized = preg_replace('/^(a|an|the)\s+/i', '', $name);
        $normalized = preg_replace('/\s+set\s+of\s+/i', '', $normalized);

        $item = \App\Models\Item::where('name', 'LIKE', "%{$normalized}%")->first();
        return $item?->id;
    } catch (\Exception $e) {
        return null; // DB not available (unit tests)
    }
}
```

**Update parse() method:**
```php
$backgrounds[] = [
    // ...
    'equipment' => $this->parseEquipmentFromTraitText($descriptionText), // NEW
];
```

**Acceptance Criteria:**
- ✅ Parses equipment list into structured array
- ✅ Detects "(one of your choice)" as `is_choice=true`
- ✅ Extracts quantities (e.g., "15 gp" → quantity: 15)
- ✅ Attempts to match item names to items table
- ✅ Handles complex patterns like "a set of artisan's tools (one of your choice)"

**Test:** `tests/Unit/Parsers/BackgroundXmlParserTest.php`

```php
#[Test]
public function it_parses_equipment_from_trait_text(): void
{
    $xml = <<<XML
    <compendium>
        <background>
            <name>Guild Artisan</name>
            <proficiency>Insight</proficiency>
            <trait>
                <name>Description</name>
                <text>• Equipment: A set of artisan's tools (one of your choice), a letter of introduction from your guild, a set of traveler's clothes, and a belt pouch containing 15 gp</text>
            </trait>
        </background>
    </compendium>
    XML;

    $parser = new BackgroundXmlParser();
    $result = $parser->parse($xml);

    $equipment = $result[0]['equipment'];
    $this->assertGreaterThan(0, count($equipment));

    // First item should be artisan's tools with choice
    $artisanTools = $equipment[0];
    $this->assertTrue($artisanTools['is_choice']);
    $this->assertEquals('one of your choice', $artisanTools['choice_description']);
}
```

**Commit:** `feat: parse equipment from background trait text`

---

### Task 3.4: Add parsing for ALL embedded random tables

**Parser Method:** `app/Services/Parsers/BackgroundXmlParser.php`

```php
use App\Services\ItemTableDetector;
use App\Services\ItemTableParser;

/**
 * Parse ALL embedded random tables from ALL traits.
 * Uses existing ItemTableDetector + ItemTableParser.
 */
private function parseAllEmbeddedTables(array $traits): array
{
    $detector = new ItemTableDetector();
    $parser = new ItemTableParser();
    $allTables = [];

    foreach ($traits as $trait) {
        $text = $trait['description'];
        $detectedTables = $detector->detectTables($text);

        foreach ($detectedTables as $tableInfo) {
            $entries = $parser->parseTable($tableInfo['content']);

            $allTables[] = [
                'name' => $tableInfo['title'] ?? $trait['name'],
                'dice_type' => $tableInfo['dice_type'],
                'trait_name' => $trait['name'], // For linking
                'entries' => $entries,
            ];
        }
    }

    return $allTables;
}
```

**Update parse() method:**
```php
$parsedTraits = $this->parseTraits($bg->trait);
$embeddedTables = $this->parseAllEmbeddedTables($parsedTraits);

$backgrounds[] = [
    // ...
    'traits' => $parsedTraits,
    'random_tables' => $embeddedTables, // NEW
];
```

**Acceptance Criteria:**
- ✅ Detects "Guild Business:" table with d20
- ✅ Detects standard "Personality Trait", "Ideal", "Bond", "Flaw" tables
- ✅ Returns dice_type for each table
- ✅ Links table to trait name for association

**Test:** `tests/Unit/Parsers/BackgroundXmlParserTest.php`

```php
#[Test]
public function it_parses_guild_business_table_from_trait(): void
{
    $xml = <<<XML
    <compendium>
        <background>
            <name>Guild Artisan</name>
            <proficiency>Insight</proficiency>
            <trait>
                <name>Guild Business</name>
                <text>Guild Business:
d20 | Guild Business
1 | Alchemists and apothecaries
2 | Armorers, locksmiths, and finesmiths
3 | Brewers, distillers, and vintners</text>
                <roll description="Guild Business">1d20</roll>
            </trait>
        </background>
    </compendium>
    XML;

    $parser = new BackgroundXmlParser();
    $result = $parser->parse($xml);

    $tables = $result[0]['random_tables'];
    $this->assertGreaterThan(0, count($tables));

    $guildTable = collect($tables)->firstWhere('name', 'Guild Business');
    $this->assertNotNull($guildTable);
    $this->assertEquals('d20', $guildTable['dice_type']);
    $this->assertGreaterThanOrEqual(3, count($guildTable['entries']));
}
```

**Commit:** `feat: parse all embedded tables from background traits`

---

## Phase 4: Importer Updates

### Task 4.1: Update BackgroundImporter for languages

**Importer:** `app/Services/Importers/BackgroundImporter.php`

```php
use App\Models\EntityLanguage;

public function import(array $backgroundData): Background
{
    $background = Background::updateOrCreate(
        ['slug' => Str::slug($backgroundData['name'])],
        ['name' => $backgroundData['name']]
    );

    // ... existing imports ...

    // Import languages
    $background->languages()->delete();
    foreach ($backgroundData['languages'] ?? [] as $langData) {
        EntityLanguage::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'language_id' => $langData['language_id'],
            'is_choice' => $langData['is_choice'] ?? false,
            'quantity' => $langData['quantity'] ?? 1,
        ]);
    }

    return $background;
}
```

**Acceptance Criteria:**
- ✅ Imports language choices correctly
- ✅ Clears old languages on reimport
- ✅ Creates entity_languages records

**Test:** `tests/Feature/Importers/BackgroundImporterTest.php`

```php
#[Test]
public function it_imports_language_choices(): void
{
    $data = [
        'name' => 'Test Background',
        'proficiencies' => [],
        'traits' => [],
        'sources' => [],
        'languages' => [
            ['language_id' => null, 'is_choice' => true, 'quantity' => 1],
        ],
        'equipment' => [],
        'random_tables' => [],
    ];

    $importer = new BackgroundImporter();
    $background = $importer->import($data);

    $this->assertCount(1, $background->languages);
    $this->assertNull($background->languages->first()->language_id);
    $this->assertTrue($background->languages->first()->is_choice);
}
```

**Commit:** `feat: import languages from background parser data`

---

### Task 4.2: Update BackgroundImporter for tool proficiencies with choices

**Importer:** `app/Services/Importers/BackgroundImporter.php`

```php
// Update proficiency import to handle is_choice + quantity
$background->proficiencies()->delete();
foreach ($backgroundData['proficiencies'] as $profData) {
    Proficiency::create([
        'reference_type' => Background::class,
        'reference_id' => $background->id,
        'proficiency_name' => $profData['proficiency_name'],
        'proficiency_type' => $profData['proficiency_type'],
        'proficiency_type_id' => $profData['proficiency_type_id'] ?? null,
        'skill_id' => $profData['skill_id'] ?? null,
        'grants' => $profData['grants'] ?? true,
        'is_choice' => $profData['is_choice'] ?? false, // NEW
        'quantity' => $profData['quantity'] ?? 1, // NEW
    ]);
}
```

**Acceptance Criteria:**
- ✅ Imports tool proficiency with `is_choice=true`
- ✅ Stores quantity correctly
- ✅ Backward compatible (defaults work)

**Test:** `tests/Feature/Importers/BackgroundImporterTest.php`

```php
#[Test]
public function it_imports_tool_proficiency_choices(): void
{
    $data = [
        'name' => 'Guild Artisan',
        'proficiencies' => [
            [
                'proficiency_name' => "artisan's tools",
                'proficiency_type' => 'tool',
                'is_choice' => true,
                'quantity' => 1,
                'grants' => true,
            ],
        ],
        'traits' => [],
        'sources' => [],
        'languages' => [],
        'equipment' => [],
        'random_tables' => [],
    ];

    $importer = new BackgroundImporter();
    $background = $importer->import($data);

    $toolProf = $background->proficiencies()
        ->where('proficiency_type', 'tool')
        ->first();

    $this->assertNotNull($toolProf);
    $this->assertTrue($toolProf->is_choice);
    $this->assertEquals(1, $toolProf->quantity);
}
```

**Commit:** `feat: import tool proficiencies with choice support`

---

### Task 4.3: Add equipment import to BackgroundImporter

**Importer:** `app/Services/Importers/BackgroundImporter.php`

```php
use App\Models\EntityItem;

// Import equipment
$background->equipment()->delete();
foreach ($backgroundData['equipment'] ?? [] as $equipData) {
    EntityItem::create([
        'reference_type' => Background::class,
        'reference_id' => $background->id,
        'item_id' => $equipData['item_id'],
        'quantity' => $equipData['quantity'] ?? 1,
        'is_choice' => $equipData['is_choice'] ?? false,
        'choice_description' => $equipData['choice_description'] ?? null,
    ]);
}
```

**Acceptance Criteria:**
- ✅ Creates entity_items records for each equipment item
- ✅ Links to items table when item_id present
- ✅ Stores choice description for choice items

**Test:** `tests/Feature/Importers/BackgroundImporterTest.php`

```php
#[Test]
public function it_imports_equipment_with_choices(): void
{
    $data = [
        'name' => 'Guild Artisan',
        'proficiencies' => [],
        'traits' => [],
        'sources' => [],
        'languages' => [],
        'equipment' => [
            [
                'item_id' => null,
                'quantity' => 1,
                'is_choice' => true,
                'choice_description' => 'one of your choice',
                'item_name' => "artisan's tools",
            ],
        ],
        'random_tables' => [],
    ];

    $importer = new BackgroundImporter();
    $background = $importer->import($data);

    $this->assertCount(1, $background->equipment);
    $equipment = $background->equipment->first();
    $this->assertTrue($equipment->is_choice);
    $this->assertEquals('one of your choice', $equipment->choice_description);
}
```

**Commit:** `feat: import equipment via entity_items table`

---

### Task 4.4: Update table import to use ALL traits

**Importer:** `app/Services/Importers/BackgroundImporter.php`

```php
use App\Models\RandomTable;
use App\Models\RandomTableEntry;

// Import random tables (from ALL traits, not just Suggested Characteristics)
foreach ($backgroundData['random_tables'] ?? [] as $tableData) {
    $table = RandomTable::create([
        'reference_type' => Background::class,
        'reference_id' => $background->id,
        'name' => $tableData['name'],
        'dice_type' => $tableData['dice_type'],
    ]);

    foreach ($tableData['entries'] as $entry) {
        RandomTableEntry::create([
            'random_table_id' => $table->id,
            'roll_min' => $entry['roll_min'],
            'roll_max' => $entry['roll_max'],
            'result' => $entry['result'],
        ]);
    }

    // Link table to trait if needed
    if (isset($tableData['trait_name'])) {
        $trait = $background->traits()
            ->where('name', $tableData['trait_name'])
            ->first();

        if ($trait) {
            $trait->update(['random_table_id' => $table->id]);
        }
    }
}
```

**Acceptance Criteria:**
- ✅ Imports Guild Business table (d20)
- ✅ Imports Personality/Ideal/Bond/Flaw tables (d6/d8)
- ✅ Links tables to traits via random_table_id

**Test:** `tests/Feature/Importers/BackgroundImporterTest.php`

```php
#[Test]
public function it_imports_guild_business_random_table(): void
{
    $data = [
        'name' => 'Guild Artisan',
        'proficiencies' => [],
        'traits' => [
            [
                'name' => 'Guild Business',
                'description' => 'Select from the table...',
                'category' => 'flavor',
                'rolls' => [],
            ],
        ],
        'sources' => [],
        'languages' => [],
        'equipment' => [],
        'random_tables' => [
            [
                'name' => 'Guild Business',
                'dice_type' => 'd20',
                'trait_name' => 'Guild Business',
                'entries' => [
                    ['roll_min' => 1, 'roll_max' => 1, 'result' => 'Alchemists and apothecaries'],
                    ['roll_min' => 2, 'roll_max' => 2, 'result' => 'Armorers, locksmiths, and finesmiths'],
                ],
            ],
        ],
    ];

    $importer = new BackgroundImporter();
    $background = $importer->import($data);

    $tables = RandomTable::where('reference_type', Background::class)
        ->where('reference_id', $background->id)
        ->get();

    $this->assertCount(1, $tables);
    $this->assertEquals('Guild Business', $tables->first()->name);
    $this->assertEquals('d20', $tables->first()->dice_type);
    $this->assertCount(2, $tables->first()->entries);
}
```

**Commit:** `feat: import all embedded random tables from backgrounds`

---

## Phase 5: API Resources

### Task 5.1: Create EntityItemResource

**Resource:** `app/Http/Resources/EntityItemResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item' => new ItemResource($this->whenLoaded('item')),
            'quantity' => $this->quantity,
            'is_choice' => $this->is_choice,
            'choice_description' => $this->choice_description,
        ];
    }
}
```

**Commit:** `feat: create EntityItemResource for equipment API`

---

### Task 5.2: Update BackgroundResource to include equipment

**Resource:** `app/Http/Resources/BackgroundResource.php`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'slug' => $this->slug,
        'name' => $this->name,
        'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
        'traits' => CharacterTraitResource::collection($this->whenLoaded('traits')),
        'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
        'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),
        'equipment' => EntityItemResource::collection($this->whenLoaded('equipment')), // NEW
    ];
}
```

**Update Controller:** `app/Http/Controllers/Api/BackgroundController.php`

```php
public function show(Background $background)
{
    $background->load([
        'proficiencies.skill',
        'proficiencies.proficiencyType',
        'traits.randomTable.entries',
        'sources.source',
        'languages.language',
        'equipment.item', // NEW
    ]);

    return new BackgroundResource($background);
}
```

**Commit:** `feat: add equipment to BackgroundResource API response`

---

## Phase 6: Comprehensive Testing

### Task 6.1: Guild Artisan reconstruction test

**Test:** `tests/Feature/Importers/BackgroundXmlReconstructionTest.php`

```php
#[Test]
public function it_reconstructs_guild_artisan_with_all_enhancements(): void
{
    $xml = file_get_contents(base_path('import-files/backgrounds-phb.xml'));

    $parser = new BackgroundXmlParser();
    $backgrounds = $parser->parse($xml);

    $guildArtisan = collect($backgrounds)->firstWhere('name', 'Guild Artisan');
    $this->assertNotNull($guildArtisan);

    $importer = new BackgroundImporter();
    $background = $importer->import($guildArtisan);

    // Verify languages
    $this->assertCount(1, $background->languages);
    $this->assertTrue($background->languages->first()->is_choice);

    // Verify tool proficiencies
    $toolProf = $background->proficiencies()
        ->where('proficiency_type', 'tool')
        ->first();
    $this->assertNotNull($toolProf);
    $this->assertTrue($toolProf->is_choice);

    // Verify equipment
    $this->assertGreaterThan(0, $background->equipment->count());
    $choiceEquip = $background->equipment->where('is_choice', true)->first();
    $this->assertNotNull($choiceEquip);

    // Verify Guild Business table
    $guildTable = $background->traits()
        ->where('name', 'Guild Business')
        ->first()
        ?->randomTable;
    $this->assertNotNull($guildTable);
    $this->assertEquals('d20', $guildTable->dice_type);
    $this->assertCount(20, $guildTable->entries);
}
```

**Commit:** `test: add Guild Artisan reconstruction test with all enhancements`

---

## Phase 7: Quality Gates & Rollout

### Task 7.1: Run quality checks

```bash
# Format code
docker compose exec php ./vendor/bin/pint

# Run all tests
docker compose exec php php artisan test

# Verify no regressions
docker compose exec php php artisan test --filter=Background
```

**Acceptance Criteria:**
- ✅ All tests passing
- ✅ Code formatted with Pint
- ✅ No new warnings or deprecations

**Commit:** `chore: run Laravel Pint formatter`

---

### Task 7.2: Full import workflow

```bash
# Fresh database
docker compose exec php php artisan migrate:fresh --seed

# Import all backgrounds
docker compose exec php bash -c 'for file in import-files/backgrounds-*.xml; do php artisan import:backgrounds "$file"; done'

# Verify counts
docker compose exec php php artisan tinker --execute="
echo 'Backgrounds: ' . \App\Models\Background::count() . '\n';
echo 'Languages: ' . \App\Models\EntityLanguage::whereNotNull('reference_type')->count() . '\n';
echo 'Tool Proficiencies (choice): ' . \App\Models\Proficiency::where('is_choice', true)->count() . '\n';
echo 'Equipment: ' . \App\Models\EntityItem::count() . '\n';
echo 'Random Tables: ' . \App\Models\RandomTable::where('reference_type', 'App\\\Models\\\Background')->count() . '\n';
"

# Run tests again
docker compose exec php php artisan test
```

**Acceptance Criteria:**
- ✅ All backgrounds imported successfully
- ✅ Language choices created
- ✅ Tool proficiency choices created
- ✅ Equipment records created
- ✅ Guild Business table created with 20 entries
- ✅ All tests still passing

---

## Summary

**Total Tasks:** 18
**Estimated Duration:** 4-6 hours
**Commits:** ~15 atomic commits
**Test Coverage:**
- 4 migration tests
- 6 parser unit tests
- 5 importer feature tests
- 1 comprehensive reconstruction test

**Success Criteria:**
- ✅ Guild Artisan background fully reconstructed from XML
- ✅ Languages: "One of your choice" → entity_languages with is_choice=true
- ✅ Tool Proficiencies: "One type of artisan's tools" → proficiency with is_choice=true
- ✅ Equipment: Parsed and linked via entity_items table
- ✅ Random Tables: Guild Business d20 table extracted and stored
- ✅ API returns all new fields in BackgroundResource
- ✅ All existing tests still passing (no regressions)

**Next Steps After Completion:**
- Update SESSION-HANDOVER.md with enhancements
- Consider applying similar patterns to Race/Class entities
- Document entity_items table in CLAUDE.md
