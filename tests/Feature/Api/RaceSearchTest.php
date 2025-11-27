<?php

namespace Tests\Feature\Api;

use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class RaceSearchTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_races_using_scout_when_available(): void
    {
        Race::factory()->create(['name' => 'Dwarf']);
        Race::factory()->create(['name' => 'Elf']);

        $this->artisan('scout:import', ['model' => Race::class]);
        $this->waitForMeilisearchIndex('test_races');

        $response = $this->getJson('/api/v1/races?q=dwarf');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'slug']],
                'meta',
            ])
            ->assertJsonPath('data.0.name', 'Dwarf');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/races?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        Race::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/races');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }
}
