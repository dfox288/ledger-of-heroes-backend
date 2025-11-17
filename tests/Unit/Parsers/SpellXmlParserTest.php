<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SpellXmlParser;
use Tests\TestCase;

class SpellXmlParserTest extends TestCase
{
    public function test_parses_basic_spell_data(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Fireball</name>
        <level>3</level>
        <school>EV</school>
        <time>1 action</time>
        <range>150 feet</range>
        <components>V, S, M (a tiny ball of bat guano and sulfur)</components>
        <duration>Instantaneous</duration>
        <classes>Wizard, Sorcerer</classes>
        <text>A bright streak flashes from your pointing finger to a point you choose within range and then blossoms with a low roar into an explosion of flame.</text>
        <text>Source: Player's Handbook p. 241</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertCount(1, $spells);
        $this->assertEquals('Fireball', $spells[0]['name']);
        $this->assertEquals(3, $spells[0]['level']);
        $this->assertEquals('EV', $spells[0]['school']);
        $this->assertEquals('1 action', $spells[0]['casting_time']);
        $this->assertEquals('150 feet', $spells[0]['range']);
        $this->assertEquals('V, S, M', $spells[0]['components']);
        $this->assertEquals('a tiny ball of bat guano and sulfur', $spells[0]['material_components']);
        $this->assertEquals('Instantaneous', $spells[0]['duration']);
        $this->assertFalse($spells[0]['needs_concentration']);
        $this->assertFalse($spells[0]['is_ritual']);
        $this->assertEquals(['Wizard', 'Sorcerer'], $spells[0]['classes']);
        $this->assertEquals('PHB', $spells[0]['source_code']);
        $this->assertEquals('241', $spells[0]['source_pages']);
    }

    public function test_detects_concentration(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Invisibility</name>
        <level>2</level>
        <school>I</school>
        <time>1 action</time>
        <range>Touch</range>
        <components>V, S, M</components>
        <duration>Concentration, up to 1 hour</duration>
        <classes>Wizard</classes>
        <text>A creature you touch becomes invisible.</text>
        <text>Source: Player's Handbook p. 254</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertTrue($spells[0]['needs_concentration']);
    }

    public function test_detects_ritual(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Detect Magic</name>
        <level>1</level>
        <school>D</school>
        <ritual>YES</ritual>
        <time>1 action</time>
        <range>Self</range>
        <components>V, S</components>
        <duration>Concentration, up to 10 minutes</duration>
        <classes>Wizard</classes>
        <text>You sense the presence of magic.</text>
        <text>Source: Player's Handbook p. 231</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertTrue($spells[0]['is_ritual']);
    }

    public function test_parses_source_with_year(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Acid Splash</name>
        <level>0</level>
        <school>C</school>
        <time>1 action</time>
        <range>60 feet</range>
        <components>V, S</components>
        <duration>Instantaneous</duration>
        <classes>Sorcerer, Wizard</classes>
        <text>You hurl a bubble of acid.</text>
        <text>Source: Player's Handbook (2014) p. 211</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertEquals('PHB', $spells[0]['source_code']);
        $this->assertEquals('211', $spells[0]['source_pages']);
    }

    public function test_strips_school_prefix_from_classes(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Aid</name>
        <level>2</level>
        <school>A</school>
        <time>1 action</time>
        <range>30 feet</range>
        <components>V, S, M</components>
        <duration>8 hours</duration>
        <classes>School: Abjuration, Cleric, Paladin</classes>
        <text>Your spell bolsters your allies with toughness and resolve.</text>
        <text>Source: Player's Handbook p. 211</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertEquals(['Cleric', 'Paladin'], $spells[0]['classes']);
        $this->assertNotContains('School: Abjuration', $spells[0]['classes']);
    }

    public function test_parses_description_from_combined_text_element(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Acid Splash</name>
        <level>0</level>
        <school>C</school>
        <time>1 action</time>
        <range>60 feet</range>
        <components>V, S</components>
        <duration>Instantaneous</duration>
        <classes>Sorcerer, Wizard</classes>
        <text>You hurl a bubble of acid. Choose one creature you can see within range, or choose two creatures you can see within range that are within 5 feet of each other. A target must succeed on a Dexterity saving throw or take 1d6 acid damage.

Cantrip Upgrade: This spell's damage increases by 1d6 when you reach 5th level (2d6), 11th level (3d6), and 17th level (4d6).

Source:	Player's Handbook (2014) p. 211</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertStringContainsString('You hurl a bubble of acid', $spells[0]['description']);
        $this->assertStringContainsString('Cantrip Upgrade', $spells[0]['description']);
        $this->assertStringNotContainsString('Source:', $spells[0]['description']);
        $this->assertEquals('PHB', $spells[0]['source_code']);
        $this->assertEquals('211', $spells[0]['source_pages']);
    }

    public function test_parses_description_with_separate_text_elements(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Fireball</name>
        <level>3</level>
        <school>EV</school>
        <time>1 action</time>
        <range>150 feet</range>
        <components>V, S, M</components>
        <duration>Instantaneous</duration>
        <classes>Wizard, Sorcerer</classes>
        <text>A bright streak flashes from your pointing finger to a point you choose within range and then blossoms with a low roar into an explosion of flame.</text>
        <text>Source: Player's Handbook p. 241</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertStringContainsString('A bright streak flashes', $spells[0]['description']);
        $this->assertStringNotContainsString('Source:', $spells[0]['description']);
        $this->assertEquals('PHB', $spells[0]['source_code']);
        $this->assertEquals('241', $spells[0]['source_pages']);
    }

    public function test_parses_cantrip_roll_elements_with_character_level_scaling(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Acid Splash</name>
        <level>0</level>
        <school>C</school>
        <time>1 action</time>
        <range>60 feet</range>
        <components>V, S</components>
        <duration>Instantaneous</duration>
        <classes>Sorcerer, Wizard</classes>
        <text>You hurl a bubble of acid.</text>
        <text>Source: Player's Handbook (2014) p. 211</text>
        <roll description="Acid Damage" level="0">1d6</roll>
        <roll description="Acid Damage" level="5">2d6</roll>
        <roll description="Acid Damage" level="11">3d6</roll>
        <roll description="Acid Damage" level="17">4d6</roll>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertArrayHasKey('effects', $spells[0]);
        $this->assertCount(4, $spells[0]['effects']);

        // Check first effect (base cantrip)
        $this->assertEquals('damage', $spells[0]['effects'][0]['effect_type']);
        $this->assertEquals('Acid Damage', $spells[0]['effects'][0]['description']);
        $this->assertEquals('1d6', $spells[0]['effects'][0]['dice_formula']);
        $this->assertEquals('character_level', $spells[0]['effects'][0]['scaling_type']);
        $this->assertEquals(0, $spells[0]['effects'][0]['min_character_level']);
        $this->assertNull($spells[0]['effects'][0]['min_spell_slot']);

        // Check 5th level scaling
        $this->assertEquals('2d6', $spells[0]['effects'][1]['dice_formula']);
        $this->assertEquals('character_level', $spells[0]['effects'][1]['scaling_type']);
        $this->assertEquals(5, $spells[0]['effects'][1]['min_character_level']);

        // Check 11th level scaling
        $this->assertEquals('3d6', $spells[0]['effects'][2]['dice_formula']);
        $this->assertEquals(11, $spells[0]['effects'][2]['min_character_level']);

        // Check 17th level scaling
        $this->assertEquals('4d6', $spells[0]['effects'][3]['dice_formula']);
        $this->assertEquals(17, $spells[0]['effects'][3]['min_character_level']);
    }

    public function test_parses_spell_roll_elements_with_spell_slot_scaling(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Arms of Hadar</name>
        <level>1</level>
        <school>C</school>
        <time>1 action</time>
        <range>Self</range>
        <components>V, S</components>
        <duration>Instantaneous</duration>
        <classes>Warlock</classes>
        <text>Tendrils of dark energy erupt from you.</text>
        <text>Source: Player's Handbook (2014) p. 215</text>
        <roll description="Necrotic Damage" level="1">2d6</roll>
        <roll description="Necrotic Damage" level="2">3d6</roll>
        <roll description="Necrotic Damage" level="3">4d6</roll>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertCount(3, $spells[0]['effects']);

        // Check base level
        $this->assertEquals('damage', $spells[0]['effects'][0]['effect_type']);
        $this->assertEquals('Necrotic Damage', $spells[0]['effects'][0]['description']);
        $this->assertEquals('2d6', $spells[0]['effects'][0]['dice_formula']);
        $this->assertEquals('spell_slot_level', $spells[0]['effects'][0]['scaling_type']);
        $this->assertEquals(1, $spells[0]['effects'][0]['min_spell_slot']);
        $this->assertNull($spells[0]['effects'][0]['min_character_level']);

        // Check 2nd level
        $this->assertEquals('3d6', $spells[0]['effects'][1]['dice_formula']);
        $this->assertEquals(2, $spells[0]['effects'][1]['min_spell_slot']);

        // Check 3rd level
        $this->assertEquals('4d6', $spells[0]['effects'][2]['dice_formula']);
        $this->assertEquals(3, $spells[0]['effects'][2]['min_spell_slot']);
    }

    public function test_parses_roll_elements_without_level_attribute(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Alter Self</name>
        <level>2</level>
        <school>T</school>
        <time>1 action</time>
        <range>Self</range>
        <components>V, S</components>
        <duration>Concentration, up to 1 hour</duration>
        <classes>Wizard</classes>
        <text>You assume a different form.</text>
        <text>Source: Player's Handbook p. 211</text>
        <roll description="Natural Weapons">1d6</roll>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertCount(1, $spells[0]['effects']);

        $this->assertEquals('other', $spells[0]['effects'][0]['effect_type']);
        $this->assertEquals('Natural Weapons', $spells[0]['effects'][0]['description']);
        $this->assertEquals('1d6', $spells[0]['effects'][0]['dice_formula']);
        $this->assertEquals('none', $spells[0]['effects'][0]['scaling_type']);
        $this->assertNull($spells[0]['effects'][0]['min_character_level']);
        $this->assertNull($spells[0]['effects'][0]['min_spell_slot']);
    }

    public function test_parses_healing_effect_type(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Aura of Vitality</name>
        <level>3</level>
        <school>EV</school>
        <time>1 action</time>
        <range>Self</range>
        <components>V</components>
        <duration>Concentration, up to 1 minute</duration>
        <classes>Paladin</classes>
        <text>Healing energy radiates from you.</text>
        <text>Source: Player's Handbook p. 216</text>
        <roll description="Regain Hit Points">2d6</roll>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertCount(1, $spells[0]['effects']);
        $this->assertEquals('healing', $spells[0]['effects'][0]['effect_type']);
        $this->assertEquals('Regain Hit Points', $spells[0]['effects'][0]['description']);
    }

    public function test_spell_without_roll_elements_returns_empty_effects_array(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Aid</name>
        <level>2</level>
        <school>A</school>
        <time>1 action</time>
        <range>30 feet</range>
        <components>V, S, M</components>
        <duration>8 hours</duration>
        <classes>Cleric, Paladin</classes>
        <text>Your spell bolsters your allies.</text>
        <text>Source: Player's Handbook p. 211</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertArrayHasKey('effects', $spells[0]);
        $this->assertCount(0, $spells[0]['effects']);
    }
}
