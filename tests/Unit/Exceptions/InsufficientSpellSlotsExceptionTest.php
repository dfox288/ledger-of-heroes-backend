<?php

namespace Tests\Unit\Exceptions;

use App\Enums\SpellSlotType;
use App\Exceptions\InsufficientSpellSlotsException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class InsufficientSpellSlotsExceptionTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function it_constructs_with_spell_level_and_slot_details(): void
    {
        $exception = new InsufficientSpellSlotsException(
            spellLevel: 3,
            slotType: SpellSlotType::STANDARD,
            available: 1
        );

        $this->assertEquals(3, $exception->spellLevel);
        $this->assertEquals(SpellSlotType::STANDARD, $exception->slotType);
        $this->assertEquals(1, $exception->available);
    }

    #[Test]
    public function it_generates_message_when_some_slots_available(): void
    {
        $exception = new InsufficientSpellSlotsException(
            spellLevel: 2,
            slotType: SpellSlotType::STANDARD,
            available: 1
        );

        $this->assertEquals('Not enough Standard slots at level 2. Have 1, need 1.', $exception->getMessage());
    }

    #[Test]
    public function it_generates_message_when_no_slots_available(): void
    {
        $exception = new InsufficientSpellSlotsException(
            spellLevel: 5,
            slotType: SpellSlotType::STANDARD,
            available: 0
        );

        $this->assertEquals('No Standard slots available at level 5.', $exception->getMessage());
    }

    #[Test]
    public function it_handles_pact_magic_slot_type(): void
    {
        $exception = new InsufficientSpellSlotsException(
            spellLevel: 3,
            slotType: SpellSlotType::PACT_MAGIC,
            available: 0
        );

        $this->assertEquals('No Pact Magic slots available at level 3.', $exception->getMessage());
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $customMessage = 'You need a long rest to recover spell slots.';

        $exception = new InsufficientSpellSlotsException(
            spellLevel: 1,
            slotType: SpellSlotType::STANDARD,
            available: 0,
            message: $customMessage
        );

        $this->assertEquals($customMessage, $exception->getMessage());
    }
}
