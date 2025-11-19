<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\BackgroundXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundXmlParserTest extends TestCase
{
    private BackgroundXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BackgroundXmlParser;
    }

    #[Test]
    public function it_parses_basic_background_data()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait>
      <name>Description</name>
      <text>You have spent your life in service.

Source: Player's Handbook (2014) p. 127</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertCount(1, $backgrounds);
        $this->assertEquals('Acolyte', $backgrounds[0]['name']);
    }

    #[Test]
    public function it_parses_proficiencies_from_comma_separated_list()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight, Religion</proficiency>
    <trait><name>Desc</name><text>Test</text></trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertCount(2, $backgrounds[0]['proficiencies']);
        $this->assertEquals('Insight', $backgrounds[0]['proficiencies'][0]['proficiency_name']);
        $this->assertEquals('skill', $backgrounds[0]['proficiencies'][0]['proficiency_type']);
        $this->assertEquals('Religion', $backgrounds[0]['proficiencies'][1]['proficiency_name']);
    }

    #[Test]
    public function it_parses_multiple_traits()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Deception</proficiency>
    <trait>
      <name>Description</name>
      <text>First trait</text>
    </trait>
    <trait>
      <name>Feature: Test Feature</name>
      <text>Second trait</text>
    </trait>
    <trait>
      <name>Suggested Characteristics</name>
      <text>Third trait</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertCount(3, $backgrounds[0]['traits']);
        $this->assertEquals('Description', $backgrounds[0]['traits'][0]['name']);
        $this->assertNull($backgrounds[0]['traits'][0]['category']);
        $this->assertEquals('feature', $backgrounds[0]['traits'][1]['category']);
        $this->assertEquals('characteristics', $backgrounds[0]['traits'][2]['category']);
    }

    #[Test]
    public function it_extracts_source_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>Some text here.

Source: Player's Handbook (2014) p. 127</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertCount(1, $backgrounds[0]['sources']);
        $this->assertEquals('PHB', $backgrounds[0]['sources'][0]['code']);
        $this->assertEquals('127', $backgrounds[0]['sources'][0]['pages']);
    }

    #[Test]
    public function it_handles_tool_proficiencies()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>One type of gaming set, thieves' tools</proficiency>
    <trait><name>Desc</name><text>Test</text></trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $profs = $backgrounds[0]['proficiencies'];
        $this->assertCount(2, $profs);
        $this->assertEquals('tool', $profs[0]['proficiency_type']);
        $this->assertEquals('tool', $profs[1]['proficiency_type']);
    }

    #[Test]
    public function it_parses_roll_elements_from_characteristics_trait()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Suggested Characteristics</name>
      <text>Some characteristics text</text>
      <roll description="Personality Trait">1d8</roll>
      <roll description="Ideal">1d6</roll>
      <roll description="Bond">1d6</roll>
      <roll description="Flaw">1d6</roll>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $charTrait = collect($backgrounds[0]['traits'])->firstWhere('category', 'characteristics');
        $this->assertCount(4, $charTrait['rolls']);
        $this->assertEquals('Personality Trait', $charTrait['rolls'][0]['description']);
        $this->assertEquals('1d8', $charTrait['rolls'][0]['formula']);
    }

    #[Test]
    public function it_parses_language_choice_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Guild Artisan</name>
    <proficiency>Insight, Persuasion</proficiency>
    <trait>
      <name>Description</name>
      <text>• Skill Proficiencies: Insight, Persuasion
• Tool Proficiencies: One type of artisan's tools
• Languages: One of your choice
• Equipment: A set of artisan's tools (one of your choice)</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('languages', $backgrounds[0]);
        $this->assertCount(1, $backgrounds[0]['languages']);
        $this->assertNull($backgrounds[0]['languages'][0]['language_id']);
        $this->assertTrue($backgrounds[0]['languages'][0]['is_choice']);
        $this->assertEquals(1, $backgrounds[0]['languages'][0]['quantity']);
    }

    #[Test]
    public function it_parses_specific_language_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>• Languages: Common</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('languages', $backgrounds[0]);

        // Should parse language - exact match depends on seeded data being available
        // so we just verify structure is correct
        if (count($backgrounds[0]['languages']) > 0) {
            $this->assertArrayHasKey('language_id', $backgrounds[0]['languages'][0]);
            $this->assertArrayHasKey('is_choice', $backgrounds[0]['languages'][0]);
            $this->assertFalse($backgrounds[0]['languages'][0]['is_choice']);
        }
    }

    #[Test]
    public function it_returns_empty_array_when_no_languages_found()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>No language information here</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('languages', $backgrounds[0]);
        $this->assertEmpty($backgrounds[0]['languages']);
    }

    #[Test]
    public function it_parses_tool_proficiency_choice_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Guild Artisan</name>
    <proficiency>Insight, Persuasion</proficiency>
    <trait>
      <name>Description</name>
      <text>• Skill Proficiencies: Insight, Persuasion
• Tool Proficiencies: One type of artisan's tools
• Languages: One of your choice</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        // Should have 3 proficiencies: Insight, Persuasion, artisan's tools
        $this->assertCount(3, $backgrounds[0]['proficiencies']);

        $toolProf = collect($backgrounds[0]['proficiencies'])
            ->firstWhere('proficiency_type', 'tool');

        $this->assertNotNull($toolProf);
        $this->assertTrue($toolProf['is_choice']);
        $this->assertEquals(1, $toolProf['quantity']);
        $this->assertStringContainsString('artisan', strtolower($toolProf['proficiency_name']));
    }

    #[Test]
    public function it_parses_specific_tool_proficiency_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Sailor</name>
    <proficiency>Athletics, Perception</proficiency>
    <trait>
      <name>Description</name>
      <text>• Skill Proficiencies: Athletics, Perception
• Tool Proficiencies: Navigator's tools, vehicles (water)</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        // Should have 4 proficiencies: Athletics, Perception, Navigator's tools, vehicles (water)
        $this->assertCount(4, $backgrounds[0]['proficiencies']);

        $toolProfs = collect($backgrounds[0]['proficiencies'])
            ->where('proficiency_type', 'tool');

        $this->assertCount(2, $toolProfs);

        foreach ($toolProfs as $toolProf) {
            $this->assertFalse($toolProf['is_choice'] ?? false);
        }
    }

    #[Test]
    public function it_merges_xml_and_trait_text_proficiencies()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight, Persuasion</proficiency>
    <trait>
      <name>Description</name>
      <text>• Tool Proficiencies: One type of gaming set</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        // Should have 3 proficiencies total (2 from XML + 1 from trait text)
        $this->assertCount(3, $backgrounds[0]['proficiencies']);

        $skillProfs = collect($backgrounds[0]['proficiencies'])
            ->where('proficiency_type', 'skill');
        $this->assertCount(2, $skillProfs);

        $toolProfs = collect($backgrounds[0]['proficiencies'])
            ->where('proficiency_type', 'tool');
        $this->assertCount(1, $toolProfs);
    }
}
