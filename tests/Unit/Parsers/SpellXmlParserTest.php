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
}
