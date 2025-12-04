<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
/**
 * Tests for spell search functionality and filter integration.
 *
 * Covers:
 * - Basic search (q parameter)
 * - Search validation
 * - Combined search + filter operations
 * - Filter validation and error handling
 * - Pagination and sorting of filtered/searched results
 * - Empty result handling
 * - Boolean logic (AND/OR combinations)
 */
class SpellSearchAndFilterTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests use the pre-populated test Meilisearch index (test_spells)
        // which is populated via: docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:all --env=testing
        $this->artisan('search:configure-indexes');
    }

    // ===================================================================
    // SEARCH FUNCTIONALITY TESTS
    // ===================================================================

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

    // ===================================================================
    // COMBINED SEARCH + FILTER TESTS
    // ===================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_search_query_with_filter()
    {
        // Search for "fire" and filter to level <= 3
        $response = $this->getJson('/api/v1/spells?q=fire&filter=level <= 3');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertLessThanOrEqual(3, $spell['level']);
            // Name or description should contain "fire"
            $nameOrDesc = strtolower($spell['name'].' '.$spell['description']);
            $this->assertStringContainsString('fire', $nameOrDesc);
        }
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

    // ===================================================================
    // BOOLEAN LOGIC (AND/OR) TESTS
    // ===================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_multiple_filters_with_and()
    {
        $response = $this->getJson('/api/v1/spells?filter=level <= 2 AND concentration = false');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertLessThanOrEqual(2, $spell['level']);
            $this->assertFalse($spell['needs_concentration']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_multiple_filters_with_or()
    {
        $response = $this->getJson('/api/v1/spells?filter=school_code = EV OR school_code = C');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                $this->assertContains($spell['school']['name'], ['Evocation', 'Conjuration']);
            }
        }
    }

    // ===================================================================
    // FILTER VALIDATION AND ERROR HANDLING TESTS
    // ===================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_filter_max_length()
    {
        $longFilter = str_repeat('level = 1 AND ', 100);

        $response = $this->getJson('/api/v1/spells?filter='.urlencode($longFilter));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('filter');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_422_for_invalid_meilisearch_filter(): void
    {
        // This test assumes Meilisearch is running and will reject invalid filters
        $response = $this->getJson('/api/v1/spells?filter=nonexistent_field = value');

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'error',
            'filter',
            'documentation',
        ]);
        $response->assertJson([
            'message' => 'Invalid filter syntax',
            'filter' => 'nonexistent_field = value',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_documentation_link_in_error_response(): void
    {
        $response = $this->getJson('/api/v1/spells?filter=invalid_syntax');

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('documentation', $data);
        $this->assertStringContainsString('docs/meilisearch-filters', $data['documentation']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_meilisearch_error_message(): void
    {
        $response = $this->getJson('/api/v1/spells?filter=nonexistent_field = value');

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('error', $data);
        $this->assertNotEmpty($data['error']);
    }

    // ===================================================================
    // PAGINATION TESTS
    // ===================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_paginates_filtered_results()
    {
        $response = $this->getJson('/api/v1/spells?filter=level <= 5&per_page=5&page=1');

        $response->assertOk();
        $this->assertLessThanOrEqual(5, count($response->json('data')));
        $response->assertJsonStructure([
            'meta' => ['current_page', 'total', 'per_page'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
    }

    // ===================================================================
    // SORTING TESTS
    // ===================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sorts_filtered_results()
    {
        $response = $this->getJson('/api/v1/spells?filter=level <= 3&sort_by=level&sort_direction=desc');

        $response->assertOk();

        $levels = collect($response->json('data'))->pluck('level');

        // Should be in descending order
        $sorted = $levels->sortDesc()->values();
        $this->assertEquals($sorted->toArray(), $levels->toArray());
    }

    // ===================================================================
    // EMPTY RESULTS TESTS
    // ===================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_results_for_impossible_filter()
    {
        $response = $this->getJson('/api/v1/spells?filter=level = 99');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }
}
