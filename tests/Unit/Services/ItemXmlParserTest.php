<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\ItemXmlParser;
use Tests\TestCase;

class ItemXmlParserTest extends TestCase
{
    private ItemXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemXmlParser();
    }

    public function test_parses_basic_item(): void
    {
        $xml = <<<XML
        <item>
            <name>Longsword</name>
            <type>M</type>
            <weight>3</weight>
            <value>15.0</value>
            <text>A martial weapon. Source: Player's Handbook (2014) p. 149</text>
        </item>
        XML;

        $itemElement = simplexml_load_string($xml);
        $result = $this->parser->parseItemElement($itemElement);

        $this->assertEquals('Longsword', $result['name']);
        $this->assertEquals('M', $result['type_code']);
        $this->assertEquals(3.0, $result['weight_lbs']);
        $this->assertEquals(15.0, $result['value_gp']);
        $this->assertEquals('PHB', $result['source_code']);
        $this->assertEquals(149, $result['source_page']);
    }

    public function test_parses_item_properties(): void
    {
        $xml = <<<XML
        <item>
            <name>Rapier</name>
            <type>M</type>
            <property>F,V</property>
            <text>Test</text>
        </item>
        XML;

        $itemElement = simplexml_load_string($xml);
        $result = $this->parser->parseItemElement($itemElement);

        $this->assertContains('F', $result['properties']);
        $this->assertContains('V', $result['properties']);
    }

    public function test_parses_item_with_damage(): void
    {
        $xml = <<<XML
        <item>
            <name>Battleaxe</name>
            <type>M</type>
            <dmg1>1d8</dmg1>
            <dmg2>1d10</dmg2>
            <dmgType>S</dmgType>
            <text>Test</text>
        </item>
        XML;

        $itemElement = simplexml_load_string($xml);
        $result = $this->parser->parseItemElement($itemElement);

        $this->assertEquals('1d8', $result['damage_dice']);
        $this->assertEquals('1d10', $result['damage_dice_versatile']);
        $this->assertEquals('S', $result['damage_type_code']);
    }

    public function test_parses_item_with_rarity(): void
    {
        $xml = <<<XML
        <item>
            <name>Potion of Healing</name>
            <type>P</type>
            <detail>uncommon</detail>
            <text>Test</text>
        </item>
        XML;

        $itemElement = simplexml_load_string($xml);
        $result = $this->parser->parseItemElement($itemElement);

        $this->assertEquals('uncommon', $result['rarity_code']);
    }

    public function test_parses_item_with_range(): void
    {
        $xml = <<<XML
        <item>
            <name>Longbow</name>
            <type>R</type>
            <range>150/600</range>
            <text>Test</text>
        </item>
        XML;

        $itemElement = simplexml_load_string($xml);
        $result = $this->parser->parseItemElement($itemElement);

        $this->assertEquals('150/600', $result['range']);
    }
}
