<?php

namespace Tests\Feature\Api;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpellSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterSpellSlotUpdateTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Absolute Value Updates (spent: n)
    // =====================

    #[Test]
    public function it_sets_spent_slots_to_absolute_value(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['spent' => 2]
        );

        $response->assertOk()
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.spent', 2)
            ->assertJsonPath('data.available', 2);

        $this->assertDatabaseHas('character_spell_slots', [
            'character_id' => $character->id,
            'spell_level' => 1,
            'used_slots' => 2,
        ]);
    }

    #[Test]
    public function it_rejects_spent_exceeding_total(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['spent' => 5]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['spent']);
    }

    #[Test]
    public function it_rejects_negative_spent_value(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['spent' => -1]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['spent']);
    }

    // =====================
    // Action-Based Updates
    // =====================

    #[Test]
    public function it_uses_a_slot_via_action(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['action' => 'use']
        );

        $response->assertOk()
            ->assertJsonPath('data.spent', 2)
            ->assertJsonPath('data.available', 2);

        $this->assertDatabaseHas('character_spell_slots', [
            'character_id' => $character->id,
            'spell_level' => 1,
            'used_slots' => 2,
        ]);
    }

    #[Test]
    public function it_restores_a_slot_via_action(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['action' => 'restore']
        );

        $response->assertOk()
            ->assertJsonPath('data.spent', 1)
            ->assertJsonPath('data.available', 3);

        $this->assertDatabaseHas('character_spell_slots', [
            'character_id' => $character->id,
            'spell_level' => 1,
            'used_slots' => 1,
        ]);
    }

    #[Test]
    public function it_resets_slots_via_action(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 3,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['action' => 'reset']
        );

        $response->assertOk()
            ->assertJsonPath('data.spent', 0)
            ->assertJsonPath('data.available', 4);

        $this->assertDatabaseHas('character_spell_slots', [
            'character_id' => $character->id,
            'spell_level' => 1,
            'used_slots' => 0,
        ]);
    }

    #[Test]
    public function it_rejects_use_when_no_slots_available(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 4, // All used
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['action' => 'use']
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No spell slots available at this level.');
    }

    #[Test]
    public function it_rejects_restore_when_none_spent(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0, // None spent
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['action' => 'restore']
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No spell slots to restore at this level.');
    }

    #[Test]
    public function it_rejects_invalid_action(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['action' => 'invalid']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    }

    // =====================
    // Slot Type Selection
    // =====================

    #[Test]
    public function it_defaults_to_standard_slot_type(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['spent' => 1]
        );

        $response->assertOk()
            ->assertJsonPath('data.slot_type', 'standard');
    }

    #[Test]
    public function it_updates_pact_magic_slots_when_specified(): void
    {
        $warlockClass = CharacterClass::factory()->spellcaster('CHA')->create(['name' => 'Warlock']);
        $character = Character::factory()->withClass($warlockClass)->level(5)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/3",
            ['spent' => 1, 'slot_type' => 'pact_magic']
        );

        $response->assertOk()
            ->assertJsonPath('data.slot_type', 'pact_magic')
            ->assertJsonPath('data.spent', 1);

        $this->assertDatabaseHas('character_spell_slots', [
            'character_id' => $character->id,
            'spell_level' => 3,
            'slot_type' => SpellSlotType::PACT_MAGIC,
            'used_slots' => 1,
        ]);
    }

    // =====================
    // Edge Cases & Validation
    // =====================

    #[Test]
    public function it_requires_either_spent_or_action(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            []
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_spell_level(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(1)->create();

        // Level 1 wizard has level 1 slots, not level 5
        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 2,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/5",
            ['spent' => 1]
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_rejects_invalid_spell_level(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/0",
            ['spent' => 1]
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_rejects_spell_level_above_9(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(20)->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/10",
            ['spent' => 1]
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_correct_response_structure(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['spent' => 2]
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'level',
                    'total',
                    'spent',
                    'available',
                    'slot_type',
                ],
            ]);
    }

    #[Test]
    public function it_creates_slot_record_if_not_exists_when_using(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(3)->create();

        // Don't create CharacterSpellSlot record upfront

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spell-slots/1",
            ['action' => 'use']
        );

        // Should create the slot record with used_slots = 1
        $response->assertOk()
            ->assertJsonPath('data.spent', 1);

        $this->assertDatabaseHas('character_spell_slots', [
            'character_id' => $character->id,
            'spell_level' => 1,
            'used_slots' => 1,
        ]);
    }
}
