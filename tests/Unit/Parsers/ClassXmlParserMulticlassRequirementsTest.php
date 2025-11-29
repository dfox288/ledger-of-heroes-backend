<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for parsing multiclass ability score requirements from class XML.
 *
 * Requirements are found in the "Multiclass {Class}" feature description
 * and follow patterns like:
 * - Single: "• Charisma 13"
 * - AND: "• Dexterity 13\n• Wisdom 13"
 * - OR: "• Strength 13, or\n• Dexterity 13"
 */
#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ClassXmlParserMulticlassRequirementsTest extends TestCase
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
    public function it_parses_single_ability_requirement()
    {
        // Bard requires only Charisma 13
        $xml = $this->createClassXmlWithMulticlassFeature('Bard', <<<'TEXT'
Ability Score Minimum:
As a multiclass character, you must have at least the following ability score(s).

	• Charisma 13

Proficiencies Gained:
TEXT
        );

        $classes = $this->parser->parse($xml);
        $bard = $classes[0];

        $this->assertArrayHasKey('multiclass_requirements', $bard);
        $requirements = $bard['multiclass_requirements'];

        $this->assertCount(1, $requirements);
        $this->assertEquals('charisma', $requirements[0]['ability']);
        $this->assertEquals(13, $requirements[0]['minimum']);
        $this->assertFalse($requirements[0]['is_alternative']);
    }

    #[Test]
    public function it_parses_and_ability_requirements()
    {
        // Monk requires Dexterity 13 AND Wisdom 13
        $xml = $this->createClassXmlWithMulticlassFeature('Monk', <<<'TEXT'
Ability Score Minimum:
As a multiclass character, you must have at least the following ability score(s).

	• Dexterity 13
	• Wisdom 13

Proficiencies Gained:
TEXT
        );

        $classes = $this->parser->parse($xml);
        $monk = $classes[0];

        $this->assertArrayHasKey('multiclass_requirements', $monk);
        $requirements = $monk['multiclass_requirements'];

        $this->assertCount(2, $requirements);

        // Both should be AND (not alternatives)
        $this->assertEquals('dexterity', $requirements[0]['ability']);
        $this->assertEquals(13, $requirements[0]['minimum']);
        $this->assertFalse($requirements[0]['is_alternative']);

        $this->assertEquals('wisdom', $requirements[1]['ability']);
        $this->assertEquals(13, $requirements[1]['minimum']);
        $this->assertFalse($requirements[1]['is_alternative']);
    }

    #[Test]
    public function it_parses_or_ability_requirements()
    {
        // Fighter requires Strength 13 OR Dexterity 13
        $xml = $this->createClassXmlWithMulticlassFeature('Fighter', <<<'TEXT'
Ability Score Minimum:
As a multiclass character, you must have at least 1 of the following ability score(s).

	• Strength 13, or
	• Dexterity 13

Proficiencies Gained:
TEXT
        );

        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        $this->assertArrayHasKey('multiclass_requirements', $fighter);
        $requirements = $fighter['multiclass_requirements'];

        $this->assertCount(2, $requirements);

        // Both should be OR (alternatives)
        $this->assertEquals('strength', $requirements[0]['ability']);
        $this->assertEquals(13, $requirements[0]['minimum']);
        $this->assertTrue($requirements[0]['is_alternative']);

        $this->assertEquals('dexterity', $requirements[1]['ability']);
        $this->assertEquals(13, $requirements[1]['minimum']);
        $this->assertTrue($requirements[1]['is_alternative']);
    }

    #[Test]
    public function it_parses_paladin_and_requirements()
    {
        // Paladin requires Strength 13 AND Charisma 13
        $xml = $this->createClassXmlWithMulticlassFeature('Paladin', <<<'TEXT'
Ability Score Minimum:
As a multiclass character, you must have at least the following ability score(s).

	• Strength 13
	• Charisma 13

Proficiencies Gained:
TEXT
        );

        $classes = $this->parser->parse($xml);
        $paladin = $classes[0];

        $this->assertArrayHasKey('multiclass_requirements', $paladin);
        $requirements = $paladin['multiclass_requirements'];

        $this->assertCount(2, $requirements);

        $this->assertEquals('strength', $requirements[0]['ability']);
        $this->assertFalse($requirements[0]['is_alternative']);

        $this->assertEquals('charisma', $requirements[1]['ability']);
        $this->assertFalse($requirements[1]['is_alternative']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_multiclass_feature()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <class>
    <name>TestClass</name>
    <hd>8</hd>
    <autolevel level="1">
      <feature>
        <name>Some Feature</name>
        <text>Description</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $testClass = $classes[0];

        $this->assertArrayHasKey('multiclass_requirements', $testClass);
        $this->assertEmpty($testClass['multiclass_requirements']);
    }

    #[Test]
    public function it_parses_real_fighter_xml()
    {
        $xmlPath = base_path('import-files/class-fighter-phb.xml');

        if (! file_exists($xmlPath)) {
            $this->markTestSkipped('Fighter XML file not available');
        }

        $xml = file_get_contents($xmlPath);
        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        $this->assertArrayHasKey('multiclass_requirements', $fighter);
        $requirements = $fighter['multiclass_requirements'];

        // Fighter has OR requirement: STR 13 or DEX 13
        $this->assertCount(2, $requirements);
        $this->assertTrue($requirements[0]['is_alternative']);
        $this->assertTrue($requirements[1]['is_alternative']);

        $abilities = array_column($requirements, 'ability');
        $this->assertContains('strength', $abilities);
        $this->assertContains('dexterity', $abilities);
    }

    #[Test]
    public function it_parses_real_monk_xml()
    {
        $xmlPath = base_path('import-files/class-monk-phb.xml');

        if (! file_exists($xmlPath)) {
            $this->markTestSkipped('Monk XML file not available');
        }

        $xml = file_get_contents($xmlPath);
        $classes = $this->parser->parse($xml);
        $monk = $classes[0];

        $this->assertArrayHasKey('multiclass_requirements', $monk);
        $requirements = $monk['multiclass_requirements'];

        // Monk has AND requirement: DEX 13 and WIS 13
        $this->assertCount(2, $requirements);
        $this->assertFalse($requirements[0]['is_alternative']);
        $this->assertFalse($requirements[1]['is_alternative']);

        $abilities = array_column($requirements, 'ability');
        $this->assertContains('dexterity', $abilities);
        $this->assertContains('wisdom', $abilities);
    }

    /**
     * Helper to create minimal class XML with a Multiclass feature.
     */
    private function createClassXmlWithMulticlassFeature(string $className, string $multiclassText): string
    {
        $featureName = "Multiclass {$className}";

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <class>
    <name>{$className}</name>
    <hd>8</hd>
    <autolevel level="1">
      <feature optional="YES">
        <name>{$featureName}</name>
        <text>{$multiclassText}</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }
}
