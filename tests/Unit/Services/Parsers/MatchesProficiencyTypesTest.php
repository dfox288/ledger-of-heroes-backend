<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class MatchesProficiencyTypesTest extends TestCase
{
    private $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test class that uses the trait
        $this->matcher = new class
        {
            use MatchesProficiencyTypes;

            // Expose protected method for testing
            public function test_normalize(string $name): string
            {
                return $this->normalizeName($name);
            }
        };
    }

    #[Test]
    public function it_normalizes_to_lowercase(): void
    {
        $this->assertEquals('longsword', $this->matcher->test_normalize('Longsword'));
        $this->assertEquals('longsword', $this->matcher->test_normalize('LONGSWORD'));
        $this->assertEquals('longsword', $this->matcher->test_normalize('longsword'));
    }

    #[Test]
    public function it_removes_straight_apostrophes(): void
    {
        $this->assertEquals('smithstools', $this->matcher->test_normalize("Smith's Tools"));
    }

    #[Test]
    public function it_removes_curly_apostrophes(): void
    {
        // Right single quotation mark (curly)
        $this->assertEquals('smithstools', $this->matcher->test_normalize("Smith's Tools"));

        // Left single quotation mark
        $this->assertEquals('smithstools', $this->matcher->test_normalize("Smith's Tools"));
    }

    #[Test]
    public function it_removes_spaces(): void
    {
        $this->assertEquals('smithstools', $this->matcher->test_normalize('Smiths Tools'));
        $this->assertEquals('lightarmor', $this->matcher->test_normalize('Light Armor'));
    }

    #[Test]
    public function it_normalizes_complex_names(): void
    {
        $this->assertEquals('alchemistssupplies', $this->matcher->test_normalize("Alchemist's Supplies"));
        $this->assertEquals('brewerssupplies', $this->matcher->test_normalize("Brewer's Supplies"));
        $this->assertEquals('thievestools', $this->matcher->test_normalize("Thieves' Tools"));
    }
}
