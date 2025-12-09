<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\RaceXmlParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class RaceXmlParserTest extends TestCase
{
    private RaceXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RaceXmlParser;
    }

    #[Test]
    public function it_parses_dragonborn_race_from_real_xml()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_hill_dwarf_with_subrace_naming()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_multiple_races()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_handles_race_without_source_citation()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_base_race_and_subrace_from_name()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_race_without_subrace()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_handles_slash_in_subrace_names()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_skill_proficiencies()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_weapon_proficiencies()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_armor_proficiencies()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_multiple_proficiency_types()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_traits_from_xml()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_ability_score_bonuses()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_rolls_from_traits()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_parses_multiple_sources_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Warforged</name>
        <size>M</size>
        <speed>30</speed>
        <ability>Con +2</ability>
        <trait>
            <name>Description</name>
            <text>Test race.

Source: Eberron: Rising from the Last War p. 35, Wayfinder's Guide to Eberron p. 67</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        // Without database, parser defaults to PHB when source not found
        // But it should still parse multiple sources (just with default codes)
        $this->assertCount(2, $races[0]['sources']);
        $this->assertEquals('35', $races[0]['sources'][0]['pages']);
        $this->assertEquals('67', $races[0]['sources'][1]['pages']);
    }

    #[Test]
    public function it_parses_ability_choice_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>TestRace</name>
        <size>M</size>
        <speed>30</speed>
        <ability>Con +2</ability>
        <trait>
            <name>Ability Score Increase</name>
            <text>Your Constitution score increases by 2. In addition, one other ability score of your choice increases by 1.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertArrayHasKey('ability_choices', $races[0]);
        $this->assertCount(1, $races[0]['ability_choices']);
        $this->assertTrue($races[0]['ability_choices'][0]['is_choice']);
        $this->assertEquals(1, $races[0]['ability_choices'][0]['choice_count']);
        $this->assertEquals(1, $races[0]['ability_choices'][0]['value']);
    }

    #[Test]
    public function it_parses_multiple_ability_choices()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Human Variant</name>
        <size>M</size>
        <speed>30</speed>
        <trait>
            <name>Ability Score Increase</name>
            <text>Two different ability scores of your choice increase by 1.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races[0]['ability_choices']);
        $this->assertEquals(2, $races[0]['ability_choices'][0]['choice_count']);
        $this->assertEquals('different', $races[0]['ability_choices'][0]['choice_constraint']);
    }

    #[Test]
    public function it_parses_condition_immunity()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Warforged</name>
        <size>M</size>
        <speed>30</speed>
        <trait>
            <name>Constructed Resilience</name>
            <text>You are immune to disease.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertArrayHasKey('conditions', $races[0]);
        $this->assertCount(1, $races[0]['conditions']);
        $this->assertEquals('disease', $races[0]['conditions'][0]['condition_name']);
        $this->assertEquals('immunity', $races[0]['conditions'][0]['effect_type']);
    }

    #[Test]
    public function it_parses_advantage_on_saving_throws()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Halfling</name>
        <size>S</size>
        <speed>25</speed>
        <trait>
            <name>Brave</name>
            <text>You have advantage on saving throws against being frightened.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races[0]['conditions']);
        $this->assertEquals('frightened', $races[0]['conditions'][0]['condition_name']);
        $this->assertEquals('advantage', $races[0]['conditions'][0]['effect_type']);
    }

    #[Test]
    public function it_parses_racial_spellcasting()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Tiefling</name>
        <size>M</size>
        <speed>30</speed>
        <spellAbility>Charisma</spellAbility>
        <trait>
            <name>Infernal Legacy</name>
            <text>You know the Thaumaturgy cantrip. Once you reach 3rd level, you can cast the hellish rebuke spell as a 2nd-level spell. Once you reach 5th level, you can also cast the darkness spell. Charisma is your spellcasting ability for these spells.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertArrayHasKey('spellcasting', $races[0]);
        $this->assertEquals('Charisma', $races[0]['spellcasting']['ability']);
        $this->assertCount(3, $races[0]['spellcasting']['spells']);

        // Check cantrip
        $cantrip = collect($races[0]['spellcasting']['spells'])->firstWhere('is_cantrip', true);
        $this->assertEquals('Thaumaturgy', $cantrip['spell_name']);

        // Check leveled spells
        $hellishRebuke = collect($races[0]['spellcasting']['spells'])->firstWhere('spell_name', 'hellish rebuke');
        $this->assertEquals(3, $hellishRebuke['level_requirement']);
    }

    #[Test]
    public function it_parses_cantrip_choice_from_class_spell_list()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Elf, High</name>
        <size>M</size>
        <speed>30</speed>
        <spellAbility>Intelligence</spellAbility>
        <trait category="subspecies">
            <name>Cantrip</name>
            <text>You know one cantrip of your choice from the wizard spell list. Intelligence is your spellcasting ability for it.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertArrayHasKey('spellcasting', $races[0]);
        $this->assertEquals('Intelligence', $races[0]['spellcasting']['ability']);
        $this->assertCount(1, $races[0]['spellcasting']['spells']);

        // Verify the cantrip choice is parsed correctly
        $choice = $races[0]['spellcasting']['spells'][0];
        $this->assertTrue($choice['is_choice']);
        $this->assertEquals(1, $choice['choice_count']);
        $this->assertEquals('wizard', $choice['class_name']);
        $this->assertEquals(0, $choice['max_level']); // cantrip = level 0
        $this->assertTrue($choice['is_cantrip']);
    }

    #[Test]
    public function it_parses_cantrip_choice_with_multiple_class_options()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Aereni Elf</name>
        <size>M</size>
        <speed>30</speed>
        <trait category="subspecies">
            <name>Cantrip</name>
            <text>You know one cantrip of your choice from the cleric or wizard spell list. Your spellcasting ability depends on the class you chose: Wisdom for cleric or Intelligence for wizard.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertArrayHasKey('spellcasting', $races[0]);
        $this->assertCount(2, $races[0]['spellcasting']['spells']);

        // Should create two choice entries - one for cleric, one for wizard
        $classNames = collect($races[0]['spellcasting']['spells'])->pluck('class_name')->sort()->values()->all();
        $this->assertEquals(['cleric', 'wizard'], $classNames);

        foreach ($races[0]['spellcasting']['spells'] as $choice) {
            $this->assertTrue($choice['is_choice']);
            $this->assertEquals(1, $choice['choice_count']);
            $this->assertEquals(0, $choice['max_level']);
            $this->assertTrue($choice['is_cantrip']);
        }
    }

    #[Test]
    public function it_parses_damage_resistance_from_resist_element()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Warforged</name>
        <size>M</size>
        <speed>30</speed>
        <ability>Con +2</ability>
        <resist>poison</resist>
        <trait category="species">
            <name>Constructed Resilience</name>
            <text>You have advantage on saving throws against being poisoned, and you have resistance to poison damage.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        // Check that resistance is parsed
        $this->assertArrayHasKey('resistances', $races[0]);
        $this->assertCount(1, $races[0]['resistances']);
        $this->assertEquals('poison', $races[0]['resistances'][0]['damage_type']);

        // Also verify the advantage on saving throws is still captured
        $this->assertArrayHasKey('conditions', $races[0]);
        $poisonedCondition = collect($races[0]['conditions'])->firstWhere('condition_name', 'poisoned');
        $this->assertNotNull($poisonedCondition);
        $this->assertEquals('advantage', $poisonedCondition['effect_type']);
    }

    #[Test]
    public function it_parses_choice_based_proficiencies_from_trait_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Warforged</name>
        <size>M</size>
        <speed>30</speed>
        <ability>Con +2</ability>
        <trait category="species">
            <name>Specialized Design</name>
            <text>You gain one skill proficiency and one tool proficiency of your choice.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        // Check that choice-based proficiencies are parsed
        $this->assertArrayHasKey('proficiencies', $races[0]);
        $this->assertCount(2, $races[0]['proficiencies']);

        // First: skill proficiency choice
        $skillChoice = $races[0]['proficiencies'][0];
        $this->assertEquals('skill', $skillChoice['type']);
        $this->assertTrue($skillChoice['is_choice']);
        $this->assertEquals(1, $skillChoice['quantity']);

        // Second: tool proficiency choice
        $toolChoice = $races[0]['proficiencies'][1];
        $this->assertEquals('tool', $toolChoice['type']);
        $this->assertTrue($toolChoice['is_choice']);
        $this->assertEquals(1, $toolChoice['quantity']);
    }

    #[Test]
    public function it_expands_tiefling_variants_into_separate_subraces()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Tiefling, Variants</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Dex +2, Int +1</ability>
    <resist>fire</resist>
    <spellAbility>Charisma</spellAbility>
    <trait category="description">
      <name>Description</name>
      <text>Since not all tieflings are of the blood of Asmodeus, some have traits that differ from those in the Player's Handbook. The Dungeon Master may permit the following variants for your tiefling character, although Devil's Tongue, Hellfire, and Winged are mutually exclusive. (Choose only one)

Source:	Player's Handbook (2014) p. 43,
		Sword Coast Adventurer's Guide p. 118</text>
    </trait>
    <trait>
      <name>Age</name>
      <text>Tieflings mature at the same rate as humans but live a few years longer.</text>
    </trait>
    <trait>
      <name>Size</name>
      <text>Tieflings are about the same size and build as humans. Your size is Medium.</text>
    </trait>
    <trait category="species">
      <name>Darkvision</name>
      <text>Thanks to your infernal heritage, you have superior vision in dark and dim conditions. You can see in dim light within 60 feet of you as if it were bright light, and in darkness as if it were dim light. You can't discern color in darkness, only shades of gray.</text>
    </trait>
    <trait category="species">
      <name>Hellish Resistance</name>
      <text>You have resistance to fire damage.</text>
    </trait>
    <trait category="species">
      <name>Infernal Legacy</name>
      <text>You know the Thaumaturgy cantrip. Once you reach 3rd level, you can cast the hellish rebuke spell as a 2nd-level spell; you must finish a long rest in order to cast the spell again using this trait. Once you reach 5th level, you can also cast the darkness spell; you must finish a long rest in order to cast the spell again using this trait. Charisma is your spellcasting ability for these spells.</text>
    </trait>
    <trait category="subspecies">
      <name>Variant: Appearance</name>
      <text>Your tiefling might not look like other tieflings. Rather than having the physical characteristics described in the Player's Handbook, choose 1d4 + 1 of the following features:

Small Horns;
Fangs or Sharp Teeth;
A Forked Tongue;
Catlike Eyes;
Six Fingers On Each Hand;
Goat-Like Legs;
Cloven Hoofs;
A Forked Tail;
Leathery or Scaly Skin;
Red or Dark Blue Skin;
Cast No Shadow Or Reflection;
Exude a Smell Of Brimstone.</text>
      <roll description="Features">1d4+1</roll>
    </trait>
    <trait category="subspecies">
      <name>Variant: Devil's Tongue</name>
      <text>You know the vicious mockery cantrip. When you reach 3rd level, you can cast the charm person spell as a 2nd-level spell once with this trait. When you reach 5th level, you can cast the enthrall spell once with this trait. You must finish a long rest to cast these spells once again with this trait. Charisma is your spellcasting ability for them. This trait replaces the Infernal Legacy trait.</text>
    </trait>
    <trait category="subspecies">
      <name>Variant: Hellfire</name>
      <text>Once you reach 3rd level, you can cast the burning hands spell once as a 2nd-level spell. This trait replaces the hellish rebuke spell of the Infernal Legacy trait.</text>
    </trait>
    <trait category="subspecies">
      <name>Variant: Winged</name>
      <text>You have bat-like wings sprouting from your shoulder blades. You have a flying speed of 30 feet while you aren't wearing heavy armor. This replaces the Infernal Legacy trait.</text>
    </trait>
    <trait>
      <name>Languages</name>
      <text>You can speak, read, and write Common and Infernal.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        // Should expand into 4 separate subraces
        $this->assertCount(4, $races);

        // All should have Tiefling as base race
        foreach ($races as $race) {
            $this->assertEquals('Tiefling', $race['base_race_name']);
        }

        // Extract race names for easier assertions
        $raceNames = array_column($races, 'name');
        $this->assertContains('Feral', $raceNames);
        $this->assertContains("Devil's Tongue", $raceNames);
        $this->assertContains('Hellfire', $raceNames);
        $this->assertContains('Winged', $raceNames);

        // All should have the same ability scores (Dex +2, Int +1)
        foreach ($races as $race) {
            $this->assertCount(2, $race['ability_bonuses']);
            $this->assertEquals('Dex', $race['ability_bonuses'][0]['ability']);
            $this->assertEquals('Int', $race['ability_bonuses'][1]['ability']);
        }

        // All should have Darkvision trait
        foreach ($races as $race) {
            $traitNames = array_column($race['traits'], 'name');
            $this->assertContains('Darkvision', $traitNames, "Race {$race['name']} missing Darkvision");
        }

        // All should have Hellish Resistance trait
        foreach ($races as $race) {
            $traitNames = array_column($race['traits'], 'name');
            $this->assertContains('Hellish Resistance', $traitNames, "Race {$race['name']} missing Hellish Resistance");
        }

        // All should have Variant: Appearance trait (duplicated per design)
        foreach ($races as $race) {
            $traitNames = array_column($race['traits'], 'name');
            $this->assertContains('Variant: Appearance', $traitNames, "Race {$race['name']} missing Variant: Appearance");
        }

        // Feral should have Infernal Legacy
        $feral = collect($races)->firstWhere('name', 'Feral');
        $feralTraitNames = array_column($feral['traits'], 'name');
        $this->assertContains('Infernal Legacy', $feralTraitNames);
        $this->assertNotContains("Variant: Devil's Tongue", $feralTraitNames);
        $this->assertNotContains('Variant: Hellfire', $feralTraitNames);
        $this->assertNotContains('Variant: Winged', $feralTraitNames);

        // Devil's Tongue should have its trait but NOT Infernal Legacy
        $devilsTongue = collect($races)->firstWhere('name', "Devil's Tongue");
        $devilsTongueTraitNames = array_column($devilsTongue['traits'], 'name');
        $this->assertContains("Variant: Devil's Tongue", $devilsTongueTraitNames);
        $this->assertNotContains('Infernal Legacy', $devilsTongueTraitNames);

        // Hellfire should have its trait but NOT Infernal Legacy
        $hellfire = collect($races)->firstWhere('name', 'Hellfire');
        $hellfireTraitNames = array_column($hellfire['traits'], 'name');
        $this->assertContains('Variant: Hellfire', $hellfireTraitNames);
        $this->assertNotContains('Infernal Legacy', $hellfireTraitNames);

        // Winged should have its trait but NOT Infernal Legacy
        $winged = collect($races)->firstWhere('name', 'Winged');
        $wingedTraitNames = array_column($winged['traits'], 'name');
        $this->assertContains('Variant: Winged', $wingedTraitNames);
        $this->assertNotContains('Infernal Legacy', $wingedTraitNames);
    }

    #[Test]
    public function it_parses_spell_without_level_requirement()
    {
        // Test case: Eladrin (DMG) - "You can cast the misty step spell once using this trait"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, Eladrin (DMG)</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Dex +2, Int +1</ability>
    <trait category="subspecies">
      <name>Fey Step</name>
      <text>You can cast the misty step spell once using this trait. You regain the ability to do so when you finish a short or long rest.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('spellcasting', $races[0]);
        $this->assertCount(1, $races[0]['spellcasting']['spells']);

        $spell = $races[0]['spellcasting']['spells'][0];
        $this->assertEquals('misty step', $spell['spell_name']);
        $this->assertFalse($spell['is_cantrip']);
        $this->assertNull($spell['level_requirement']);
        $this->assertEquals('1/short rest', $spell['usage_limit']);
    }

    #[Test]
    public function it_parses_spell_with_long_rest_recovery()
    {
        // Test case for spells with long rest only recovery
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Test Race</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="subspecies">
      <name>Magic Trait</name>
      <text>You can cast the shield spell once using this trait. You regain the ability to do so when you finish a long rest.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $spell = $races[0]['spellcasting']['spells'][0];
        $this->assertEquals('shield', $spell['spell_name']);
        $this->assertEquals('1/long rest', $spell['usage_limit']);
    }

    #[Test]
    public function it_parses_skill_choice_from_skills_trait()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <race>
    <name>Human, Variant</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="subspecies">
      <name>Skills</name>
      <text>You gain proficiency in one skill of your choice.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $proficiencies = $races[0]['proficiencies'];
        $skillChoice = collect($proficiencies)->first(fn ($p) => $p['type'] === 'skill' && ($p['is_choice'] ?? false));

        $this->assertNotNull($skillChoice, 'Should parse skill choice from Skills trait');
        $this->assertEquals(1, $skillChoice['quantity']);
        $this->assertTrue($skillChoice['is_choice']);
    }

    #[Test]
    public function it_parses_bonus_feat_from_feat_trait()
    {
        // Test case: Variant Human - "You gain one feat of your choice"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <race>
    <name>Human, Variant</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Feat</name>
      <text>You gain one feat of your choice.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('modifiers', $races[0]);

        // Should have a bonus_feat modifier with value 1
        $bonusFeatModifier = collect($races[0]['modifiers'])
            ->firstWhere('modifier_category', 'bonus_feat');

        $this->assertNotNull($bonusFeatModifier, 'Should parse bonus_feat modifier from Feat trait');
        $this->assertEquals(1, $bonusFeatModifier['value']);
    }

    #[Test]
    public function it_parses_bonus_feat_from_custom_lineage()
    {
        // Test case: Custom Lineage - "You gain one feat of your choice for which you qualify"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <race>
    <name>Custom Lineage</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Feat</name>
      <text>You gain one feat of your choice for which you qualify.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('modifiers', $races[0]);

        // Should have a bonus_feat modifier with value 1
        $bonusFeatModifier = collect($races[0]['modifiers'])
            ->firstWhere('modifier_category', 'bonus_feat');

        $this->assertNotNull($bonusFeatModifier, 'Should parse bonus_feat modifier from Feat trait');
        $this->assertEquals(1, $bonusFeatModifier['value']);
    }

    #[Test]
    public function it_does_not_parse_bonus_feat_from_non_feat_trait()
    {
        // Make sure we don't accidentally parse "feat" from other trait text
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <race>
    <name>Human</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Description</name>
      <text>Humans are versatile and can accomplish great feats.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);

        // Should NOT have a bonus_feat modifier
        $bonusFeatModifier = collect($races[0]['modifiers'] ?? [])
            ->firstWhere('modifier_category', 'bonus_feat');

        $this->assertNull($bonusFeatModifier, 'Should not parse bonus_feat from non-Feat traits');
    }

    #[Test]
    public function it_parses_size_choice_from_custom_lineage()
    {
        // Test case: Custom Lineage - "You are Small or Medium (your choice)"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <race>
    <name>Custom Lineage</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Size</name>
      <text>You are Small or Medium (your choice).</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('has_size_choice', $races[0]);
        $this->assertTrue($races[0]['has_size_choice'], 'Should detect size choice from Size trait');
    }

    #[Test]
    public function it_does_not_parse_size_choice_from_fixed_size()
    {
        // Standard races have fixed size, not a choice
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <race>
    <name>Human</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Size</name>
      <text>Humans vary widely in height and build. Your size is Medium.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('has_size_choice', $races[0]);
        $this->assertFalse($races[0]['has_size_choice'], 'Should not detect size choice for fixed-size races');
    }

    #[Test]
    public function it_parses_skill_advantages_from_trait_descriptions()
    {
        // Stonecunning trait from Dwarf race (without Source to avoid DB lookup)
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <race>
    <name>Dwarf</name>
    <size>M</size>
    <speed>25</speed>
    <ability>Con +2</ability>
    <trait category="description">
      <name>Description</name>
      <text>Dwarves are solid and enduring like the mountains.</text>
    </trait>
    <trait>
      <name>Stonecunning</name>
      <text>Whenever you make an Intelligence (History) check related to the origin of stonework, you are considered proficient in the History skill and add double your proficiency bonus to the check, instead of your normal proficiency bonus. You have advantage on Intelligence (History) checks related to the origin of stonework.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('skill_advantage_modifiers', $races[0]);
        $this->assertCount(1, $races[0]['skill_advantage_modifiers']);

        $modifier = $races[0]['skill_advantage_modifiers'][0];
        $this->assertEquals('skill_advantage', $modifier['modifier_category']);
        $this->assertEquals('History', $modifier['skill_name']);
        $this->assertEquals('advantage', $modifier['value']);
        $this->assertEquals('the origin of stonework', $modifier['condition']);
    }

    #[Test]
    public function it_returns_empty_skill_advantages_when_none_found()
    {
        // Minimal race without traits that would trigger DB lookups
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <race>
    <name>Custom Race</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>A custom race with no skill advantages.</text>
    </trait>
  </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('skill_advantage_modifiers', $races[0]);
        $this->assertCount(0, $races[0]['skill_advantage_modifiers']);
    }
}
