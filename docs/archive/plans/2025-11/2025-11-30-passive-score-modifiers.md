# Passive Score Modifiers Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Extract passive score modifiers from description text with proper skill associations.

**Issue:** [#69 - Parser: Extract passive score modifiers (Observant feat)](https://github.com/dfox288/dnd-rulebook-project/issues/69)

---

## Analysis

### The Problem

The Observant feat description says:
> "You have a +5 bonus to your passive Wisdom (Perception) and passive Intelligence (Investigation) scores."

The XML modifier is ambiguous:
```xml
<modifier category="bonus">Passive Wisdom +5</modifier>
```

This doesn't tell us WHICH skills - only "Passive Wisdom". But the description explicitly names the skills in parentheses.

### Our Interpretation

Parse the **description text** to extract specific skills:
- Pattern: `passive Ability (Skill)` → extract the skill name from parentheses
- Pattern: `+N bonus to your passive` → extract the bonus value
- Create separate modifier for each skill mentioned

### Desired Output

For Observant, parser should produce:
```php
[
    'modifier_category' => 'passive_score',
    'value' => 5,
    'skill_name' => 'Perception',
],
[
    'modifier_category' => 'passive_score',
    'value' => 5,
    'skill_name' => 'Investigation',
]
```

The importer already supports `skill_name` → `skill_id` resolution.

---

## Task 1: Write Failing Parser Test

**File:** `tests/Unit/Parsers/FeatXmlParserTest.php`

```php
#[Test]
public function it_parses_passive_score_modifiers_from_description_text()
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Observant (Intelligence)</name>
        <text>Quick to notice details of your environment, you gain the following benefits:

	• Increase your Intelligence or Wisdom score by 1, to a maximum of 20.

	• You have a +5 bonus to your passive Wisdom (Perception) and passive Intelligence (Investigation) scores.

Source:	Player's Handbook (2014) p. 168</text>
        <modifier category="ability score">intelligence +1</modifier>
        <modifier category="bonus">Passive Wisdom +5</modifier>
    </feat>
</compendium>
XML;

    $feats = $this->parser->parse($xml);

    $this->assertCount(1, $feats);
    $modifiers = $feats[0]['modifiers'];

    // Find passive score modifiers (from description parsing)
    $passiveModifiers = array_filter($modifiers, fn($m) => ($m['modifier_category'] ?? '') === 'passive_score');
    $this->assertCount(2, $passiveModifiers);

    $passiveModifiers = array_values($passiveModifiers);
    $skillNames = array_column($passiveModifiers, 'skill_name');

    $this->assertContains('Perception', $skillNames);
    $this->assertContains('Investigation', $skillNames);

    // Both should have value 5
    foreach ($passiveModifiers as $mod) {
        $this->assertEquals(5, $mod['value']);
    }
}
```

**Run:** `docker compose exec php php artisan test --filter=it_parses_passive_score_modifiers_from_description_text`

**Expected:** FAIL

**Commit:** `test: add failing test for passive score modifiers from description text (Issue #69)`

---

## Task 2: Implement Description-Based Passive Score Parsing

**File:** `app/Services/Parsers/FeatXmlParser.php`

### Step 1: Add parsePassiveScoreModifiers method

```php
/**
 * Parse passive score bonuses from description text.
 *
 * Detects patterns like:
 * - "+5 bonus to your passive Wisdom (Perception) and passive Intelligence (Investigation)"
 *
 * Extracts the skill name from parentheses, not the ability name.
 *
 * @return array<int, array<string, mixed>>
 */
private function parsePassiveScoreModifiers(string $text): array
{
    $modifiers = [];

    // Extract the bonus value: "+N bonus to your passive" or "+N bonus to passive"
    $bonusValue = null;
    if (preg_match('/\+(\d+)\s+bonus\s+to\s+(?:your\s+)?passive/i', $text, $valueMatch)) {
        $bonusValue = (int) $valueMatch[1];
    }

    if ($bonusValue === null) {
        return [];
    }

    // Pattern: "passive Ability (Skill)" - extract skill name from parentheses
    // Examples: "passive Wisdom (Perception)", "passive Intelligence (Investigation)"
    if (preg_match_all('/passive\s+(?:Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma)\s*\(([^)]+)\)/i', $text, $matches)) {
        foreach ($matches[1] as $skillName) {
            $modifiers[] = [
                'modifier_category' => 'passive_score',
                'value' => $bonusValue,
                'skill_name' => trim($skillName),
            ];
        }
    }

    return $modifiers;
}
```

### Step 2: Update parseFeat() to include passive score modifiers

In the `parseFeat()` method, add the call and merge results:

```php
// Parse modifiers from XML elements
$modifiersFromXml = $this->parseModifiers($element);

// Parse passive score modifiers from description text
$passiveScoreModifiers = $this->parsePassiveScoreModifiers($description);

return [
    // ... other fields ...
    'modifiers' => array_merge($modifiersFromXml, $passiveScoreModifiers),
    // ... other fields ...
];
```

**Run:** `docker compose exec php php artisan test --filter=it_parses_passive_score_modifiers_from_description_text`

**Expected:** PASS

**Run regression:** `docker compose exec php php artisan test --filter=FeatXmlParserTest`

**Expected:** All PASS

**Commit:** `feat: parse passive score modifiers from description text (Issue #69)`

---

## Task 3: Verify Importer Handles skill_name

**File:** `app/Services/Importers/Concerns/ImportsModifiers.php`

The trait already has this logic (lines 37-42):
```php
$skillId = $modData['skill_id'] ?? null;
if (! $skillId && isset($modData['skill_name'])) {
    $skill = Skill::where('name', $modData['skill_name'])->first();
    $skillId = $skill?->id;
}
```

**Verification:** Write a quick integration test or verify manually that this works.

**Optional test in:** `tests/Unit/Services/Importers/Concerns/ImportsModifiersTest.php`

```php
#[Test]
public function it_imports_passive_score_modifier_with_skill_name_lookup()
{
    $skill = Skill::factory()->create(['name' => 'Perception']);
    $entity = Feat::factory()->create();

    // Use anonymous class to test the trait
    $importer = new class {
        use \App\Services\Importers\Concerns\ImportsModifiers;

        public function import($entity, $data) {
            $this->importEntityModifiers($entity, $data);
        }
    };

    $importer->import($entity, [
        [
            'modifier_category' => 'passive_score',
            'value' => 5,
            'skill_name' => 'Perception',
        ],
    ]);

    $modifier = $entity->modifiers()->first();
    $this->assertEquals('passive_score', $modifier->modifier_category);
    $this->assertEquals(5, $modifier->value);
    $this->assertEquals($skill->id, $modifier->skill_id);
}
```

**Commit:** `test: verify importer resolves skill_name to skill_id (Issue #69)`

---

## Task 4: Ensure API Returns Skill Association

**Files to check:**
- `app/Http/Resources/ModifierResource.php` - should include `skill` relationship
- `app/Models/Feat.php` - `searchableWith()` should include `modifiers.skill`

If changes needed, update and commit.

---

## Task 5: Re-import and Verify

```bash
# Re-import feats
docker compose exec php php artisan import:feats

# Verify in tinker
docker compose exec php php artisan tinker --execute="
\$feat = App\Models\Feat::where('slug', 'observant-intelligence')
    ->with(['modifiers.skill'])
    ->first();
echo 'Feat: ' . \$feat->name . PHP_EOL;
foreach (\$feat->modifiers as \$m) {
    echo '  ' . \$m->modifier_category . ': value=' . \$m->value;
    if (\$m->skill) echo ' skill=' . \$m->skill->name;
    echo PHP_EOL;
}
"
```

**Expected:**
```
Feat: Observant (Intelligence)
  ability_score: value=1
  bonus: value=5
  passive_score: value=5 skill=Perception
  passive_score: value=5 skill=Investigation
```

Note: The XML-based `bonus` modifier will still appear (value=5, no skill). That's okay - the frontend can use the `passive_score` modifiers which have proper skill associations.

---

## Task 6: Run Full Test Suite and Commit

```bash
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php ./vendor/bin/pint
```

Update `CHANGELOG.md` under `[Unreleased]`:
```markdown
### Added
- Passive score modifier parsing from description text (Issue #69)
  - Extracts skill names from "passive Ability (Skill)" patterns
  - Creates `passive_score` modifiers with `skill_id` associations
  - Observant feat now links +5 bonus to Perception and Investigation skills
```

**Final commit:**
```bash
git add -A
git commit -m "feat: complete passive score modifier parsing (Issue #69)

- Parse description text for 'passive Ability (Skill)' patterns
- Extract skill names from parentheses
- Create passive_score modifiers with skill_name for importer
- Importer resolves skill_name to skill_id

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"

git push origin main
```

**Close issue:**
```bash
gh issue close 69 --repo dfox288/dnd-rulebook-project --comment "Passive score modifiers now parsed from description text with skill associations.

Observant feat produces:
- passive_score: value=5, skill=Perception
- passive_score: value=5, skill=Investigation"
```

---

## Summary

| Task | Description |
|------|-------------|
| 1 | Write failing test for description-based parsing |
| 2 | Implement `parsePassiveScoreModifiers()` method |
| 3 | Verify importer handles `skill_name` lookup |
| 4 | Ensure API returns skill association |
| 5 | Re-import and verify data |
| 6 | Full test suite and commit |
