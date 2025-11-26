<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ClassXmlParserOptionalSlotsTest extends TestCase
{
    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_skips_optional_spell_slots_for_base_class()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <spellAbility>Intelligence</spellAbility>
    <slotsReset>L</slotsReset>
    <autolevel level="3">
      <slots optional="YES">3,2</slots>
    </autolevel>
    <autolevel level="4">
      <slots optional="YES">3,3</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class should have NO spell progression (all slots are optional)
        $this->assertEmpty($result[0]['spell_progression'], 'Base class should not have spell progression when all slots are optional');

        // Base class should NOT have spellcasting ability set
        $this->assertArrayNotHasKey('spellcasting_ability', $result[0], 'Base class should not have spellcasting ability when all slots are optional');
    }

    #[Test]
    public function it_includes_non_optional_spell_slots_for_base_class()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Wizard</name>
    <hd>6</hd>
    <spellAbility>Intelligence</spellAbility>
    <slotsReset>L</slotsReset>
    <autolevel level="1">
      <slots>3,2</slots>
    </autolevel>
    <autolevel level="2">
      <slots>3,3</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class SHOULD have spell progression (slots are NOT optional)
        $this->assertNotEmpty($result[0]['spell_progression'], 'Base class should have spell progression when slots are non-optional');
        $this->assertCount(2, $result[0]['spell_progression']);

        // Verify level 1 progression
        $this->assertEquals(1, $result[0]['spell_progression'][0]['level']);
        $this->assertEquals(3, $result[0]['spell_progression'][0]['cantrips_known']);
        $this->assertEquals(2, $result[0]['spell_progression'][0]['spell_slots_1st']);

        // Base class SHOULD have spellcasting ability
        $this->assertArrayHasKey('spellcasting_ability', $result[0], 'Base class should have spellcasting ability when slots are non-optional');
        $this->assertEquals('Intelligence', $result[0]['spellcasting_ability']);
    }

    #[Test]
    public function it_handles_mixed_optional_and_non_optional_slots()
    {
        // Hypothetical: A class with base spellcasting PLUS optional subclass slots
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>TestClass</name>
    <hd>8</hd>
    <spellAbility>Wisdom</spellAbility>
    <slotsReset>L</slotsReset>
    <autolevel level="1">
      <slots>2,2</slots>
    </autolevel>
    <autolevel level="2">
      <slots>2,3</slots>
    </autolevel>
    <autolevel level="3">
      <slots optional="YES">3,4,2</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Should only include levels 1-2 (non-optional)
        $this->assertCount(2, $result[0]['spell_progression'], 'Should only include non-optional spell slots');

        $levels = array_column($result[0]['spell_progression'], 'level');
        $this->assertEquals([1, 2], $levels, 'Should only have levels 1 and 2');

        // Should have spellcasting ability (because level 1-2 are non-optional)
        $this->assertArrayHasKey('spellcasting_ability', $result[0]);
        $this->assertEquals('Wisdom', $result[0]['spellcasting_ability']);
    }

    #[Test]
    public function it_handles_class_with_no_spell_slots_at_all()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Barbarian</name>
    <hd>12</hd>
    <autolevel level="1">
      <feature>
        <name>Rage</name>
        <text>In battle, you fight with primal ferocity.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // No spell progression
        $this->assertEmpty($result[0]['spell_progression'], 'Barbarian should have no spell progression');

        // No spellcasting ability
        $this->assertArrayNotHasKey('spellcasting_ability', $result[0], 'Barbarian should not have spellcasting ability');
    }

    #[Test]
    public function it_correctly_identifies_fighter_eldritch_knight_pattern()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <spellAbility>Intelligence</spellAbility>
    <slotsReset>L</slotsReset>
    <autolevel level="3">
      <slots optional="YES">2,2</slots>
    </autolevel>
    <autolevel level="4">
      <slots optional="YES">2,3</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base Fighter should have NO spell progression (Eldritch Knight only)
        $this->assertEmpty($result[0]['spell_progression'], 'Base Fighter should not have spell progression');

        // Base Fighter should NOT have spellcasting ability
        $this->assertArrayNotHasKey('spellcasting_ability', $result[0], 'Base Fighter should not have spellcasting ability');
    }

    #[Test]
    public function it_preserves_spell_slot_values_correctly()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Wizard</name>
    <hd>6</hd>
    <spellAbility>Intelligence</spellAbility>
    <autolevel level="9">
      <slots>4,4,3,3,3,1</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        $this->assertCount(1, $result[0]['spell_progression']);

        $progression = $result[0]['spell_progression'][0];
        $this->assertEquals(9, $progression['level']);
        $this->assertEquals(4, $progression['cantrips_known']);
        $this->assertEquals(4, $progression['spell_slots_1st']);
        $this->assertEquals(3, $progression['spell_slots_2nd']);
        $this->assertEquals(3, $progression['spell_slots_3rd']);
        $this->assertEquals(3, $progression['spell_slots_4th']);
        $this->assertEquals(1, $progression['spell_slots_5th']);
        $this->assertEquals(0, $progression['spell_slots_6th'], 'Unspecified slots should be 0');
        $this->assertEquals(0, $progression['spell_slots_7th'], 'Unspecified slots should be 0');
    }
}
