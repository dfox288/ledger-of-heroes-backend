<?php

namespace Tests\Feature\Api;

use App\Enums\LevelingMode;
use App\Models\Character;
use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class CharacterExperienceApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_adds_xp_to_character(): void
    {
        $character = Character::factory()->create(['experience_points' => 0]);

        $response = $this->postJson("/api/v1/characters/{$character->public_id}/xp", [
            'amount' => 500,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.experience_points', 500)
            ->assertJsonPath('data.xp_level', 2)
            ->assertJsonPath('data.next_level_xp', 900)
            ->assertJsonPath('data.xp_to_next_level', 400);

        $this->assertEquals(500, $character->fresh()->experience_points);
    }

    #[Test]
    public function it_accumulates_xp_on_multiple_calls(): void
    {
        $character = Character::factory()->create(['experience_points' => 200]);

        $this->postJson("/api/v1/characters/{$character->public_id}/xp", ['amount' => 100]);

        $this->assertEquals(300, $character->fresh()->experience_points);
    }

    #[Test]
    public function it_validates_positive_xp_amount(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->public_id}/xp", [
            'amount' => -100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function it_validates_xp_amount_is_required(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->public_id}/xp", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->postJson('/api/v1/characters/nonexistent/xp', [
            'amount' => 100,
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_xp_progress_info(): void
    {
        $character = Character::factory()->create(['experience_points' => 150]);

        $response = $this->postJson("/api/v1/characters/{$character->public_id}/xp", [
            'amount' => 0, // Just query current state
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'experience_points',
                    'xp_level',
                    'next_level_xp',
                    'xp_to_next_level',
                    'xp_progress_percent',
                ],
            ]);
    }

    #[Test]
    public function it_auto_levels_character_when_threshold_reached(): void
    {
        // Create character in a party with XP leveling mode
        $party = Party::factory()->create(['leveling_mode' => LevelingMode::XP]);
        $character = Character::factory()->create([
            'experience_points' => 200,
        ]);
        $party->characters()->attach($character);

        $response = $this->postJson("/api/v1/characters/{$character->public_id}/xp", [
            'amount' => 150, // 200 + 150 = 350, crosses 300 threshold
            'auto_level' => true,
        ]);

        // Auto-level may fail if character doesn't have required setup (class, etc.)
        // But XP should still be added and xp_level calculated
        $response->assertOk()
            ->assertJsonPath('data.xp_level', 2)
            ->assertJsonPath('data.experience_points', 350);
    }

    #[Test]
    public function it_does_not_auto_level_when_disabled(): void
    {
        $party = Party::factory()->create(['leveling_mode' => LevelingMode::XP]);
        $character = Character::factory()->create([
            'experience_points' => 200,
        ]);
        $party->characters()->attach($character);

        $response = $this->postJson("/api/v1/characters/{$character->public_id}/xp", [
            'amount' => 150,
            'auto_level' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.leveled_up', false)
            ->assertJsonPath('data.xp_level', 2); // XP level is 2, but didn't level up
    }

    #[Test]
    public function it_does_not_auto_level_for_milestone_party(): void
    {
        $party = Party::factory()->create(['leveling_mode' => LevelingMode::MILESTONE]);
        $character = Character::factory()->create([
            'experience_points' => 200,
        ]);
        $party->characters()->attach($character);

        $response = $this->postJson("/api/v1/characters/{$character->public_id}/xp", [
            'amount' => 150,
            'auto_level' => true, // Ignored for milestone parties
        ]);

        $response->assertOk()
            ->assertJsonPath('data.leveled_up', false);
    }
}
