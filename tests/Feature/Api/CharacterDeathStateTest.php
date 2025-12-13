<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for character death state tracking (is_dead flag).
 *
 * Issue #543 - Add is_dead flag for character death state.
 *
 * D&D 5e Death Rules:
 * - Exhaustion level 6 = instant death
 * - 3 death save failures = death
 * - These are distinct from being at 0 HP (dying state)
 */
class CharacterDeathStateTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Default Values Tests
    // =====================

    #[Test]
    public function it_defaults_is_dead_to_false(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_dead', false);
    }

    #[Test]
    public function it_includes_is_dead_in_character_response(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'is_dead',
                ],
            ]);
    }

    // =====================
    // Exhaustion Level 6 = Death
    // =====================

    #[Test]
    public function it_sets_is_dead_true_when_exhaustion_reaches_level_6(): void
    {
        $character = Character::factory()->create(['is_dead' => false]);
        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'core:exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition' => $exhaustion->slug,
            'level' => 6,
        ]);

        $response->assertSuccessful();

        // Verify character is now dead
        $character->refresh();
        expect($character->is_dead)->toBeTrue();

        // Verify API response reflects death state
        $response = $this->getJson("/api/v1/characters/{$character->id}");
        $response->assertOk()
            ->assertJsonPath('data.is_dead', true);
    }

    #[Test]
    public function it_does_not_set_is_dead_for_exhaustion_below_level_6(): void
    {
        $character = Character::factory()->create(['is_dead' => false]);
        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'core:exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );

        foreach ([1, 2, 3, 4, 5] as $level) {
            $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
                'condition' => $exhaustion->slug,
                'level' => $level,
            ]);

            $response->assertSuccessful();

            $character->refresh();
            expect($character->is_dead)->toBeFalse("Character should not be dead at exhaustion level {$level}");
        }
    }

    // =====================
    // Death Save Failures = Death
    // =====================

    #[Test]
    public function it_sets_is_dead_true_when_death_save_failures_reach_3(): void
    {
        $character = Character::factory()->create([
            'is_dead' => false,
            'current_hit_points' => 0,
            'death_save_successes' => 0,
            'death_save_failures' => 2,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/death-saves", [
            'roll' => 5, // Failure roll
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_failures', 3)
            ->assertJsonPath('data.outcome', 'dead');

        // Verify character is now dead
        $character->refresh();
        expect($character->is_dead)->toBeTrue();

        // Verify API response reflects death state
        $response = $this->getJson("/api/v1/characters/{$character->id}");
        $response->assertOk()
            ->assertJsonPath('data.is_dead', true);
    }

    #[Test]
    public function it_sets_is_dead_true_on_critical_failure_reaching_3(): void
    {
        $character = Character::factory()->create([
            'is_dead' => false,
            'current_hit_points' => 0,
            'death_save_successes' => 0,
            'death_save_failures' => 1,
        ]);

        // Rolling a 1 = 2 failures, so 1 + 2 = 3
        $response = $this->postJson("/api/v1/characters/{$character->id}/death-saves", [
            'roll' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_failures', 3)
            ->assertJsonPath('data.outcome', 'dead');

        $character->refresh();
        expect($character->is_dead)->toBeTrue();
    }

    #[Test]
    public function it_sets_is_dead_true_on_critical_damage_reaching_3(): void
    {
        $character = Character::factory()->create([
            'is_dead' => false,
            'current_hit_points' => 0,
            'death_save_successes' => 0,
            'death_save_failures' => 1,
        ]);

        // Critical damage at 0 HP = 2 failures, so 1 + 2 = 3
        $response = $this->postJson("/api/v1/characters/{$character->id}/death-saves", [
            'damage' => 10,
            'is_critical' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.death_save_failures', 3)
            ->assertJsonPath('data.outcome', 'dead');

        $character->refresh();
        expect($character->is_dead)->toBeTrue();
    }

    #[Test]
    public function it_does_not_set_is_dead_for_less_than_3_failures(): void
    {
        $character = Character::factory()->create([
            'is_dead' => false,
            'current_hit_points' => 0,
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        // First failure
        $response = $this->postJson("/api/v1/characters/{$character->id}/death-saves", [
            'roll' => 5,
        ]);

        $response->assertOk();
        $character->refresh();
        expect($character->is_dead)->toBeFalse();

        // Second failure
        $response = $this->postJson("/api/v1/characters/{$character->id}/death-saves", [
            'roll' => 5,
        ]);

        $response->assertOk();
        $character->refresh();
        expect($character->is_dead)->toBeFalse();
    }

    // =====================
    // Manual Death State Update
    // =====================

    #[Test]
    public function it_can_manually_set_is_dead_via_patch(): void
    {
        $character = Character::factory()->create(['is_dead' => false]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'is_dead' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_dead', true);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_dead' => true,
        ]);
    }

    // =====================
    // Auto-Computation via PATCH (Issue #590)
    // =====================

    #[Test]
    public function it_auto_computes_is_dead_when_death_save_failures_set_to_3_via_patch(): void
    {
        $character = Character::factory()->create([
            'is_dead' => false,
            'current_hit_points' => 0,
            'death_save_failures' => 0,
        ]);

        // Issue #590: Setting death_save_failures to 3 via PATCH should auto-set is_dead
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'death_save_failures' => 3,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_dead', true)
            ->assertJsonPath('data.death_save_failures', 3);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_dead' => true,
            'death_save_failures' => 3,
        ]);
    }

    #[Test]
    public function it_auto_computes_is_dead_when_model_death_save_failures_reaches_3(): void
    {
        $character = Character::factory()->create([
            'is_dead' => false,
            'current_hit_points' => 0,
            'death_save_failures' => 2,
        ]);

        // Direct model update should also trigger is_dead computation
        $character->death_save_failures = 3;
        $character->save();

        $character->refresh();
        expect($character->is_dead)->toBeTrue();
    }

    #[Test]
    public function it_does_not_auto_set_is_dead_when_death_save_failures_below_3(): void
    {
        $character = Character::factory()->create([
            'is_dead' => false,
            'current_hit_points' => 0,
            'death_save_failures' => 0,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'death_save_failures' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_dead', false)
            ->assertJsonPath('data.death_save_failures', 2);
    }

    #[Test]
    public function it_can_resurrect_character_by_setting_is_dead_false(): void
    {
        $character = Character::factory()->create(['is_dead' => true]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'is_dead' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_dead', false);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_dead' => false,
        ]);
    }

    // =====================
    // Character List Endpoint
    // =====================

    #[Test]
    public function it_includes_is_dead_in_character_list(): void
    {
        Character::factory()->create(['is_dead' => false]);
        Character::factory()->create(['is_dead' => true]);

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['is_dead'],
                ],
            ]);
    }
}
