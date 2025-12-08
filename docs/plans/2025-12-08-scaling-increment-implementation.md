# Scaling Increment Parser Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Parse `scaling_increment` values (e.g., "1d6", "5") from spell "At Higher Levels" text to enable automatic damage scaling calculations.

**Architecture:** Create `ParsesScalingIncrement` trait with regex patterns, integrate into `SpellXmlParser`, apply parsed values to damage effects during import.

**Tech Stack:** Laravel 12.x, Pest 3.x, PHP 8.4, Regex

**Design Doc:** `../dnd-rulebook-project/docs/backend/plans/2025-12-08-scaling-increment-design.md`

---

## Task 1: Create ParsesScalingIncrement Trait with Tests

**Files:**
- Create: `app/Services/Parsers/Concerns/ParsesScalingIncrement.php`
- Create: `tests/Unit/Parsers/ParsesScalingIncrementTest.php`

### Step 1: Write failing test for dice notation parsing

Create `tests/Unit/Parsers/ParsesScalingIncrementTest.php`:

```php
<?php

namespace Tests\Unit\Parsers;

use PHPUnit\Framework\Attributes\Test;

// Create a test class that uses the trait
class ParsesScalingIncrementTestClass
{
    use \App\Services\Parsers\Concerns\ParsesScalingIncrement;

    public function parse(?string $text): ?string
    {
        return $this->parseScalingIncrement($text);
    }
}

class ParsesScalingIncrementTest extends \Tests\TestCase
{
    private ParsesScalingIncrementTestClass $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ParsesScalingIncrementTestClass();
    }

    #[Test]
    public function it_parses_1d6_dice_notation(): void
    {
        $text = 'When you cast this spell using a spell slot of 4th level or higher, the damage increases by 1d6 for each slot level above 3rd.';

        $result = $this->parser->parse($text);

        $this->assertEquals('1d6', $result);
    }
}
```

### Step 2: Run test to verify it fails

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/ParsesScalingIncrementTest.php
```

Expected: FAIL - trait does not exist

### Step 3: Create trait with minimal implementation

Create `app/Services/Parsers/Concerns/ParsesScalingIncrement.php`:

```php
<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing scaling increment from spell "At Higher Levels" text.
 *
 * Handles patterns like:
 * - "the damage increases by 1d6 for each slot level above 3rd" → "1d6"
 * - "both effects increase by 5 for each slot level above 1st" → "5"
 *
 * Used by: SpellXmlParser
 *
 * @see GitHub Issue #198
 */
trait ParsesScalingIncrement
{
    /**
     * Parse scaling increment from "At Higher Levels" text.
     *
     * @param  string|null  $higherLevels  The "At Higher Levels" text
     * @return string|null Dice notation (e.g., "1d6") or flat value (e.g., "5")
     */
    protected function parseScalingIncrement(?string $higherLevels): ?string
    {
        if (empty($higherLevels)) {
            return null;
        }

        // Pattern 1: Dice notation - "increases by 1d6 for each"
        if (preg_match('/increases?\s+by\s+(\d+d\d+)\s+for\s+each/i', $higherLevels, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
```

### Step 4: Run test to verify it passes

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/ParsesScalingIncrementTest.php
```

Expected: PASS

### Step 5: Add test for 3d6 notation

Add to test file:

```php
#[Test]
public function it_parses_3d6_dice_notation(): void
{
    $text = 'When you cast this spell using a spell slot of 7th level or higher, the damage increases by 3d6 for each slot level above 6th.';

    $result = $this->parser->parse($text);

    $this->assertEquals('3d6', $result);
}
```

### Step 6: Run test (should pass with existing implementation)

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/ParsesScalingIncrementTest.php
```

Expected: PASS (regex already handles this)

### Step 7: Add test for flat value parsing

Add to test file:

```php
#[Test]
public function it_parses_flat_value(): void
{
    $text = 'When you cast this spell using a spell slot of 2nd level or higher, both the temporary hit points and the cold damage increase by 5 for each slot level above 1st.';

    $result = $this->parser->parse($text);

    $this->assertEquals('5', $result);
}
```

### Step 8: Run test to verify it fails

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/ParsesScalingIncrementTest.php --filter="flat_value"
```

Expected: FAIL - returns null

### Step 9: Add flat value pattern to trait

Update `parseScalingIncrement()` in trait:

```php
protected function parseScalingIncrement(?string $higherLevels): ?string
{
    if (empty($higherLevels)) {
        return null;
    }

    // Pattern 1: Dice notation - "increases by 1d6 for each"
    if (preg_match('/increases?\s+by\s+(\d+d\d+)\s+for\s+each/i', $higherLevels, $matches)) {
        return $matches[1];
    }

    // Pattern 2: Flat value - "increase by 5 for each"
    if (preg_match('/increases?\s+by\s+(\d+)\s+for\s+each/i', $higherLevels, $matches)) {
        return $matches[1];
    }

    return null;
}
```

### Step 10: Run all tests

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/ParsesScalingIncrementTest.php
```

Expected: PASS (3 tests)

### Step 11: Add edge case tests

Add to test file:

```php
#[Test]
public function it_returns_null_for_target_scaling(): void
{
    $text = 'When you cast this spell using a spell slot of 3rd level or higher, you can target one additional creature for each slot level above 2nd.';

    $result = $this->parser->parse($text);

    $this->assertNull($result);
}

#[Test]
public function it_returns_null_for_duration_scaling(): void
{
    $text = 'If you cast this spell using a spell slot of 4th level or higher, the duration is concentration, up to 10 minutes.';

    $result = $this->parser->parse($text);

    $this->assertNull($result);
}

#[Test]
public function it_returns_null_for_null_input(): void
{
    $result = $this->parser->parse(null);

    $this->assertNull($result);
}

#[Test]
public function it_returns_null_for_empty_string(): void
{
    $result = $this->parser->parse('');

    $this->assertNull($result);
}

#[Test]
public function it_parses_damage_type_prefix(): void
{
    // "the cold damage increases by 1d6" should still match
    $text = 'When you cast this spell using a spell slot of 2nd level or higher, the cold damage increases by 1d6 for each slot level above 1st.';

    $result = $this->parser->parse($text);

    $this->assertEquals('1d6', $result);
}
```

### Step 12: Run all tests

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/ParsesScalingIncrementTest.php
```

Expected: PASS (8 tests)

### Step 13: Commit

```bash
git add app/Services/Parsers/Concerns/ParsesScalingIncrement.php tests/Unit/Parsers/ParsesScalingIncrementTest.php
git commit -m "feat(#198): Add ParsesScalingIncrement trait

- Parse dice notation (1d6, 3d6) from higher levels text
- Parse flat values (5) from higher levels text
- Return null for non-matching patterns (target scaling, duration)
- 8 unit tests

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: Integrate Trait into SpellXmlParser

**Files:**
- Modify: `app/Services/Parsers/SpellXmlParser.php`
- Test: `tests/Unit/Parsers/SpellXmlParserTest.php`

### Step 1: Write failing integration test

Add to `tests/Unit/Parsers/SpellXmlParserTest.php` (or create if needed):

```php
#[Test]
public function it_parses_scaling_increment_for_damage_effects(): void
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <spell>
        <name>Test Spell</name>
        <level>3</level>
        <school>EV</school>
        <time>1 action</time>
        <range>150 feet</range>
        <components>V, S, M (a tiny ball of bat guano)</components>
        <duration>Instantaneous</duration>
        <roll description="Fire Damage">8d6</roll>
        <text>A bright streak flashes from your finger.</text>
        <text>At Higher Levels: When you cast this spell using a spell slot of 4th level or higher, the damage increases by 1d6 for each slot level above 3rd.</text>
    </spell>
</compendium>
XML;

    $parser = new \App\Services\Parsers\SpellXmlParser();
    $spells = $parser->parse($xml);

    $this->assertCount(1, $spells);
    $this->assertCount(1, $spells[0]['effects']);
    $this->assertEquals('1d6', $spells[0]['effects'][0]['scaling_increment']);
}
```

### Step 2: Run test to verify it fails

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/SpellXmlParserTest.php --filter="scaling_increment"
```

Expected: FAIL - scaling_increment is null

### Step 3: Add trait to SpellXmlParser

Update `app/Services/Parsers/SpellXmlParser.php`:

At the top, add the use statement:

```php
use App\Services\Parsers\Concerns\ParsesScalingIncrement;
```

In the class, add the trait:

```php
class SpellXmlParser
{
    use LoadsLookupData;
    use ParsesDataTables;
    use ParsesProjectileScaling;
    use ParsesSavingThrows;
    use ParsesScalingIncrement;  // ADD THIS
    use ParsesSourceCitations;
```

### Step 4: Update parseSpell to pass higherLevels to parseRollElements

In `parseSpell()` method, change line ~106:

```php
// OLD:
$effects = $this->parseRollElements($element);

// NEW:
$effects = $this->parseRollElements($element, $higherLevels);
```

### Step 5: Update parseRollElements to accept and use higherLevels

Change method signature and add scaling logic at the end:

```php
private function parseRollElements(SimpleXMLElement $element, ?string $higherLevels = null): array
{
    $effects = [];
    $spellLevel = (int) $element->level;

    foreach ($element->roll as $roll) {
        $description = (string) $roll['description'];
        $diceFormula = (string) $roll;
        $rollLevel = isset($roll['level']) ? (int) $roll['level'] : null;

        // Determine effect type based on description
        $effectType = $this->determineEffectType($description);

        // Extract damage type name from description (e.g., "Acid Damage" -> "Acid")
        $damageTypeName = $this->extractDamageTypeName($description);

        // Determine scaling type and levels
        if ($rollLevel !== null) {
            if ($spellLevel === 0 && in_array($rollLevel, [0, 5, 11, 17])) {
                // Cantrip scaling by character level
                $scalingType = 'character_level';
                $minCharacterLevel = $rollLevel;
                $minSpellSlot = null;
            } else {
                // Spell slot level scaling
                $scalingType = 'spell_slot_level';
                $minCharacterLevel = null;
                $minSpellSlot = $rollLevel;
            }
        } else {
            // No scaling
            $scalingType = 'none';
            $minCharacterLevel = null;
            $minSpellSlot = null;
        }

        $effects[] = [
            'effect_type' => $effectType,
            'description' => $description,
            'damage_type_name' => $damageTypeName,
            'dice_formula' => $diceFormula,
            'base_value' => null,
            'scaling_type' => $scalingType,
            'min_character_level' => $minCharacterLevel,
            'min_spell_slot' => $minSpellSlot,
            'scaling_increment' => null, // Will be set below for damage effects
        ];
    }

    // Apply scaling increment to damage effects
    $scalingIncrement = $this->parseScalingIncrement($higherLevels);

    if ($scalingIncrement !== null) {
        foreach ($effects as &$effect) {
            if ($effect['effect_type'] === 'damage') {
                $effect['scaling_increment'] = $scalingIncrement;
            }
        }
        unset($effect); // Break reference
    }

    return $effects;
}
```

### Step 6: Run test to verify it passes

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/SpellXmlParserTest.php --filter="scaling_increment"
```

Expected: PASS

### Step 7: Add test for non-damage effects not getting scaling

Add to test file:

```php
#[Test]
public function it_does_not_apply_scaling_increment_to_healing_effects(): void
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <spell>
        <name>Cure Wounds</name>
        <level>1</level>
        <school>EV</school>
        <time>1 action</time>
        <range>Touch</range>
        <components>V, S</components>
        <duration>Instantaneous</duration>
        <roll description="Healing">1d8</roll>
        <text>A creature you touch regains hit points.</text>
        <text>At Higher Levels: When you cast this spell using a spell slot of 2nd level or higher, the healing increases by 1d8 for each slot level above 1st.</text>
    </spell>
</compendium>
XML;

    $parser = new \App\Services\Parsers\SpellXmlParser();
    $spells = $parser->parse($xml);

    $this->assertCount(1, $spells);
    $this->assertEquals('healing', $spells[0]['effects'][0]['effect_type']);
    $this->assertNull($spells[0]['effects'][0]['scaling_increment']);
}
```

### Step 8: Run test (should pass - healing effects excluded)

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/SpellXmlParserTest.php --filter="healing_effects"
```

Expected: PASS

### Step 9: Run full parser test suite

```bash
docker compose exec php ./vendor/bin/pest tests/Unit/Parsers/SpellXmlParserTest.php
```

Expected: All PASS

### Step 10: Commit

```bash
git add app/Services/Parsers/SpellXmlParser.php tests/Unit/Parsers/SpellXmlParserTest.php
git commit -m "feat(#198): Integrate scaling increment into SpellXmlParser

- Add ParsesScalingIncrement trait to parser
- Pass higherLevels to parseRollElements
- Apply scaling_increment to damage effects only
- 2 integration tests

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: Verify with Real Data Import

**Files:**
- Test: `tests/Feature/Importers/SpellImporterTest.php`

### Step 1: Add integration test for Fireball scaling

Add to `tests/Feature/Importers/SpellImporterTest.php`:

```php
#[Test]
public function it_imports_scaling_increment_for_fireball(): void
{
    // This test uses actual XML import
    $this->artisan('import:spells')->assertSuccessful();

    $fireball = \App\Models\Spell::where('slug', 'fireball')->first();

    $this->assertNotNull($fireball, 'Fireball spell should exist');
    $this->assertNotNull($fireball->higher_levels, 'Fireball should have higher_levels text');

    $damageEffect = $fireball->effects->firstWhere('effect_type', 'damage');

    $this->assertNotNull($damageEffect, 'Fireball should have a damage effect');
    $this->assertEquals('1d6', $damageEffect->scaling_increment);
}
```

### Step 2: Run import test

```bash
docker compose exec php ./vendor/bin/pest tests/Feature/Importers/SpellImporterTest.php --filter="scaling_increment_for_fireball"
```

Expected: PASS (after fresh import)

### Step 3: Run Unit-DB test suite

```bash
docker compose exec php ./vendor/bin/pest --testsuite=Unit-DB
```

Expected: All PASS

### Step 4: Run Feature-DB test suite

```bash
docker compose exec php ./vendor/bin/pest --testsuite=Feature-DB
```

Expected: All PASS

### Step 5: Commit

```bash
git add tests/Feature/Importers/SpellImporterTest.php
git commit -m "test(#198): Add integration test for scaling increment import

- Verify Fireball imports with scaling_increment = '1d6'

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: Update CHANGELOG and Create PR

**Files:**
- Modify: `CHANGELOG.md`

### Step 1: Update CHANGELOG

Add under `[Unreleased]`:

```markdown
### Added
- Spell scaling increment parsing (#198)
  - New `ParsesScalingIncrement` trait extracts dice/flat values from "At Higher Levels" text
  - `scaling_increment` field now populated for ~79 damage-scaling spells
  - Supports dice notation (1d6, 3d6) and flat values (5)
```

### Step 2: Run Pint

```bash
docker compose exec php ./vendor/bin/pint
```

### Step 3: Commit changelog

```bash
git add CHANGELOG.md
git commit -m "docs(#198): Update CHANGELOG for scaling increment feature

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

### Step 4: Create feature branch and push

```bash
git checkout -b feature/issue-198-scaling-increment
git push -u origin feature/issue-198-scaling-increment
```

### Step 5: Create PR

```bash
gh pr create --title "feat(#198): Parse spell scaling from At Higher Levels text" --body "$(cat <<'EOF'
## Summary
- Add `ParsesScalingIncrement` trait to extract scaling values from spell text
- Integrate into `SpellXmlParser` to populate `scaling_increment` field
- Apply to damage effects during import

## Changes
- New trait: `ParsesScalingIncrement` with regex patterns for dice (1d6) and flat (5) values
- Modified: `SpellXmlParser` to use trait and apply to damage effects
- New tests: 8 unit tests + 2 integration tests

## Test Plan
- [x] Parses 1d6 dice notation
- [x] Parses 3d6 dice notation
- [x] Parses flat value (5)
- [x] Returns null for target scaling
- [x] Returns null for duration scaling
- [x] Only applies to damage effects
- [x] Fireball imports with scaling_increment = "1d6"

## Data Impact
After re-import, ~79 spells will have populated `scaling_increment`:
- 71 with dice notation (1d6, 1d8, 3d6, etc.)
- 8 with flat values (5, etc.)

Closes #198

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Summary

| Task | Description | Tests |
|------|-------------|-------|
| 1 | Create ParsesScalingIncrement trait | 8 unit tests |
| 2 | Integrate into SpellXmlParser | 2 integration tests |
| 3 | Verify with real data import | 1 feature test |
| 4 | CHANGELOG + PR | - |

**Total new tests:** 11
**Estimated time:** 30-45 minutes
