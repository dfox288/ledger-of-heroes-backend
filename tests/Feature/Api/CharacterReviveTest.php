<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for character revival endpoint.
 *
 * D&D 5e Revival Rules:
 * - Various spells can bring dead characters back to life
 * - Revival typically resets death saves and sets HP to 1+
 * - Some revival spells remove exhaustion, others don't
 */
class CharacterReviveTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Successful Revival
    // =====================

    #[Test]
    public function it_revives_a_dead_character(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
            'death_save_successes' => 2,
            'death_save_failures' => 3,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive");

        $response->assertOk()
            ->assertJsonPath('data.is_dead', false);

        $character->refresh();
        expect($character->is_dead)->toBeFalse();
    }

    #[Test]
    public function it_resets_death_saves_on_revival(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
            'death_save_successes' => 2,
            'death_save_failures' => 3,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive");

        $response->assertOk()
            ->assertJsonPath('data.death_save_successes', 0)
            ->assertJsonPath('data.death_save_failures', 0);

        $character->refresh();
        expect($character->death_save_successes)->toBe(0);
        expect($character->death_save_failures)->toBe(0);
    }

    #[Test]
    public function it_sets_hp_to_1_by_default(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
            'max_hit_points' => 50,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive");

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 1);

        $character->refresh();
        expect($character->current_hit_points)->toBe(1);
    }

    #[Test]
    public function it_sets_hp_to_specified_value(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
            'max_hit_points' => 50,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive", [
            'hit_points' => 25,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 25);

        $character->refresh();
        expect($character->current_hit_points)->toBe(25);
    }

    #[Test]
    public function it_caps_hp_at_max_hp(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
            'max_hit_points' => 50,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive", [
            'hit_points' => 100,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_hit_points', 50);

        $character->refresh();
        expect($character->current_hit_points)->toBe(50);
    }

    // =====================
    // Exhaustion Handling
    // =====================

    #[Test]
    public function it_clears_exhaustion_by_default(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
        ]);

        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'core:exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );

        CharacterCondition::create([
            'character_id' => $character->id,
            'condition_slug' => $exhaustion->slug,
            'level' => 6,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive");

        $response->assertOk();

        // Exhaustion should be removed
        expect(
            CharacterCondition::where('character_id', $character->id)
                ->where('condition_slug', $exhaustion->slug)
                ->exists()
        )->toBeFalse();
    }

    #[Test]
    public function it_can_preserve_exhaustion_when_requested(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
        ]);

        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'core:exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );

        // Set exhaustion level 3 (not lethal)
        CharacterCondition::create([
            'character_id' => $character->id,
            'condition_slug' => $exhaustion->slug,
            'level' => 3,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive", [
            'clear_exhaustion' => false,
        ]);

        $response->assertOk();

        // Exhaustion should still exist
        $condition = CharacterCondition::where('character_id', $character->id)
            ->where('condition_slug', $exhaustion->slug)
            ->first();

        expect($condition)->not->toBeNull();
        expect($condition->level)->toBe(3);
    }

    // =====================
    // Validation
    // =====================

    #[Test]
    public function it_rejects_revival_of_living_character(): void
    {
        $character = Character::factory()->create([
            'is_dead' => false,
            'current_hit_points' => 10,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['character']);
    }

    #[Test]
    public function it_rejects_negative_hit_points(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive", [
            'hit_points' => -5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hit_points']);
    }

    #[Test]
    public function it_rejects_zero_hit_points(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive", [
            'hit_points' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hit_points']);
    }

    // =====================
    // Source Parameter
    // =====================

    #[Test]
    public function it_accepts_source_parameter(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive", [
            'source' => 'Revivify spell',
        ]);

        // Should succeed - source is accepted but not stored yet
        $response->assertOk();
    }

    // =====================
    // Response Structure
    // =====================

    #[Test]
    public function it_returns_full_character_resource(): void
    {
        $character = Character::factory()->create([
            'is_dead' => true,
            'current_hit_points' => 0,
            'max_hit_points' => 50,
            'death_save_successes' => 1,
            'death_save_failures' => 3,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/revive");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'is_dead',
                    'current_hit_points',
                    'max_hit_points',
                    'death_save_successes',
                    'death_save_failures',
                ],
            ]);
    }
}
