<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbilityScoreIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_paginates_ability_scores()
    {
        // Ability scores are seeded by default (6 scores: STR, DEX, CON, INT, WIS, CHA)
        // Request with per_page
        $response = $this->getJson('/api/v1/ability-scores?per_page=3');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name'],
                ],
                'links',
                'meta',
            ]);

        // Verify pagination is working
        $this->assertLessThanOrEqual(3, count($response->json('data')));
    }

    #[Test]
    public function it_searches_ability_scores_by_name()
    {
        // Use seeded data - ability scores include Strength, Dexterity, Constitution, etc.
        // Search for 'Strength'
        $response = $this->getJson('/api/v1/ability-scores?search=Strength');
        $response->assertStatus(200);

        // Should find Strength ability score
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $this->assertEquals('Strength', $data[0]['name']);
    }

    #[Test]
    public function it_searches_ability_scores_by_code()
    {
        // Use seeded data - ability scores include STR, DEX, CON, etc.
        // Search for 'DEX'
        $response = $this->getJson('/api/v1/ability-scores?search=DEX');
        $response->assertStatus(200);

        // Should find Dexterity by code
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $this->assertEquals('DEX', $data[0]['code']);
    }

    #[Test]
    public function it_searches_ability_scores_by_partial_match()
    {
        // Use seeded data
        // Search for 'str' (partial code match)
        $response = $this->getJson('/api/v1/ability-scores?search=str');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $this->assertEquals('STR', $data[0]['code']);

        // Search for 'Con' (partial name/code match)
        $response = $this->getJson('/api/v1/ability-scores?search=Con');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $this->assertEquals('CON', $data[0]['code']);
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
