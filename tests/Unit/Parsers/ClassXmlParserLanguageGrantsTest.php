<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ClassXmlParserLanguageGrantsTest extends TestCase
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
    public function it_detects_thieves_cant_language_grant_from_rogue()
    {
        $xml = '<compendium>
            <class>
                <name>Rogue</name>
                <hd>8</hd>
                <autolevel level="1">
                    <feature>
                        <name>Thieves\' Cant</name>
                        <text>During your rogue training you learned thieves\' cant, a secret mix of dialect, jargon, and code.</text>
                    </feature>
                </autolevel>
            </class>
        </compendium>';

        $classes = $this->parser->parse($xml);
        $rogue = $classes[0];

        // Assert languages array exists
        $this->assertArrayHasKey('languages', $rogue);
        $this->assertIsArray($rogue['languages']);
        $this->assertCount(1, $rogue['languages']);

        // Assert Thieves' Cant is detected
        $language = $rogue['languages'][0];
        $this->assertEquals('thieves-cant', $language['slug']);
        $this->assertFalse($language['is_choice']);
        $this->assertEquals(1, $language['level']);
    }

    #[Test]
    public function it_detects_druidic_language_grant_from_druid()
    {
        $xml = '<compendium>
            <class>
                <name>Druid</name>
                <hd>8</hd>
                <autolevel level="1">
                    <feature>
                        <name>Druidic</name>
                        <text>You know Druidic, the secret language of druids.</text>
                    </feature>
                </autolevel>
            </class>
        </compendium>';

        $classes = $this->parser->parse($xml);
        $druid = $classes[0];

        // Assert languages array exists
        $this->assertArrayHasKey('languages', $druid);
        $this->assertIsArray($druid['languages']);
        $this->assertCount(1, $druid['languages']);

        // Assert Druidic is detected
        $language = $druid['languages'][0];
        $this->assertEquals('druidic', $language['slug']);
        $this->assertFalse($language['is_choice']);
        $this->assertEquals(1, $language['level']);
    }

    #[Test]
    public function it_returns_empty_languages_for_classes_without_language_grants()
    {
        $xml = '<compendium>
            <class>
                <name>Fighter</name>
                <hd>10</hd>
                <autolevel level="1">
                    <feature>
                        <name>Fighting Style</name>
                        <text>You adopt a particular style of fighting as your specialty.</text>
                    </feature>
                </autolevel>
            </class>
        </compendium>';

        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        // Assert languages array exists but is empty
        $this->assertArrayHasKey('languages', $fighter);
        $this->assertIsArray($fighter['languages']);
        $this->assertEmpty($fighter['languages']);
    }

    #[Test]
    public function it_parses_real_rogue_xml_for_thieves_cant()
    {
        // Load real Rogue XML from file
        $xmlPath = base_path('import-files/class-rogue-phb.xml');

        if (! file_exists($xmlPath)) {
            $this->markTestSkipped('Rogue XML file not found at: '.$xmlPath);
        }

        $xml = file_get_contents($xmlPath);
        $classes = $this->parser->parse($xml);
        $rogue = $classes[0];

        $this->assertEquals('Rogue', $rogue['name']);
        $this->assertArrayHasKey('languages', $rogue);

        // Find Thieves' Cant in languages
        $thievesCant = collect($rogue['languages'])->firstWhere('slug', 'thieves-cant');
        $this->assertNotNull($thievesCant, "Thieves' Cant should be detected in Rogue");
        $this->assertFalse($thievesCant['is_choice']);
    }

    #[Test]
    public function it_parses_real_druid_xml_for_druidic()
    {
        // Load real Druid XML from file
        $xmlPath = base_path('import-files/class-druid-phb.xml');

        if (! file_exists($xmlPath)) {
            $this->markTestSkipped('Druid XML file not found at: '.$xmlPath);
        }

        $xml = file_get_contents($xmlPath);
        $classes = $this->parser->parse($xml);
        $druid = $classes[0];

        $this->assertEquals('Druid', $druid['name']);
        $this->assertArrayHasKey('languages', $druid);

        // Find Druidic in languages
        $druidic = collect($druid['languages'])->firstWhere('slug', 'druidic');
        $this->assertNotNull($druidic, 'Druidic should be detected in Druid');
        $this->assertFalse($druidic['is_choice']);
    }
}
