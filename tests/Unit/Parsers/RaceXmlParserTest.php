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

        // Check traits are parsed
        $this->assertArrayHasKey('traits', $race);
        $this->assertCount(3, $race['traits']);

        // Check description trait
        $descTrait = collect($race['traits'])->firstWhere('category', 'description');
        $this->assertStringContainsString('Born of dragons', $descTrait['description']);

        // Check all traits are present
        $traitNames = array_column($race['traits'], 'name');
        $this->assertContains('Description', $traitNames);
        $this->assertContains('Age', $traitNames);
        $this->assertContains('Languages', $traitNames);

        // Check sources array format
        $this->assertArrayHasKey('sources', $race);
        $this->assertCount(1, $race['sources']);
        $this->assertEquals('PHB', $race['sources'][0]['code']);
        $this->assertEquals('32', $race['sources'][0]['pages']);
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
        $this->assertArrayHasKey('sources', $race);
        $this->assertCount(1, $race['sources']);
        $this->assertEquals('PHB', $race['sources'][0]['code']);
        $this->assertEquals('', $race['sources'][0]['pages']);
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

    /** @test */
    public function it_parses_skill_proficiencies()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <trait category="description">
      <name>Description</name>
      <text>High elf description.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('proficiencies', $races[0]);
        $this->assertCount(1, $races[0]['proficiencies']);
        $this->assertEquals('skill', $races[0]['proficiencies'][0]['type']);
        $this->assertEquals('Perception', $races[0]['proficiencies'][0]['name']);
    }

    /** @test */
    public function it_parses_weapon_proficiencies()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <weapons>Longsword, Shortsword, Shortbow, Longbow</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(4, $races[0]['proficiencies']);
        $this->assertEquals('weapon', $races[0]['proficiencies'][0]['type']);
        $this->assertEquals('Longsword', $races[0]['proficiencies'][0]['name']);
    }

    /** @test */
    public function it_parses_armor_proficiencies()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Mountain</name>
    <size>M</size>
    <speed>25</speed>
    <armor>Light Armor, Medium Armor</armor>
    <trait category="description">
      <name>Description</name>
      <text>Mountain dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(2, $races[0]['proficiencies']);
        $this->assertEquals('armor', $races[0]['proficiencies'][0]['type']);
        $this->assertEquals('Light Armor', $races[0]['proficiencies'][0]['name']);
    }

    /** @test */
    public function it_parses_multiple_proficiency_types()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Mountain</name>
    <size>M</size>
    <speed>25</speed>
    <proficiency>Perception</proficiency>
    <weapons>Battleaxe, Handaxe</weapons>
    <armor>Light Armor, Medium Armor</armor>
    <trait category="description">
      <name>Description</name>
      <text>Dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $proficiencies = $races[0]['proficiencies'];
        $this->assertCount(5, $proficiencies); // 1 skill + 2 weapons + 2 armor

        // Verify we have all types
        $types = array_column($proficiencies, 'type');
        $this->assertContains('skill', $types);
        $this->assertContains('weapon', $types);
        $this->assertContains('armor', $types);
    }

    /** @test */
    public function it_parses_traits_from_xml()
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
    <trait category="species">
      <name>Breath Weapon</name>
      <text>You can use your action to exhale destructive energy.</text>
    </trait>
    <trait>
      <name>Languages</name>
      <text>You can speak Common and Draconic.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertArrayHasKey('traits', $races[0]);
        $this->assertCount(3, $races[0]['traits']);

        // Check description trait
        $this->assertEquals('Description', $races[0]['traits'][0]['name']);
        $this->assertEquals('description', $races[0]['traits'][0]['category']);
        $this->assertStringContainsString('Born of dragons', $races[0]['traits'][0]['description']);

        // Check species trait
        $this->assertEquals('Breath Weapon', $races[0]['traits'][1]['name']);
        $this->assertEquals('species', $races[0]['traits'][1]['category']);

        // Check trait without category
        $this->assertEquals('Languages', $races[0]['traits'][2]['name']);
        $this->assertNull($races[0]['traits'][2]['category']);
    }

    /** @test */
    public function it_parses_ability_score_bonuses()
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
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertArrayHasKey('ability_bonuses', $races[0]);
        $this->assertCount(2, $races[0]['ability_bonuses']);

        $this->assertEquals('Str', $races[0]['ability_bonuses'][0]['ability']);
        $this->assertEquals('+2', $races[0]['ability_bonuses'][0]['value']);

        $this->assertEquals('Cha', $races[0]['ability_bonuses'][1]['ability']);
        $this->assertEquals('+1', $races[0]['ability_bonuses'][1]['value']);
    }

    /** @test */
    public function it_parses_rolls_from_traits()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Size</name>
      <text>Your size is Medium. To set your height randomly:
Size modifier = 2d8</text>
      <roll description="Size Modifier">2d8</roll>
      <roll description="Weight Modifier">2d6</roll>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $sizeTrait = $races[0]['traits'][0];
        $this->assertEquals('Size', $sizeTrait['name']);
        $this->assertArrayHasKey('rolls', $sizeTrait);
        $this->assertCount(2, $sizeTrait['rolls']);

        $this->assertEquals('Size Modifier', $sizeTrait['rolls'][0]['description']);
        $this->assertEquals('2d8', $sizeTrait['rolls'][0]['formula']);

        $this->assertEquals('Weight Modifier', $sizeTrait['rolls'][1]['description']);
        $this->assertEquals('2d6', $sizeTrait['rolls'][1]['formula']);
    }
}
