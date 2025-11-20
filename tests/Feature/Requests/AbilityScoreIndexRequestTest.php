<?php

namespace Tests\Feature\Requests;

use App\Models\AbilityScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbilityScoreIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_paginates_ability_scores()
    {
        AbilityScore::factory()->count(10)->create();

        // Request with per_page
        $response = $this->getJson('/api/v1/ability-scores?per_page=5');
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name'],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_searches_ability_scores_by_name()
    {
        AbilityScore::factory()->create(['name' => 'Strength', 'code' => 'STR']);
        AbilityScore::factory()->create(['name' => 'Dexterity', 'code' => 'DEX']);
        AbilityScore::factory()->create(['name' => 'Constitution', 'code' => 'CON']);

        // Search for 'Strength'
        $response = $this->getJson('/api/v1/ability-scores?search=Strength');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Strength']);
    }

    #[Test]
    public function it_searches_ability_scores_by_code()
    {
        AbilityScore::factory()->create(['name' => 'Strength', 'code' => 'STR']);
        AbilityScore::factory()->create(['name' => 'Dexterity', 'code' => 'DEX']);
        AbilityScore::factory()->create(['name' => 'Constitution', 'code' => 'CON']);

        // Search for 'DEX'
        $response = $this->getJson('/api/v1/ability-scores?search=DEX');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['code' => 'DEX']);
    }

    #[Test]
    public function it_searches_ability_scores_by_partial_match()
    {
        AbilityScore::factory()->create(['name' => 'Strength', 'code' => 'STR']);
        AbilityScore::factory()->create(['name' => 'Dexterity', 'code' => 'DEX']);
        AbilityScore::factory()->create(['name' => 'Constitution', 'code' => 'CON']);

        // Search for 'str' (partial code match)
        $response = $this->getJson('/api/v1/ability-scores?search=str');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['code' => 'STR']);

        // Search for 'Con' (partial name/code match)
        $response = $this->getJson('/api/v1/ability-scores?search=Con');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['code' => 'CON']);
    }

    #[Test]
    public function it_validates_per_page_maximum()
    {
        // Valid: 50
        $response = $this->getJson('/api/v1/ability-scores?per_page=50');
        $response->assertStatus(200);

        // Invalid: 101 (exceeds max of 100)
        $response = $this->getJson('/api/v1/ability-scores?per_page=101');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_validates_search_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/ability-scores?search={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/ability-scores?search={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    #[Test]
    public function it_validates_page_is_positive_integer()
    {
        // Valid: 1
        $response = $this->getJson('/api/v1/ability-scores?page=1');
        $response->assertStatus(200);

        // Invalid: 0
        $response = $this->getJson('/api/v1/ability-scores?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Invalid: -1
        $response = $this->getJson('/api/v1/ability-scores?page=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }
}
