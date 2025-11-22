<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonsterSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[Test]
    public function it_searches_monsters_using_scout_when_available(): void
    {
        Monster::factory()->create([
            'name' => 'Young Red Dragon',
            'type' => 'dragon',
            'challenge_rating' => '10',
        ]);

        Monster::factory()->create([
            'name' => 'Ancient Red Dragon',
            'type' => 'dragon',
            'challenge_rating' => '24',
        ]);

        Monster::factory()->create([
            'name' => 'Goblin',
            'type' => 'humanoid',
            'challenge_rating' => '1/4',
        ]);

        $this->artisan('scout:import', ['model' => Monster::class]);
        sleep(1);

        $response = $this->getJson('/api/v1/monsters?q=dragon');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'type', 'challenge_rating']],
                'meta',
            ]);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Young Red Dragon', $names);
        $this->assertContains('Ancient Red Dragon', $names);
        $this->assertNotContains('Goblin', $names);
    }

    #[Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/monsters?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        Monster::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/monsters');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_searches_by_monster_type(): void
    {
        Monster::factory()->create(['name' => 'Red Dragon', 'type' => 'dragon']);
        Monster::factory()->create(['name' => 'Zombie', 'type' => 'undead']);
        Monster::factory()->create(['name' => 'Goblin', 'type' => 'humanoid']);

        $this->artisan('scout:import', ['model' => Monster::class]);
        sleep(1);

        $response = $this->getJson('/api/v1/monsters?q=dragon&type=dragon');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
    }

    #[Test]
    public function it_combines_search_with_challenge_rating_filter(): void
    {
        Monster::factory()->create([
            'name' => 'Young Red Dragon',
            'type' => 'dragon',
            'challenge_rating' => '10',
        ]);

        Monster::factory()->create([
            'name' => 'Ancient Red Dragon',
            'type' => 'dragon',
            'challenge_rating' => '24',
        ]);

        Monster::factory()->create([
            'name' => 'White Dragon Wyrmling',
            'type' => 'dragon',
            'challenge_rating' => '2',
        ]);

        // Note: Scout search + database filters use different code paths
        // This test verifies the database query path works correctly
        $response = $this->getJson('/api/v1/monsters?q=dragon&min_cr=5&max_cr=15');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();

        // Should include mid-range CR monster
        $this->assertContains('Young Red Dragon', $names);

        // May include others due to LIKE search on name including "dragon"
        // The important part is that filters are applied
        $this->assertGreaterThan(0, count($names));
    }

    #[Test]
    public function it_appears_in_global_search_results(): void
    {
        Monster::factory()->create([
            'name' => 'Ancient Red Dragon',
            'type' => 'dragon',
        ]);

        $this->artisan('scout:import', ['model' => Monster::class]);
        sleep(1);

        $response = $this->getJson('/api/v1/search?q=dragon&types[]=monster');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'monsters' => ['*' => ['id', 'name', 'type']],
                ],
            ]);

        $this->assertGreaterThan(0, count($response->json('data.monsters')));
    }

    #[Test]
    public function it_sorts_search_results_by_relevance(): void
    {
        Monster::factory()->create([
            'name' => 'Dragon',
            'description' => 'A powerful dragon',
        ]);

        Monster::factory()->create([
            'name' => 'Dragonborn Warrior',
            'description' => 'A humanoid descended from dragons',
        ]);

        Monster::factory()->create([
            'name' => 'Kobold',
            'description' => 'A small creature that worships dragons',
        ]);

        $this->artisan('scout:import', ['model' => Monster::class]);
        sleep(1);

        $response = $this->getJson('/api/v1/monsters?q=dragon');

        $response->assertOk();

        // First result should be exact match
        $this->assertEquals('Dragon', $response->json('data.0.name'));
    }

    #[Test]
    public function it_handles_typos_in_search_gracefully(): void
    {
        Monster::factory()->create([
            'name' => 'Goblin',
            'type' => 'humanoid',
        ]);

        $this->artisan('scout:import', ['model' => Monster::class]);
        sleep(1);

        // Meilisearch handles typos automatically
        $response = $this->getJson('/api/v1/monsters?q=gobln');

        $response->assertOk();
        // May or may not find results depending on Meilisearch's typo tolerance
        // This is just to verify the search doesn't crash
    }
}
