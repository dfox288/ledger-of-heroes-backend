<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\ConvertsWordNumbers;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ConvertsWordNumbersTest extends TestCase
{
    use ConvertsWordNumbers;

    #[Test]
    public function it_converts_basic_number_words()
    {
        $this->assertEquals(1, $this->wordToNumber('one'));
        $this->assertEquals(2, $this->wordToNumber('two'));
        $this->assertEquals(3, $this->wordToNumber('three'));
        $this->assertEquals(4, $this->wordToNumber('four'));
        $this->assertEquals(5, $this->wordToNumber('five'));
        $this->assertEquals(6, $this->wordToNumber('six'));
        $this->assertEquals(7, $this->wordToNumber('seven'));
        $this->assertEquals(8, $this->wordToNumber('eight'));
    }

    #[Test]
    public function it_is_case_insensitive()
    {
        $this->assertEquals(3, $this->wordToNumber('THREE'));
        $this->assertEquals(5, $this->wordToNumber('Five'));
        $this->assertEquals(2, $this->wordToNumber('TwO'));
    }

    #[Test]
    public function it_returns_default_for_unknown_words()
    {
        $this->assertEquals(1, $this->wordToNumber('unknown'));
        $this->assertEquals(1, $this->wordToNumber(''));
    }

    #[Test]
    public function it_can_use_custom_default()
    {
        $this->assertEquals(0, $this->wordToNumber('unknown', 0));
        $this->assertEquals(10, $this->wordToNumber('', 10));
    }

    #[Test]
    public function it_handles_numeric_strings()
    {
        // Should return default since it's not a word
        $this->assertEquals(1, $this->wordToNumber('5'));
    }
}
