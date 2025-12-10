<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\FeatXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for parsing movement cost modifiers from feat description text.
 *
 * These modifiers represent changes to how movement costs are calculated,
 * such as climbing not costing extra movement, or standing from prone
 * costing less movement than normal.
 */
#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ParsesMovementModifiersTest extends TestCase
{
    private FeatXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FeatXmlParser;
    }

    #[Test]
    public function it_parses_climbing_no_extra_movement()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Athlete (Dexterity)</name>
        <text>You have undergone extensive physical training to gain the following benefits:

	• Increase your Strength or Dexterity score by 1, to a maximum of 20.

	• When you are prone, standing up uses only 5 feet of your movement.

	• Climbing doesn't cost you extra movement.

	• You can make a running long jump or a running high jump after moving only 5 feet on foot, rather than 10 feet.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="ability score">dexterity +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);

        $movementMods = $feats[0]['movement_modifiers'];
        $climbingMod = array_values(array_filter($movementMods, fn ($m) => $m['activity'] === 'climbing'));

        $this->assertNotEmpty($climbingMod, 'Should have climbing movement modifier');
        $climbingMod = $climbingMod[0];
        $this->assertEquals('movement_cost', $climbingMod['type']);
        $this->assertEquals('climbing', $climbingMod['activity']);
        $this->assertEquals('normal', $climbingMod['cost']);
    }

    #[Test]
    public function it_parses_standing_from_prone_reduced_cost()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Athlete (Strength)</name>
        <text>You have undergone extensive physical training to gain the following benefits:

	• Increase your Strength or Dexterity score by 1, to a maximum of 20.

	• When you are prone, standing up uses only 5 feet of your movement.

	• Climbing doesn't cost you extra movement.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="ability score">strength +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);

        $movementMods = $feats[0]['movement_modifiers'];
        $standingMod = array_values(array_filter($movementMods, fn ($m) => $m['activity'] === 'standing_from_prone'));

        $this->assertNotEmpty($standingMod, 'Should have standing from prone movement modifier');
        $standingMod = $standingMod[0];
        $this->assertEquals('movement_cost', $standingMod['type']);
        $this->assertEquals('standing_from_prone', $standingMod['activity']);
        $this->assertEquals(5, $standingMod['cost']);
    }

    #[Test]
    public function it_parses_difficult_terrain_no_extra_movement_with_condition()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Mobile</name>
        <text>You are exceptionally speedy and agile. You gain the following benefits:

	• Your speed increases by 10 feet.

	• When you use the Dash action, difficult terrain doesn't cost you extra movement on that turn.

	• When you make a melee attack against a creature, you don't provoke opportunity attacks from that creature for the rest of the turn, whether you hit or not.

Source:	Player's Handbook (2014) p. 168</text>
        <modifier category="bonus">speed +10</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);

        $movementMods = $feats[0]['movement_modifiers'];
        $difficultTerrainMod = array_values(array_filter($movementMods, fn ($m) => ($m['activity'] ?? null) === 'difficult_terrain'));

        $this->assertNotEmpty($difficultTerrainMod, 'Should have difficult terrain movement modifier');
        $difficultTerrainMod = $difficultTerrainMod[0];
        $this->assertEquals('movement_cost', $difficultTerrainMod['type']);
        $this->assertEquals('difficult_terrain', $difficultTerrainMod['activity']);
        $this->assertEquals('normal', $difficultTerrainMod['cost']);
        $this->assertStringContainsString('Dash', $difficultTerrainMod['condition']);
    }

    #[Test]
    public function it_parses_multiple_movement_modifiers_from_same_feat()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Athlete (Dexterity)</name>
        <text>You have undergone extensive physical training to gain the following benefits:

	• Increase your Strength or Dexterity score by 1, to a maximum of 20.

	• When you are prone, standing up uses only 5 feet of your movement.

	• Climbing doesn't cost you extra movement.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="ability score">dexterity +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);

        // Should have both climbing and standing from prone modifiers
        $movementMods = $feats[0]['movement_modifiers'];
        $this->assertCount(2, $movementMods);

        $activities = array_column($movementMods, 'activity');
        $this->assertContains('climbing', $activities);
        $this->assertContains('standing_from_prone', $activities);
    }

    #[Test]
    public function it_returns_empty_movement_modifiers_for_feats_without_movement_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger, you gain the following benefits:

	• You gain a +5 bonus to initiative.

	• You can't be surprised while you are conscious.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="bonus">initiative +5</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);
        $this->assertEmpty($feats[0]['movement_modifiers']);
    }

    #[Test]
    public function it_parses_swimming_no_extra_movement()
    {
        // This pattern might exist in some feats or racial traits
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Test Swimmer Feat</name>
        <text>You gain the following benefit:

	• Swimming doesn't cost you extra movement.

Source:	Test Book p. 1</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);

        $movementMods = $feats[0]['movement_modifiers'];
        $swimmingMod = array_values(array_filter($movementMods, fn ($m) => $m['activity'] === 'swimming'));

        $this->assertNotEmpty($swimmingMod, 'Should have swimming movement modifier');
        $swimmingMod = $swimmingMod[0];
        $this->assertEquals('movement_cost', $swimmingMod['type']);
        $this->assertEquals('swimming', $swimmingMod['activity']);
        $this->assertEquals('normal', $swimmingMod['cost']);
    }

    #[Test]
    public function it_parses_running_jump_reduced_distance()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Athlete (Dexterity)</name>
        <text>You have undergone extensive physical training to gain the following benefits:

	• You can make a running long jump or a running high jump after moving only 5 feet on foot, rather than 10 feet.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="ability score">dexterity +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);

        $movementMods = $feats[0]['movement_modifiers'];
        $jumpMod = array_values(array_filter($movementMods, fn ($m) => $m['activity'] === 'running_jump'));

        $this->assertNotEmpty($jumpMod, 'Should have running jump movement modifier');
        $jumpMod = $jumpMod[0];
        $this->assertEquals('movement_cost', $jumpMod['type']);
        $this->assertEquals('running_jump', $jumpMod['activity']);
        $this->assertEquals(5, $jumpMod['cost']);
    }

    #[Test]
    public function it_parses_speed_increase_bonus()
    {
        // Mobile feat: "Your speed increases by 10 feet."
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Mobile</name>
        <text>You are exceptionally speedy and agile. You gain the following benefits:

	• Your speed increases by 10 feet.

	• When you use the Dash action, difficult terrain doesn't cost you extra movement on that turn.

Source:	Player's Handbook (2014) p. 168</text>
        <modifier category="bonus">speed +10</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);

        $movementMods = $feats[0]['movement_modifiers'];
        $speedBonus = array_values(array_filter($movementMods, fn ($m) => $m['type'] === 'speed_bonus'));

        $this->assertNotEmpty($speedBonus, 'Should have speed bonus modifier');
        $speedBonus = $speedBonus[0];
        $this->assertEquals('speed_bonus', $speedBonus['type']);
        $this->assertEquals(10, $speedBonus['value']);
        $this->assertEquals('walk', $speedBonus['movement_type']);
    }

    #[Test]
    public function it_parses_walking_speed_increase()
    {
        // Squat Nimbleness: "Increase your walking speed by 5 feet."
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Squat Nimbleness (Dexterity)</name>
        <text>You are uncommonly nimble for your race. You gain the following benefits:

	• Increase your Strength or Dexterity score by 1, to a maximum of 20.

	• Increase your walking speed by 5 feet.

Source:	Xanathar's Guide to Everything p. 75</text>
        <modifier category="ability score">dexterity +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('movement_modifiers', $feats[0]);

        $movementMods = $feats[0]['movement_modifiers'];
        $speedBonus = array_values(array_filter($movementMods, fn ($m) => $m['type'] === 'speed_bonus'));

        $this->assertNotEmpty($speedBonus, 'Should have speed bonus modifier');
        $speedBonus = $speedBonus[0];
        $this->assertEquals('speed_bonus', $speedBonus['type']);
        $this->assertEquals(5, $speedBonus['value']);
        $this->assertEquals('walk', $speedBonus['movement_type']);
    }
}
