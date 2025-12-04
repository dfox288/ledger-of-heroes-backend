<?php

namespace Tests\Unit\Models;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterSpellSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterSpellSlotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_character(): void
    {
        $character = Character::factory()->create();
        $slot = CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
        ]);

        $this->assertTrue($slot->character->is($character));
    }

    #[Test]
    public function it_casts_slot_type_to_enum(): void
    {
        $slot = CharacterSpellSlot::factory()->create([
            'slot_type' => 'standard',
        ]);

        $this->assertInstanceOf(SpellSlotType::class, $slot->slot_type);
        $this->assertEquals(SpellSlotType::STANDARD, $slot->slot_type);
    }

    #[Test]
    public function it_calculates_available_slots(): void
    {
        $slot = CharacterSpellSlot::factory()->create([
            'max_slots' => 4,
            'used_slots' => 1,
        ]);

        $this->assertEquals(3, $slot->available);
    }

    #[Test]
    public function it_checks_if_slots_are_available(): void
    {
        $slotWithAvailable = CharacterSpellSlot::factory()->create([
            'max_slots' => 4,
            'used_slots' => 2,
        ]);

        $slotFullyUsed = CharacterSpellSlot::factory()->create([
            'max_slots' => 2,
            'used_slots' => 2,
        ]);

        $this->assertTrue($slotWithAvailable->hasAvailable());
        $this->assertFalse($slotFullyUsed->hasAvailable());
    }

    #[Test]
    public function it_can_use_a_slot(): void
    {
        $slot = CharacterSpellSlot::factory()->create([
            'max_slots' => 4,
            'used_slots' => 1,
        ]);

        $result = $slot->useSlot();

        $this->assertTrue($result);
        $this->assertEquals(2, $slot->fresh()->used_slots);
    }

    #[Test]
    public function it_cannot_use_slot_when_none_available(): void
    {
        $slot = CharacterSpellSlot::factory()->create([
            'max_slots' => 2,
            'used_slots' => 2,
        ]);

        $result = $slot->useSlot();

        $this->assertFalse($result);
        $this->assertEquals(2, $slot->fresh()->used_slots);
    }

    #[Test]
    public function it_can_reset_used_slots(): void
    {
        $slot = CharacterSpellSlot::factory()->create([
            'max_slots' => 4,
            'used_slots' => 3,
        ]);

        $slot->reset();

        $this->assertEquals(0, $slot->fresh()->used_slots);
    }

    #[Test]
    public function it_enforces_unique_character_level_type_combination(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);
    }

    #[Test]
    public function it_allows_same_level_with_different_types(): void
    {
        $character = Character::factory()->create();

        $standardSlot = CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $pactSlot = CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $this->assertDatabaseHas('character_spell_slots', [
            'id' => $standardSlot->id,
            'slot_type' => SpellSlotType::STANDARD->value,
        ]);
        $this->assertDatabaseHas('character_spell_slots', [
            'id' => $pactSlot->id,
            'slot_type' => SpellSlotType::PACT_MAGIC->value,
        ]);
    }
}
