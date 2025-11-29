<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\ItemTableParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class ItemTableParserTest extends TestCase
{
    private ItemTableParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemTableParser;
    }

    #[Test]
    public function it_parses_simple_table()
    {
        $tableText = <<<'TEXT'
Test Table:
Option | Effect
1 | Effect A
2 | Effect B
TEXT;

        $parsed = $this->parser->parse($tableText, 'd8');

        $this->assertEquals('Test Table', $parsed['table_name']);
        $this->assertEquals('d8', $parsed['dice_type']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertEquals(1, $parsed['rows'][0]['roll_min']);
        $this->assertEquals(1, $parsed['rows'][0]['roll_max']);
        $this->assertEquals('Effect A', $parsed['rows'][0]['result_text']);
    }

    #[Test]
    public function it_parses_roll_ranges()
    {
        $tableText = <<<'TEXT'
Wild Magic:
d100 | Effect
01-02 | Fireball
03-04 | Teleport
05 | Unicorn
TEXT;

        $parsed = $this->parser->parse($tableText, 'd100');

        $this->assertEquals('Wild Magic', $parsed['table_name']);
        $this->assertEquals('d100', $parsed['dice_type']);
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
        $tableText = <<<'TEXT'
Options:
Lever | Effect
A | Effect A
B | Effect B
TEXT;

        $parsed = $this->parser->parse($tableText, null);

        $this->assertEquals('Options', $parsed['table_name']);
        $this->assertNull($parsed['dice_type']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertNull($parsed['rows'][0]['roll_min']);
        $this->assertNull($parsed['rows'][0]['roll_max']);
        // When first column is not numeric, it's preserved as part of the result
        $this->assertEquals('A | Effect A', $parsed['rows'][0]['result_text']);
        $this->assertEquals('B | Effect B', $parsed['rows'][1]['result_text']);
    }

    #[Test]
    public function it_handles_multi_column_tables()
    {
        $tableText = <<<'TEXT'
Apparatus Levers:
Lever | Up | Down
1 | Extend legs | Retract legs
2 | Open window | Close window
TEXT;

        $parsed = $this->parser->parse($tableText, null);

        $this->assertEquals('Apparatus Levers', $parsed['table_name']);
        $this->assertNull($parsed['dice_type']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertEquals('Extend legs | Retract legs', $parsed['rows'][0]['result_text']);
    }

    #[Test]
    public function it_parses_level_ordinal_rows(): void
    {
        $tableText = <<<'TEXT'
Martial Arts:
Level | Martial Arts
1st | 1d4
5th | 1d6
11th | 1d8
17th | 1d10
TEXT;

        $result = $this->parser->parseLevelProgression($tableText);

        $this->assertEquals('Martial Arts', $result['table_name']);
        $this->assertEquals('Martial Arts', $result['column_name']);
        $this->assertCount(4, $result['rows']);
        $this->assertEquals(['level' => 1, 'value' => '1d4'], $result['rows'][0]);
        $this->assertEquals(['level' => 5, 'value' => '1d6'], $result['rows'][1]);
        $this->assertEquals(['level' => 11, 'value' => '1d8'], $result['rows'][2]);
        $this->assertEquals(['level' => 17, 'value' => '1d10'], $result['rows'][3]);
    }

    #[Test]
    public function it_parses_speed_bonus_progression(): void
    {
        $tableText = <<<'TEXT'
Unarmored Movement:
Level | Speed Bonus
2nd | +10
6th | +15
10th | +20
TEXT;

        $result = $this->parser->parseLevelProgression($tableText);

        $this->assertEquals('Unarmored Movement', $result['table_name']);
        $this->assertEquals('Speed Bonus', $result['column_name']);
        $this->assertCount(3, $result['rows']);
        $this->assertEquals(['level' => 2, 'value' => '+10'], $result['rows'][0]);
        $this->assertEquals(['level' => 6, 'value' => '+15'], $result['rows'][1]);
        $this->assertEquals(['level' => 10, 'value' => '+20'], $result['rows'][2]);
    }

    #[Test]
    public function it_parses_all_ordinal_suffixes(): void
    {
        $tableText = <<<'TEXT'
Test:
Level | Value
1st | A
2nd | B
3rd | C
4th | D
21st | E
22nd | F
23rd | G
TEXT;

        $result = $this->parser->parseLevelProgression($tableText);

        $this->assertCount(7, $result['rows']);
        $this->assertEquals(1, $result['rows'][0]['level']);
        $this->assertEquals(2, $result['rows'][1]['level']);
        $this->assertEquals(3, $result['rows'][2]['level']);
        $this->assertEquals(4, $result['rows'][3]['level']);
        $this->assertEquals(21, $result['rows'][4]['level']);
        $this->assertEquals(22, $result['rows'][5]['level']);
        $this->assertEquals(23, $result['rows'][6]['level']);
    }

    #[Test]
    public function it_handles_empty_level_progression_table(): void
    {
        $tableText = "Table Name:\nLevel | Value";

        $result = $this->parser->parseLevelProgression($tableText);

        $this->assertEquals('Table Name', $result['table_name']);
        $this->assertEmpty($result['rows']);
    }
}
