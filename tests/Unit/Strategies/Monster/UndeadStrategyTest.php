<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\UndeadStrategy;
use Tests\TestCase;

class UndeadStrategyTest extends TestCase
{
    protected UndeadStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new UndeadStrategy();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_undead_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'undead']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Undead']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'humanoid']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_metadata_for_turn_resistance(): void
    {
        $monsterData = [
            'traits' => [
                ['name' => 'Turn Resistance', 'description' => 'The zombie has advantage on saving throws against any effect that turns undead.'],
            ],
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertTrue($metadata['has_turn_resistance']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_metadata_for_sunlight_sensitivity(): void
    {
        $monsterData = [
            'traits' => [
                ['name' => 'Sunlight Sensitivity', 'description' => 'While in sunlight...'],
            ],
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertTrue($metadata['has_sunlight_sensitivity']);
    }
}
