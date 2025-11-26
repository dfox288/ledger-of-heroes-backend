<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SpellClassMappingParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class SpellClassMappingParserTest extends TestCase
{
    private SpellClassMappingParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpellClassMappingParser;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_spell_name_and_classes_from_xml()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Animate Dead</name>
    <classes>Cleric (Death Domain), Paladin (Oathbreaker)</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);

        $result = $this->parser->parse($filePath);

        $this->assertArrayHasKey('Animate Dead', $result);
        $this->assertEquals([
            'Cleric (Death Domain)',
            'Paladin (Oathbreaker)',
        ], $result['Animate Dead']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_multiple_spells()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Animate Dead</name>
    <classes>Cleric (Death Domain), Paladin (Oathbreaker)</classes>
  </spell>
  <spell>
    <name>Blight</name>
    <classes>Cleric (Death Domain)</classes>
  </spell>
  <spell>
    <name>Confusion</name>
    <classes>Paladin (Oathbreaker)</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);

        $result = $this->parser->parse($filePath);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('Animate Dead', $result);
        $this->assertArrayHasKey('Blight', $result);
        $this->assertArrayHasKey('Confusion', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_single_class_without_subclass()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Fireball</name>
    <classes>Wizard</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);

        $result = $this->parser->parse($filePath);

        $this->assertEquals(['Wizard'], $result['Fireball']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_spells_without_name()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <classes>Wizard</classes>
  </spell>
  <spell>
    <name>Fireball</name>
    <classes>Wizard</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);

        $result = $this->parser->parse($filePath);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('Fireball', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_spells_without_classes()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Fireball</name>
  </spell>
  <spell>
    <name>Magic Missile</name>
    <classes>Wizard</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);

        $result = $this->parser->parse($filePath);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('Magic Missile', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_missing_file()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('XML file not found');

        $this->parser->parse('/nonexistent/file.xml');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_invalid_xml()
    {
        $invalidXml = '<?xml version="1.0"?><broken><unclosed>';
        $filePath = $this->createTempXmlFile($invalidXml);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to parse XML');

        $this->parser->parse($filePath);
    }

    /**
     * Create a temporary XML file for testing.
     */
    private function createTempXmlFile(string $content): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'spell_test_');
        file_put_contents($filePath, $content);

        // Register for cleanup after test
        $this->beforeApplicationDestroyed(function () use ($filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        });

        return $filePath;
    }
}
