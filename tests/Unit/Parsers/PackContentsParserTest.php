<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesPackContents;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class PackContentsParserTest extends TestCase
{
    use ParsesPackContents;

    #[Test]
    public function it_parses_simple_item_with_quantity_one(): void
    {
        $description = "Includes:\n\n\t• a backpack\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('backpack', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_item_with_numeric_quantity(): void
    {
        $description = "Includes:\n\n\t• 10 torches\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('torch', $result[0]['name']);
        $this->assertEquals(10, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_item_with_days_of_rations(): void
    {
        $description = "Includes:\n\n\t• 10 days of rations\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('rations (1 day)', $result[0]['name']);
        $this->assertEquals(10, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_item_with_feet_of_rope(): void
    {
        $description = "Includes:\n\n\t• 50 feet of hempen rope\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('hempen rope (50 feet)', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_item_with_flasks_of_oil(): void
    {
        $description = "Includes:\n\n\t• 2 flasks of oil\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('oil (flask)', $result[0]['name']);
        $this->assertEquals(2, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_item_with_sheets_of_paper(): void
    {
        $description = "Includes:\n\n\t• 5 sheets of paper\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('paper (one sheet)', $result[0]['name']);
        $this->assertEquals(5, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_pitons_singular_or_typo(): void
    {
        // The XML has "10 piton" (typo) - we should handle it
        $description = "Includes:\n\n\t• 10 piton\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('piton', $result[0]['name']);
        $this->assertEquals(10, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_item_with_bottle_of_ink(): void
    {
        $description = "Includes:\n\n\t• a bottle of ink (1-ounce bottle)\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('ink (1-ounce bottle)', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_ball_bearings_bag(): void
    {
        $description = "Includes:\n\n\t• a bag of 1,000 ball bearings\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('ball bearings (bag of 1,000)', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_map_or_scroll_case(): void
    {
        $description = "Includes:\n\n\t• 2 cases for maps and scrolls\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('map or scroll case', $result[0]['name']);
        $this->assertEquals(2, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_vial_of_perfume(): void
    {
        $description = "Includes:\n\n\t• a vial of perfume\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('perfume (vial)', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_sheets_of_parchment(): void
    {
        $description = "Includes:\n\n\t• 10 sheets of parchment\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('parchment (one sheet)', $result[0]['name']);
        $this->assertEquals(10, $result[0]['quantity']);
    }

    #[Test]
    public function it_parses_full_explorers_pack(): void
    {
        $description = "Includes:\n\n\t• a backpack\n\t• a bedroll\n\t• a mess kit\n\t• a tinderbox\n\t• 10 torches\n\t• 10 days of rations\n\t• a waterskin\n\t• 50 feet of hempen rope\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(8, $result);

        $itemMap = collect($result)->keyBy('name');

        $this->assertEquals(1, $itemMap['backpack']['quantity']);
        $this->assertEquals(1, $itemMap['bedroll']['quantity']);
        $this->assertEquals(1, $itemMap['mess kit']['quantity']);
        $this->assertEquals(1, $itemMap['tinderbox']['quantity']);
        $this->assertEquals(10, $itemMap['torch']['quantity']);
        $this->assertEquals(10, $itemMap['rations (1 day)']['quantity']);
        $this->assertEquals(1, $itemMap['waterskin']['quantity']);
        $this->assertEquals(1, $itemMap['hempen rope (50 feet)']['quantity']);
    }

    #[Test]
    public function it_parses_full_burglars_pack(): void
    {
        $description = "Includes:\n\n\t• a backpack\n\t• a bag of 1,000 ball bearings\n\t• 10 feet of string\n\t• a bell\n\t• 5 candles\n\t• a crowbar\n\t• a hammer\n\t• 10 pitons\n\t• a hooded lantern\n\t• 2 flasks of oil\n\t• 5 days Rations\n\t• a tinderbox\n\t• a waterskin\n\t• 50 feet of hempen rope\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(14, $result);

        $itemMap = collect($result)->keyBy('name');

        $this->assertEquals(1, $itemMap['backpack']['quantity']);
        $this->assertEquals(1, $itemMap['ball bearings (bag of 1,000)']['quantity']);
        $this->assertEquals(1, $itemMap['string (10 feet)']['quantity']);
        $this->assertEquals(1, $itemMap['bell']['quantity']);
        $this->assertEquals(5, $itemMap['candle']['quantity']);
        $this->assertEquals(1, $itemMap['crowbar']['quantity']);
        $this->assertEquals(1, $itemMap['hammer']['quantity']);
        $this->assertEquals(10, $itemMap['piton']['quantity']);
        $this->assertEquals(1, $itemMap['hooded lantern']['quantity']);
        $this->assertEquals(2, $itemMap['oil (flask)']['quantity']);
        $this->assertEquals(5, $itemMap['rations (1 day)']['quantity']);
        $this->assertEquals(1, $itemMap['tinderbox']['quantity']);
        $this->assertEquals(1, $itemMap['waterskin']['quantity']);
        $this->assertEquals(1, $itemMap['hempen rope (50 feet)']['quantity']);
    }

    #[Test]
    public function it_returns_empty_array_for_non_pack_items(): void
    {
        $description = 'A backpack can hold one cubic foot or 30 pounds of gear.';

        $result = $this->parsePackContents($description);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_handles_string_with_feet_measurement(): void
    {
        $description = "Includes:\n\n\t• 10 feet of string\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('string (10 feet)', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    #[Test]
    public function it_handles_costume_clothes(): void
    {
        $description = "Includes:\n\n\t• 2 costumes\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('costume clothes', $result[0]['name']);
        $this->assertEquals(2, $result[0]['quantity']);
    }

    #[Test]
    public function it_handles_book_of_lore(): void
    {
        $description = "Includes:\n\n\t• a book of lore\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('book', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    #[Test]
    public function it_handles_set_of_fine_clothes(): void
    {
        $description = "Includes:\n\n\t• a set of fine clothes\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        $this->assertCount(1, $result);
        $this->assertEquals('fine clothes', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    #[Test]
    public function it_handles_blocks_of_incense(): void
    {
        // Incense doesn't exist in DB - should be skipped or marked
        $description = "Includes:\n\n\t• 2 blocks of incense\n\nSource:\tPlayer's Handbook (2014) p. 151";

        $result = $this->parsePackContents($description);

        // Should return the parsed name so we can try to match later
        $this->assertCount(1, $result);
        $this->assertEquals('incense', $result[0]['name']);
        $this->assertEquals(2, $result[0]['quantity']);
    }
}
