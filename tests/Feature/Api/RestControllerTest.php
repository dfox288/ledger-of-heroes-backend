<?php

namespace Tests\Feature\Api;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterSpellSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Short Rest Tests
    // =========================================================================

    #[Test]
    public function short_rest_resets_pact_magic_slots(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/short-rest");

        $response->assertOk()
            ->assertJsonPath('data.pact_magic_reset', true);

        $this->assertDatabaseHas('character_spell_slots', [
            'character_id' => $character->id,
            'slot_type' => SpellSlotType::PACT_MAGIC->value,
            'used_slots' => 0,
        ]);
    }

    #[Test]
    public function short_rest_does_not_reset_standard_slots(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 3,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $this->postJson("/api/v1/characters/{$character->id}/short-rest");

        $this->assertDatabaseHas('character_spell_slots', [
            'character_id' => $character->id,
            'slot_type' => SpellSlotType::STANDARD->value,
            'used_slots' => 3, // Unchanged
        ]);
    }

    #[Test]
    public function short_rest_returns_features_reset_list(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/short-rest");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pact_magic_reset',
                    'features_reset',
                ],
            ]);
    }

    // =========================================================================
    // Long Rest Tests
    // =========================================================================

    #[Test]
    public function long_rest_resets_all_spell_slots(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 3,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/long-rest");

        $response->assertOk()
            ->assertJsonPath('data.spell_slots_reset', true);

        // All slots should be reset
        $this->assertEquals(
            0,
            CharacterSpellSlot::where('character_id', $character->id)->sum('used_slots')
        );
    }

    #[Test]
    public function long_rest_restores_hp_to_max(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 15,
            'max_hit_points' => 45,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/long-rest");

        $response->assertOk()
            ->assertJsonPath('data.hp_restored', 30);

        $character->refresh();
        $this->assertEquals(45, $character->current_hit_points);
    }

    #[Test]
    public function long_rest_recovers_half_hit_dice(): void
    {
        $fighterClass = CharacterClass::factory()->baseClass()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $fighterClass->id,
            'level' => 8,
            'hit_dice_spent' => 6,
            'is_primary' => true,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/long-rest");

        $response->assertOk()
            ->assertJsonPath('data.hit_dice_recovered', 4);
    }

    #[Test]
    public function long_rest_clears_death_saves(): void
    {
        $character = Character::factory()->create([
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/long-rest");

        $response->assertOk()
            ->assertJsonPath('data.death_saves_cleared', true);

        $character->refresh();
        $this->assertEquals(0, $character->death_save_successes);
        $this->assertEquals(0, $character->death_save_failures);
    }

    #[Test]
    public function long_rest_returns_complete_summary(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/long-rest");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'hp_restored',
                    'hit_dice_recovered',
                    'spell_slots_reset',
                    'death_saves_cleared',
                    'features_reset',
                ],
            ]);
    }

    // =========================================================================
    // Error Cases
    // =========================================================================

    #[Test]
    public function short_rest_returns_404_for_nonexistent_character(): void
    {
        $response = $this->postJson('/api/v1/characters/99999/short-rest');

        $response->assertNotFound();
    }

    #[Test]
    public function long_rest_returns_404_for_nonexistent_character(): void
    {
        $response = $this->postJson('/api/v1/characters/99999/long-rest');

        $response->assertNotFound();
    }
}
