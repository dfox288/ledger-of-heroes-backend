<?php

namespace Tests\Unit\Enums;

use App\Enums\ResetTiming;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResetTimingTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('short_rest', ResetTiming::SHORT_REST->value);
        $this->assertSame('long_rest', ResetTiming::LONG_REST->value);
        $this->assertSame('dawn', ResetTiming::DAWN->value);
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        $this->assertSame('Short Rest', ResetTiming::SHORT_REST->label());
        $this->assertSame('Long Rest', ResetTiming::LONG_REST->label());
        $this->assertSame('Dawn', ResetTiming::DAWN->label());
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(ResetTiming::SHORT_REST, ResetTiming::from('short_rest'));
        $this->assertSame(ResetTiming::LONG_REST, ResetTiming::from('long_rest'));
        $this->assertSame(ResetTiming::DAWN, ResetTiming::from('dawn'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(ResetTiming::tryFrom('invalid'));
        $this->assertNull(ResetTiming::tryFrom('full_rest'));
    }
}
