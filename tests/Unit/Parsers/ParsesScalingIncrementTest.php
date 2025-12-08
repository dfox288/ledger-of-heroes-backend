<?php

namespace Tests\Unit\Parsers;

use PHPUnit\Framework\Attributes\Test;

// Create a test class that uses the trait
class ParsesScalingIncrementTestClass
{
    use \App\Services\Parsers\Concerns\ParsesScalingIncrement;

    public function parse(?string $text): ?string
    {
        return $this->parseScalingIncrement($text);
    }
}

class ParsesScalingIncrementTest extends \Tests\TestCase
{
    private ParsesScalingIncrementTestClass $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ParsesScalingIncrementTestClass;
    }

    #[Test]
    public function it_parses_1d6_dice_notation(): void
    {
        $text = 'When you cast this spell using a spell slot of 4th level or higher, the damage increases by 1d6 for each slot level above 3rd.';

        $result = $this->parser->parse($text);

        $this->assertEquals('1d6', $result);
    }

    #[Test]
    public function it_parses_3d6_dice_notation(): void
    {
        $text = 'When you cast this spell using a spell slot of 7th level or higher, the damage increases by 3d6 for each slot level above 6th.';

        $result = $this->parser->parse($text);

        $this->assertEquals('3d6', $result);
    }

    #[Test]
    public function it_parses_flat_value(): void
    {
        $text = 'When you cast this spell using a spell slot of 2nd level or higher, both the temporary hit points and the cold damage increase by 5 for each slot level above 1st.';

        $result = $this->parser->parse($text);

        $this->assertEquals('5', $result);
    }

    #[Test]
    public function it_returns_null_for_target_scaling(): void
    {
        $text = 'When you cast this spell using a spell slot of 3rd level or higher, you can target one additional creature for each slot level above 2nd.';

        $result = $this->parser->parse($text);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_duration_scaling(): void
    {
        $text = 'If you cast this spell using a spell slot of 4th level or higher, the duration is concentration, up to 10 minutes.';

        $result = $this->parser->parse($text);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_null_input(): void
    {
        $result = $this->parser->parse(null);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_empty_string(): void
    {
        $result = $this->parser->parse('');

        $this->assertNull($result);
    }

    #[Test]
    public function it_parses_damage_type_prefix(): void
    {
        // "the cold damage increases by 1d6" should still match
        $text = 'When you cast this spell using a spell slot of 2nd level or higher, the cold damage increases by 1d6 for each slot level above 1st.';

        $result = $this->parser->parse($text);

        $this->assertEquals('1d6', $result);
    }
}
