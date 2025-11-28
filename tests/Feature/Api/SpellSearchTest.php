<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class SpellSearchTest extends TestCase
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
    public function it_searches_spells_using_scout_when_available(): void
    {
        // Use fixture data - "Acid Splash" exists in TestDatabaseSeeder
        $response = $this->getJson('/api/v1/spells?q=acid');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'description']],
                'meta',
            ]);

        // Verify Acid Splash is in the search results
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Acid Splash', $names, 'Acid Splash should be in search results');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_meilisearch_results_by_level_with_search(): void
    {
        // Use fixture data - search for "animate" with level 3 filter
        // Animate Dead is a level 3 spell in fixtures
        $response = $this->getJson('/api/v1/spells?q=animate&filter=level = 3');

        $response->assertOk();

        // All results should be level 3
        foreach ($response->json('data') as $spell) {
            $this->assertEquals(3, $spell['level']);
        }

        // Animate Dead should be in results
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Animate Dead', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        // Use fixture data - returns paginated results (default 15 per page)
        $response = $this->getJson('/api/v1/spells');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', Spell::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_special_characters_in_search(): void
    {
        // Use fixture data - Bigby's Hand exists in fixtures
        $response = $this->getJson('/api/v1/spells?q='.urlencode("Bigby's"));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/spells?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }
}
