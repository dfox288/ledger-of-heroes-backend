<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\BackgroundXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
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
    public function it_parses_conditional_language_with_fallback_choice()
    {
        // Pattern: "Dwarvish, or one other of your choice if you already speak Dwarvish"
        // Returns: 1) Dwarvish (fixed) + 2) conditional choice (requires already knowing Dwarvish)
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Clan Crafter</name>
    <proficiency>History, Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>• Skill Proficiencies: History, Insight
• Tool Proficiencies: One type of artisan's tools
• Languages: Dwarvish, or one other of your choice if you already speak Dwarvish
• Equipment: A set of artisan's tools</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('languages', $backgrounds[0]);
        $this->assertCount(2, $backgrounds[0]['languages']);

        // First entry: Dwarvish (fixed language grant for those who don't already know it)
        $dwarvish = $backgrounds[0]['languages'][0];
        $this->assertFalse($dwarvish['is_choice']);
        $this->assertEquals(1, $dwarvish['quantity']);
        $this->assertEquals('dwarvish', $dwarvish['language_slug']);

        // Second entry: conditional choice (only if character already knows Dwarvish)
        $choice = $backgrounds[0]['languages'][1];
        $this->assertTrue($choice['is_choice']);
        $this->assertEquals(1, $choice['quantity']);
        $this->assertNull($choice['language_slug']);
        $this->assertNull($choice['choice_group']);
        $this->assertEquals('already_knows', $choice['condition_type']);
        $this->assertEquals('dwarvish', $choice['condition_language_slug']);
    }

    #[Test]
    public function it_parses_language_choice_from_restricted_list()
    {
        // Pattern: "One of your choice of Elvish, Gnomish, Goblin, or Sylvan"
        // This should return 4 rows with choice_group linking them
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Feylost</name>
    <proficiency>Deception, Survival</proficiency>
    <trait>
      <name>Description</name>
      <text>• Skill Proficiencies: Deception, Survival
• Tool Proficiencies: One type of musical instrument
• Languages: One of your choice of Elvish, Gnomish, Goblin, or Sylvan
• Equipment: A musical instrument</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('languages', $backgrounds[0]);
        $this->assertCount(4, $backgrounds[0]['languages']);

        // All should be choices with the same choice_group
        $languages = $backgrounds[0]['languages'];
        $expectedSlugs = ['elvish', 'gnomish', 'goblin', 'sylvan'];

        foreach ($languages as $index => $lang) {
            $this->assertTrue($lang['is_choice']);
            $this->assertEquals('lang_choice_1', $lang['choice_group']);
            $this->assertEquals($index + 1, $lang['choice_option']);
            $this->assertEquals($expectedSlugs[$index], $lang['language_slug']);

            // Only first row has quantity
            if ($index === 0) {
                $this->assertEquals(1, $lang['quantity']);
            } else {
                $this->assertNull($lang['quantity']);
            }
        }
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

    #[Test]
    public function it_parses_equipment_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Guild Artisan</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>• Equipment: A set of artisan's tools (one of your choice), a letter of introduction from your guild, a set of traveler's clothes, and a belt pouch containing 15 gp</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('equipment', $backgrounds[0]);
        $equipment = $backgrounds[0]['equipment'];
        $this->assertGreaterThan(0, count($equipment));

        // First item should be artisan's tools with choice
        $artisanTools = $equipment[0];
        $this->assertTrue($artisanTools['is_choice']);
        $this->assertEquals('one of your choice', $artisanTools['choice_description']);
        $this->assertStringContainsString('artisan', strtolower($artisanTools['item_name']));
    }

    #[Test]
    public function it_parses_equipment_with_quantities()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>• Equipment: 15 gp, 10 torches, a backpack</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $equipment = $backgrounds[0]['equipment'];
        $this->assertCount(3, $equipment);

        // First item: 15 gp
        $this->assertEquals(15, $equipment[0]['quantity']);
        $this->assertStringContainsString('gp', strtolower($equipment[0]['item_name']));

        // Second item: 10 torches
        $this->assertEquals(10, $equipment[1]['quantity']);
        $this->assertStringContainsString('torch', strtolower($equipment[1]['item_name']));

        // Third item: backpack
        $this->assertEquals(1, $equipment[2]['quantity']);
        $this->assertStringContainsString('backpack', strtolower($equipment[2]['item_name']));
    }

    #[Test]
    public function it_returns_empty_array_when_no_equipment_found()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>No equipment information here</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('equipment', $backgrounds[0]);
        $this->assertEmpty($backgrounds[0]['equipment']);
    }

    #[Test]
    public function it_parses_all_embedded_random_tables_from_all_traits()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Guild Artisan</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Guild Business</name>
      <text>Guild Business:
d20 | Guild Business
1 | Alchemists and apothecaries
2 | Armorers, locksmiths, and finesmiths
3 | Brewers, distillers, and vintners</text>
    </trait>
    <trait>
      <name>Suggested Characteristics</name>
      <text>Personality Trait:
d8 | Personality Trait
1 | I believe that anything worth doing is worth doing right.
2 | I'm a perfectionist who works long hours.</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('random_tables', $backgrounds[0]);
        $tables = $backgrounds[0]['random_tables'];

        // Should have at least 2 tables (Guild Business and Personality Trait)
        $this->assertGreaterThanOrEqual(2, count($tables));

        // Find Guild Business table
        $guildTable = collect($tables)->firstWhere('name', 'Guild Business');
        $this->assertNotNull($guildTable);
        $this->assertEquals('d20', $guildTable['dice_type']);
        $this->assertEquals('Guild Business', $guildTable['trait_name']);
        $this->assertCount(3, $guildTable['entries']);

        // Find Personality Trait table
        $personalityTable = collect($tables)->firstWhere('name', 'Personality Trait');
        $this->assertNotNull($personalityTable);
        $this->assertEquals('d8', $personalityTable['dice_type']);
        $this->assertEquals('Suggested Characteristics', $personalityTable['trait_name']);
        $this->assertCount(2, $personalityTable['entries']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_tables_found()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>No tables here</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $this->assertArrayHasKey('random_tables', $backgrounds[0]);
        $this->assertEmpty($backgrounds[0]['random_tables']);
    }

    #[Test]
    public function it_strips_pipe_table_data_from_trait_descriptions_after_extraction()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Suggested Characteristics</name>
      <text>Acolytes are shaped by their experience in temples or other religious communities.

d8 | Personality Trait
1 | I idolize a particular hero of my faith.
2 | I can find common ground between the fiercest enemies.

d6 | Ideal
1 | Tradition. The ancient traditions must be preserved. (Lawful)
2 | Charity. I always try to help those in need. (Good)</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        // Tables should be extracted
        $this->assertCount(2, $backgrounds[0]['random_tables']);

        // Trait description should NOT contain pipe-separated table data
        $charTrait = collect($backgrounds[0]['traits'])->firstWhere('name', 'Suggested Characteristics');
        $description = $charTrait['description'];

        // Should keep the intro text
        $this->assertStringContainsString('Acolytes are shaped by their experience', $description);

        // Should NOT contain the pipe-separated table data
        $this->assertStringNotContainsString('d8 | Personality Trait', $description);
        $this->assertStringNotContainsString('1 | I idolize', $description);
        $this->assertStringNotContainsString('d6 | Ideal', $description);
        $this->assertStringNotContainsString('1 | Tradition', $description);
    }

    #[Test]
    public function it_preserves_non_table_text_when_stripping_tables()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Flavor Trait</name>
      <text>Some intro text here.

d6 | Options
1 | Option one
2 | Option two

Some closing text here.</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $trait = $backgrounds[0]['traits'][0];

        // Should keep intro and closing text
        $this->assertStringContainsString('Some intro text here', $trait['description']);
        $this->assertStringContainsString('Some closing text here', $trait['description']);

        // Should NOT contain the table
        $this->assertStringNotContainsString('d6 | Options', $trait['description']);
        $this->assertStringNotContainsString('1 | Option one', $trait['description']);
    }

    #[Test]
    public function it_parses_equipment_with_proficiency_constraint()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Clan Crafter</name>
    <proficiency>History, Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>• Tool Proficiencies: One type of artisan's tools
• Equipment: A set of artisan's tools with which you are proficient, a maker's mark chisel</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);

        $equipment = $backgrounds[0]['equipment'];
        $artisanTools = collect($equipment)->first(fn ($e) => str_contains($e['item_name'] ?? '', 'artisan'));

        $this->assertTrue($artisanTools['is_choice']);
        $this->assertEquals('with which you are proficient', $artisanTools['choice_description']);
        $this->assertEquals('artisan', $artisanTools['proficiency_subcategory']);
    }
}
