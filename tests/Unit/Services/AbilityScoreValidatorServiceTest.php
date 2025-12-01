<?php

namespace Tests\Unit\Services;

use App\Services\AbilityScoreValidatorService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbilityScoreValidatorServiceTest extends TestCase
{
    private AbilityScoreValidatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbilityScoreValidatorService;
    }

    // Point Buy Cost Tests

    #[Test]
    public function it_calculates_point_buy_cost_for_each_score(): void
    {
        $this->assertEquals(0, $this->service->getPointBuyCost(8));
        $this->assertEquals(1, $this->service->getPointBuyCost(9));
        $this->assertEquals(2, $this->service->getPointBuyCost(10));
        $this->assertEquals(3, $this->service->getPointBuyCost(11));
        $this->assertEquals(4, $this->service->getPointBuyCost(12));
        $this->assertEquals(5, $this->service->getPointBuyCost(13));
        $this->assertEquals(7, $this->service->getPointBuyCost(14));
        $this->assertEquals(9, $this->service->getPointBuyCost(15));
    }

    #[Test]
    public function it_throws_for_score_below_point_buy_minimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Score 7 is invalid for point buy. Must be 8-15.');

        $this->service->getPointBuyCost(7);
    }

    #[Test]
    public function it_throws_for_score_above_point_buy_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Score 16 is invalid for point buy. Must be 8-15.');

        $this->service->getPointBuyCost(16);
    }

    // Total Cost Calculation Tests

    #[Test]
    public function it_calculates_total_point_buy_cost(): void
    {
        // Standard array values cost: 9+7+5+4+2+0 = 27
        $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];

        $this->assertEquals(27, $this->service->calculateTotalCost($scores));
    }

    #[Test]
    public function it_calculates_total_cost_for_all_eights(): void
    {
        $scores = ['STR' => 8, 'DEX' => 8, 'CON' => 8, 'INT' => 8, 'WIS' => 8, 'CHA' => 8];

        $this->assertEquals(0, $this->service->calculateTotalCost($scores));
    }

    #[Test]
    public function it_calculates_total_cost_for_all_fifteens(): void
    {
        // 6 * 9 = 54 points (way over budget)
        $scores = ['STR' => 15, 'DEX' => 15, 'CON' => 15, 'INT' => 15, 'WIS' => 15, 'CHA' => 15];

        $this->assertEquals(54, $this->service->calculateTotalCost($scores));
    }

    // Point Buy Validation Tests

    #[Test]
    public function it_validates_valid_point_buy(): void
    {
        // Exactly 27 points
        $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];

        $this->assertTrue($this->service->validatePointBuy($scores));
    }

    #[Test]
    public function it_validates_another_valid_point_buy_allocation(): void
    {
        // 15+15+15+8+8+8 = 9+9+9+0+0+0 = 27
        $scores = ['STR' => 15, 'DEX' => 15, 'CON' => 15, 'INT' => 8, 'WIS' => 8, 'CHA' => 8];

        $this->assertTrue($this->service->validatePointBuy($scores));
    }

    #[Test]
    public function it_rejects_point_buy_over_budget(): void
    {
        // 15+15+15+10+8+8 = 9+9+9+2+0+0 = 29 (over)
        $scores = ['STR' => 15, 'DEX' => 15, 'CON' => 15, 'INT' => 10, 'WIS' => 8, 'CHA' => 8];

        $this->assertFalse($this->service->validatePointBuy($scores));
    }

    #[Test]
    public function it_rejects_point_buy_under_budget(): void
    {
        // All 8s = 0 points (under)
        $scores = ['STR' => 8, 'DEX' => 8, 'CON' => 8, 'INT' => 8, 'WIS' => 8, 'CHA' => 8];

        $this->assertFalse($this->service->validatePointBuy($scores));
    }

    #[Test]
    public function it_rejects_point_buy_with_score_below_minimum(): void
    {
        $scores = ['STR' => 7, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];

        $this->assertFalse($this->service->validatePointBuy($scores));
    }

    #[Test]
    public function it_rejects_point_buy_with_score_above_maximum(): void
    {
        $scores = ['STR' => 16, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];

        $this->assertFalse($this->service->validatePointBuy($scores));
    }

    #[Test]
    public function it_requires_all_six_scores_for_point_buy(): void
    {
        $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13]; // Missing 3

        $this->assertFalse($this->service->validatePointBuy($scores));
    }

    #[Test]
    public function it_rejects_point_buy_with_extra_scores(): void
    {
        $scores = [
            'STR' => 15, 'DEX' => 14, 'CON' => 13,
            'INT' => 12, 'WIS' => 10, 'CHA' => 8,
            'EXTRA' => 10,
        ];

        $this->assertFalse($this->service->validatePointBuy($scores));
    }

    // Standard Array Validation Tests

    #[Test]
    public function it_validates_valid_standard_array(): void
    {
        $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];

        $this->assertTrue($this->service->validateStandardArray($scores));
    }

    #[Test]
    public function it_validates_standard_array_in_different_arrangement(): void
    {
        // Same values, different assignment
        $scores = ['STR' => 8, 'DEX' => 10, 'CON' => 12, 'INT' => 13, 'WIS' => 14, 'CHA' => 15];

        $this->assertTrue($this->service->validateStandardArray($scores));
    }

    #[Test]
    public function it_rejects_standard_array_with_wrong_value(): void
    {
        // 16 is not in standard array
        $scores = ['STR' => 16, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];

        $this->assertFalse($this->service->validateStandardArray($scores));
    }

    #[Test]
    public function it_rejects_standard_array_with_duplicates(): void
    {
        // Two 15s instead of 15 and 14
        $scores = ['STR' => 15, 'DEX' => 15, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];

        $this->assertFalse($this->service->validateStandardArray($scores));
    }

    #[Test]
    public function it_requires_all_six_scores_for_standard_array(): void
    {
        $scores = ['STR' => 15, 'DEX' => 14]; // Missing 4

        $this->assertFalse($this->service->validateStandardArray($scores));
    }

    #[Test]
    public function it_rejects_standard_array_with_extra_scores(): void
    {
        $scores = [
            'STR' => 15, 'DEX' => 14, 'CON' => 13,
            'INT' => 12, 'WIS' => 10, 'CHA' => 8,
            'EXTRA' => 10,
        ];

        $this->assertFalse($this->service->validateStandardArray($scores));
    }

    // Validation Error Message Tests

    #[Test]
    public function it_returns_error_messages_for_invalid_point_buy(): void
    {
        // Over budget
        $scores = ['STR' => 15, 'DEX' => 15, 'CON' => 15, 'INT' => 10, 'WIS' => 8, 'CHA' => 8];
        $errors = $this->service->getPointBuyErrors($scores);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('29', $errors[0]); // Should mention the total
    }

    #[Test]
    public function it_returns_empty_errors_for_valid_point_buy(): void
    {
        $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
        $errors = $this->service->getPointBuyErrors($scores);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_returns_error_messages_for_invalid_standard_array(): void
    {
        // Wrong values
        $scores = ['STR' => 16, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
        $errors = $this->service->getStandardArrayErrors($scores);

        $this->assertNotEmpty($errors);
    }

    #[Test]
    public function it_returns_empty_errors_for_valid_standard_array(): void
    {
        $scores = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
        $errors = $this->service->getStandardArrayErrors($scores);

        $this->assertEmpty($errors);
    }
}
