<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\ParsesRolls;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ParsesRollsTest extends TestCase
{
    use ParsesRolls;

    #[Test]
    public function it_parses_basic_roll_with_formula()
    {
        $xml = <<<'XML'
        <root>
            <roll>2d6</roll>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $rolls = $this->parseRollElements($element);

        $this->assertCount(1, $rolls);
        $this->assertEquals('2d6', $rolls[0]['formula']);
        $this->assertNull($rolls[0]['description']);
        $this->assertNull($rolls[0]['level']);
    }

    #[Test]
    public function it_parses_roll_with_description_attribute()
    {
        $xml = <<<'XML'
        <root>
            <roll description="Fire damage">2d6</roll>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $rolls = $this->parseRollElements($element);

        $this->assertEquals('Fire damage', $rolls[0]['description']);
        $this->assertEquals('2d6', $rolls[0]['formula']);
    }

    #[Test]
    public function it_parses_roll_with_level_attribute()
    {
        $xml = <<<'XML'
        <root>
            <roll description="At 5th level" level="5">3d6</roll>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $rolls = $this->parseRollElements($element);

        $this->assertEquals(5, $rolls[0]['level']);
    }

    #[Test]
    public function it_parses_multiple_rolls()
    {
        $xml = <<<'XML'
        <root>
            <roll description="Level 1">1d6</roll>
            <roll description="Level 5" level="5">2d6</roll>
            <roll description="Level 11" level="11">3d6</roll>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $rolls = $this->parseRollElements($element);

        $this->assertCount(3, $rolls);
        $this->assertEquals('1d6', $rolls[0]['formula']);
        $this->assertEquals('2d6', $rolls[1]['formula']);
        $this->assertEquals('3d6', $rolls[2]['formula']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_rolls()
    {
        $xml = <<<'XML'
        <root>
            <text>No rolls here</text>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $rolls = $this->parseRollElements($element);

        $this->assertEmpty($rolls);
    }

    #[Test]
    public function it_handles_complex_dice_formulas()
    {
        $xml = <<<'XML'
        <root>
            <roll>1d8+5</roll>
            <roll>2d6+1d4</roll>
            <roll>3d10+3</roll>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $rolls = $this->parseRollElements($element);

        $this->assertEquals('1d8+5', $rolls[0]['formula']);
        $this->assertEquals('2d6+1d4', $rolls[1]['formula']);
        $this->assertEquals('3d10+3', $rolls[2]['formula']);
    }
}
