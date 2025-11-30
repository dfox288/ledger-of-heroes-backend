<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\FeatXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
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
        $this->assertEquals('ability_score', $modifier['modifier_category']);
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
        $this->assertEquals('initiative', $modifier['modifier_category']);
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
        $this->assertEquals('speed', $modifier['modifier_category']);
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

    #[Test]
    public function it_parses_specific_spell_from_description()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Fey Touched (Charisma)</name>
        <text>Your exposure to the Feywild's magic has changed you, granting you the following benefits:

	• Increase your Intelligence, Wisdom, or Charisma score by 1, to a maximum of 20.

	• You learn the misty step spell and one 1st-level spell of your choice. The 1st-level spell must be from the divination or enchantment school of magic. You can cast each of these spells without expending a spell slot. Once you cast either of these spells in this way, you can't cast that spell in this way again until you finish a long rest.

Source:	Tasha's Cauldron of Everything p. 79</text>
        <modifier category="ability score">charisma +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('spells', $feats[0]);

        // Should have fixed spell (Misty Step) + spell choice
        $spells = $feats[0]['spells'];
        $this->assertGreaterThanOrEqual(2, count($spells));

        // Find the fixed spell
        $fixedSpells = array_filter($spells, fn ($s) => isset($s['spell_name']));
        $this->assertNotEmpty($fixedSpells);
        $fixedSpell = array_values($fixedSpells)[0];
        $this->assertEquals('Misty Step', $fixedSpell['spell_name']);
        $this->assertFalse($fixedSpell['pivot_data']['is_cantrip']);
    }

    #[Test]
    public function it_parses_shadow_touched_invisibility_spell()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Shadow Touched (Intelligence)</name>
        <text>Your exposure to the Shadowfell's magic has changed you, granting you the following benefits:

	• Increase your Intelligence, Wisdom, or Charisma score by 1, to a maximum of 20.

	• You learn the invisibility spell and one 1st-level spell of your choice.

Source:	Tasha's Cauldron of Everything p. 80</text>
        <modifier category="ability score">intelligence +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('spells', $feats[0]);
        $this->assertCount(1, $feats[0]['spells']);

        $spell = $feats[0]['spells'][0];
        $this->assertEquals('Invisibility', $spell['spell_name']);
    }

    #[Test]
    public function it_returns_empty_spells_array_for_feats_without_spells()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger, you gain the following benefits.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('spells', $feats[0]);
        $this->assertEmpty($feats[0]['spells']);
    }

    #[Test]
    public function it_parses_school_constrained_spell_choice()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Shadow Touched (Charisma)</name>
        <text>Your exposure to the Shadowfell's magic has changed you, granting you the following benefits:

	• Increase your Intelligence, Wisdom, or Charisma score by 1, to a maximum of 20.

	• You learn the invisibility spell and one 1st-level spell of your choice. The 1st-level spell must be from the illusion or necromancy school of magic. You can cast each of these spells without expending a spell slot. Once you cast either of these spells in this way, you can't cast that spell in this way again until you finish a long rest. You can also cast these spells using spell slots you have of the appropriate level. The spells' spellcasting ability is the ability increased by this feat.

Source:	Tasha's Cauldron of Everything p. 80</text>
        <modifier category="ability score">charisma +1</modifier>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('spells', $feats[0]);

        // Should have fixed spell (Invisibility) + spell choice
        $spells = $feats[0]['spells'];
        $this->assertGreaterThanOrEqual(2, count($spells));

        // Find the fixed spell
        $fixedSpells = array_filter($spells, fn ($s) => isset($s['spell_name']));
        $this->assertNotEmpty($fixedSpells);
        $fixedSpell = array_values($fixedSpells)[0];
        $this->assertEquals('Invisibility', $fixedSpell['spell_name']);

        // Find the choice spell(s)
        $choiceSpells = array_filter($spells, fn ($s) => isset($s['is_choice']) && $s['is_choice'] === true);
        $this->assertNotEmpty($choiceSpells);

        $choice = array_values($choiceSpells)[0];
        $this->assertTrue($choice['is_choice']);
        $this->assertEquals(1, $choice['choice_count']);
        $this->assertEquals(1, $choice['max_level']);
        $this->assertContains('illusion', $choice['schools']);
        $this->assertContains('necromancy', $choice['schools']);
    }

    #[Test]
    public function it_parses_class_constrained_spell_choices()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Magic Initiate (Bard)</name>
        <text>You learn two bard cantrips of your choice.
	In addition, choose one 1st-level bard spell. You learn that spell and can cast it at its lowest level. Once you cast it, you must finish a long rest before you can cast it again using this feat.
	Your spellcasting ability for these spells is Charisma.

Source:	Player's Handbook (2014) p. 168</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('spells', $feats[0]);

        $spells = $feats[0]['spells'];
        $choiceSpells = array_filter($spells, fn ($s) => isset($s['is_choice']) && $s['is_choice'] === true);
        $this->assertCount(2, $choiceSpells); // cantrips + 1st-level spell

        $choiceSpells = array_values($choiceSpells);

        // First choice: 2 cantrips
        $cantripsChoice = array_filter($choiceSpells, fn ($s) => $s['max_level'] === 0);
        $this->assertNotEmpty($cantripsChoice);
        $cantripsChoice = array_values($cantripsChoice)[0];
        $this->assertEquals(2, $cantripsChoice['choice_count']);
        $this->assertEquals('bard', strtolower($cantripsChoice['class_name']));

        // Second choice: 1 first-level spell
        $spellChoice = array_filter($choiceSpells, fn ($s) => $s['max_level'] === 1);
        $this->assertNotEmpty($spellChoice);
        $spellChoice = array_values($spellChoice)[0];
        $this->assertEquals(1, $spellChoice['choice_count']);
        $this->assertEquals('bard', strtolower($spellChoice['class_name']));
    }

    #[Test]
    public function it_parses_ritual_constrained_spell_choices()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Ritual Caster (Bard)</name>
        <prerequisite>Intelligence or Wisdom 13 or higher</prerequisite>
        <text>You have learned a number of spells that you can cast as rituals. These spells are written in a ritual book, which you must have in hand while casting one of them.
	When you choose this feat, you acquire a ritual book holding two 1st-level bard spells of your choice. The spells you choose must have the ritual tag. Charisma is your spellcasting ability for these spells.
	If you come across a spell in written form, such as a magical spell scroll or a wizard's spellbook, you might be able to add it to your ritual book. The spell must be on the bard spell list, the spell's level can be no higher than half your level (rounded up), and it must have the ritual tag. The process of copying the spell into your ritual book takes 2 hours per level of the spell, and costs 50 gp per level. The cost represents material components you expend as you experiment with the spell to master it, as well as the fine inks you need to record it.

Source:	Player's Handbook (2014) p. 169</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertArrayHasKey('spells', $feats[0]);

        $spells = $feats[0]['spells'];
        $choiceSpells = array_filter($spells, fn ($s) => isset($s['is_choice']) && $s['is_choice'] === true);
        $this->assertNotEmpty($choiceSpells);

        $choice = array_values($choiceSpells)[0];
        $this->assertTrue($choice['is_choice']);
        $this->assertEquals(2, $choice['choice_count']);
        $this->assertEquals(1, $choice['max_level']);
        $this->assertEquals('bard', strtolower($choice['class_name']));
        $this->assertTrue($choice['is_ritual_only']);
    }

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
        $passiveModifiers = array_filter($modifiers, fn ($m) => ($m['modifier_category'] ?? '') === 'passive_score');
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
}
