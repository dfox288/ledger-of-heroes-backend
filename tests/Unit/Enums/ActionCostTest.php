<?php

namespace Tests\Unit\Enums;

use App\Enums\ActionCost;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActionCostTest extends TestCase
{
    #[Test]
    #[DataProvider('castingTimeProvider')]
    public function it_parses_casting_time_to_action_cost(?string $castingTime, ?ActionCost $expected): void
    {
        $result = ActionCost::fromCastingTime($castingTime);

        $this->assertSame($expected, $result);
    }

    public static function castingTimeProvider(): array
    {
        return [
            // Standard action economy
            ['1 action', ActionCost::ACTION],
            ['1 Action', ActionCost::ACTION],
            ['1 bonus action', ActionCost::BONUS_ACTION],
            ['1 Bonus Action', ActionCost::BONUS_ACTION],
            ['1 reaction', ActionCost::REACTION],
            ['1 Reaction', ActionCost::REACTION],

            // Reaction with trigger text
            ['1 reaction, which you take when...', ActionCost::REACTION],

            // Longer casting times (don't map to action economy)
            ['1 minute', null],
            ['10 minutes', null],
            ['1 hour', null],
            ['8 hours', null],
            ['24 hours', null],
            ['Instantaneous', null],

            // Edge cases
            [null, null],
            ['', null],
        ];
    }

    #[Test]
    public function it_returns_human_readable_labels(): void
    {
        $this->assertSame('Action', ActionCost::ACTION->label());
        $this->assertSame('Bonus Action', ActionCost::BONUS_ACTION->label());
        $this->assertSame('Reaction', ActionCost::REACTION->label());
        $this->assertSame('Free', ActionCost::FREE->label());
        $this->assertSame('Passive', ActionCost::PASSIVE->label());
    }
}
