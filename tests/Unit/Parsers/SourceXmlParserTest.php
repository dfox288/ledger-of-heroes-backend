<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SourceXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class SourceXmlParserTest extends TestCase
{
    private SourceXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SourceXmlParser;
    }

    #[Test]
    public function it_parses_complete_source_xml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<source>
  <name>Player's Handbook (2014)</name>
  <abbreviation>PHB</abbreviation>
  <url>https://marketplace.dndbeyond.com/core-rules/players-handbook</url>
  <author>Jeremy Crawford, Mike Mearls</author>
  <artist>Kate Irwin</artist>
  <publisher>Wizards of the Coast</publisher>
  <website>https://www.dndbeyond.com/</website>
  <category>Core Rulebooks</category>
  <pubdate>2014-08-19</pubdate>
  <description>Everything a player needs to create heroic characters.</description>
  <collection>
    <doc href="class-barbarian-phb.xml"/>
    <doc href="spells-phb.xml"/>
  </collection>
</source>
XML;

        $result = $this->parser->parse($xml);

        $this->assertCount(1, $result);

        $source = $result[0];
        $this->assertEquals("Player's Handbook (2014)", $source['name']);
        $this->assertEquals('PHB', $source['code']);
        $this->assertEquals('https://marketplace.dndbeyond.com/core-rules/players-handbook', $source['url']);
        $this->assertEquals('Jeremy Crawford, Mike Mearls', $source['author']);
        $this->assertEquals('Kate Irwin', $source['artist']);
        $this->assertEquals('Wizards of the Coast', $source['publisher']);
        $this->assertEquals('https://www.dndbeyond.com/', $source['website']);
        $this->assertEquals('Core Rulebooks', $source['category']);
        $this->assertEquals(2014, $source['publication_year']);
        $this->assertEquals('Everything a player needs to create heroic characters.', $source['description']);
    }

    #[Test]
    public function it_extracts_year_from_pubdate(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<source>
  <name>Test Source</name>
  <abbreviation>TST</abbreviation>
  <pubdate>2020-11-17</pubdate>
</source>
XML;

        $result = $this->parser->parse($xml);

        $this->assertEquals(2020, $result[0]['publication_year']);
    }

    #[Test]
    public function it_handles_missing_optional_fields(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<source>
  <name>Minimal Source</name>
  <abbreviation>MIN</abbreviation>
</source>
XML;

        $result = $this->parser->parse($xml);

        $this->assertCount(1, $result);

        $source = $result[0];
        $this->assertEquals('Minimal Source', $source['name']);
        $this->assertEquals('MIN', $source['code']);
        $this->assertNull($source['url']);
        $this->assertNull($source['author']);
        $this->assertNull($source['artist']);
        $this->assertEquals('Wizards of the Coast', $source['publisher']); // Default
        $this->assertNull($source['website']);
        $this->assertNull($source['category']);
        $this->assertNull($source['publication_year']);
        $this->assertNull($source['description']);
    }

    #[Test]
    public function it_returns_empty_array_for_invalid_xml(): void
    {
        $result = $this->parser->parse('not valid xml');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_for_non_source_root(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <spell>
    <name>Fireball</name>
  </spell>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_trims_whitespace_from_fields(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<source>
  <name>  Whitespace Test  </name>
  <abbreviation>  WST  </abbreviation>
  <author>  Author Name  </author>
</source>
XML;

        $result = $this->parser->parse($xml);

        $this->assertEquals('Whitespace Test', $result[0]['name']);
        $this->assertEquals('WST', $result[0]['code']);
        $this->assertEquals('Author Name', $result[0]['author']);
    }

    #[Test]
    public function it_handles_empty_string_fields_as_null(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<source>
  <name>Test</name>
  <abbreviation>TST</abbreviation>
  <url></url>
  <author></author>
</source>
XML;

        $result = $this->parser->parse($xml);

        $this->assertNull($result[0]['url']);
        $this->assertNull($result[0]['author']);
    }
}
