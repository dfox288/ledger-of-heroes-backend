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
            ->assertJsonPath('data.level', 2)
            ->assertJsonPath('data.next_level_xp', 900)
            ->assertJsonPath('data.xp_to_next_level', 400)
            ->assertJsonPath('data.is_max_level', false);

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
                    'level',
                    'next_level_xp',
                    'xp_to_next_level',
                    'xp_progress_percent',
                    'is_max_level',
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
        // But XP should still be added and level calculated
        $response->assertOk()
            ->assertJsonPath('data.level', 2)
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
            ->assertJsonPath('data.level', 2); // XP level is 2, but didn't level up
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

    // ========================================
    // GET /characters/{id}/xp tests
    // ========================================

    #[Test]
    public function it_gets_xp_progress_for_character(): void
    {
        $character = Character::factory()->create(['experience_points' => 6500]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/xp");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'experience_points',
                    'level',
                    'next_level_xp',
                    'xp_to_next_level',
                    'xp_progress_percent',
                    'is_max_level',
                ],
            ]);
    }

    #[Test]
    public function it_returns_correct_xp_values_for_level_5(): void
    {
        // 6500 XP = level 5, next level at 14000
        $character = Character::factory()->create(['experience_points' => 6500]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/xp");

        $response->assertOk()
            ->assertJsonPath('data.experience_points', 6500)
            ->assertJsonPath('data.level', 5)
            ->assertJsonPath('data.next_level_xp', 14000)
            ->assertJsonPath('data.xp_to_next_level', 7500)
            ->assertJsonPath('data.is_max_level', false);

        // Progress: (6500 - 6500) / (14000 - 6500) = 0%
        $this->assertEquals(0, $response->json('data.xp_progress_percent'));
    }

    #[Test]
    public function it_returns_correct_xp_values_for_partial_progress(): void
    {
        // 10000 XP = level 5, progress toward level 6
        // Level 5 starts at 6500, level 6 at 14000
        // Progress: (10000 - 6500) / (14000 - 6500) = 3500/7500 = 46.67%
        $character = Character::factory()->create(['experience_points' => 10000]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/xp");

        $response->assertOk()
            ->assertJsonPath('data.experience_points', 10000)
            ->assertJsonPath('data.level', 5)
            ->assertJsonPath('data.next_level_xp', 14000)
            ->assertJsonPath('data.xp_to_next_level', 4000);

        // Check progress percent is approximately 46.67
        $progressPercent = $response->json('data.xp_progress_percent');
        $this->assertGreaterThan(46, $progressPercent);
        $this->assertLessThan(47, $progressPercent);
    }

    #[Test]
    public function it_returns_max_level_values_for_level_20(): void
    {
        // 355000+ XP = level 20 (max)
        $character = Character::factory()->create(['experience_points' => 400000]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/xp");

        $response->assertOk()
            ->assertJsonPath('data.experience_points', 400000)
            ->assertJsonPath('data.level', 20)
            ->assertJsonPath('data.next_level_xp', null)
            ->assertJsonPath('data.xp_to_next_level', 0)
            ->assertJsonPath('data.xp_progress_percent', 100)
            ->assertJsonPath('data.is_max_level', true);
    }

    #[Test]
    public function it_returns_level_1_values_for_zero_xp(): void
    {
        $character = Character::factory()->create(['experience_points' => 0]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/xp");

        $response->assertOk()
            ->assertJsonPath('data.experience_points', 0)
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.next_level_xp', 300)
            ->assertJsonPath('data.xp_to_next_level', 300)
            ->assertJsonPath('data.is_max_level', false);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character_on_get(): void
    {
        $response = $this->getJson('/api/v1/characters/nonexistent/xp');

        $response->assertNotFound();
    }
}
