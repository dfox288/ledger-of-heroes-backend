<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassXmlParserTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_parses_fighter_base_class()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $this->assertFileExists($xmlPath, 'Fighter XML file should exist');

        $xml = file_get_contents($xmlPath);
        $this->assertNotEmpty($xml, 'Fighter XML should not be empty');

        // Parse the XML
        $classes = $this->parser->parse($xml);

        // Assert: result is array
        $this->assertIsArray($classes);

        // Assert: at least one class (base Fighter)
        $this->assertGreaterThanOrEqual(1, count($classes));

        // Assert: first element is Fighter
        $fighter = $classes[0];
        $this->assertArrayHasKey('name', $fighter);
        $this->assertEquals('Fighter', $fighter['name']);

        // Assert: hit die is 10
        $this->assertArrayHasKey('hit_die', $fighter);
        $this->assertEquals(10, $fighter['hit_die']);
    }
}
