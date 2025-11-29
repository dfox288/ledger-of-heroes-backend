<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\ItemTableDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class ItemTableDetectorTest extends TestCase
{
    private ItemTableDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new ItemTableDetector;
    }

    #[Test]
    public function it_detects_simple_table()
    {
        $text = "Description text.\n\nTest Table:\nCol1 | Col2\n1 | Data A\n2 | Data B\n\nMore text.";

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals('Test Table', $tables[0]['name']);
        $this->assertStringContainsString('1 | Data A', $tables[0]['text']);
        $this->assertStringContainsString('2 | Data B', $tables[0]['text']);
    }

    #[Test]
    public function it_detects_multiple_tables()
    {
        $text = <<<'TEXT'
First Table:
Header | Value
1 | A
2 | B

Second Table:
Name | Count
1 | X
2 | Y
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(2, $tables);
        $this->assertEquals('First Table', $tables[0]['name']);
        $this->assertEquals('Second Table', $tables[1]['name']);
    }

    #[Test]
    public function it_handles_text_without_tables()
    {
        $text = 'Just some regular text without any tables.';

        $tables = $this->detector->detectTables($text);

        $this->assertCount(0, $tables);
    }

    #[Test]
    public function it_detects_tables_with_roll_ranges()
    {
        $text = <<<'TEXT'
Wild Magic:
d100 | Effect
01-02 | Fireball
03-04 | Teleport
05 | Unicorn
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals('Wild Magic', $tables[0]['name']);
        $this->assertStringContainsString('01-02 | Fireball', $tables[0]['text']);
    }

    #[Test]
    public function it_extracts_dice_type_from_header()
    {
        $text = <<<'TEXT'
Wild Magic:
d100 | Effect
1-2 | Fireball
3-4 | Teleport
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals('d100', $tables[0]['dice_type']);
    }

    #[Test]
    public function it_handles_tables_without_dice_type()
    {
        $text = <<<'TEXT'
Lever Controls:
Lever | Effect
1 | Effect A
2 | Effect B
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertNull($tables[0]['dice_type']);
    }

    #[Test]
    public function it_extracts_unusual_dice_types()
    {
        $text = <<<'TEXT'
Deck of Many Things:
1d22 | Playing Card | Card
1 | Ace of diamonds | Vizier
2 | King of diamonds | Sun
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals('1d22', $tables[0]['dice_type']);
    }

    #[Test]
    public function it_extracts_multi_dice_types()
    {
        $text = <<<'TEXT'
Damage Table:
2d6 | Damage Type
1-2 | Fire
3-4 | Cold
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals('2d6', $tables[0]['dice_type']);
    }

    #[Test]
    public function it_detects_tables_with_text_first_column()
    {
        $text = <<<'TEXT'
Choose one type of dragon from the Draconic Ancestry table.

Draconic Ancestry:
Dragon | Damage Type | Breath Weapon
Black | Acid | 5 by 30 ft. line (Dex. save)
Blue | Lightning | 5 by 30 ft. line (Dex. save)
Brass | Fire | 5 by 30 ft. line (Dex. save)
Bronze | Lightning | 5 by 30 ft. line (Dex. save)
Copper | Acid | 5 by 30 ft. line (Dex. save)
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals('Draconic Ancestry', $tables[0]['name']);
        $this->assertStringContainsString('Black | Acid', $tables[0]['text']);
        $this->assertStringContainsString('Blue | Lightning', $tables[0]['text']);
        $this->assertNull($tables[0]['dice_type']); // No dice type for this table
    }

    #[Test]
    public function it_detects_level_ordinal_tables(): void
    {
        $text = <<<'TEXT'
Your martial arts training allows...

The Monk Table:
Level | Martial Arts
1st | 1d4
5th | 1d6
11th | 1d8
17th | 1d10

Source: PHB
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals('The Monk Table', $tables[0]['name']);
        $this->assertStringContainsString('1st | 1d4', $tables[0]['text']);
        $this->assertStringContainsString('17th | 1d10', $tables[0]['text']);
        $this->assertTrue($tables[0]['is_level_progression'] ?? false);
    }

    #[Test]
    public function it_detects_speed_bonus_ordinal_tables(): void
    {
        $text = <<<'TEXT'
Unarmored Movement:
Level | Speed Bonus
2nd | +10
6th | +15
10th | +20
14th | +25
18th | +30
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertEquals('Unarmored Movement', $tables[0]['name']);
        $this->assertStringContainsString('2nd | +10', $tables[0]['text']);
        $this->assertTrue($tables[0]['is_level_progression'] ?? false);
    }

    #[Test]
    public function it_handles_2nd_and_3rd_ordinals(): void
    {
        $text = <<<'TEXT'
Test Table:
Level | Value
1st | A
2nd | B
3rd | C
4th | D
TEXT;

        $tables = $this->detector->detectTables($text);

        $this->assertCount(1, $tables);
        $this->assertStringContainsString('2nd | B', $tables[0]['text']);
        $this->assertStringContainsString('3rd | C', $tables[0]['text']);
    }

    #[Test]
    public function it_does_not_duplicate_ordinal_tables_with_pattern1(): void
    {
        // Ensure level-ordinal tables don't get captured by other patterns
        // This table has ordinals, not plain numbers
        $text = <<<'TEXT'
Sneak Attack:
Level | Extra Damage
1st | 1d6
3rd | 2d6
5th | 3d6
TEXT;

        $tables = $this->detector->detectTables($text);

        // Should only find one table, not duplicates
        $this->assertCount(1, $tables);
        $this->assertEquals('Sneak Attack', $tables[0]['name']);
    }
}
