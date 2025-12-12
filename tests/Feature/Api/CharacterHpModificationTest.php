<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for character HP modification endpoint.
 *
 * Covers issue #536 - HP Modification Endpoint with D&D Rule Enforcement.
 *
 * API Contract:
 * - PATCH /api/v1/characters/{character}/hp
 * - hp: string with prefix ("-12" = damage, "+15" = heal, "45" = set)
 * - temp_hp: integer (always absolute, higher-wins logic)
 *
 * D&D 5e Rules:
 * - Damage subtracts from temp HP first, overflow to current HP
 * - Healing adds to current HP, caps at max HP
 * - Current HP cannot go below 0
 * - Temp HP doesn't stack - keep higher value
 * - Death saves reset when HP goes from 0 to positive
 */
class CharacterHpModificationTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Damage Scenarios
    // =====================

    #[Test]
    public function it_absorbs_damage_with_temp_hp_only(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'temp_hit_points' => 15,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-10',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 50)
            ->assertJsonPath('data.temp_hit_points', 5);
    }

    #[Test]
    public function it_overflows_damage_from_temp_hp_to_current_hp(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'temp_hit_points' => 5,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-12',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 43) // 50 - (12 - 5)
            ->assertJsonPath('data.temp_hit_points', 0);
    }

    #[Test]
    public function it_applies_damage_directly_when_no_temp_hp(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 40,
            'temp_hit_points' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-15',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 25)
            ->assertJsonPath('data.temp_hit_points', 0);
    }

    #[Test]
    public function it_floors_current_hp_at_zero_on_massive_damage(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 20,
            'temp_hit_points' => 5,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-100',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 0)
            ->assertJsonPath('data.temp_hit_points', 0);
    }

    // =====================
    // Healing Scenarios
    // =====================

    #[Test]
    public function it_adds_healing_to_current_hp(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
            'temp_hit_points' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '+15',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 45)
            ->assertJsonPath('data.max_hit_points', 50);
    }

    #[Test]
    public function it_caps_healing_at_max_hp(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 45,
            'temp_hit_points' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '+20',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 50)
            ->assertJsonPath('data.max_hit_points', 50);
    }

    #[Test]
    public function it_resets_death_saves_when_healing_from_zero(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 0,
            'temp_hit_points' => 0,
            'death_save_successes' => 2,
            'death_save_failures' => 2,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '+10',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 10)
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 0);
    }

    // =====================
    // Set Scenarios
    // =====================

    #[Test]
    public function it_sets_absolute_hp_value(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
            'temp_hit_points' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '45',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 45);
    }

    #[Test]
    public function it_caps_absolute_value_at_max_hp(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
            'temp_hit_points' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '100',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 50);
    }

    #[Test]
    public function it_allows_setting_hp_to_zero(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
            'temp_hit_points' => 0,
            'death_save_successes' => 1,
            'death_save_failures' => 1,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '0',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 0)
            // Existing death saves should be preserved when setting to 0
            ->assertJsonPath('data.death_save_successes', 1)
            ->assertJsonPath('data.death_save_failures', 1);
    }

    #[Test]
    public function it_resets_death_saves_when_setting_hp_from_zero_to_positive(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 0,
            'temp_hit_points' => 0,
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '25',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 25)
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 0);
    }

    // =====================
    // Temp HP Scenarios
    // =====================

    #[Test]
    public function it_replaces_temp_hp_when_new_value_is_higher(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'temp_hit_points' => 5,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'temp_hp' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.temp_hit_points', 10);
    }

    #[Test]
    public function it_keeps_current_temp_hp_when_new_value_is_lower(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'temp_hit_points' => 15,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'temp_hp' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.temp_hit_points', 15);
    }

    #[Test]
    public function it_clears_temp_hp_when_set_to_zero(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'temp_hit_points' => 10,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'temp_hp' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.temp_hit_points', 0);
    }

    // =====================
    // Combined Scenarios
    // =====================

    #[Test]
    public function it_handles_hp_and_temp_hp_in_same_request(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
            'temp_hit_points' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '+10',
            'temp_hp' => 15,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 40)
            ->assertJsonPath('data.temp_hit_points', 15);
    }

    #[Test]
    public function it_handles_damage_and_temp_hp_grant_in_same_request(): void
    {
        // Character has 50 HP, 5 temp HP
        // Takes 10 damage (-10) and gains 15 temp HP
        // Expected: 45 HP (temp absorbed 5, overflow 5), 15 temp HP (higher-wins)
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'temp_hit_points' => 5,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-10',
            'temp_hp' => 15,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 45)
            ->assertJsonPath('data.temp_hit_points', 15);
    }

    #[Test]
    public function it_returns_no_change_for_empty_request(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
            'temp_hit_points' => 10,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", []);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 30)
            ->assertJsonPath('data.temp_hit_points', 10);
    }

    #[Test]
    public function it_handles_character_already_at_zero_hp(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 0,
            'temp_hit_points' => 0,
            'death_save_successes' => 1,
            'death_save_failures' => 2,
        ]);

        // Taking more damage at 0 HP - still stays at 0
        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-10',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 0)
            // Death saves preserved - damage at 0 HP is handled by death save endpoint
            ->assertJsonPath('data.death_save_successes', 1)
            ->assertJsonPath('data.death_save_failures', 2);
    }

    // =====================
    // Response Structure
    // =====================

    #[Test]
    public function it_returns_correct_response_structure(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
            'temp_hit_points' => 5,
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-5',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'current_hit_points',
                    'max_hit_points',
                    'temp_hit_points',
                    'death_save_successes',
                    'death_save_failures',
                ],
            ]);
    }

    // =====================
    // Validation Tests
    // =====================

    #[Test]
    public function it_rejects_invalid_hp_format(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hp']);
    }

    #[Test]
    public function it_rejects_hp_with_invalid_characters(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '++10',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hp']);
    }

    #[Test]
    public function it_rejects_negative_temp_hp(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'temp_hp' => -5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['temp_hp']);
    }

    #[Test]
    public function it_rejects_non_integer_temp_hp(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'temp_hp' => 'ten',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['temp_hp']);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character(): void
    {
        $response = $this->patchJson('/api/v1/characters/999999/hp', [
            'hp' => '-10',
        ]);

        $response->assertNotFound();
    }

    // =====================
    // Edge Cases
    // =====================

    #[Test]
    public function it_handles_healing_exact_amount_to_max(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 45,
            'temp_hit_points' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '+5',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 50);
    }

    #[Test]
    public function it_handles_damage_equal_to_temp_hp(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'temp_hit_points' => 10,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-10',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 50)
            ->assertJsonPath('data.temp_hit_points', 0);
    }

    #[Test]
    public function it_accepts_zero_as_healing_amount(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '+0',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 30);
    }

    #[Test]
    public function it_accepts_zero_as_damage_amount(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 30,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/hp", [
            'hp' => '-0',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 30);
    }
}
