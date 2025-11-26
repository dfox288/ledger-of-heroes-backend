<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\ParsesTraits;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ParsesTraitsTest extends TestCase
{
    use ParsesTraits;

    #[Test]
    public function it_parses_basic_trait_with_name_and_description()
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Darkvision</name>
                <text>You can see in dim light within 60 feet of you.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertCount(1, $traits);
        $this->assertEquals('Darkvision', $traits[0]['name']);
        $this->assertStringContainsString('60 feet', $traits[0]['description']);
        $this->assertNull($traits[0]['category']);
        $this->assertEquals(0, $traits[0]['sort_order']);
    }

    #[Test]
    public function it_parses_trait_with_category()
    {
        $xml = <<<'XML'
        <root>
            <trait category="racial">
                <name>Fey Ancestry</name>
                <text>You have advantage on saving throws.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertEquals('racial', $traits[0]['category']);
    }

    #[Test]
    public function it_parses_multiple_traits_with_sort_order()
    {
        $xml = <<<'XML'
        <root>
            <trait><name>First</name><text>One</text></trait>
            <trait><name>Second</name><text>Two</text></trait>
            <trait><name>Third</name><text>Three</text></trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertCount(3, $traits);
        $this->assertEquals(0, $traits[0]['sort_order']);
        $this->assertEquals(1, $traits[1]['sort_order']);
        $this->assertEquals(2, $traits[2]['sort_order']);
    }

    #[Test]
    public function it_parses_traits_with_embedded_rolls()
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Breath Weapon</name>
                <text>You can use your breath weapon.</text>
                <roll description="Fire damage">2d6</roll>
                <roll description="At 11th level" level="11">3d6</roll>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $rolls = $traits[0]['rolls'];
        $this->assertCount(2, $rolls);
        $this->assertEquals('Fire damage', $rolls[0]['description']);
        $this->assertEquals('2d6', $rolls[0]['formula']);
        $this->assertNull($rolls[0]['level']);
        $this->assertEquals(11, $rolls[1]['level']);
    }

    #[Test]
    public function it_handles_traits_without_rolls()
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Lucky</name>
                <text>You can reroll dice.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertEmpty($traits[0]['rolls']);
    }

    #[Test]
    public function it_trims_whitespace_from_descriptions()
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Test</name>
                <text>

                    Description with whitespace

                </text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertEquals('Description with whitespace', $traits[0]['description']);
    }

    // Mock the parseRollElements method that will come from ParsesRolls trait
    protected function parseRollElements(\SimpleXMLElement $element): array
    {
        $rolls = [];
        foreach ($element->roll as $rollElement) {
            $rolls[] = [
                'description' => isset($rollElement['description']) ? (string) $rollElement['description'] : null,
                'formula' => (string) $rollElement,
                'level' => isset($rollElement['level']) ? (int) $rollElement['level'] : null,
            ];
        }

        return $rolls;
    }
}
