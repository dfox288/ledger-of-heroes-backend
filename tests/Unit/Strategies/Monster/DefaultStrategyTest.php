<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\DefaultStrategy;
use Tests\TestCase;

class DefaultStrategyTest extends TestCase
{
    protected DefaultStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DefaultStrategy();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_always_applies(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'beast']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'monstrosity']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'anything']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_traits_unmodified(): void
    {
        $traits = [
            ['name' => 'Keen Smell', 'description' => 'Advantage on Wisdom checks...'],
        ];

        $enhanced = $this->strategy->enhanceTraits($traits, []);

        $this->assertEquals($traits, $enhanced);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_actions_unmodified(): void
    {
        $actions = [
            ['name' => 'Bite', 'description' => 'Melee Weapon Attack...'],
        ];

        $enhanced = $this->strategy->enhanceActions($actions, []);

        $this->assertEquals($actions, $enhanced);
    }
}
