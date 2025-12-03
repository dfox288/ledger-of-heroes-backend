<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ClassXmlParserSpellsKnownTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_spells_known_into_spell_progression(): void
    {
        // Test with NON-optional slots (e.g., Wizard)
        // Optional slots should NOT create base class spell progression
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Wizard</name>
    <hd>6</hd>
    <autolevel level="3">
      <slots>2,4,2</slots>
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
    </autolevel>
    <autolevel level="4">
      <slots>3,4,3</slots>
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

        $this->assertCount(2, $data[0]['spell_progression']);

        // Level 3
        $this->assertEquals(3, $data[0]['spell_progression'][0]['level']);
        $this->assertEquals(3, $data[0]['spell_progression'][0]['spells_known']);
        $this->assertEquals(2, $data[0]['spell_progression'][0]['cantrips_known']);

        // Level 4
        $this->assertEquals(4, $data[0]['spell_progression'][1]['level']);
        $this->assertEquals(4, $data[0]['spell_progression'][1]['spells_known']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_include_spells_known_in_counters(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <autolevel level="3">
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
      <counter>
        <name>Second Wind</name>
        <value>1</value>
        <reset>S</reset>
      </counter>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        // Should only have Second Wind counter
        $this->assertCount(1, $data[0]['counters']);
        $this->assertEquals('Second Wind', $data[0]['counters'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_merges_spells_known_from_separate_autolevel_elements(): void
    {
        // Real-world case: Bard has slots and counters in SEPARATE autolevels
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Bard</name>
    <hd>8</hd>
    <!-- Slots in one autolevel -->
    <autolevel level="1">
      <slots>2,2</slots>
    </autolevel>
    <autolevel level="2">
      <slots>2,3</slots>
    </autolevel>
    <!-- Spells Known counters in separate autolevels -->
    <autolevel level="1">
      <counter>
        <name>Spells Known</name>
        <value>4</value>
      </counter>
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

        $this->assertCount(2, $data[0]['spell_progression']);

        // Level 1: should have merged spells_known from separate autolevel
        $level1 = collect($data[0]['spell_progression'])->firstWhere('level', 1);
        $this->assertEquals(4, $level1['spells_known'], 'Level 1 should have 4 spells known');
        $this->assertEquals(2, $level1['cantrips_known']);
        $this->assertEquals(2, $level1['spell_slots_1st']);

        // Level 2
        $level2 = collect($data[0]['spell_progression'])->firstWhere('level', 2);
        $this->assertEquals(5, $level2['spells_known'], 'Level 2 should have 5 spells known');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_spells_known_for_optional_slots(): void
    {
        // Classes with optional slots (Fighter, Rogue) should NOT have spell progression
        // The "Spells Known" counters are for subclasses only
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <autolevel level="3">
      <slots optional="YES">2,2</slots>
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        // Optional slots should NOT create spell progression
        $this->assertEmpty($data[0]['spell_progression'], 'Optional slots should not create base class spell progression');
    }
}
