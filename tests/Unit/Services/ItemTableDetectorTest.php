<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\ItemTableDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemTableDetectorTest extends TestCase
{
    private ItemTableDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new ItemTableDetector();
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
        $text = <<<TEXT
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
        $text = "Just some regular text without any tables.";

        $tables = $this->detector->detectTables($text);

        $this->assertCount(0, $tables);
    }

    #[Test]
    public function it_detects_tables_with_roll_ranges()
    {
        $text = <<<TEXT
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
}
