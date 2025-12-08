<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterHpProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_max_hit_points_update_for_calculated_mode(): void
    {
        $character = Character::factory()->create([
            'hp_calculation_method' => 'calculated',
            'max_hit_points' => 10,
            'current_hit_points' => 10,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'max_hit_points' => 50,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_hit_points']);

        // HP should not have changed
        $character->refresh();
        $this->assertEquals(10, $character->max_hit_points);
    }

    /** @test */
    public function it_allows_max_hit_points_update_for_manual_mode(): void
    {
        $character = Character::factory()->create([
            'hp_calculation_method' => 'manual',
            'max_hit_points' => 10,
            'current_hit_points' => 10,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'max_hit_points' => 50,
        ]);

        $response->assertOk();

        $character->refresh();
        $this->assertEquals(50, $character->max_hit_points);
    }

    /** @test */
    public function it_allows_switching_from_calculated_to_manual_mode(): void
    {
        $character = Character::factory()->create([
            'hp_calculation_method' => 'calculated',
            'max_hit_points' => 10,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'hp_calculation_method' => 'manual',
        ]);

        $response->assertOk();

        $character->refresh();
        $this->assertEquals('manual', $character->hp_calculation_method);
    }

    /** @test */
    public function it_allows_max_hit_points_when_switching_to_manual_in_same_request(): void
    {
        $character = Character::factory()->create([
            'hp_calculation_method' => 'calculated',
            'max_hit_points' => 10,
            'current_hit_points' => 10,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'hp_calculation_method' => 'manual',
            'max_hit_points' => 50,
        ]);

        $response->assertOk();

        $character->refresh();
        $this->assertEquals('manual', $character->hp_calculation_method);
        $this->assertEquals(50, $character->max_hit_points);
    }

    /** @test */
    public function it_allows_switching_from_manual_to_calculated_mode(): void
    {
        $character = Character::factory()->create([
            'hp_calculation_method' => 'manual',
            'max_hit_points' => 50,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'hp_calculation_method' => 'calculated',
        ]);

        $response->assertOk();

        $character->refresh();
        $this->assertEquals('calculated', $character->hp_calculation_method);
        // HP should be preserved
        $this->assertEquals(50, $character->max_hit_points);
    }

    /** @test */
    public function it_rejects_invalid_hp_calculation_method(): void
    {
        $character = Character::factory()->create([
            'hp_calculation_method' => 'calculated',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'hp_calculation_method' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['hp_calculation_method']);
    }

    /** @test */
    public function it_rejects_max_hit_points_when_switching_to_calculated_mode(): void
    {
        $character = Character::factory()->create([
            'hp_calculation_method' => 'manual',
            'max_hit_points' => 50,
            'current_hit_points' => 50,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'hp_calculation_method' => 'calculated',
            'max_hit_points' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_hit_points']);

        // HP should not have changed
        $character->refresh();
        $this->assertEquals(50, $character->max_hit_points);
    }
}
