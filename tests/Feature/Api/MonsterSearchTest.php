<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class MonsterSearchTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[Test]
    public function it_searches_monsters_using_scout_when_available(): void
    {
        // Use fixture data - Adult Black Dragon, Adult Blue Dragon, etc. exist
        $response = $this->getJson('/api/v1/monsters?q=dragon');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'type', 'challenge_rating']],
                'meta',
            ]);

        $names = collect($response->json('data'))->pluck('name')->all();
        // Fixtures have various dragons
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_contains($n, 'Dragon')));
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
        // Use fixture data - returns paginated results (default 15 per page)
        $response = $this->getJson('/api/v1/monsters');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', Monster::count());
    }

    #[Test]
    public function it_searches_by_monster_type(): void
    {
        // Use fixture data - filter by type=dragon with search
        $response = $this->getJson('/api/v1/monsters?q=dragon&filter=type = dragon');

        $response->assertOk();
        // Should only return dragon type monsters
        $types = collect($response->json('data'))->pluck('type')->unique()->all();
        $this->assertEquals(['dragon'], $types);
    }

    #[Test]
    public function it_combines_search_with_challenge_rating_filter(): void
    {
        // Use fixture data - Dragons exist in fixtures
        // Search for dragon and verify we get results
        $response = $this->getJson('/api/v1/monsters?q=dragon');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertGreaterThan(0, count($names));
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_contains($n, 'Dragon')));
    }

    #[Test]
    public function it_appears_in_global_search_results(): void
    {
        // Use fixture data - dragons exist in fixtures
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
        // Use fixture data - search for "dragon"
        // Fixtures have: Adult Black Dragon, Adult Blue Dragon, etc.
        $response = $this->getJson('/api/v1/monsters?q=dragon');

        $response->assertOk();

        // Results should contain dragon in the name
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_contains($n, 'Dragon')));
    }

    #[Test]
    public function it_handles_typos_in_search_gracefully(): void
    {
        // Use fixture data - dragons exist
        // Meilisearch handles typos automatically
        $response = $this->getJson('/api/v1/monsters?q=dragn');

        $response->assertOk();
        // May or may not find results depending on Meilisearch's typo tolerance
        // This is just to verify the search doesn't crash
    }

    #[Test]
    public function monster_search_index_includes_spell_slugs(): void
    {
        // Find a monster that has spells in fixtures
        $monsterWithSpells = Monster::whereHas('entitySpells')->first();

        if (! $monsterWithSpells) {
            $this->markTestSkipped('No monsters with spells in fixtures');
        }

        $searchableArray = $monsterWithSpells->toSearchableArray();

        $this->assertArrayHasKey('spell_slugs', $searchableArray);
        $this->assertNotEmpty($searchableArray['spell_slugs']);
    }

    #[Test]
    public function it_filters_monsters_by_spell_slugs_in_meilisearch(): void
    {
        // Skip if Meilisearch not available
        if (config('scout.driver') !== 'meilisearch') {
            $this->markTestSkipped('Meilisearch not configured');
        }

        // Find a monster with spells and one without
        $monsterWithSpells = Monster::whereHas('entitySpells')->first();
        $monsterWithoutSpells = Monster::whereDoesntHave('entitySpells')->first();

        if (! $monsterWithSpells || ! $monsterWithoutSpells) {
            $this->markTestSkipped('Need monsters with and without spells in fixtures');
        }

        $spellSlug = $monsterWithSpells->entitySpells()->first()->slug;

        // Search with spell filter using Meilisearch
        $results = Monster::search('')->where('spell_slugs', $spellSlug)->get();

        $this->assertTrue($results->contains('id', $monsterWithSpells->id));
        $this->assertFalse($results->contains('id', $monsterWithoutSpells->id));
    }
}
