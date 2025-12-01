<?php

namespace Tests\Feature\Api;

use App\Enums\AbilityScoreMethod;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterAbilityScoreMethodTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Point Buy Tests
    // =====================

    #[Test]
    public function it_accepts_valid_point_buy_scores(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 15,
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_score_method', 'point_buy')
            ->assertJsonPath('data.ability_scores.STR', 15)
            ->assertJsonPath('data.ability_scores.DEX', 14)
            ->assertJsonPath('data.ability_scores.CON', 13)
            ->assertJsonPath('data.ability_scores.INT', 12)
            ->assertJsonPath('data.ability_scores.WIS', 10)
            ->assertJsonPath('data.ability_scores.CHA', 8);
    }

    #[Test]
    public function it_accepts_another_valid_point_buy_allocation(): void
    {
        $character = Character::factory()->create();

        // 15+15+15+8+8+8 = 9+9+9+0+0+0 = 27 points
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 15,
            'dexterity' => 15,
            'constitution' => 15,
            'intelligence' => 8,
            'wisdom' => 8,
            'charisma' => 8,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_score_method', 'point_buy');
    }

    #[Test]
    public function it_rejects_point_buy_over_budget(): void
    {
        $character = Character::factory()->create();

        // 15+15+15+10+8+8 = 9+9+9+2+0+0 = 29 points (over)
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 15,
            'dexterity' => 15,
            'constitution' => 15,
            'intelligence' => 10,
            'wisdom' => 8,
            'charisma' => 8,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ability_scores']);
    }

    #[Test]
    public function it_rejects_point_buy_under_budget(): void
    {
        $character = Character::factory()->create();

        // All 8s = 0 points (under)
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 8,
            'dexterity' => 8,
            'constitution' => 8,
            'intelligence' => 8,
            'wisdom' => 8,
            'charisma' => 8,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ability_scores']);
    }

    #[Test]
    public function it_rejects_point_buy_with_score_outside_range(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 16, // Invalid: max is 15 for point buy
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['strength']);
    }

    #[Test]
    public function it_rejects_point_buy_with_missing_scores(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 15,
            'dexterity' => 14,
            // Missing other 4 scores
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ability_scores']);
    }

    // =====================
    // Standard Array Tests
    // =====================

    #[Test]
    public function it_accepts_valid_standard_array_scores(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'standard_array',
            'strength' => 8,
            'dexterity' => 10,
            'constitution' => 12,
            'intelligence' => 13,
            'wisdom' => 14,
            'charisma' => 15,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_score_method', 'standard_array')
            ->assertJsonPath('data.ability_scores.STR', 8)
            ->assertJsonPath('data.ability_scores.CHA', 15);
    }

    #[Test]
    public function it_rejects_standard_array_with_wrong_values(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'standard_array',
            'strength' => 16, // Not in standard array
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ability_scores']);
    }

    #[Test]
    public function it_rejects_standard_array_with_duplicate_values(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'standard_array',
            'strength' => 15,
            'dexterity' => 15, // Duplicate
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ability_scores']);
    }

    #[Test]
    public function it_rejects_standard_array_with_missing_scores(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'standard_array',
            'strength' => 15,
            'dexterity' => 14,
            // Missing other 4 scores
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ability_scores']);
    }

    // =====================
    // Manual Mode Tests
    // =====================

    #[Test]
    public function it_allows_manual_scores_with_partial_update(): void
    {
        $character = Character::factory()->create(['strength' => 10]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'manual',
            'strength' => 18, // Only updating one score
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR', 18);
    }

    #[Test]
    public function it_allows_manual_scores_in_full_range(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'manual',
            'strength' => 3,  // Min allowed for manual
            'dexterity' => 20, // Max allowed for manual
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR', 3)
            ->assertJsonPath('data.ability_scores.DEX', 20);
    }

    #[Test]
    public function it_defaults_to_manual_when_method_not_specified(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'strength' => 18,
        ]);

        $response->assertOk();

        $character->refresh();
        $this->assertEquals(AbilityScoreMethod::Manual, $character->ability_score_method);
    }

    #[Test]
    public function it_allows_updating_without_method_when_already_set(): void
    {
        $character = Character::factory()->create([
            'ability_score_method' => AbilityScoreMethod::Manual,
            'strength' => 15,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.ability_score_method', 'manual');
    }

    // =====================
    // Method Switching Tests
    // =====================

    #[Test]
    public function it_rejects_switching_to_point_buy_without_providing_scores(): void
    {
        $character = Character::factory()->create([
            'ability_score_method' => AbilityScoreMethod::Manual,
            'strength' => 18, // Outside point buy range
            'dexterity' => 16,
            'constitution' => 14,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        // Try to switch to point_buy without providing new scores
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
        ]);

        // Should fail because all 6 scores are required for point_buy
        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_switching_to_standard_array_without_providing_scores(): void
    {
        $character = Character::factory()->create([
            'ability_score_method' => AbilityScoreMethod::Manual,
            'strength' => 18,
            'dexterity' => 16,
            'constitution' => 14,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        // Try to switch to standard_array without providing new scores
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'standard_array',
        ]);

        // Should fail because all 6 scores are required for standard_array
        $response->assertUnprocessable();
    }

    #[Test]
    public function it_allows_switching_from_point_buy_to_manual(): void
    {
        $character = Character::factory()->create([
            'ability_score_method' => AbilityScoreMethod::PointBuy,
            'strength' => 15,
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'manual',
            'strength' => 18, // Now allowed since we're in manual mode
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_score_method', 'manual')
            ->assertJsonPath('data.ability_scores.STR', 18);
    }

    #[Test]
    public function it_allows_switching_from_manual_to_point_buy(): void
    {
        $character = Character::factory()->create([
            'ability_score_method' => AbilityScoreMethod::Manual,
            'strength' => 18,
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'ability_score_method' => 'point_buy',
            'strength' => 15,
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 12,
            'wisdom' => 10,
            'charisma' => 8,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_score_method', 'point_buy')
            ->assertJsonPath('data.ability_scores.STR', 15);
    }

    // =====================
    // Resource Response Tests
    // =====================

    #[Test]
    public function it_includes_ability_score_method_in_show_response(): void
    {
        $character = Character::factory()->create([
            'ability_score_method' => AbilityScoreMethod::PointBuy,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.ability_score_method', 'point_buy');
    }

    #[Test]
    public function it_includes_ability_score_method_in_index_response(): void
    {
        Character::factory()->create([
            'ability_score_method' => AbilityScoreMethod::StandardArray,
        ]);

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonPath('data.0.ability_score_method', 'standard_array');
    }
}
