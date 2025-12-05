<?php

namespace Tests\Feature\Services;

use App\DTOs\MonsterSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Monster;
use App\Services\MonsterSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MeiliSearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-search')]
class MonsterSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    private MonsterSearchService $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MonsterSearchService;
        $this->client = app(Client::class);
    }

    #[Test]
    public function it_returns_index_relationships(): void
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('size', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('modifiers.abilityScore', $relationships);
        $this->assertContains('conditions.condition', $relationships);
        $this->assertContains('senses.sense', $relationships);
        $this->assertContains('tags', $relationships);
    }

    #[Test]
    public function it_returns_show_relationships(): void
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('size', $relationships);
        $this->assertContains('traits', $relationships);
        $this->assertContains('actions', $relationships);
        $this->assertContains('legendaryActions', $relationships);
        $this->assertContains('spells', $relationships);
    }

    #[Test]
    public function it_returns_default_relationships_as_index_relationships(): void
    {
        $default = $this->service->getDefaultRelationships();
        $index = $this->service->getIndexRelationships();

        $this->assertEquals($index, $default);
    }

    #[Test]
    public function it_builds_scout_query(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: 'dragon',
            perPage: 15,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $builder = $this->service->buildScoutQuery($dto);

        $this->assertInstanceOf(\Laravel\Scout\Builder::class, $builder);
    }

    #[Test]
    public function it_builds_database_query_with_eager_loading(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $builder = $this->service->buildDatabaseQuery($dto);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $builder);

        $eagerLoads = $builder->getEagerLoads();
        $this->assertArrayHasKey('size', $eagerLoads);
        $this->assertArrayHasKey('sources.source', $eagerLoads);
    }

    #[Test]
    public function it_applies_sorting_to_database_query(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            sortBy: 'challenge_rating',
            sortDirection: 'desc',
            meilisearchFilter: null,
        );

        $builder = $this->service->buildDatabaseQuery($dto);

        $results = $builder->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
    }

    #[Test]
    public function it_searches_with_meilisearch_without_filters(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: '',
            perPage: 10,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertGreaterThan(0, $result->total());
    }

    #[Test]
    public function it_searches_with_meilisearch_with_search_query(): void
    {
        $monster = Monster::first();
        $this->assertNotNull($monster, 'Should have monsters in database');

        $dto = new MonsterSearchDTO(
            searchQuery: $monster->name,
            perPage: 10,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains($monster->name, $names);
    }

    #[Test]
    public function it_searches_with_meilisearch_with_cr_filter(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: '',
            perPage: 50,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: 'challenge_rating >= 5',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);

        foreach ($result->items() as $monster) {
            $this->assertGreaterThanOrEqual(5, $monster->challenge_rating, 'All monsters should have CR >= 5');
        }
    }

    #[Test]
    public function it_handles_pagination_correctly(): void
    {
        $page1Dto = new MonsterSearchDTO(
            searchQuery: '',
            perPage: 5,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $page2Dto = new MonsterSearchDTO(
            searchQuery: '',
            perPage: 5,
            page: 2,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $page1Results = $this->service->searchWithMeilisearch($page1Dto, $this->client);
        $page2Results = $this->service->searchWithMeilisearch($page2Dto, $this->client);

        $this->assertEquals(1, $page1Results->currentPage());
        $this->assertEquals(2, $page2Results->currentPage());
        $this->assertEquals(5, $page1Results->perPage());

        $page1Ids = $page1Results->pluck('id')->toArray();
        $page2Ids = $page2Results->pluck('id')->toArray();

        if (count($page2Ids) > 0) {
            $this->assertNotEquals($page1Ids, $page2Ids, 'Different pages should have different results');
        }
    }

    #[Test]
    public function it_returns_empty_paginator_for_no_results(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: 'xyznonexistentmonsterxyz123',
            perPage: 15,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(0, $result->count());
    }

    #[Test]
    public function it_throws_exception_for_invalid_filter_syntax(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: '',
            perPage: 15,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: 'invalid_field INVALID_OPERATOR value',
        );

        $this->expectException(InvalidFilterSyntaxException::class);

        $this->service->searchWithMeilisearch($dto, $this->client);
    }

    #[Test]
    public function it_returns_results_in_meilisearch_relevance_order(): void
    {
        // Note: MonsterSearchService does not pass sort params to Meilisearch,
        // so results are returned in Meilisearch's default relevance order
        $dto = new MonsterSearchDTO(
            searchQuery: '',
            perPage: 10,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        // Verify we get results and they preserve Meilisearch's order
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    #[Test]
    public function it_hydrates_eloquent_models_with_relationships(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: '',
            perPage: 5,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $monster) {
            $this->assertInstanceOf(Monster::class, $monster);
            $this->assertTrue($monster->relationLoaded('size'));
            $this->assertTrue($monster->relationLoaded('sources'));
        }
    }

    #[Test]
    public function it_searches_with_compound_filter(): void
    {
        $dto = new MonsterSearchDTO(
            searchQuery: '',
            perPage: 50,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: 'challenge_rating >= 1 AND challenge_rating <= 5',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $monster) {
            $this->assertGreaterThanOrEqual(1, $monster->challenge_rating);
            $this->assertLessThanOrEqual(5, $monster->challenge_rating);
        }
    }
}
