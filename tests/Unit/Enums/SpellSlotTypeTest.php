<?php

namespace Tests\Unit\Enums;

use App\Enums\SpellSlotType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SpellSlotTypeTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('standard', SpellSlotType::STANDARD->value);
        $this->assertSame('pact_magic', SpellSlotType::PACT_MAGIC->value);
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        $this->assertSame('Standard', SpellSlotType::STANDARD->label());
        $this->assertSame('Pact Magic', SpellSlotType::PACT_MAGIC->label());
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(SpellSlotType::STANDARD, SpellSlotType::from('standard'));
        $this->assertSame(SpellSlotType::PACT_MAGIC, SpellSlotType::from('pact_magic'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(SpellSlotType::tryFrom('invalid'));
        $this->assertNull(SpellSlotType::tryFrom('warlock'));
    }
}
