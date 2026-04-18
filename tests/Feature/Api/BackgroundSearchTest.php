<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[Group('feature-search')]
class BackgroundSearchTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected $seeder = TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[Test]
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

    #[Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/backgrounds?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        // Use fixture data - returns paginated results (default 15 per page)
        $response = $this->getJson('/api/v1/backgrounds');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', Background::count());
    }
}
