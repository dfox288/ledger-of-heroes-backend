<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
        $this->assertEquals('longsword', $this->matcher->testNormalize('Longsword'));
        $this->assertEquals('longsword', $this->matcher->testNormalize('LONGSWORD'));
        $this->assertEquals('longsword', $this->matcher->testNormalize('longsword'));
    }

    #[Test]
    public function it_removes_straight_apostrophes(): void
    {
        $this->assertEquals('smithstools', $this->matcher->testNormalize("Smith's Tools"));
    }

    #[Test]
    public function it_removes_curly_apostrophes(): void
    {
        // Right single quotation mark (curly)
        $this->assertEquals('smithstools', $this->matcher->testNormalize("Smith's Tools"));

        // Left single quotation mark
        $this->assertEquals('smithstools', $this->matcher->testNormalize("Smith's Tools"));
    }

    #[Test]
    public function it_removes_spaces(): void
    {
        $this->assertEquals('smithstools', $this->matcher->testNormalize('Smiths Tools'));
        $this->assertEquals('lightarmor', $this->matcher->testNormalize('Light Armor'));
    }

    #[Test]
    public function it_normalizes_complex_names(): void
    {
        $this->assertEquals('alchemistssupplies', $this->matcher->testNormalize("Alchemist's Supplies"));
        $this->assertEquals('brewerssupplies', $this->matcher->testNormalize("Brewer's Supplies"));
        $this->assertEquals('thievestools', $this->matcher->testNormalize("Thieves' Tools"));
    }
}
