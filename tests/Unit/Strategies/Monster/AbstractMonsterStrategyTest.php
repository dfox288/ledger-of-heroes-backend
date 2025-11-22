<?php

namespace Tests\Unit\Strategies\Monster;

use Tests\TestCase;

class AbstractMonsterStrategyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_action_cost_from_legendary_name(): void
    {
        $strategy = new class extends \App\Services\Importers\Strategies\Monster\AbstractMonsterStrategy {
            public function appliesTo(array $monsterData): bool { return true; }

            public function testExtractCost(string $name): int
            {
                return $this->extractActionCost($name);
            }
        };

        $this->assertEquals(1, $strategy->testExtractCost('Detect'));
        $this->assertEquals(2, $strategy->testExtractCost('Wing Attack (Costs 2 Actions)'));
        $this->assertEquals(3, $strategy->testExtractCost('Psychic Drain (Costs 3 Actions)'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_lair_actions_from_category(): void
    {
        $strategy = new class extends \App\Services\Importers\Strategies\Monster\AbstractMonsterStrategy {
            public function appliesTo(array $monsterData): bool { return true; }
        };

        $legendary = [
            ['name' => 'Detect', 'description' => '...', 'category' => null],
            ['name' => 'Lair Actions', 'description' => '...', 'category' => 'lair'],
        ];

        $enhanced = $strategy->enhanceLegendaryActions($legendary, []);

        $this->assertEquals(1, $enhanced[0]['action_cost']);
        $this->assertFalse($enhanced[0]['is_lair_action']);

        $this->assertEquals(1, $enhanced[1]['action_cost']);
        $this->assertTrue($enhanced[1]['is_lair_action']);
    }
}
