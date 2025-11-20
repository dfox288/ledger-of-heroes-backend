<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Tests\TestCase;

class ClassXmlParserSpellsKnownTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_spells_known_into_spell_progression(): void
    {
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
    <autolevel level="4">
      <slots optional="YES">2,3</slots>
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
    public function it_handles_spells_known_without_slots(): void
    {
        // Some levels might have spells_known counter but no slots element
        // Parser should still create spell_progression entry
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
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        $this->assertCount(1, $data[0]['spell_progression']);
        $this->assertEquals(3, $data[0]['spell_progression'][0]['level']);
        $this->assertEquals(3, $data[0]['spell_progression'][0]['spells_known']);
        $this->assertEquals(0, $data[0]['spell_progression'][0]['cantrips_known']);
    }
}
