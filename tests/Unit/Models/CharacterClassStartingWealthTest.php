<?php

namespace Tests\Unit\Models;

use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for CharacterClass starting wealth functionality.
 *
 * D&D 5e allows players to choose starting gold instead of fixed equipment.
 * Each class has a dice formula (e.g., "5d4x10" = roll 5d4, multiply by 10 gp).
 */
#[Group('unit-db')]
class CharacterClassStartingWealthTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_computes_starting_wealth_with_multiplier()
    {
        $class = CharacterClass::factory()->create([
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $wealth = $class->starting_wealth;

        $this->assertNotNull($wealth);
        $this->assertEquals('5d4', $wealth['dice']);
        $this->assertEquals(10, $wealth['multiplier']);
        $this->assertEquals(125, $wealth['average']); // 5 * 2.5 * 10 = 125
        $this->assertEquals('5d4 × 10 gp', $wealth['formula']);
        $this->assertEquals('Roll 5d4 and multiply by 10 for starting gold', $wealth['description']);
    }

    #[Test]
    public function it_computes_starting_wealth_without_multiplier()
    {
        // Monk has "5d4" with no multiplier (effectively ×1)
        $class = CharacterClass::factory()->create([
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 1,
        ]);

        $wealth = $class->starting_wealth;

        $this->assertNotNull($wealth);
        $this->assertEquals('5d4', $wealth['dice']);
        $this->assertEquals(1, $wealth['multiplier']);
        $this->assertEquals(12, $wealth['average']); // 5 * 2.5 * 1 = 12.5, truncated to 12
        $this->assertEquals('5d4 gp', $wealth['formula']);
        $this->assertEquals('Roll 5d4 for starting gold', $wealth['description']);
    }

    #[Test]
    public function it_returns_null_when_no_starting_wealth()
    {
        $class = CharacterClass::factory()->create([
            'starting_wealth_dice' => null,
            'starting_wealth_multiplier' => null,
        ]);

        $this->assertNull($class->starting_wealth);
    }

    #[Test]
    public function it_handles_various_dice_formulas()
    {
        $testCases = [
            // Fighter: 5d4 × 10 = 50-200 gp (avg 125)
            ['dice' => '5d4', 'multiplier' => 10, 'average' => 125],
            // Barbarian: 2d4 × 10 = 20-80 gp (avg 50)
            ['dice' => '2d4', 'multiplier' => 10, 'average' => 50],
            // Rogue: 4d4 × 10 = 40-160 gp (avg 100)
            ['dice' => '4d4', 'multiplier' => 10, 'average' => 100],
            // Sorcerer: 3d4 × 10 = 30-120 gp (avg 75)
            ['dice' => '3d4', 'multiplier' => 10, 'average' => 75],
            // Monk: 5d4 × 1 = 5-20 gp (avg 12.5, truncated to 12)
            ['dice' => '5d4', 'multiplier' => 1, 'average' => 12],
        ];

        foreach ($testCases as $case) {
            $class = CharacterClass::factory()->create([
                'starting_wealth_dice' => $case['dice'],
                'starting_wealth_multiplier' => $case['multiplier'],
            ]);

            $wealth = $class->starting_wealth;

            $this->assertEquals(
                $case['average'],
                $wealth['average'],
                "Expected average {$case['average']} for {$case['dice']}×{$case['multiplier']}"
            );
        }
    }

    #[Test]
    public function it_handles_invalid_dice_formula_gracefully()
    {
        $class = CharacterClass::factory()->create([
            'starting_wealth_dice' => 'invalid',
            'starting_wealth_multiplier' => 10,
        ]);

        $this->assertNull($class->starting_wealth);
    }
}
