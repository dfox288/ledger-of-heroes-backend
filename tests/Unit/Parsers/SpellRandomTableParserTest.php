<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SpellXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class SpellRandomTableParserTest extends TestCase
{
    private SpellXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpellXmlParser;
    }

    #[Test]
    public function it_detects_random_table_in_spell_description(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Prismatic Spray</name>
    <level>7</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Self (60-foot cone)</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>Eight multicolored rays of light flash from your hand. Each ray is a different color and has a different power and purpose. Each creature in a 60-foot cone must make a Dexterity saving throw. For each target, roll a d8 to determine which color ray affects it.

d8 | Power
1 | Red: The target takes 10d6 fire damage on a failed save, or half as much damage on a successful one.
2 | Orange: The target takes 10d6 acid damage on a failed save, or half as much damage on a successful one.
3 | Yellow: The target takes 10d6 lightning damage on a failed save, or half as much damage on a successful one.
4 | Green: The target takes 10d6 poison damage on a failed save, or half as much damage on a successful one.
5 | Blue: The target takes 10d6 cold damage on a failed save, or half as much damage on a successful one.
6 | Indigo: On a failed save, the target is restrained.
7 | Violet: On a failed save, the target is blinded.
8 | Special: The target is struck by two rays. Roll twice more, rerolling any 8.</text>
  </spell>
</compendium>
XML;

        $spells = $this->parser->parse($xml);

        $this->assertCount(1, $spells);
        $spell = $spells[0];

        // Verify spell has random_tables array
        $this->assertArrayHasKey('random_tables', $spell);
        $this->assertCount(1, $spell['random_tables']);

        // Verify table structure
        $table = $spell['random_tables'][0];
        $this->assertEquals('Power', $table['table_name']);
        $this->assertEquals('d8', $table['dice_type']);
        $this->assertCount(8, $table['entries']);

        // Verify first entry
        $this->assertEquals(1, $table['entries'][0]['roll_min']);
        $this->assertEquals(1, $table['entries'][0]['roll_max']);
        $this->assertStringContainsString('Red', $table['entries'][0]['result_text']);
        $this->assertStringContainsString('10d6 fire damage', $table['entries'][0]['result_text']);

        // Verify special entry (last one)
        $lastEntry = $table['entries'][7];
        $this->assertEquals(8, $lastEntry['roll_min']);
        $this->assertEquals(8, $lastEntry['roll_max']);
        $this->assertStringContainsString('Special', $lastEntry['result_text']);
        $this->assertStringContainsString('two rays', $lastEntry['result_text']);
    }

    #[Test]
    public function it_parses_spell_without_random_tables(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Fireball</name>
    <level>3</level>
    <school>EV</school>
    <time>1 action</time>
    <range>150 feet</range>
    <components>V, S, M (a tiny ball of bat guano and sulfur)</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>A bright streak flashes from your pointing finger to a point you choose within range and then blossoms with a low roar into an explosion of flame.</text>
  </spell>
</compendium>
XML;

        $spells = $this->parser->parse($xml);

        $this->assertCount(1, $spells);
        $spell = $spells[0];

        // Spell should have empty random_tables array
        $this->assertArrayHasKey('random_tables', $spell);
        $this->assertEmpty($spell['random_tables']);
    }

    #[Test]
    public function it_preserves_spell_description_with_table(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Prismatic Spray</name>
    <level>7</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Self (60-foot cone)</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>Eight multicolored rays of light flash from your hand.

d8 | Power
1 | Red: Fire damage
2 | Orange: Acid damage</text>
  </spell>
</compendium>
XML;

        $spells = $this->parser->parse($xml);
        $spell = $spells[0];

        // Description should still be complete
        $this->assertStringContainsString('Eight multicolored rays', $spell['description']);
        $this->assertStringContainsString('d8 | Power', $spell['description']);
        $this->assertStringContainsString('Red: Fire damage', $spell['description']);
    }

    #[Test]
    public function it_handles_multiple_tables_in_single_spell(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Wild Magic Surge</name>
    <level>1</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Self</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer</classes>
    <text>Roll on the Wild Magic table.

d20 | Effect Type
1-5 | Minor effect
6-10 | Moderate effect

d6 | Damage Type
1 | Fire
2 | Cold
3 | Lightning</text>
  </spell>
</compendium>
XML;

        $spells = $this->parser->parse($xml);
        $spell = $spells[0];

        // Should detect both tables
        $this->assertArrayHasKey('random_tables', $spell);
        $this->assertCount(2, $spell['random_tables']);

        // First table
        $this->assertEquals('Effect Type', $spell['random_tables'][0]['table_name']);
        $this->assertEquals('d20', $spell['random_tables'][0]['dice_type']);

        // Second table
        $this->assertEquals('Damage Type', $spell['random_tables'][1]['table_name']);
        $this->assertEquals('d6', $spell['random_tables'][1]['dice_type']);
    }

    #[Test]
    public function it_handles_table_with_range_entries(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Confusion</name>
    <level>4</level>
    <school>EN</school>
    <time>1 action</time>
    <range>90 feet</range>
    <components>V, S, M (three nut shells)</components>
    <duration>Concentration, up to 1 minute</duration>
    <classes>Bard, Druid, Sorcerer, Wizard</classes>
    <text>At the end of each of its turns, an affected target can make a Wisdom saving throw. If it succeeds, this effect ends for that target. Roll a d10 to determine what it does on its turn.

d10 | Behavior
1 | The creature uses all its movement to move in a random direction.
2-6 | The creature doesn't move or take actions this turn.
7-8 | The creature uses its action to make a melee attack against a randomly determined creature within its reach.
9-10 | The creature can act and move normally.</text>
  </spell>
</compendium>
XML;

        $spells = $this->parser->parse($xml);
        $spell = $spells[0];

        $this->assertArrayHasKey('random_tables', $spell);
        $this->assertCount(1, $spell['random_tables']);

        $table = $spell['random_tables'][0];
        $this->assertEquals('Behavior', $table['table_name']);
        $this->assertEquals('d10', $table['dice_type']);
        $this->assertCount(4, $table['entries']);

        // Check range entry
        $rangeEntry = $table['entries'][1];
        $this->assertEquals(2, $rangeEntry['roll_min']);
        $this->assertEquals(6, $rangeEntry['roll_max']);
        $this->assertStringContainsString("doesn't move", $rangeEntry['result_text']);
    }
}
