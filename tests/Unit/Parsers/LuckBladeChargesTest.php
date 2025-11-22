<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesCharges;
use App\Services\Parsers\Concerns\ParsesItemSpells;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LuckBladeChargesTest extends TestCase
{
    private object $parser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous class that uses both traits
        $this->parser = new class
        {
            use ParsesCharges;
            use ParsesItemSpells;

            public function test_parse_charges(string $text): array
            {
                return $this->parseCharges($text);
            }

            public function test_parse_item_spells(string $text): array
            {
                return $this->parseItemSpells($text);
            }
        };
    }

    #[Test]
    public function it_parses_luck_blade_charge_count_from_has_xdy_formula()
    {
        $text = 'The sword has 1d4 - 1 charges. While holding it, you can use an action to expend 1 charge and cast the wish spell from it.';

        $result = $this->parser->test_parse_charges($text);

        // Should detect variable charge count from "has 1d4-1 charges"
        $this->assertEquals('1d4-1', $result['charges_max']);
    }

    #[Test]
    public function it_parses_luck_blade_charge_cost_from_expend_x_charge()
    {
        $text = 'While holding it, you can use an action to expend 1 charge and cast the wish spell from it.';

        $result = $this->parser->test_parse_item_spells($text);

        $this->assertCount(1, $result);
        $this->assertEquals('wish', $result[0]['spell_name']); // Parser returns lowercase
        $this->assertEquals(1, $result[0]['charges_cost_min']);
        $this->assertEquals(1, $result[0]['charges_cost_max']);
    }

    #[Test]
    public function it_parses_luck_blade_recharge_timing_from_next_dawn()
    {
        $text = "This property can't be used again until the next dawn.";

        $result = $this->parser->test_parse_charges($text);

        $this->assertEquals('dawn', $result['recharge_timing']);
    }
}
