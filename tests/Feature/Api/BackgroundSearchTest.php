<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class BackgroundSearchTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_backgrounds_using_scout_when_available(): void
    {
        // Use fixture data - Acolyte and Soldier exist in TestDatabaseSeeder
        $response = $this->getJson('/api/v1/backgrounds?q=acolyte');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'slug']],
                'meta',
            ])
            ->assertJsonPath('data.0.name', 'Acolyte');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/backgrounds?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        // Use fixture data - returns paginated results (default 15 per page)
        $response = $this->getJson('/api/v1/backgrounds');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', Background::count());
    }
}
