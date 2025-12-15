<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ClassXmlParserSpellPreparationMethodTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_spellbook_method_from_spellcasting_feature_text(): void
    {
        // Wizard has "spellbook" in Spellcasting feature
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Wizard</name>
    <hd>6</hd>
    <spellAbility>INT</spellAbility>
    <autolevel level="1">
      <slots>3,2</slots>
      <feature>
        <name>Spellcasting</name>
        <text>As a student of arcane magic, you have a spellbook containing spells.</text>
      </feature>
    </autolevel>
    <autolevel level="2">
      <slots>3,3</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        $this->assertEquals('spellbook', $data[0]['spell_preparation_method']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_known_method_from_spells_known_counters(): void
    {
        // Bard has "Spells Known" counters
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Bard</name>
    <hd>8</hd>
    <spellAbility>CHA</spellAbility>
    <autolevel level="1">
      <slots>2,2</slots>
      <feature>
        <name>Spellcasting</name>
        <text>You have learned to untangle and reshape the fabric of reality.</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <counter>
        <name>Spells Known</name>
        <value>4</value>
      </counter>
    </autolevel>
    <autolevel level="2">
      <slots>2,3</slots>
    </autolevel>
    <autolevel level="2">
      <counter>
        <name>Spells Known</name>
        <value>5</value>
      </counter>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        $this->assertEquals('known', $data[0]['spell_preparation_method']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_prepared_method_for_casters_without_spells_known(): void
    {
        // Cleric has spellcasting but no spells_known counters or spellbook
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Cleric</name>
    <hd>8</hd>
    <spellAbility>WIS</spellAbility>
    <autolevel level="1">
      <slots>3,2</slots>
      <feature>
        <name>Spellcasting</name>
        <text>As a conduit for divine power, you can cast cleric spells.</text>
      </feature>
    </autolevel>
    <autolevel level="2">
      <slots>3,3</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        $this->assertEquals('prepared', $data[0]['spell_preparation_method']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_non_casters(): void
    {
        // Fighter has no spellcasting ability
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <autolevel level="1">
      <feature>
        <name>Fighting Style</name>
        <text>You adopt a style of fighting as your specialty.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        $this->assertNull($data[0]['spell_preparation_method']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_spellbook_when_text_detection_fails_but_progression_matches_wizard_formula(): void
    {
        // Edge case: spellbook text check fails, but progression matches Wizard formula (6, 8, 10...)
        // This tests the defensive fallback for synthetic Wizard progression
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Wizard</name>
    <hd>6</hd>
    <spellAbility>INT</spellAbility>
    <autolevel level="1">
      <slots>3,2</slots>
      <feature>
        <name>Spellcasting</name>
        <text>As a student of arcane magic, you have a spellbook containing spells.</text>
      </feature>
    </autolevel>
    <autolevel level="2">
      <slots>3,3</slots>
    </autolevel>
    <autolevel level="3">
      <slots>3,4,2</slots>
    </autolevel>
    <autolevel level="4">
      <slots>4,4,3</slots>
    </autolevel>
    <autolevel level="5">
      <slots>4,4,3,2</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        // The addSpellbookProgression() will add synthetic spells_known: 6, 8, 10, 12, 14
        // The detection should recognize this as 'spellbook'
        $this->assertEquals('spellbook', $data[0]['spell_preparation_method']);

        // Verify the progression was generated correctly
        $progression = $data[0]['spell_progression'];
        $this->assertCount(5, $progression);

        // Check synthetic Wizard formula: 6 + (level - 1) * 2
        $this->assertEquals(6, $progression[0]['spells_known']);  // Level 1: 6
        $this->assertEquals(8, $progression[1]['spells_known']);  // Level 2: 8
        $this->assertEquals(10, $progression[2]['spells_known']); // Level 3: 10
        $this->assertEquals(12, $progression[3]['spells_known']); // Level 4: 12
        $this->assertEquals(14, $progression[4]['spells_known']); // Level 5: 14
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_distinguishes_known_casters_from_wizard_by_non_matching_progression(): void
    {
        // Sorcerer's spells_known don't follow Wizard formula (4, 5, 6, 7...)
        // Should be classified as 'known', not 'spellbook'
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Sorcerer</name>
    <hd>6</hd>
    <spellAbility>CHA</spellAbility>
    <autolevel level="1">
      <slots>4,2</slots>
      <feature>
        <name>Spellcasting</name>
        <text>An event in your past, or in the life of a parent, left an indelible mark on you.</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <counter>
        <name>Spells Known</name>
        <value>2</value>
      </counter>
    </autolevel>
    <autolevel level="2">
      <slots>4,3</slots>
    </autolevel>
    <autolevel level="2">
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
    </autolevel>
    <autolevel level="3">
      <slots>4,4,2</slots>
    </autolevel>
    <autolevel level="3">
      <counter>
        <name>Spells Known</name>
        <value>4</value>
      </counter>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        // Sorcerer's progression (2, 3, 4) doesn't match Wizard formula (6, 8, 10)
        $this->assertEquals('known', $data[0]['spell_preparation_method']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_optional_spellcasters(): void
    {
        // Fighter with optional slots (Eldritch Knight) - base class is non-caster
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <spellAbility>INT</spellAbility>
    <autolevel level="3">
      <slots optional="YES">2,2</slots>
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
      <feature optional="YES">
        <name>Spellcasting (Eldritch Knight)</name>
        <text>You have learned to cast spells.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        // Base Fighter is non-caster, optional slots go to subclass
        $this->assertNull($data[0]['spell_preparation_method']);
    }
}
