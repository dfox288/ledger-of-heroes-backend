<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\ItemTableParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemTableParserTest extends TestCase
{
    private ItemTableParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemTableParser();
    }

    #[Test]
    public function it_parses_simple_table()
    {
        $tableText = <<<TEXT
Test Table:
Option | Effect
1 | Effect A
2 | Effect B
TEXT;

        $parsed = $this->parser->parse($tableText);

        $this->assertEquals('Test Table', $parsed['table_name']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertEquals(1, $parsed['rows'][0]['roll_min']);
        $this->assertEquals(1, $parsed['rows'][0]['roll_max']);
        $this->assertEquals('Effect A', $parsed['rows'][0]['result_text']);
    }

    #[Test]
    public function it_parses_roll_ranges()
    {
        $tableText = <<<TEXT
Wild Magic:
d100 | Effect
01-02 | Fireball
03-04 | Teleport
05 | Unicorn
TEXT;

        $parsed = $this->parser->parse($tableText);

        $this->assertEquals('Wild Magic', $parsed['table_name']);
        $this->assertCount(3, $parsed['rows']);

        // First row: range 01-02
        $this->assertEquals(1, $parsed['rows'][0]['roll_min']);
        $this->assertEquals(2, $parsed['rows'][0]['roll_max']);
        $this->assertEquals('Fireball', $parsed['rows'][0]['result_text']);

        // Second row: range 03-04
        $this->assertEquals(3, $parsed['rows'][1]['roll_min']);
        $this->assertEquals(4, $parsed['rows'][1]['roll_max']);

        // Third row: single number 05
        $this->assertEquals(5, $parsed['rows'][2]['roll_min']);
        $this->assertEquals(5, $parsed['rows'][2]['roll_max']);
    }

    #[Test]
    public function it_handles_non_numeric_first_column()
    {
        $tableText = <<<TEXT
Options:
Lever | Effect
A | Effect A
B | Effect B
TEXT;

        $parsed = $this->parser->parse($tableText);

        $this->assertEquals('Options', $parsed['table_name']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertNull($parsed['rows'][0]['roll_min']);
        $this->assertNull($parsed['rows'][0]['roll_max']);
        $this->assertEquals('Effect A', $parsed['rows'][0]['result_text']);
    }

    #[Test]
    public function it_handles_multi_column_tables()
    {
        $tableText = <<<TEXT
Apparatus Levers:
Lever | Up | Down
1 | Extend legs | Retract legs
2 | Open window | Close window
TEXT;

        $parsed = $this->parser->parse($tableText);

        $this->assertEquals('Apparatus Levers', $parsed['table_name']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertEquals('Extend legs | Retract legs', $parsed['rows'][0]['result_text']);
    }
}
