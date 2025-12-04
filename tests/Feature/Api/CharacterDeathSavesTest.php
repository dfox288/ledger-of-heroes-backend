<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for character death save tracking.
 *
 * Covers issue #112 - Death Saves Tracking.
 *
 * D&D 5e Rules:
 * - When at 0 HP, character makes death saving throws
 * - 3 successes = stabilized (unconscious but not dying)
 * - 3 failures = character dies
 * - Rolling a 1 = 2 failures
 * - Rolling a 20 = regain 1 HP, wake up, reset saves
 * - Taking damage at 0 HP = automatic failure (crit = 2 failures)
 * - Death saves reset when HP goes above 0
 */
class CharacterDeathSavesTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Default Values Tests
    // =====================

    #[Test]
    public function it_defaults_death_saves_to_zero(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 0);
    }

    #[Test]
    public function it_can_create_character_with_death_saves(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Dying Hero',
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.death_save_successes', 2)
            ->assertJsonPath('data.death_save_failures', 1);

        $this->assertDatabaseHas('characters', [
            'name' => 'Dying Hero',
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);
    }

    // =====================
    // PATCH Update Tests
    // =====================

    #[Test]
    public function it_can_update_death_save_successes(): void
    {
        $character = Character::factory()->create([
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'death_save_successes' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 2);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'death_save_successes' => 2,
        ]);
    }

    #[Test]
    public function it_can_update_death_save_failures(): void
    {
        $character = Character::factory()->create([
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'death_save_failures' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_failures', 2);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'death_save_failures' => 2,
        ]);
    }

    #[Test]
    public function it_can_update_both_death_saves_at_once(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 2)
            ->assertJsonPath('data.death_save_failures', 1);
    }

    #[Test]
    public function it_can_reset_death_saves_to_zero(): void
    {
        $character = Character::factory()->create([
            'death_save_successes' => 3,
            'death_save_failures' => 2,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 0);
    }

    // =====================
    // Validation Tests
    // =====================

    #[Test]
    public function it_validates_death_save_successes_is_integer(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test',
            'death_save_successes' => 'not-an-integer',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['death_save_successes']);
    }

    #[Test]
    public function it_validates_death_save_failures_is_integer(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test',
            'death_save_failures' => 'not-an-integer',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['death_save_failures']);
    }

    #[Test]
    public function it_validates_death_save_successes_minimum_is_zero(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test',
            'death_save_successes' => -1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['death_save_successes']);
    }

    #[Test]
    public function it_validates_death_save_failures_minimum_is_zero(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test',
            'death_save_failures' => -1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['death_save_failures']);
    }

    #[Test]
    public function it_validates_death_save_successes_maximum_is_three(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test',
            'death_save_successes' => 4,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['death_save_successes']);
    }

    #[Test]
    public function it_validates_death_save_failures_maximum_is_three(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test',
            'death_save_failures' => 4,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['death_save_failures']);
    }

    #[Test]
    public function it_accepts_all_valid_death_save_values(): void
    {
        foreach ([0, 1, 2, 3] as $value) {
            $response = $this->postJson('/api/v1/characters', [
                'name' => "Character with {$value} saves",
                'death_save_successes' => $value,
                'death_save_failures' => $value,
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.death_save_successes', $value)
                ->assertJsonPath('data.death_save_failures', $value);
        }
    }

    // =====================
    // POST /death-save Endpoint Tests
    // =====================

    #[Test]
    public function it_can_record_death_save_roll_success(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 10, // 10+ is a success
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 1)
            ->assertJsonPath('data.death_save_failures', 0)
            ->assertJsonPath('data.result', 'success')
            ->assertJsonPath('data.outcome', null);
    }

    #[Test]
    public function it_can_record_death_save_roll_failure(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 9, // Below 10 is a failure
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 1)
            ->assertJsonPath('data.result', 'failure')
            ->assertJsonPath('data.outcome', null);
    }

    #[Test]
    public function it_counts_natural_one_as_two_failures(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_failures', 2)
            ->assertJsonPath('data.result', 'critical_failure');
    }

    #[Test]
    public function it_handles_natural_twenty_regain_hp_and_reset(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'max_hit_points' => 20,
            'death_save_successes' => 2,
            'death_save_failures' => 2,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 0)
            ->assertJsonPath('data.current_hit_points', 1)
            ->assertJsonPath('data.result', 'critical_success')
            ->assertJsonPath('data.outcome', 'conscious');
    }

    #[Test]
    public function it_stabilizes_on_three_successes(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 15,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 3)
            ->assertJsonPath('data.outcome', 'stable');
    }

    #[Test]
    public function it_dies_on_three_failures(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 1,
            'death_save_failures' => 2,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_failures', 3)
            ->assertJsonPath('data.outcome', 'dead');
    }

    #[Test]
    public function it_handles_damage_as_automatic_failure(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 1,
            'death_save_failures' => 0,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'damage' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_failures', 1)
            ->assertJsonPath('data.result', 'damage');
    }

    #[Test]
    public function it_handles_critical_damage_as_two_failures(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 1,
            'death_save_failures' => 0,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'damage' => 5,
            'is_critical' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_failures', 2)
            ->assertJsonPath('data.result', 'critical_damage');
    }

    #[Test]
    public function it_validates_roll_is_between_1_and_20(): void
    {
        $character = Character::factory()->create(['current_hit_points' => 0]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 21,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['roll']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['roll']);
    }

    #[Test]
    public function it_validates_damage_is_positive(): void
    {
        $character = Character::factory()->create(['current_hit_points' => 0]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'damage' => -5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['damage']);
    }

    #[Test]
    public function it_requires_either_roll_or_damage(): void
    {
        $character = Character::factory()->create(['current_hit_points' => 0]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", []);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_death_save_when_character_has_hp(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 10,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-save", [
            'roll' => 15,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Character is not at 0 HP');
    }

    // =====================
    // POST /stabilize Endpoint Tests
    // =====================

    #[Test]
    public function it_can_stabilize_character_and_reset_death_saves(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/stabilize");

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 0)
            ->assertJsonPath('data.is_stable', true);
    }

    // =====================
    // Auto-Reset on Healing Tests
    // =====================

    #[Test]
    public function it_resets_death_saves_when_hp_goes_above_zero(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 2,
            'death_save_failures' => 2,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'current_hit_points' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 5)
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 0);
    }

    #[Test]
    public function it_does_not_reset_death_saves_when_hp_stays_zero(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        // Update something else, HP stays 0
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'name' => 'Still Dying',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 2)
            ->assertJsonPath('data.death_save_failures', 1);
    }

    #[Test]
    public function it_does_not_reset_death_saves_when_hp_not_updated(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 5,
            'death_save_successes' => 2,
            'death_save_failures' => 1,
        ]);

        // Just update name, don't touch HP
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 2)
            ->assertJsonPath('data.death_save_failures', 1);
    }

    // =====================
    // Conditional Display Tests
    // =====================

    #[Test]
    public function it_includes_death_saves_in_response_regardless_of_hp(): void
    {
        // Death saves should always be visible for tracking purposes
        $character = Character::factory()->create([
            'current_hit_points' => 20,
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'death_save_successes',
                    'death_save_failures',
                ],
            ]);
    }
}
