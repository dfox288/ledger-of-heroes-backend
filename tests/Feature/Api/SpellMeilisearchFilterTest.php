<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-imported')]
class SpellMeilisearchFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests use the pre-populated test Meilisearch index (test_spells)
        // which is populated via: docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:all --env=testing
        // No need to import spells on every test - just ensure indexes are configured
        $this->artisan('search:configure-indexes');
    }

    #[Test]
    public function it_filters_spells_by_level_range_with_and()
    {
        $response = $this->getJson('/api/v1/spells?filter=level >= 1 AND level <= 3');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find spells level 1-3');

        foreach ($response->json('data') as $spell) {
            $this->assertGreaterThanOrEqual(1, $spell['level']);
            $this->assertLessThanOrEqual(3, $spell['level']);
        }
    }

    #[Test]
    public function it_filters_by_level_range_using_to_operator()
    {
        $response = $this->getJson('/api/v1/spells?filter=level 1 TO 3');

        $response->assertOk();

        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find spells level 1-3');

        foreach ($response->json('data') as $spell) {
            $this->assertGreaterThanOrEqual(1, $spell['level']);
            $this->assertLessThanOrEqual(3, $spell['level']);
        }
    }

    #[Test]
    public function it_filters_by_school_code()
    {
        $response = $this->getJson('/api/v1/spells?filter=school_code = EV');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                $this->assertEquals('Evocation', $spell['school']['name']);
            }
        } else {
            $this->markTestSkipped('No Evocation spells in test data');
        }
    }

    #[Test]
    public function it_combines_multiple_filters_with_and()
    {
        $response = $this->getJson('/api/v1/spells?filter=level <= 2 AND concentration = false');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertLessThanOrEqual(2, $spell['level']);
            $this->assertFalse($spell['needs_concentration']);
        }
    }

    #[Test]
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

    #[Test]
    public function it_filters_by_concentration()
    {
        $response = $this->getJson('/api/v1/spells?filter=concentration = true');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertTrue($spell['needs_concentration']);
        }
    }

    #[Test]
    public function it_filters_by_ritual()
    {
        $response = $this->getJson('/api/v1/spells?filter=ritual = true');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertTrue($spell['is_ritual']);
        }
    }

    #[Test]
    public function it_combines_search_query_with_filter()
    {
        $response = $this->getJson('/api/v1/spells?q=fire&filter=level <= 3');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertLessThanOrEqual(3, $spell['level']);
            // Name or description should contain "fire"
            $nameOrDesc = strtolower($spell['name'].' '.$spell['description']);
            $this->assertStringContainsString('fire', $nameOrDesc);
        }
    }

    #[Test]
    public function it_validates_filter_max_length()
    {
        $longFilter = str_repeat('level = 1 AND ', 100);

        $response = $this->getJson('/api/v1/spells?filter='.urlencode($longFilter));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('filter');
    }

    #[Test]
    public function it_returns_error_for_invalid_filter_syntax()
    {
        // Use a clearly invalid filter that Meilisearch will reject
        $response = $this->getJson('/api/v1/spells?filter=invalid_field = value');

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'error']);
    }

    #[Test]
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

    #[Test]
    public function it_returns_empty_results_for_impossible_filter()
    {
        $response = $this->getJson('/api/v1/spells?filter=level = 99');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function it_sorts_filtered_results()
    {
        $response = $this->getJson('/api/v1/spells?filter=level <= 3&sort_by=level&sort_direction=desc');

        $response->assertOk();

        $levels = collect($response->json('data'))->pluck('level');

        // Should be in descending order
        $sorted = $levels->sortDesc()->values();
        $this->assertEquals($sorted->toArray(), $levels->toArray());
    }
}
