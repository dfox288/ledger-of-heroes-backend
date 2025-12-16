<?php

namespace Tests\Unit\Services;

use App\Services\ExperiencePointService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class ExperiencePointServiceTest extends TestCase
{
    private ExperiencePointService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExperiencePointService;
    }

    #[Test]
    public function it_returns_level_1_for_zero_xp(): void
    {
        $this->assertEquals(1, $this->service->getLevelForXp(0));
    }

    #[Test]
    public function it_returns_level_1_for_299_xp(): void
    {
        $this->assertEquals(1, $this->service->getLevelForXp(299));
    }

    #[Test]
    public function it_returns_level_2_for_300_xp(): void
    {
        $this->assertEquals(2, $this->service->getLevelForXp(300));
    }

    #[Test]
    public function it_returns_level_3_for_900_xp(): void
    {
        $this->assertEquals(3, $this->service->getLevelForXp(900));
    }

    #[Test]
    public function it_returns_level_5_for_6500_xp(): void
    {
        $this->assertEquals(5, $this->service->getLevelForXp(6500));
    }

    #[Test]
    public function it_returns_level_20_for_355000_xp(): void
    {
        $this->assertEquals(20, $this->service->getLevelForXp(355000));
    }

    #[Test]
    public function it_returns_level_20_for_xp_above_max(): void
    {
        $this->assertEquals(20, $this->service->getLevelForXp(1000000));
    }

    #[Test]
    #[DataProvider('xpThresholdProvider')]
    public function it_returns_correct_level_for_xp_thresholds(int $xp, int $expectedLevel): void
    {
        $this->assertEquals($expectedLevel, $this->service->getLevelForXp($xp));
    }

    public static function xpThresholdProvider(): array
    {
        return [
            'level 1 boundary' => [0, 1],
            'just below level 2' => [299, 1],
            'level 2 exact' => [300, 2],
            'level 2 mid' => [500, 2],
            'just below level 3' => [899, 2],
            'level 3 exact' => [900, 3],
            'level 4 exact' => [2700, 4],
            'level 5 exact' => [6500, 5],
            'level 10 exact' => [64000, 10],
            'level 15 exact' => [165000, 15],
            'level 20 exact' => [355000, 20],
            'above max' => [500000, 20],
        ];
    }

    #[Test]
    public function it_returns_xp_threshold_for_next_level(): void
    {
        $this->assertEquals(300, $this->service->getXpForLevel(2));
        $this->assertEquals(900, $this->service->getXpForLevel(3));
        $this->assertEquals(6500, $this->service->getXpForLevel(5));
        $this->assertEquals(355000, $this->service->getXpForLevel(20));
    }

    #[Test]
    public function it_returns_zero_xp_for_level_1(): void
    {
        $this->assertEquals(0, $this->service->getXpForLevel(1));
    }

    #[Test]
    public function it_returns_null_for_level_above_20(): void
    {
        $this->assertNull($this->service->getXpForLevel(21));
    }

    #[Test]
    public function it_calculates_xp_to_next_level(): void
    {
        // At 0 XP (level 1), need 300 to reach level 2
        $this->assertEquals(300, $this->service->getXpToNextLevel(0));

        // At 150 XP (level 1), need 150 more to reach level 2
        $this->assertEquals(150, $this->service->getXpToNextLevel(150));

        // At 300 XP (level 2), need 600 more to reach level 3 (900 total)
        $this->assertEquals(600, $this->service->getXpToNextLevel(300));

        // At 6500 XP (level 5), need 7500 more to reach level 6 (14000 total)
        $this->assertEquals(7500, $this->service->getXpToNextLevel(6500));
    }

    #[Test]
    public function it_returns_zero_xp_to_next_level_at_max(): void
    {
        // At level 20, no more XP needed
        $this->assertEquals(0, $this->service->getXpToNextLevel(355000));
        $this->assertEquals(0, $this->service->getXpToNextLevel(500000));
    }

    #[Test]
    public function it_calculates_xp_progress_percentage(): void
    {
        // At 0 XP, 0% progress to level 2
        $this->assertEquals(0.0, $this->service->getXpProgressPercent(0));

        // At 150 XP, 50% progress to level 2 (0-300 range)
        $this->assertEquals(50.0, $this->service->getXpProgressPercent(150));

        // At 300 XP (just hit level 2), 0% progress to level 3
        $this->assertEquals(0.0, $this->service->getXpProgressPercent(300));

        // At 600 XP, 50% progress to level 3 (300-900 range = 600, at 300 in)
        $this->assertEquals(50.0, $this->service->getXpProgressPercent(600));
    }

    #[Test]
    public function it_returns_100_percent_progress_at_max_level(): void
    {
        $this->assertEquals(100.0, $this->service->getXpProgressPercent(355000));
        $this->assertEquals(100.0, $this->service->getXpProgressPercent(500000));
    }
}
