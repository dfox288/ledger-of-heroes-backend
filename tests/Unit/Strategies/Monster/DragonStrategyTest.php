<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\DragonStrategy;
use Tests\TestCase;

class DragonStrategyTest extends TestCase
{
    protected DragonStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DragonStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_dragon_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'humanoid']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_breath_weapon_recharge(): void
    {
        $actions = [
            [
                'name' => 'Fire Breath (Recharge 5-6)',
                'description' => 'The dragon exhales fire...',
                'recharge' => null,
            ],
        ];

        $enhanced = $this->strategy->enhanceActions($actions, []);

        $this->assertEquals('5-6', $enhanced[0]['recharge']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_legendary_resistance_recharge(): void
    {
        $traits = [
            [
                'name' => 'Legendary Resistance (3/Day)',
                'description' => 'If the dragon fails a saving throw...',
                'recharge' => null,
            ],
        ];

        $enhanced = $this->strategy->enhanceTraits($traits, []);

        $this->assertEquals('3/DAY', $enhanced[0]['recharge']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_metadata(): void
    {
        $monsterData = [
            'traits' => [
                ['name' => 'Legendary Resistance (3/Day)', 'description' => '...'],
            ],
            'actions' => [
                ['name' => 'Fire Breath (Recharge 5-6)', 'description' => '...'],
                ['name' => 'Bite', 'description' => '...'],
            ],
            'legendary' => [
                ['name' => 'Detect', 'description' => '...', 'category' => null],
                ['name' => 'Lair Actions', 'description' => '...', 'category' => 'lair'],
            ],
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertEquals(1, $metadata['breath_weapons_detected']);
        $this->assertTrue($metadata['legendary_resistance']);
        $this->assertEquals(1, $metadata['lair_actions']);
    }
}
