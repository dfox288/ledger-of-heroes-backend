<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\InsufficientHitDiceException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class InsufficientHitDiceExceptionTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function it_constructs_with_die_type_and_counts(): void
    {
        $exception = new InsufficientHitDiceException(
            dieType: 'd10',
            available: 2,
            requested: 5
        );

        $this->assertEquals('d10', $exception->dieType);
        $this->assertEquals(2, $exception->available);
        $this->assertEquals(5, $exception->requested);
    }

    #[Test]
    public function it_generates_message_when_some_available(): void
    {
        $exception = new InsufficientHitDiceException(
            dieType: 'd8',
            available: 3,
            requested: 5
        );

        $this->assertEquals('Not enough d8 hit dice available. Have 3, need 5.', $exception->getMessage());
    }

    #[Test]
    public function it_generates_message_when_none_available(): void
    {
        $exception = new InsufficientHitDiceException(
            dieType: 'd12',
            available: 0,
            requested: 2
        );

        $this->assertEquals('Character does not have any d12 hit dice.', $exception->getMessage());
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $customMessage = 'You need to rest before using more hit dice.';

        $exception = new InsufficientHitDiceException(
            dieType: 'd6',
            available: 1,
            requested: 3,
            message: $customMessage
        );

        $this->assertEquals($customMessage, $exception->getMessage());
    }
}
