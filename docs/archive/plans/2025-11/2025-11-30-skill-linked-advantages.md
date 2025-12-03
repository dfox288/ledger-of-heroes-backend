# Skill-Linked Advantages Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Route skill-based advantages (like Actor's "advantage on Deception/Performance checks") to `entity_modifiers` instead of `entity_conditions`, with proper skill associations.

**Architecture:** Modify `FeatXmlParser::parseConditions()` to detect skill-based advantages and emit them as modifiers instead. The `entity_conditions` table should only contain D&D condition interactions (Blinded, Charmed, etc.).

**Tech Stack:** Laravel 12.x, PHPUnit 11, MySQL/SQLite

**Issue:** [#70 - Parser: Route skill-based advantages to entity_modifiers (Actor feat)](https://github.com/dfox288/dnd-rulebook-project/issues/70)

---

## Analysis

### Current State

Actor feat description:
> "You have advantage on Charisma (Deception) and Charisma (Performance) checks when trying to pass yourself off as a different person."

Current parser routes this to `entity_conditions`:
```php
[
    'effect_type' => 'advantage',
    'description' => 'Charisma (Deception) and Charisma (Performance) checks when trying to pass yourself off as a different person',
    // condition_id is NULL (correct - no D&D condition involved)
]
```

### Problem

The `entity_conditions` table is semantically for D&D Conditions (Blinded, Charmed, Frightened, etc.):
- "Advantage on saves against being **frightened**" → `condition_id` links to Frightened
- "Immunity to **poison**" → `condition_id` links to Poisoned

Actor's ability isn't condition-related - it's a skill check modifier with a situational trigger.

### Desired State

Route to `entity_modifiers` instead:
```php
[
    'modifier_category' => 'skill_advantage',
    'skill_name' => 'Deception',
    'value' => null,  // Advantage has no numeric value
    'condition' => 'when trying to pass yourself off as a different person',
],
[
    'modifier_category' => 'skill_advantage',
    'skill_name' => 'Performance',
    'value' => null,
    'condition' => 'when trying to pass yourself off as a different person',
]
```

### Pattern to Detect

```
advantage on (Ability) \((Skill)\)( and (Ability) \((Skill)\))? checks (when|while)? (.+)
```

Examples:
- "advantage on Charisma (Deception) and Charisma (Performance) checks when..."
- "advantage on Wisdom (Perception) checks while..."

---

## Task 1: Write Failing Parser Test

**Files:**
- Modify: `tests/Unit/Parsers/FeatXmlParserTest.php`

**Step 1: Add test for skill-based advantage parsing**

```php
#[Test]
public function it_parses_skill_based_advantages_as_modifiers_not_conditions()
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Actor</name>
        <text>Skilled at mimicry and dramatics, you gain the following benefits:

	• Increase your Charisma score by 1, to a maximum of 20.

	• You have advantage on Charisma (Deception) and Charisma (Performance) checks when trying to pass yourself off as a different person.

	• You can mimic the speech of another person or the sounds made by other creatures. You must have heard the person speaking, or heard the creature make the sound, for at least 1 minute. A successful Wisdom (Insight) check contested by your Charisma (Deception) check allows a listener to determine that the effect is faked.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="ability score">charisma +1</modifier>
    </feat>
</compendium>
XML;

    $feats = $this->parser->parse($xml);

    $this->assertCount(1, $feats);

    // Skill-based advantages should be in modifiers, not conditions
    $conditions = $feats[0]['conditions'];
    $modifiers = $feats[0]['modifiers'];

    // Should NOT have the skill advantage in conditions
    $skillAdvantageConditions = array_filter($conditions, fn($c) =>
        str_contains(strtolower($c['description'] ?? ''), 'deception')
    );
    $this->assertEmpty($skillAdvantageConditions, 'Skill-based advantages should not be in conditions');

    // Should have skill advantages in modifiers
    $skillAdvantageModifiers = array_filter($modifiers, fn($m) =>
        ($m['modifier_category'] ?? '') === 'skill_advantage'
    );
    $this->assertCount(2, $skillAdvantageModifiers);

    $skillAdvantageModifiers = array_values($skillAdvantageModifiers);

    // Check Deception modifier
    $deceptionMod = array_filter($skillAdvantageModifiers, fn($m) => ($m['skill_name'] ?? '') === 'Deception');
    $this->assertNotEmpty($deceptionMod);
    $deceptionMod = array_values($deceptionMod)[0];
    $this->assertStringContainsString('pass yourself off', $deceptionMod['condition']);

    // Check Performance modifier
    $performanceMod = array_filter($skillAdvantageModifiers, fn($m) => ($m['skill_name'] ?? '') === 'Performance');
    $this->assertNotEmpty($performanceMod);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=it_parses_skill_based_advantages_as_modifiers_not_conditions
```

Expected: FAIL - skill advantages currently go to conditions.

**Step 3: Commit failing test**

```bash
git add tests/Unit/Parsers/FeatXmlParserTest.php
git commit -m "test: add failing test for skill-based advantages as modifiers

Tests Actor pattern: 'advantage on Charisma (Deception) checks' should
be in modifiers with skill_id, not conditions (Issue #70)"
```

---

## Task 2: Implement Skill-Advantage Parsing

**Files:**
- Modify: `app/Services/Parsers/FeatXmlParser.php`

**Step 1: Add method to parse skill-based advantages**

Add this method to `FeatXmlParser`:

```php
/**
 * Parse skill-based advantages from description text.
 *
 * Detects patterns like:
 * - "advantage on Charisma (Deception) and Charisma (Performance) checks when..."
 * - "advantage on Wisdom (Perception) checks while..."
 *
 * @return array<int, array<string, mixed>>
 */
private function parseSkillAdvantages(string $text): array
{
    $modifiers = [];

    // Pattern: "advantage on Ability (Skill) checks" with optional condition
    // Captures: skill names in parentheses, and the conditional text after "when/while"
    $pattern = '/advantage on\s+(?:([A-Z][a-z]+)\s*\(([^)]+)\))(?:\s+and\s+(?:[A-Z][a-z]+)\s*\(([^)]+)\))?\s+checks?\s*(?:(when|while)\s+(.+?))?(?:\.|$)/i';

    if (preg_match($pattern, $text, $match)) {
        $skills = [];

        // First skill
        if (! empty($match[2])) {
            $skills[] = trim($match[2]);
        }

        // Second skill (if "and" pattern)
        if (! empty($match[3])) {
            $skills[] = trim($match[3]);
        }

        // Condition text (after "when" or "while")
        $conditionText = ! empty($match[5]) ? trim($match[5]) : null;

        foreach ($skills as $skillName) {
            $modifiers[] = [
                'modifier_category' => 'skill_advantage',
                'skill_name' => $skillName,
                'value' => null,  // Advantage has no numeric value
                'condition' => $conditionText,
            ];
        }
    }

    return $modifiers;
}
```

**Step 2: Update parseConditions() to exclude skill-based advantages**

Modify `parseConditions()` to skip patterns that are skill-based:

```php
/**
 * Parse advantage/disadvantage conditions from feat description text.
 *
 * NOTE: Skill-based advantages like "advantage on Charisma (Deception) checks"
 * are handled by parseSkillAdvantages() and routed to modifiers instead.
 *
 * @return array<int, array<string, mixed>>
 */
private function parseConditions(string $text): array
{
    $conditions = [];

    // Pattern for "You have advantage on..."
    if (preg_match_all('/you have advantage on ([^.]+)/i', $text, $matches)) {
        foreach ($matches[1] as $match) {
            // Skip skill-based advantages - handled separately
            // Pattern: "Ability (Skill) checks" or "Ability (Skill) and Ability (Skill) checks"
            if (preg_match('/^[A-Z][a-z]+\s*\([^)]+\)(?:\s+and\s+[A-Z][a-z]+\s*\([^)]+\))?\s+checks?\s/i', $match)) {
                continue;
            }

            $conditions[] = [
                'effect_type' => 'advantage',
                'description' => trim($match),
            ];
        }
    }

    // Pattern for "doesn't impose disadvantage on..."
    if (preg_match_all('/(?:doesn\'t|does not) impose disadvantage on ([^.]+)/i', $text, $matches)) {
        foreach ($matches[1] as $match) {
            $conditions[] = [
                'effect_type' => 'negates_disadvantage',
                'description' => trim($match),
            ];
        }
    }

    // Pattern for "you have disadvantage on..." (less common but possible)
    if (preg_match_all('/you have disadvantage on ([^.]+)/i', $text, $matches)) {
        foreach ($matches[1] as $match) {
            // Skip skill-based disadvantages
            if (preg_match('/^[A-Z][a-z]+\s*\([^)]+\)(?:\s+and\s+[A-Z][a-z]+\s*\([^)]+\))?\s+checks?\s/i', $match)) {
                continue;
            }

            $conditions[] = [
                'effect_type' => 'disadvantage',
                'description' => trim($match),
            ];
        }
    }

    return $conditions;
}
```

**Step 3: Update parseFeat() to include skill advantages in modifiers**

Modify `parseFeat()` to merge skill advantages with other modifiers:

```php
private function parseFeat(SimpleXMLElement $element): array
{
    // ... existing code ...

    // Parse modifiers from XML elements
    $modifiersFromXml = $this->parseModifiers($element);

    // Parse passive score modifiers from description text
    $passiveScoreModifiers = $this->parsePassiveScoreModifiers($description);

    // Parse skill-based advantages from description text
    $skillAdvantageModifiers = $this->parseSkillAdvantages($description);

    return [
        'name' => (string) $element->name,
        'prerequisites' => isset($element->prerequisite) ? (string) $element->prerequisite : null,
        'description' => trim($description),
        'sources' => $sources,
        'modifiers' => array_merge($modifiersFromXml, $passiveScoreModifiers, $skillAdvantageModifiers),
        'proficiencies' => array_merge($proficienciesFromXml, $proficienciesFromText),
        'conditions' => $this->parseConditions($description),
        'spells' => $this->parseSpells($description),
    ];
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=it_parses_skill_based_advantages_as_modifiers_not_conditions
```

Expected: PASS.

**Step 5: Run existing parser tests for regression**

```bash
docker compose exec php php artisan test --filter=FeatXmlParserTest
```

Expected: All tests PASS.

**Step 6: Commit implementation**

```bash
git add app/Services/Parsers/FeatXmlParser.php
git commit -m "feat: route skill-based advantages to modifiers instead of conditions

Detects pattern 'advantage on Ability (Skill) checks' and creates
modifiers with skill_name and condition text. entity_conditions now
only contains D&D condition interactions (Issue #70)"
```

---

## Task 3: Update Importer to Handle Null Value Modifiers

**Files:**
- Modify: `app/Services/Importers/Concerns/ImportsModifiers.php`

**Step 1: Verify importer handles null value**

The current importer may require a `value` field. Check and update if needed to handle `skill_advantage` modifiers with null value:

```php
// In importEntityModifiers() method
$values = [
    'value' => $modData['value'] ?? null,  // Allow null for advantage/disadvantage
    'is_choice' => $modData['is_choice'] ?? false,
    'choice_count' => $modData['choice_count'] ?? null,
    'choice_constraint' => $modData['choice_constraint'] ?? null,
    'condition' => $modData['condition'] ?? null,
];
```

**Step 2: Write test to verify import with null value**

Add to `tests/Unit/Services/Importers/Concerns/ImportsModifiersTest.php`:

```php
#[Test]
public function it_imports_skill_advantage_modifier_with_null_value()
{
    $skill = Skill::factory()->create(['name' => 'Deception']);

    $entity = Feat::factory()->create();

    $importer = new class
    {
        use ImportsModifiers;

        public function import(Model $entity, array $data): void
        {
            $this->importEntityModifiers($entity, $data);
        }
    };

    $importer->import($entity, [
        [
            'modifier_category' => 'skill_advantage',
            'value' => null,
            'skill_name' => 'Deception',
            'condition' => 'when trying to pass yourself off as a different person',
        ],
    ]);

    $modifier = $entity->modifiers()->first();

    $this->assertNotNull($modifier);
    $this->assertEquals('skill_advantage', $modifier->modifier_category);
    $this->assertNull($modifier->value);
    $this->assertEquals($skill->id, $modifier->skill_id);
    $this->assertEquals('when trying to pass yourself off as a different person', $modifier->condition);
}
```

**Step 3: Run test**

```bash
docker compose exec php php artisan test --filter=it_imports_skill_advantage_modifier_with_null_value
```

Expected: PASS.

**Step 4: Commit**

```bash
git add app/Services/Importers/Concerns/ImportsModifiers.php tests/Unit/Services/Importers/Concerns/ImportsModifiersTest.php
git commit -m "feat: support skill_advantage modifiers with null value

Advantage/disadvantage modifiers don't have numeric values, only
skill associations and conditional text (Issue #70)"
```

---

## Task 4: Re-import and Verify

**Step 1: Re-import feats**

```bash
docker compose exec php php artisan import:feats
```

**Step 2: Verify Actor data in database**

```bash
docker compose exec php php artisan tinker --execute="
\$feat = App\Models\Feat::where('slug', 'actor')
    ->with(['modifiers.skill', 'conditions'])
    ->first();
echo 'Feat: ' . \$feat->name . \"\n\";
echo 'Modifiers: ' . \$feat->modifiers->count() . \"\n\";
foreach (\$feat->modifiers as \$m) {
    echo \"  {$m->modifier_category}:\" .
        (\$m->skill ? \" skill={$m->skill->name}\" : '') .
        (\$m->value ? \" value={$m->value}\" : '') .
        (\$m->condition ? \" condition='{$m->condition}'\" : '') . \"\n\";
}
echo 'Conditions: ' . \$feat->conditions->count() . \"\n\";
foreach (\$feat->conditions as \$c) {
    echo \"  {$c->effect_type}: {$c->description}\n\";
}
"
```

Expected output:
```
Feat: Actor
Modifiers: 3
  ability_score: ability=Charisma value=1
  skill_advantage: skill=Deception condition='when trying to pass yourself off as a different person'
  skill_advantage: skill=Performance condition='when trying to pass yourself off as a different person'
Conditions: 0
```

**Step 3: Verify API response**

```bash
curl -s "http://localhost:8080/api/v1/feats?filter=slug%20%3D%20actor&include=modifiers,conditions" | jq '.data[0] | {modifiers, conditions}'
```

Expected: Shows skill_advantage modifiers with skill associations, empty conditions array.

---

## Task 5: Run Full Test Suite and Final Commit

**Step 1: Run Unit-Pure suite**

```bash
docker compose exec php php artisan test --testsuite=Unit-Pure
```

**Step 2: Run Unit-DB suite**

```bash
docker compose exec php php artisan test --testsuite=Unit-DB
```

**Step 3: Run Pint**

```bash
docker compose exec php ./vendor/bin/pint
```

**Step 4: Update CHANGELOG.md**

Add under `[Unreleased]`:

```markdown
### Changed
- Skill-based advantages now stored in entity_modifiers instead of entity_conditions (Issue #70)
  - "Advantage on Charisma (Deception) checks" creates modifier with skill_id, not condition
  - entity_conditions reserved for D&D Condition interactions (Blinded, Charmed, etc.)
  - Actor feat now correctly shows skill_advantage modifiers for Deception/Performance
```

**Step 5: Final commit**

```bash
git add -A
git commit -m "feat: route skill-based advantages to entity_modifiers (Issue #70)

- Parser detects 'advantage on Ability (Skill) checks' patterns
- Creates skill_advantage modifiers with skill_name and condition
- parseConditions() skips skill-based patterns
- entity_conditions now only contains D&D condition interactions
- Actor feat correctly shows Deception/Performance advantages

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Step 6: Push**

```bash
git push origin main
```

**Step 7: Close GitHub issue**

```bash
gh issue close 70 --repo dfox288/dnd-rulebook-project --comment "Skill-based advantages now correctly route to entity_modifiers.

Actor feat shows:
- \`skill_advantage\`: skill=Deception, condition='when trying to pass yourself off as a different person'
- \`skill_advantage\`: skill=Performance, condition='when trying to pass yourself off as a different person'

entity_conditions is now reserved for D&D Condition interactions (Blinded, Charmed, etc.)."
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Failing parser test | `tests/Unit/Parsers/FeatXmlParserTest.php` |
| 2 | Parser implementation | `app/Services/Parsers/FeatXmlParser.php` |
| 3 | Importer null value support | `app/Services/Importers/Concerns/ImportsModifiers.php` |
| 4 | Re-import & verify | - |
| 5 | Full suite & commit | - |

---

## Notes on Semantic Correctness

After this change:

**entity_conditions** should contain:
- Advantage/disadvantage on saves against D&D conditions (Frightened, Charmed, etc.)
- Immunity to D&D conditions
- Resistance to D&D conditions
- All should have a `condition_id` populated

**entity_modifiers** should contain:
- Ability score bonuses (`ability_score`)
- Skill check bonuses (`skill`)
- Passive score bonuses (`passive_score`)
- Skill advantages/disadvantages (`skill_advantage`, `skill_disadvantage`)
- Combat bonuses (attack, damage, AC, etc.)

This separation makes the schema semantically correct and the data more useful for character builders.
