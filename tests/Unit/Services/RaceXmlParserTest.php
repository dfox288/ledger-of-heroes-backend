<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\RaceXmlParser;
use Tests\TestCase;

class RaceXmlParserTest extends TestCase
{
    private RaceXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RaceXmlParser();
    }

    public function test_parses_race_with_ability_modifiers(): void
    {
        $xml = <<<XML
        <race>
            <name>Dragonborn</name>
            <size>M</size>
            <speed>30</speed>
            <ability>Str +2, Cha +1</ability>
            <trait>
                <name>Description</name>
                <text>Born of dragons. Source: Player's Handbook (2014) p. 32</text>
            </trait>
        </race>
        XML;

        $raceElement = simplexml_load_string($xml);
        $result = $this->parser->parseRaceElement($raceElement);

        $this->assertEquals('Dragonborn', $result['name']);
        $this->assertEquals('M', $result['size_code']);
        $this->assertEquals(30, $result['speed']);
        $this->assertCount(2, $result['modifiers']);
        $this->assertEquals('strength', $result['modifiers'][0]['target']);
        $this->assertEquals('+2', $result['modifiers'][0]['value']);
        $this->assertEquals('charisma', $result['modifiers'][1]['target']);
        $this->assertEquals('+1', $result['modifiers'][1]['value']);
    }

    public function test_parses_race_traits(): void
    {
        $xml = <<<XML
        <race>
            <name>Elf</name>
            <size>M</size>
            <speed>30</speed>
            <ability>Dex +2</ability>
            <trait category="description">
                <name>Description</name>
                <text>Elves are magical. Source: Player's Handbook (2014) p. 21</text>
            </trait>
            <trait>
                <name>Darkvision</name>
                <text>You can see in dim light within 60 feet of you as if it were bright light.</text>
            </trait>
        </race>
        XML;

        $raceElement = simplexml_load_string($xml);
        $result = $this->parser->parseRaceElement($raceElement);

        $this->assertCount(2, $result['traits']);
        $this->assertEquals('Description', $result['traits'][0]['name']);
        $this->assertEquals('description', $result['traits'][0]['category']);
        $this->assertEquals('Darkvision', $result['traits'][1]['name']);
    }

    public function test_parses_proficiencies_from_traits(): void
    {
        $xml = <<<XML
        <race>
            <name>Dwarf</name>
            <size>M</size>
            <speed>25</speed>
            <ability>Con +2</ability>
            <proficiency>battleaxe, handaxe, light hammer, warhammer</proficiency>
            <trait>
                <name>Description</name>
                <text>Test. Source: Player's Handbook (2014) p. 18</text>
            </trait>
        </race>
        XML;

        $raceElement = simplexml_load_string($xml);
        $result = $this->parser->parseRaceElement($raceElement);

        $this->assertCount(4, $result['proficiencies']);
        $this->assertEquals('battleaxe', $result['proficiencies'][0]['name']);
        $this->assertEquals('weapon', $result['proficiencies'][0]['proficiency_type']);
    }
}
