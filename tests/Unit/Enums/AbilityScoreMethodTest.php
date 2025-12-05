<?php

namespace Tests\Unit\Enums;

use App\Enums\AbilityScoreMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AbilityScoreMethodTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('manual', AbilityScoreMethod::Manual->value);
        $this->assertSame('point_buy', AbilityScoreMethod::PointBuy->value);
        $this->assertSame('standard_array', AbilityScoreMethod::StandardArray->value);
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        $this->assertSame('Manual', AbilityScoreMethod::Manual->label());
        $this->assertSame('Point Buy', AbilityScoreMethod::PointBuy->label());
        $this->assertSame('Standard Array', AbilityScoreMethod::StandardArray->label());
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(AbilityScoreMethod::Manual, AbilityScoreMethod::from('manual'));
        $this->assertSame(AbilityScoreMethod::PointBuy, AbilityScoreMethod::from('point_buy'));
        $this->assertSame(AbilityScoreMethod::StandardArray, AbilityScoreMethod::from('standard_array'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(AbilityScoreMethod::tryFrom('invalid'));
        $this->assertNull(AbilityScoreMethod::tryFrom('rolled'));
    }
}
