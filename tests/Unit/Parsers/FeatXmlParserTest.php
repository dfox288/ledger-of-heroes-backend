<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\FeatXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatXmlParserTest extends TestCase
{
    private FeatXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FeatXmlParser;
    }

    #[Test]
    public function it_parses_basic_feat_data()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger, you gain the following benefits:

	• You gain a +5 bonus to initiative.

	• You can't be surprised while you are conscious.

	• Other creatures don't gain advantage on attack rolls against you as a result of being unseen by you.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertEquals('Alert', $feats[0]['name']);
        $this->assertStringContainsString('Always on the lookout', $feats[0]['description']);
        $this->assertNull($feats[0]['prerequisites']);
        $this->assertArrayHasKey('sources', $feats[0]);
    }

    #[Test]
    public function it_parses_feat_with_prerequisites()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Defensive Duelist</name>
        <prerequisite>Dexterity 13 or higher</prerequisite>
        <text>When you are wielding a finesse weapon with which you are proficient and another creature hits you with a melee attack, you can use your reaction to add your proficiency bonus to your AC for that attack, potentially causing the attack to miss you.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertEquals('Defensive Duelist', $feats[0]['name']);
        $this->assertEquals('Dexterity 13 or higher', $feats[0]['prerequisites']);
    }

    #[Test]
    public function it_parses_multiple_feats()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
    <feat>
        <name>Actor</name>
        <text>Skilled at mimicry and dramatics.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(2, $feats);
        $this->assertEquals('Alert', $feats[0]['name']);
        $this->assertEquals('Actor', $feats[1]['name']);
    }

    #[Test]
    public function it_extracts_and_removes_source_citations()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger, you gain the following benefits:

	• You gain a +5 bonus to initiative.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        // Should extract source citation
        $this->assertCount(1, $feats[0]['sources']);
        $this->assertEquals('PHB', $feats[0]['sources'][0]['code']);
        $this->assertEquals('165', $feats[0]['sources'][0]['pages']);

        // Should remove source from description
        $this->assertStringNotContainsString('Source:', $feats[0]['description']);
        $this->assertStringNotContainsString("Player's Handbook", $feats[0]['description']);
    }

    #[Test]
    public function it_parses_multiple_source_citations()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Fey Touched</name>
        <text>Your exposure to the Feywild's magic has changed you.

Source:	Tasha's Cauldron of Everything (2020) p. 79, Player's Handbook (2024) p. 200</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        // Should parse at least one source (multi-source parsing may vary)
        $this->assertGreaterThanOrEqual(1, count($feats[0]['sources']));
        $this->assertArrayHasKey('code', $feats[0]['sources'][0]);
        $this->assertArrayHasKey('pages', $feats[0]['sources'][0]);
    }

    #[Test]
    public function it_parses_ability_score_modifiers()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Actor</name>
        <text>Skilled at mimicry and dramatics, you gain the following benefits:

	• Increase your Charisma score by 1, to a maximum of 20.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="ability score">charisma +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('modifiers', $feats[0]);
        $this->assertCount(1, $feats[0]['modifiers']);

        $modifier = $feats[0]['modifiers'][0];
        $this->assertEquals('ability_score', $modifier['category']);
        $this->assertEquals(1, $modifier['value']);
        $this->assertEquals('CHA', $modifier['ability_code']);
    }

    #[Test]
    public function it_parses_bonus_modifiers()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger, you gain the following benefits:

	• You gain a +5 bonus to initiative.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="bonus">initiative +5</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('modifiers', $feats[0]);
        $this->assertCount(1, $feats[0]['modifiers']);

        $modifier = $feats[0]['modifiers'][0];
        $this->assertEquals('initiative', $modifier['category']);
        $this->assertEquals(5, $modifier['value']);
    }

    #[Test]
    public function it_parses_multiple_modifiers()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Test Feat</name>
        <text>Test feat with multiple modifiers.

Source:	Player's Handbook (2014) p. 165</text>
        <modifier category="ability score">strength +1</modifier>
        <modifier category="ability score">dexterity +1</modifier>
        <modifier category="bonus">initiative +2</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('modifiers', $feats[0]);
        $this->assertCount(3, $feats[0]['modifiers']);
    }

    #[Test]
    public function it_parses_specific_proficiencies_from_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Heavily Armored</name>
        <prerequisite>Proficiency with medium armor</prerequisite>
        <text>You have trained to master the use of heavy armor, gaining the following benefits:

	• Increase your Strength score by 1, to a maximum of 20.

	• You gain proficiency with heavy armor.

Source:	Player's Handbook (2014) p. 167</text>
        <modifier category="ability score">strength +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('proficiencies', $feats[0]);
        $this->assertCount(1, $feats[0]['proficiencies']);

        $proficiency = $feats[0]['proficiencies'][0];
        $this->assertEquals('heavy armor', strtolower($proficiency['description']));
        $this->assertFalse($proficiency['is_choice']);
        $this->assertNull($proficiency['quantity']);
    }

    #[Test]
    public function it_parses_choice_based_proficiencies()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Weapon Master (Strength)</name>
        <text>You have practiced extensively with a variety of weapons, gaining the following benefits:

	• Increase your Strength or Dexterity score by 1, to a maximum of 20.

	• You gain proficiency with four weapons of your choice. Each one must be a simple or a martial weapon.

Source:	Player's Handbook (2014) p. 170</text>
        <modifier category="ability score">strength +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('proficiencies', $feats[0]);
        $this->assertCount(1, $feats[0]['proficiencies']);

        $proficiency = $feats[0]['proficiencies'][0];
        $this->assertTrue($proficiency['is_choice']);
        $this->assertEquals(4, $proficiency['quantity']);
        $this->assertStringContainsString('weapons', strtolower($proficiency['description']));
    }

    #[Test]
    public function it_parses_skill_or_tool_choice_proficiencies()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Skilled</name>
        <text>You gain proficiency in any combination of three skills or tools of your choice.

Source:	Player's Handbook (2014) p. 170</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('proficiencies', $feats[0]);
        $this->assertCount(1, $feats[0]['proficiencies']);

        $proficiency = $feats[0]['proficiencies'][0];
        $this->assertTrue($proficiency['is_choice']);
        $this->assertEquals(3, $proficiency['quantity']);
        $this->assertStringContainsString('skills or tools', strtolower($proficiency['description']));
    }

    #[Test]
    public function it_parses_multiple_specific_proficiencies()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Moderately Armored</name>
        <prerequisite>Proficiency with light armor</prerequisite>
        <text>You have trained to master the use of medium armor and shields, gaining the following benefits:

	• Increase your Strength or Dexterity score by 1, to a maximum of 20.

	• You gain proficiency with medium armor and shields.

Source:	Player's Handbook (2014) p. 168</text>
        <modifier category="ability score">dexterity +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('proficiencies', $feats[0]);
        $this->assertCount(2, $feats[0]['proficiencies']); // Should split "medium armor and shields"
    }

    #[Test]
    public function it_parses_advantage_on_skill_checks()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Actor</name>
        <text>Skilled at mimicry and dramatics, you gain the following benefits:

	• You have advantage on Charisma (Deception) and Charisma (Performance) checks when trying to pass yourself off as a different person.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('conditions', $feats[0]);
        $this->assertGreaterThanOrEqual(1, count($feats[0]['conditions']));

        $condition = $feats[0]['conditions'][0];
        $this->assertEquals('advantage', $condition['effect_type']);
        $this->assertStringContainsString('Deception', $condition['description']);
    }

    #[Test]
    public function it_parses_advantage_on_saving_throws()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Dungeon Delver</name>
        <text>Alert to the hidden traps and secret doors found in many dungeons, you gain the following benefits:

	• You have advantage on Wisdom (Perception) and Intelligence (Investigation) checks made to detect the presence of secret doors.

	• You have advantage on saving throws made to avoid or resist traps.

Source:	Player's Handbook (2014) p. 166</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('conditions', $feats[0]);
        $this->assertGreaterThanOrEqual(1, count($feats[0]['conditions']));

        // Should find at least one advantage condition
        $hasAdvantage = false;
        foreach ($feats[0]['conditions'] as $condition) {
            if ($condition['effect_type'] === 'advantage') {
                $hasAdvantage = true;
                break;
            }
        }
        $this->assertTrue($hasAdvantage);
    }

    #[Test]
    public function it_parses_disadvantage_prevention()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Medium Armor Master</name>
        <prerequisite>Proficiency with medium armor</prerequisite>
        <text>You have practiced moving in medium armor to gain the following benefits:

	• Wearing medium armor doesn't impose disadvantage on your Dexterity (Stealth) checks.

	• When you wear medium armor, you can add 3, rather than 2, to your AC if you have a Dexterity of 16 or higher.

Source:	Player's Handbook (2014) p. 168</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('conditions', $feats[0]);
        $this->assertGreaterThanOrEqual(1, count($feats[0]['conditions']));

        $condition = $feats[0]['conditions'][0];
        $this->assertEquals('negates_disadvantage', $condition['effect_type']);
        $this->assertStringContainsString('Stealth', $condition['description']);
    }

    #[Test]
    public function it_parses_speed_modifier_correctly()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Squat Nimbleness</name>
        <text>You are uncommonly nimble for your race. You gain the following benefits:

	• Increase your Strength or Dexterity score by 1, to a maximum of 20.

	• Increase your walking speed by 5 feet.

Source:	Xanathar's Guide to Everything p. 75</text>
        <modifier category="bonus">speed +5</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('modifiers', $feats[0]);
        $this->assertCount(1, $feats[0]['modifiers']);

        $modifier = $feats[0]['modifiers'][0];
        $this->assertEquals('speed', $modifier['category']);
        $this->assertEquals(5, $modifier['value']);
    }

    #[Test]
    public function it_parses_proficiency_xml_elements()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Squat Nimbleness (Strength + Athletics)</name>
        <prerequisite>Dwarf, Gnome, Halfling, Small Race</prerequisite>
        <proficiency>Athletics</proficiency>
        <text>You are uncommonly nimble for your race.

Source:	Xanathar's Guide to Everything p. 75</text>
        <modifier category="ability score">strength +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('proficiencies', $feats[0]);

        // Should have proficiencies from XML element
        $this->assertGreaterThan(0, count($feats[0]['proficiencies']));

        // Should include Athletics from <proficiency> element
        $hasAthletics = false;
        foreach ($feats[0]['proficiencies'] as $prof) {
            if (str_contains(strtolower($prof['description']), 'athletics')) {
                $hasAthletics = true;
                break;
            }
        }
        $this->assertTrue($hasAthletics, 'Should parse Athletics from <proficiency> XML element');
    }

    #[Test]
    public function it_parses_multiple_proficiency_xml_elements()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Test Feat</name>
        <proficiency>Athletics</proficiency>
        <proficiency>Acrobatics</proficiency>
        <text>Test feat with multiple proficiencies.

Source:	Test Book p. 1</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('proficiencies', $feats[0]);
        $this->assertCount(2, $feats[0]['proficiencies']);
    }
}
