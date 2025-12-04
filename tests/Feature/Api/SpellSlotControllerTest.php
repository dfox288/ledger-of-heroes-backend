<?php

namespace Tests\Feature\Api;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterSpellSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellSlotControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Get Spell Slots
    // =========================================================================

    #[Test]
    public function it_returns_spell_slots_grouped_by_type(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 3,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'standard',
                    'pact_magic',
                ],
            ])
            ->assertJsonPath('data.standard.1.max', 4)
            ->assertJsonPath('data.standard.1.used', 1)
            ->assertJsonPath('data.standard.1.available', 3)
            ->assertJsonPath('data.pact_magic.3.max', 2);
    }

    // =========================================================================
    // Use Spell Slot
    // =========================================================================

    #[Test]
    public function it_uses_a_standard_spell_slot(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/spell-slots/use", [
            'spell_level' => 1,
            'slot_type' => 'standard',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.standard.1.used', 2)
            ->assertJsonPath('data.standard.1.available', 2);
    }

    #[Test]
    public function it_uses_a_pact_magic_spell_slot(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/spell-slots/use", [
            'spell_level' => 3,
            'slot_type' => 'pact_magic',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pact_magic.3.used', 1);
    }

    #[Test]
    public function it_returns_error_when_no_slots_available(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/spell-slots/use", [
            'spell_level' => 1,
            'slot_type' => 'standard',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No Standard slots available at level 1.');
    }

    #[Test]
    public function it_validates_spell_level(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/spell-slots/use", [
            'spell_level' => 10, // Invalid (max is 9)
            'slot_type' => 'standard',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['spell_level']);
    }

    #[Test]
    public function it_validates_slot_type(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/spell-slots/use", [
            'spell_level' => 1,
            'slot_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slot_type']);
    }
}
