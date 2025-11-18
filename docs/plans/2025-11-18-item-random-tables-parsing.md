# Item Random Tables Parsing Implementation Plan

> **Status:** Future Enhancement - Not Currently Implemented
> **Complexity:** High (3-4 hours estimated)
> **Priority:** Low (data preserved in text, readable but not structured)

**Goal:** Extract pipe-separated random tables from item description text into structured `random_tables` and `random_table_entries` records.

**Architecture:** Extend ItemImporter to detect and parse table patterns in description text → Create RandomTable with polymorphic reference to Item → Parse entries into RandomTableEntry records → Remove/flag table text in description to avoid duplication.

**Tech Stack:** Laravel 12.x, PHP 8.4, Regex pattern matching, Polymorphic relationships

---

## Background

### Current State

**What's Missing:**
- Random tables embedded in item descriptions are not extracted to database
- Tables remain as formatted text in `items.description` field
- Not queryable as structured data
- Not easily presentable as HTML tables

**Impact:**
- ~10-20 items estimated to have embedded tables (Apparatus of Kwalish, Deck of Many Things, etc.)
- Data is preserved and readable, just not structured
- Frontend applications must parse text to display tables

**Example XML:**
```xml
<item>
  <name>Apparatus of Kwalish</name>
  <text>The apparatus floats on water...

Apparatus of Kwalish Levers:
Lever | Up | Down
1 | Up: Legs and tail extend, allowing the apparatus to walk and swim. | Down: Legs and tail retract, reducing the apparatus's speed to 0.
2 | Up: Forward window shutter opens. | Down: Forward window shutter closes.
3 | Up: Side window shutters open (two per side). | Down: Side window shutters close.
...

Source: Dungeon Master's Guide (2014) p. 151</text>
</item>
```

### Desired State

**Database Schema (Already Exists):**
```php
// random_tables table
- id
- reference_type (Item::class)
- reference_id (item.id)
- table_name ("Apparatus of Kwalish Levers")
- dice_type (nullable - no dice for this table)
- description (nullable)

// random_table_entries table
- id
- random_table_id
- roll_min (1, 2, 3, ... or null)
- roll_max (1, 2, 3, ... or null)
- result_text ("Lever | Up: Legs extend... | Down: Legs retract...")
- sort_order (0, 1, 2, ...)
```

**API Output:**
```json
{
  "id": 105,
  "name": "Apparatus of Kwalish",
  "description": "The apparatus floats on water...",
  "random_tables": [
    {
      "id": 1,
      "table_name": "Apparatus of Kwalish Levers",
      "dice_type": null,
      "entries": [
        {
          "roll_min": 1,
          "roll_max": 1,
          "result_text": "Up: Legs extend... | Down: Legs retract...",
          "sort_order": 0
        },
        {
          "roll_min": 2,
          "roll_max": 2,
          "result_text": "Up: Window opens... | Down: Window closes...",
          "sort_order": 1
        }
      ]
    }
  ]
}
```

---

## Challenge Analysis

### Pattern Detection Complexity

**Problem:** Tables in D&D text use various formats:

**Format 1: Header + Rows (Apparatus of Kwalish)**
```
Table Name:
Header | Header | Header
1 | Data | Data
2 | Data | Data
```

**Format 2: d8/d10/d20 Tables**
```
Table Name
d8 | Result
1 | Effect A
2-3 | Effect B
4-7 | Effect C
8 | Effect D
```

**Format 3: Narrative Tables (Deck of Many Things)**
```
Card Name: Effect description
Card Name: Effect description
```

**Format 4: Multi-column Data**
```
Level | Slots | Spells
1st | 2 | 3
2nd | 3 | 5
```

**Risk:** False positives on narrative text that contains pipes for other reasons

### Data Extraction Challenges

1. **Column Alignment:** Pipes don't guarantee fixed-width columns
2. **Multi-line Cells:** Some cells contain line breaks
3. **Header Detection:** First row may or may not be a header
4. **Roll Ranges:** "2-3" needs to be split into roll_min=2, roll_max=3
5. **Table Name:** Line above table, or inline?

### Implementation Trade-offs

| Approach | Pros | Cons |
|----------|------|------|
| **Regex Pattern Matching** | Fast, no dependencies | Fragile, hard to maintain, false positives |
| **Dedicated Parser Library** | Robust, handles edge cases | Heavy dependency, overkill for small dataset |
| **Manual XML Annotation** | 100% accuracy | Requires XML file editing, high effort |
| **Leave as Text** | No code needed, data preserved | Not queryable, frontend must parse |

**Recommendation:** Leave as text for now (current state), revisit if API consumers request structured tables

---

## Implementation Plan (If Pursuing)

### Task 1: Pattern Detection

**Goal:** Identify table boundaries in description text

**Step 1: Define regex patterns**
```php
// Pattern 1: Table with header row
$pattern1 = '/(?P<name>.+?):\n(?P<header>[^\n]+\|[^\n]+)\n(?P<rows>(?:\d+.*\|.*\n?)+)/';

// Pattern 2: Dice table
$pattern2 = '/(?P<name>.+?)\nd\d+\s*\|\s*Result\n(?P<rows>(?:\d+(?:-\d+)?\s*\|.*\n?)+)/';
```

**Step 2: Test patterns on sample data**
- Apparatus of Kwalish (10-row table)
- Deck of Many Things (card list)
- Scroll of Protection (effect list)

**Step 3: Create unit test**
```php
public function test_it_detects_table_patterns()
{
    $text = "Description text.\n\nTable Name:\nCol1 | Col2\n1 | Data A\n2 | Data B\n\nMore text.";

    $tables = $this->detector->detectTables($text);

    $this->assertCount(1, $tables);
    $this->assertEquals('Table Name', $tables[0]['name']);
    $this->assertCount(2, $tables[0]['rows']);
}
```

**Expected Output:**
```php
[
    'name' => 'Apparatus of Kwalish Levers',
    'dice_type' => null,
    'start_pos' => 350,  // Character position in text
    'end_pos' => 1200,
    'rows' => [
        ['1', 'Up: Legs extend...', 'Down: Legs retract...'],
        ['2', 'Up: Window opens...', 'Down: Window closes...'],
    ]
]
```

---

### Task 2: Table Extraction

**Goal:** Parse detected tables into structured data

**Step 1: Create TableParser service**
```php
class ItemTableParser
{
    public function parse(string $tableText): array
    {
        $lines = explode("\n", $tableText);
        $tableName = array_shift($lines);  // First line is name
        $header = array_shift($lines);      // Second line is header

        $rows = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $cells = array_map('trim', explode('|', $line));

            // Parse roll range from first cell
            $rollCell = array_shift($cells);
            [$rollMin, $rollMax] = $this->parseRollRange($rollCell);

            $rows[] = [
                'roll_min' => $rollMin,
                'roll_max' => $rollMax,
                'result_text' => implode(' | ', $cells),
            ];
        }

        return [
            'table_name' => trim($tableName, ':'),
            'rows' => $rows,
        ];
    }

    private function parseRollRange(string $cell): array
    {
        // "1" => [1, 1]
        // "2-3" => [2, 3]
        // "Lever" => [null, null]

        if (preg_match('/^(\d+)-(\d+)$/', $cell, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        } elseif (is_numeric($cell)) {
            return [(int) $cell, (int) $cell];
        } else {
            return [null, null];
        }
    }
}
```

**Step 2: Unit test parser**
```php
public function test_it_parses_table_with_roll_ranges()
{
    $tableText = <<<TEXT
Wild Magic Surge:
d100 | Effect
01-02 | Roll on this table...
03-04 | You teleport...
05 | A unicorn appears...
TEXT;

    $parsed = $this->parser->parse($tableText);

    $this->assertEquals('Wild Magic Surge', $parsed['table_name']);
    $this->assertCount(3, $parsed['rows']);
    $this->assertEquals(1, $parsed['rows'][0]['roll_min']);
    $this->assertEquals(2, $parsed['rows'][0]['roll_max']);
}
```

---

### Task 3: Database Import

**Goal:** Store parsed tables in database

**Step 1: Add table parsing to ItemImporter**
```php
private function importRandomTables(Item $item, string $description): void
{
    // Detect tables in description
    $detector = new ItemTableDetector();
    $tables = $detector->detectTables($description);

    if (empty($tables)) {
        return;
    }

    // Clear existing tables
    $item->randomTables()->delete();

    foreach ($tables as $tableData) {
        $parser = new ItemTableParser();
        $parsed = $parser->parse($tableData['text']);

        $table = RandomTable::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'table_name' => $parsed['table_name'],
            'dice_type' => $parsed['dice_type'] ?? null,
        ]);

        foreach ($parsed['rows'] as $index => $row) {
            RandomTableEntry::create([
                'random_table_id' => $table->id,
                'roll_min' => $row['roll_min'],
                'roll_max' => $row['roll_max'],
                'result_text' => $row['result_text'],
                'sort_order' => $index,
            ]);
        }
    }
}
```

**Step 2: Call from import() method**
```php
public function import(array $itemData): Item
{
    // ... existing import logic ...

    // Import random tables from description
    $this->importRandomTables($item, $itemData['description']);

    return $item;
}
```

**Step 3: Add relationship to Item model**
```php
public function randomTables(): MorphMany
{
    return $this->morphMany(RandomTable::class, 'reference');
}
```

---

### Task 4: Description Cleanup (Optional)

**Goal:** Remove table text from description to avoid duplication

**Option 1: Replace with placeholder**
```php
$description = str_replace($tableData['text'], '[Table: ' . $parsed['table_name'] . ']', $description);
```

**Option 2: Remove entirely**
```php
$description = str_replace($tableData['text'], '', $description);
$description = preg_replace('/\n{3,}/', "\n\n", $description);  // Clean extra newlines
```

**Option 3: Keep original (safest)**
- Leave description unchanged
- Provide table data separately in API
- Let frontend choose how to render

**Recommendation:** Option 3 - preserve original text

---

### Task 5: API Resource Updates

**Goal:** Include random tables in Item API responses

**Step 1: Update ItemResource**
```php
'random_tables' => RandomTableResource::collection($this->whenLoaded('randomTables')),
```

**Step 2: Create RandomTableResource**
```php
class RandomTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table_name' => $this->table_name,
            'dice_type' => $this->dice_type,
            'entries' => RandomTableEntryResource::collection($this->whenLoaded('entries')),
        ];
    }
}
```

**Step 3: Create RandomTableEntryResource**
```php
class RandomTableEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'roll_min' => $this->roll_min,
            'roll_max' => $this->roll_max,
            'result_text' => $this->result_text,
            'sort_order' => $this->sort_order,
        ];
    }
}
```

---

### Task 6: Reconstruction Tests

**Goal:** Verify table parsing accuracy

**Test 1: Simple table**
```php
#[Test]
public function it_parses_simple_table_from_description()
{
    $originalXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <item>
    <name>Test Item</name>
    <type>W</type>
    <text>Item description.

Test Table:
Option | Effect
1 | Effect A
2 | Effect B

Source: Test p. 1</text>
  </item>
</compendium>
XML;

    $items = $this->parser->parse($originalXml);
    $item = $this->importer->import($items[0]);

    $item->load('randomTables.entries');
    $this->assertCount(1, $item->randomTables);

    $table = $item->randomTables->first();
    $this->assertEquals('Test Table', $table->table_name);
    $this->assertCount(2, $table->entries);

    $this->assertEquals(1, $table->entries[0]->roll_min);
    $this->assertEquals('Effect A', $table->entries[0]->result_text);
}
```

**Test 2: Roll ranges**
```php
#[Test]
public function it_parses_roll_ranges_in_tables()
{
    // Test "2-3" => roll_min=2, roll_max=3
}
```

**Test 3: Apparatus of Kwalish (real data)**
```php
#[Test]
public function it_parses_apparatus_of_kwalish_lever_table()
{
    // Use actual XML from items-dmg.xml
    // Verify 10 lever entries parsed correctly
}
```

---

## Edge Cases and Limitations

### Known Edge Cases

1. **Multi-line cells:** Cells with embedded newlines will break row parsing
   - **Solution:** Detect by counting pipes per line, merge lines with fewer pipes

2. **Nested tables:** Tables within tables (rare)
   - **Solution:** Only parse top-level tables, leave nested as text

3. **Tables without delimiters:** Narrative lists without pipes
   - **Solution:** Don't attempt to parse, leave as text

4. **Variable column counts:** Different rows have different numbers of pipes
   - **Solution:** Pad missing columns with empty strings

5. **Tables at start/end of description:** Boundary detection
   - **Solution:** Use lookahead/lookbehind regex assertions

### Items Known to Have Tables

Based on manual inspection of XML files:

1. **Apparatus of Kwalish** (items-dmg.xml) - 10-row lever table ✓
2. **Deck of Many Things** (items-dmg.xml) - Card list
3. **Scroll of Protection** (items-dmg.xml) - Protection types
4. **Bag of Beans** (items-dmg.xml) - d100 effect table
5. **Sphere of Annihilation** (items-dmg.xml) - Control table

**Estimated Total:** 10-20 items across all files

---

## Testing Strategy

### Phase 1: Pattern Detection (Unit Tests)
- Test regex patterns on sample text
- Verify no false positives on regular text
- Test edge cases (tables at start/end, multiple tables)

### Phase 2: Parser Logic (Unit Tests)
- Test row parsing with various formats
- Test roll range extraction
- Test column splitting

### Phase 3: Integration (Feature Tests)
- Test full import from XML → Database
- Test relationship loading
- Test API output

### Phase 4: Real Data (Manual Verification)
- Import all 17 item XML files
- Check Apparatus of Kwalish has 10 entries
- Verify no parser errors in logs
- Compare parsed output to original XML

---

## Alternative Approaches

### Option 1: XML Annotation (Highest Accuracy)

**Approach:** Edit XML files to wrap tables in structured elements

**Before:**
```xml
<text>Description...

Table Name:
Col1 | Col2
1 | Data A
2 | Data B

More text...</text>
```

**After:**
```xml
<text>Description... More text...</text>
<table name="Table Name">
  <entry roll="1">
    <column>Col1</column>
    <column>Col2</column>
  </entry>
  <entry roll="2">
    <column>Data A</column>
    <column>Data B</column>
  </entry>
</table>
```

**Pros:**
- 100% parsing accuracy
- No regex fragility
- Explicit structure

**Cons:**
- Requires editing all XML files (~1000+ files)
- Must maintain edits when upstream updates
- Not scalable

### Option 2: AI/LLM Table Detection (Experimental)

**Approach:** Use Claude API to detect and extract tables from text

**Prompt:**
```
Extract any tables from the following D&D item description.
Return as JSON with table_name, headers, and rows.

Description: {item description}
```

**Pros:**
- Handles varied formats gracefully
- No fragile regex patterns
- Can handle complex multi-line cells

**Cons:**
- Requires API calls (cost, latency)
- Non-deterministic (may return different results)
- Requires internet connection

### Option 3: Frontend Parsing (Defer to Client)

**Approach:** Pass raw text to frontend, let JavaScript parse tables

**Pros:**
- No backend complexity
- Frontend can choose rendering
- Easy to update parsing logic

**Cons:**
- Inconsistent across clients
- Not queryable in database
- Performance cost on client

---

## Recommendation

**Leave as current state (text preserved)** for the following reasons:

1. **Low Impact:** Only ~10-20 items affected out of 1,942
2. **Data Preserved:** All table data is in description text, readable
3. **High Complexity:** 3-4 hours of development for marginal benefit
4. **Fragile Code:** Regex patterns break easily with format variations
5. **No Blocking Issues:** API consumers can parse text if needed

**Revisit if:**
- API consumers request structured table data
- Frontend requires consistent table rendering
- New XML files have significantly more tables
- Time available for enhancement work

---

## Implementation Checklist (If Pursuing)

- [ ] **Task 1:** Pattern detection regex and unit tests (1 hour)
- [ ] **Task 2:** Table parser service and unit tests (1 hour)
- [ ] **Task 3:** Import integration and cleanup (45 minutes)
- [ ] **Task 4:** API resource updates (30 minutes)
- [ ] **Task 5:** Reconstruction tests (45 minutes)
- [ ] **Task 6:** Real data testing and debugging (1 hour)

**Total Estimated Time:** 3.5-4.5 hours

---

## References

- **Existing Pattern:** Race random table parsing (`RaceImporter.php:132-163`)
- **Schema:** `database/migrations/*_create_random_tables.php`
- **Models:** `app/Models/RandomTable.php`, `app/Models/RandomTableEntry.php`
- **Sample Data:** `import-files/items-dmg.xml` (Apparatus of Kwalish, line ~1500)

---

## Conclusion

This feature is **not currently implemented** and is documented as a **future enhancement**. The current state (tables preserved in text) is acceptable for MVP. If structured table data becomes a priority, this plan provides a roadmap for implementation.

**Next Steps:**
- Review with stakeholders to confirm priority
- If approved, implement in priority order (Tasks 1-6)
- If deferred, revisit after other features complete
