# Implementation Plan: Fix Progression Table Columns

**Design Doc:** `docs/plans/2025-11-29-progression-table-columns-design.md`
**Estimated Tasks:** 7
**TDD Required:** Yes

---

## Pre-Implementation Checklist

- [ ] Read design document thoroughly
- [ ] Understand current `ClassProgressionTableGenerator` code
- [ ] Understand `ItemTableDetector` patterns
- [ ] Review `ImportsClassFeatures` trait

---

## Task 1: Add Exclusions for Non-Progression Counters

**Goal:** Remove `wholeness_of_body` and `stroke_of_luck` from progression tables.

### 1.1 Write Test (RED)

**File:** `tests/Unit/Services/ClassProgressionTableGeneratorTest.php`

```php
#[Test]
public function it_excludes_wholeness_of_body_from_columns(): void
{
    // Create monk with Wholeness of Body counter
    $monk = CharacterClass::factory()->create(['slug' => 'monk', 'is_base_class' => true]);
    ClassCounter::factory()->create([
        'class_id' => $monk->id,
        'counter_name' => 'Wholeness of Body',
        'level' => 6,
        'counter_value' => 1,
    ]);
    ClassCounter::factory()->create([
        'class_id' => $monk->id,
        'counter_name' => 'Ki',
        'level' => 2,
        'counter_value' => 2,
    ]);

    $generator = new ClassProgressionTableGenerator();
    $result = $generator->generate($monk);

    $columnKeys = collect($result['columns'])->pluck('key')->toArray();

    $this->assertContains('ki', $columnKeys);
    $this->assertNotContains('wholeness_of_body', $columnKeys);
}

#[Test]
public function it_excludes_stroke_of_luck_from_columns(): void
{
    $rogue = CharacterClass::factory()->create(['slug' => 'rogue', 'is_base_class' => true]);
    ClassCounter::factory()->create([
        'class_id' => $rogue->id,
        'counter_name' => 'Stroke of Luck',
        'level' => 20,
        'counter_value' => 1,
    ]);

    $generator = new ClassProgressionTableGenerator();
    $result = $generator->generate($rogue);

    $columnKeys = collect($result['columns'])->pluck('key')->toArray();

    $this->assertNotContains('stroke_of_luck', $columnKeys);
}
```

### 1.2 Implement (GREEN)

**File:** `app/Services/ClassProgressionTableGenerator.php`

Add to `EXCLUDED_COUNTERS`:

```php
private const EXCLUDED_COUNTERS = [
    'Arcane Recovery',
    'Action Surge',
    'Indomitable',
    'Second Wind',
    'Lay on Hands',
    'Channel Divinity',
    'Wholeness of Body',  // Monk L6 feature, not progression
    'Stroke of Luck',     // Rogue L20 capstone, not progression
];
```

### 1.3 Verify

```bash
docker compose exec php php artisan test --filter="it_excludes_wholeness_of_body"
docker compose exec php php artisan test --filter="it_excludes_stroke_of_luck"
```

---

## Task 2: Add Pattern 4 to ItemTableDetector

**Goal:** Detect level-ordinal tables like `1st | 1d4`.

### 2.1 Write Test (RED)

**File:** `tests/Unit/Parsers/ItemTableDetectorTest.php`

```php
#[Test]
public function it_detects_level_ordinal_tables(): void
{
    $text = <<<'TEXT'
The Monk Table:
Level | Martial Arts
1st | 1d4
5th | 1d6
11th | 1d8
17th | 1d10
TEXT;

    $detector = new ItemTableDetector();
    $tables = $detector->detectTables($text);

    $this->assertCount(1, $tables);
    $this->assertEquals('The Monk Table', $tables[0]['name']);
    $this->assertStringContains('1st | 1d4', $tables[0]['text']);
}

#[Test]
public function it_detects_speed_bonus_ordinal_tables(): void
{
    $text = <<<'TEXT'
Monk Table:
Level | Speed Bonus
1st | +10
6th | +15
10th | +20
14th | +25
18th | +30
TEXT;

    $detector = new ItemTableDetector();
    $tables = $detector->detectTables($text);

    $this->assertCount(1, $tables);
    $this->assertEquals('Monk Table', $tables[0]['name']);
}

#[Test]
public function it_handles_2nd_and_3rd_ordinals(): void
{
    $text = <<<'TEXT'
Test Table:
Level | Value
1st | A
2nd | B
3rd | C
4th | D
TEXT;

    $detector = new ItemTableDetector();
    $tables = $detector->detectTables($text);

    $this->assertCount(1, $tables);
    $this->assertStringContains('2nd | B', $tables[0]['text']);
    $this->assertStringContains('3rd | C', $tables[0]['text']);
}
```

### 2.2 Implement (GREEN)

**File:** `app/Services/Parsers/ItemTableDetector.php`

Add Pattern 4 after Pattern 3 (around line 150):

```php
// Pattern 4: Level-ordinal tables (class progression)
// Matches:
//   Table Name:
//   Level | Column (or Header | Header)
//   1st | value
//   5th | value
$pattern4 = '/^(.+?):\s*\n([^\n]+\|[^\n]+)\s*\n((?:^\d+(?:st|nd|rd|th)\s*\|[^\n]+\s*\n?)+)/mi';

if (preg_match_all($pattern4, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
    foreach ($matches as $match) {
        $matchStart = $match[0][1];
        $matchEnd = $match[0][1] + strlen($match[0][0]);

        // Check for overlap with existing tables
        $alreadyExists = false;
        foreach ($tables as $existing) {
            if ($this->rangesOverlap($matchStart, $matchEnd, $existing['start_pos'], $existing['end_pos'])) {
                $alreadyExists = true;
                break;
            }
        }

        if (! $alreadyExists) {
            $tableName = trim($match[1][0], ': ');
            $header = trim($match[2][0]);
            $rowsText = trim($match[3][0]);
            $tableText = $tableName.":\n".$header."\n".$rowsText;

            $tables[] = [
                'name' => $tableName,
                'text' => $tableText,
                'dice_type' => null,  // Level-ordinal tables don't have dice type
                'start_pos' => $matchStart,
                'end_pos' => $matchEnd,
                'is_level_progression' => true,  // Flag for progression tables
            ];
        }
    }
}
```

Also add helper method:

```php
private function rangesOverlap(int $start1, int $end1, int $start2, int $end2): bool
{
    return ($start1 >= $start2 && $start1 < $end2) ||
           ($end1 > $start2 && $end1 <= $end2) ||
           ($start1 <= $start2 && $end1 >= $end2);
}
```

### 2.3 Verify

```bash
docker compose exec php php artisan test --filter="it_detects_level_ordinal"
docker compose exec php php artisan test --testsuite=Unit-Pure --filter=ItemTableDetector
```

---

## Task 3: Create Level-Ordinal Table Parser

**Goal:** Parse detected tables into structured data with level → value mapping.

### 3.1 Write Test (RED)

**File:** `tests/Unit/Parsers/ItemTableParserTest.php` (or new file)

```php
#[Test]
public function it_parses_level_ordinal_rows(): void
{
    $parser = new ItemTableParser();
    $tableText = <<<'TEXT'
Martial Arts:
Level | Martial Arts
1st | 1d4
5th | 1d6
11th | 1d8
17th | 1d10
TEXT;

    $result = $parser->parseLevelProgression($tableText);

    $this->assertEquals('Martial Arts', $result['table_name']);
    $this->assertCount(4, $result['rows']);
    $this->assertEquals(['level' => 1, 'value' => '1d4'], $result['rows'][0]);
    $this->assertEquals(['level' => 5, 'value' => '1d6'], $result['rows'][1]);
    $this->assertEquals(['level' => 11, 'value' => '1d8'], $result['rows'][2]);
    $this->assertEquals(['level' => 17, 'value' => '1d10'], $result['rows'][3]);
}

#[Test]
public function it_parses_speed_bonus_progression(): void
{
    $parser = new ItemTableParser();
    $tableText = <<<'TEXT'
Unarmored Movement:
Level | Speed Bonus
2nd | +10
6th | +15
10th | +20
TEXT;

    $result = $parser->parseLevelProgression($tableText);

    $this->assertEquals(['level' => 2, 'value' => '+10'], $result['rows'][0]);
    $this->assertEquals(['level' => 6, 'value' => '+15'], $result['rows'][1]);
}
```

### 3.2 Implement (GREEN)

**File:** `app/Services/Parsers/ItemTableParser.php`

Add new method:

```php
/**
 * Parse level-ordinal progression table.
 *
 * @param string $tableText Table text with format "Name:\nHeader\n1st | value\n..."
 * @return array{table_name: string, column_name: string, rows: array}
 */
public function parseLevelProgression(string $tableText): array
{
    $lines = explode("\n", trim($tableText));

    // First line is table name (with colon)
    $tableName = trim($lines[0], ': ');

    // Second line is header
    $headerParts = array_map('trim', explode('|', $lines[1]));
    $columnName = $headerParts[1] ?? $tableName;

    // Remaining lines are data rows
    $rows = [];
    for ($i = 2; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 2) continue;

        // Parse ordinal to integer: "5th" → 5
        $level = $this->parseOrdinalLevel($parts[0]);
        if ($level === null) continue;

        $rows[] = [
            'level' => $level,
            'value' => $parts[1],
        ];
    }

    return [
        'table_name' => $tableName,
        'column_name' => $columnName,
        'rows' => $rows,
    ];
}

/**
 * Parse ordinal level string to integer.
 *
 * @param string $ordinal "1st", "2nd", "3rd", "5th", etc.
 * @return int|null
 */
private function parseOrdinalLevel(string $ordinal): ?int
{
    if (preg_match('/^(\d+)(?:st|nd|rd|th)$/i', trim($ordinal), $matches)) {
        return (int) $matches[1];
    }
    return null;
}
```

### 3.3 Verify

```bash
docker compose exec php php artisan test --filter="it_parses_level_ordinal"
```

---

## Task 4: Parse Progression Tables During Feature Import

**Goal:** Detect and store text progression tables from feature descriptions.

### 4.1 Write Test (RED)

**File:** `tests/Unit/Importers/ImportsClassFeaturesTest.php` (or integration test)

```php
#[Test]
public function it_imports_progression_table_from_feature_description(): void
{
    $class = CharacterClass::factory()->create(['slug' => 'monk', 'is_base_class' => true]);

    $featureData = [
        'name' => 'Martial Arts',
        'level' => 1,
        'description' => "Your martial arts training...\n\nThe Monk Table:\nLevel | Martial Arts\n1st | 1d4\n5th | 1d6\n11th | 1d8\n17th | 1d10\n\nSource: PHB",
        // ... other fields
    ];

    // Import feature
    $importer = new ClassImporter();
    // ... call import method

    $feature = ClassFeature::where('feature_name', 'Martial Arts')->first();

    // Verify RandomTable was created
    $this->assertCount(1, $feature->randomTables);
    $table = $feature->randomTables->first();
    $this->assertEquals('The Monk Table', $table->table_name);
    $this->assertCount(4, $table->entries);
    $this->assertEquals(1, $table->entries[0]->level);
    $this->assertEquals('1d4', $table->entries[0]->result_text);
}
```

### 4.2 Implement (GREEN)

**File:** `app/Services/Importers/Concerns/ImportsClassFeatures.php`

Add new method and call it from `importFeature()`:

```php
/**
 * Parse and import progression tables from feature description.
 *
 * Detects level-ordinal tables like:
 *   The Monk Table:
 *   Level | Martial Arts
 *   1st | 1d4
 *   5th | 1d6
 */
protected function importProgressionTablesFromDescription(ClassFeature $feature, string $description): void
{
    $detector = new ItemTableDetector();
    $detectedTables = $detector->detectTables($description);

    // Filter to only level-progression tables
    $progressionTables = array_filter($detectedTables, fn ($t) => $t['is_level_progression'] ?? false);

    if (empty($progressionTables)) {
        return;
    }

    $parser = new ItemTableParser();

    foreach ($progressionTables as $tableData) {
        $parsed = $parser->parseLevelProgression($tableData['text']);

        if (empty($parsed['rows'])) {
            continue;
        }

        // Determine dice type from values if applicable
        $firstValue = $parsed['rows'][0]['value'] ?? '';
        $diceType = $this->extractDiceType($firstValue);

        $table = RandomTable::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'table_name' => $parsed['column_name'],  // Use column name as table name
            'dice_type' => $diceType,
            'description' => null,
        ]);

        foreach ($parsed['rows'] as $index => $row) {
            RandomTableEntry::create([
                'random_table_id' => $table->id,
                'roll_min' => $row['level'],
                'roll_max' => $row['level'],
                'result_text' => $row['value'],
                'level' => $row['level'],
                'sort_order' => $index,
            ]);
        }
    }
}
```

In `importFeature()` method, add call after feature creation:

```php
// After: $feature = ClassFeature::updateOrCreate(...)

// Import progression tables from description text
if (!empty($featureData['description'])) {
    $this->importProgressionTablesFromDescription($feature, $featureData['description']);
}
```

### 4.3 Verify

```bash
docker compose exec php php artisan test --filter="it_imports_progression_table"
```

---

## Task 5: Update Generator to Use RandomTable Data

**Goal:** `ClassProgressionTableGenerator` pulls columns from feature RandomTables.

### 5.1 Write Test (RED)

**File:** `tests/Unit/Services/ClassProgressionTableGeneratorTest.php`

```php
#[Test]
public function it_includes_columns_from_feature_random_tables(): void
{
    $rogue = CharacterClass::factory()->create(['slug' => 'rogue', 'is_base_class' => true]);

    $feature = ClassFeature::factory()->create([
        'class_id' => $rogue->id,
        'feature_name' => 'Sneak Attack',
        'level' => 1,
    ]);

    $table = RandomTable::create([
        'reference_type' => ClassFeature::class,
        'reference_id' => $feature->id,
        'table_name' => 'Extra Damage',
        'dice_type' => 'd6',
    ]);

    RandomTableEntry::create([
        'random_table_id' => $table->id,
        'roll_min' => 1, 'roll_max' => 1,
        'result_text' => '1d6',
        'level' => 1,
        'sort_order' => 0,
    ]);
    RandomTableEntry::create([
        'random_table_id' => $table->id,
        'roll_min' => 3, 'roll_max' => 3,
        'result_text' => '2d6',
        'level' => 3,
        'sort_order' => 1,
    ]);

    $generator = new ClassProgressionTableGenerator();
    $result = $generator->generate($rogue);

    $columnKeys = collect($result['columns'])->pluck('key')->toArray();
    $this->assertContains('sneak_attack', $columnKeys);

    // Check row values
    $row1 = collect($result['rows'])->firstWhere('level', 1);
    $row3 = collect($result['rows'])->firstWhere('level', 3);
    $this->assertEquals('1d6', $row1['sneak_attack']);
    $this->assertEquals('2d6', $row3['sneak_attack']);
}
```

### 5.2 Implement (GREEN)

**File:** `app/Services/ClassProgressionTableGenerator.php`

Add new method to get progression tables from features:

```php
/**
 * Get progression-related RandomTables from class features.
 *
 * Returns tables that have level-based entries (indicating they're
 * progression tables rather than random roll tables).
 */
private function getFeatureProgressionTables(CharacterClass $class): Collection
{
    return $class->features
        ->load('randomTables.entries')
        ->flatMap(fn ($feature) => $feature->randomTables->map(
            fn ($table) => ['feature' => $feature, 'table' => $table]
        ))
        ->filter(fn ($item) => $item['table']->entries->contains(fn ($e) => $e->level !== null));
}
```

Update `buildColumns()` to include these:

```php
private function buildColumns(CharacterClass $class): array
{
    $columns = [
        ['key' => 'level', 'label' => 'Level', 'type' => 'integer'],
        ['key' => 'proficiency_bonus', 'label' => 'Proficiency Bonus', 'type' => 'bonus'],
        ['key' => 'features', 'label' => 'Features', 'type' => 'string'],
    ];

    // Add counter columns (existing logic)
    // ...

    // Add feature progression columns (NEW)
    $progressionTables = $this->getFeatureProgressionTables($class);
    foreach ($progressionTables as $item) {
        $feature = $item['feature'];
        $columns[] = [
            'key' => $this->slugify($feature->feature_name),
            'label' => $feature->feature_name,
            'type' => $this->getCounterType($feature->feature_name),
        ];
    }

    // Add spell slot columns (existing logic)
    // ...

    return $columns;
}
```

Update `buildRows()` to populate these columns:

```php
// In buildRows(), add logic to populate feature progression values
$progressionTables = $this->getFeatureProgressionTables($class);
$progressionLookup = $this->buildProgressionLookup($progressionTables);

// In row building loop:
foreach ($progressionLookup as $key => $data) {
    $row[$key] = $this->getProgressionValue($data, $level);
}
```

### 5.3 Verify

```bash
docker compose exec php php artisan test --filter="it_includes_columns_from_feature"
docker compose exec php php artisan test --testsuite=Unit-DB --filter=ClassProgressionTableGenerator
```

---

## Task 6: Re-import and Verify

**Goal:** Re-import classes to populate new data, verify API output.

### 6.1 Re-import Classes

```bash
docker compose exec php php artisan import:classes --fresh
```

### 6.2 Verify API Output

```bash
# Rogue should have sneak_attack, not stroke_of_luck
curl -s http://localhost:8080/api/v1/classes/rogue | jq '.data.computed.progression_table.columns | .[].key'

# Monk should have martial_arts, ki, unarmored_movement, not wholeness_of_body
curl -s http://localhost:8080/api/v1/classes/monk | jq '.data.computed.progression_table.columns | .[].key'

# Barbarian should have rage, rage_damage
curl -s http://localhost:8080/api/v1/classes/barbarian | jq '.data.computed.progression_table.columns | .[].key'
```

### 6.3 Run Full Test Suite

```bash
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

---

## Task 7: Handle Rage Damage Edge Case

**Goal:** Address Barbarian's Rage Damage which is only in prose text.

### Analysis

Rage Damage is NOT in a table format in the XML. It's in prose:
> "At 1st level, you have a +2 bonus to damage. Your bonus increases to +3 at 9th level and to +4 at 16th."

### Options

**Option A: Hardcode in generator**
```php
private const SYNTHETIC_PROGRESSIONS = [
    'barbarian' => [
        'rage_damage' => [
            'label' => 'Rage Damage',
            'type' => 'bonus',
            'values' => [1 => '+2', 9 => '+3', 16 => '+4'],
        ],
    ],
];
```

**Option B: Parse prose pattern**
Detect patterns like "At Xth level... +Y... increases to +Z at Nth level"

**Option C: Manual XML fix**
Add `<roll>` elements to Barbarian XML and re-import.

### Recommendation

Start with **Option A** (hardcode) for Rage Damage specifically. It's a single edge case and the values are fixed in PHB. Document as tech debt to consider Option C later.

---

## Completion Checklist

- [ ] Task 1: Exclusions added and tested
- [ ] Task 2: Pattern 4 added to ItemTableDetector
- [ ] Task 3: Level-ordinal parser created
- [ ] Task 4: Feature import parses text tables
- [ ] Task 5: Generator uses RandomTable data
- [ ] Task 6: Re-import and API verification
- [ ] Task 7: Rage Damage edge case handled
- [ ] All tests pass (Unit-Pure, Unit-DB, Feature-DB)
- [ ] Code formatted with Pint
- [ ] CHANGELOG.md updated
- [ ] Changes committed and pushed

---

## Notes for Implementing Agent

1. **TDD is mandatory** - Write tests first for each task
2. **Run smallest relevant test suite** after each change
3. **Commit after each task** completes successfully
4. **Update TODO.md** to mark this item complete when done
5. **Watch for Rage Damage** - it's the one edge case without structured XML data
