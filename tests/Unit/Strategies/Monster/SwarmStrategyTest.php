<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\SwarmStrategy;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class SwarmStrategyTest extends TestCase
{
    protected SwarmStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SwarmStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_swarm_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'swarm of medium beasts']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Swarm of Tiny creatures']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'beast']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_individual_creature_size_from_type(): void
    {
        $monsterData = [
            'type' => 'swarm of Medium beasts',
            'size' => 'M',
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertEquals('Medium', $metadata['individual_creature_size']);
        $this->assertEquals('M', $metadata['swarm_size']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_swarm_without_size_in_type(): void
    {
        $monsterData = [
            'type' => 'swarm',
            'size' => 'L',
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertNull($metadata['individual_creature_size']);
        $this->assertEquals('L', $metadata['swarm_size']);
    }
}
