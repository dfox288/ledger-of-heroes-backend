<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\SpellXmlParser;
use Tests\TestCase;

class SpellXmlParserTest extends TestCase
{
    private SpellXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpellXmlParser();
    }

    public function test_parses_simple_spell_xml(): void
    {
        $xml = <<<XML
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
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $this->assertEquals('Acid Splash', $result['name']);
        $this->assertEquals(0, $result['level']);
        $this->assertEquals('C', $result['school_code']);
        $this->assertEquals('1 action', $result['casting_time']);
        $this->assertEquals('60 feet', $result['range']);
        $this->assertTrue($result['has_verbal_component']);
        $this->assertTrue($result['has_somatic_component']);
        $this->assertFalse($result['has_material_component']);
    }

    public function test_parses_spell_with_material_components(): void
    {
        $xml = <<<XML
        <spell>
            <name>Identify</name>
            <level>1</level>
            <school>D</school>
            <time>1 action</time>
            <range>Touch</range>
            <components>V, S, M (a pearl worth at least 100 gp and an owl feather)</components>
            <duration>Instantaneous</duration>
            <classes>Wizard</classes>
            <text>Test description</text>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $this->assertTrue($result['has_material_component']);
        $this->assertStringContainsString('pearl', $result['material_description']);
        $this->assertEquals(100, $result['material_cost_gp']);
    }

    public function test_parses_ritual_spell(): void
    {
        $xml = <<<XML
        <spell>
            <name>Alarm</name>
            <level>1</level>
            <school>A</school>
            <time>1 action</time>
            <range>30 feet</range>
            <components>V, S</components>
            <duration>8 hours</duration>
            <ritual>YES</ritual>
            <classes>Wizard</classes>
            <text>Test description</text>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $this->assertTrue($result['is_ritual']);
    }

    public function test_parses_spell_classes(): void
    {
        $xml = <<<XML
        <spell>
            <name>Fireball</name>
            <level>3</level>
            <school>EV</school>
            <time>1 action</time>
            <range>150 feet</range>
            <components>V, S, M</components>
            <duration>Instantaneous</duration>
            <classes>Fighter (Eldritch Knight), Sorcerer, Wizard</classes>
            <text>Test description</text>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $classes = $result['classes'];
        $this->assertCount(3, $classes);
        $this->assertEquals('Fighter', $classes[0]['class_name']);
        $this->assertEquals('Eldritch Knight', $classes[0]['subclass_name']);
        $this->assertEquals('Sorcerer', $classes[1]['class_name']);
        $this->assertNull($classes[1]['subclass_name']);
    }

    public function test_parses_material_cost_with_comma(): void
    {
        $xml = <<<XML
        <spell>
            <name>Revivify</name>
            <level>3</level>
            <school>N</school>
            <time>1 action</time>
            <range>Touch</range>
            <components>V, S, M (diamonds worth 1,000 gp, which the spell consumes)</components>
            <duration>Instantaneous</duration>
            <classes>Cleric</classes>
            <text>Test description</text>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $this->assertEquals(1000, $result['material_cost_gp']);
        $this->assertTrue($result['material_consumed']);
    }

    public function test_extracts_source_info_from_description(): void
    {
        $xml = <<<XML
        <spell>
            <name>Test Spell</name>
            <level>1</level>
            <school>A</school>
            <time>1 action</time>
            <range>Touch</range>
            <components>V, S</components>
            <duration>Instantaneous</duration>
            <classes>Wizard</classes>
            <text>A spell description. Source: Player's Handbook (2014) p. 211</text>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $this->assertEquals('PHB', $result['source_code']);
        $this->assertEquals(211, $result['source_page']);
    }
}
