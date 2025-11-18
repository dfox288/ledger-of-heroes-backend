<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\RaceXmlParser;
use PHPUnit\Framework\TestCase;

class RaceXmlParserTest extends TestCase
{
    private RaceXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RaceXmlParser();
    }

    /** @test */
    public function it_parses_dragonborn_race_from_real_xml()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons, as their name proclaims, the dragonborn walk proudly through a world that greets them with fearful incomprehension.

Source:	Player's Handbook (2014) p. 32</text>
    </trait>
    <trait>
      <name>Age</name>
      <text>Young dragonborn grow quickly. They walk hours after hatching, attain the size and development of a 10-year-old human child by the age of 3, and reach adulthood by 15. They live to be around 80.</text>
    </trait>
    <trait>
      <name>Languages</name>
      <text>You can speak, read, and write Common and Draconic.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);

        $race = $races[0];
        $this->assertEquals('Dragonborn', $race['name']);
        $this->assertEquals('M', $race['size_code']);
        $this->assertEquals(30, $race['speed']);
        $this->assertStringContainsString('Born of dragons', $race['description']);
        // Only description category traits should be included
        $this->assertStringNotContainsString('Age', $race['description']);
        $this->assertStringNotContainsString('Languages', $race['description']);
        $this->assertEquals('PHB', $race['source_code']);
        $this->assertEquals('32', $race['source_pages']);
    }

    /** @test */
    public function it_parses_hill_dwarf_with_subrace_naming()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <ability>Con +2, Wis +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Bold and hardy dwarves are known as skilled warriors.

Source: Player's Handbook (2014) p. 19</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $race = $races[0];
        $this->assertEquals('Hill', $race['name']);
        $this->assertEquals('Dwarf', $race['base_race_name']);
        $this->assertEquals('M', $race['size_code']);
        $this->assertEquals(25, $race['speed']);
    }

    /** @test */
    public function it_parses_multiple_races()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.

Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <ability>Con +2, Wis +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Bold and hardy.

Source: Player's Handbook (2014) p. 19</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(2, $races);
        $this->assertEquals('Dragonborn', $races[0]['name']);
        $this->assertNull($races[0]['base_race_name']);
        $this->assertEquals('Hill', $races[1]['name']);
        $this->assertEquals('Dwarf', $races[1]['base_race_name']);
    }

    /** @test */
    public function it_handles_race_without_source_citation()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Custom Race</name>
    <size>S</size>
    <speed>25</speed>
    <ability>Dex +2</ability>
    <trait category="description">
      <name>Description</name>
      <text>A custom race without source citation.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $race = $races[0];
        $this->assertEquals('Custom Race', $race['name']);
        // Should default to PHB if no source found
        $this->assertEquals('PHB', $race['source_code']);
        $this->assertEquals('', $race['source_pages']);
    }

    /** @test */
    public function it_parses_base_race_and_subrace_from_name()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>As a hill dwarf, you have keen senses.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertEquals('Hill', $races[0]['name']);
        $this->assertEquals('Dwarf', $races[0]['base_race_name']);
        $this->assertEquals('M', $races[0]['size_code']);
    }

    /** @test */
    public function it_parses_race_without_subrace()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertEquals('Dragonborn', $races[0]['name']);
        $this->assertNull($races[0]['base_race_name']);
    }

    /** @test */
    public function it_handles_slash_in_subrace_names()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, Drow / Dark</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Drow description.
Source: Player's Handbook (2014) p. 24</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertEquals('Drow / Dark', $races[0]['name']);
        $this->assertEquals('Elf', $races[0]['base_race_name']);
    }
}
