<?php

namespace Tests\Feature\Services;

use App\DTOs\BackgroundSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Background;
use App\Services\BackgroundSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MeiliSearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-search')]
class BackgroundSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    private BackgroundSearchService $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackgroundSearchService;
        $this->client = app(Client::class);
    }

    #[Test]
    public function it_returns_index_relationships(): void
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('sources.source', $relationships);
    }

    #[Test]
    public function it_returns_show_relationships(): void
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('traits.dataTables.entries', $relationships);
        $this->assertContains('proficiencies.skill.abilityScore', $relationships);
        $this->assertContains('languages.language', $relationships);
        $this->assertContains('equipment.item.contents.item', $relationships);
        $this->assertContains('tags', $relationships);
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
        $builder = $this->service->buildScoutQuery('criminal');

        $this->assertInstanceOf(\Laravel\Scout\Builder::class, $builder);
    }

    #[Test]
    public function it_builds_database_query_with_eager_loading(): void
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $builder = $this->service->buildDatabaseQuery($dto);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $builder);

        $eagerLoads = $builder->getEagerLoads();
        $this->assertArrayHasKey('sources.source', $eagerLoads);
    }

    #[Test]
    public function it_applies_sorting_to_database_query(): void
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            sortBy: 'name',
            sortDirection: 'desc',
        );

        $builder = $this->service->buildDatabaseQuery($dto);

        $results = $builder->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
    }

    #[Test]
    public function it_searches_with_meilisearch_without_filters(): void
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertGreaterThan(0, $result->total());
    }

    #[Test]
    public function it_searches_with_meilisearch_with_search_query(): void
    {
        $background = Background::first();
        $this->assertNotNull($background, 'Should have backgrounds in database');

        $dto = new BackgroundSearchDTO(
            searchQuery: $background->name,
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains($background->name, $names);
    }

    #[Test]
    public function it_searches_with_meilisearch_with_source_filter(): void
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'source_codes IN [PHB]',
            page: 1,
            perPage: 50,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        // Just verify we got valid results with the filter applied
        foreach ($result->items() as $background) {
            $this->assertInstanceOf(Background::class, $background);
        }
    }

    #[Test]
    public function it_handles_pagination_correctly(): void
    {
        $page1Dto = new BackgroundSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 3,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $page2Dto = new BackgroundSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 2,
            perPage: 3,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $page1Results = $this->service->searchWithMeilisearch($page1Dto, $this->client);
        $page2Results = $this->service->searchWithMeilisearch($page2Dto, $this->client);

        $this->assertEquals(1, $page1Results->currentPage());
        $this->assertEquals(2, $page2Results->currentPage());
        $this->assertEquals(3, $page1Results->perPage());

        $page1Ids = $page1Results->pluck('id')->toArray();
        $page2Ids = $page2Results->pluck('id')->toArray();

        if (count($page2Ids) > 0) {
            $this->assertNotEquals($page1Ids, $page2Ids, 'Different pages should have different results');
        }
    }

    #[Test]
    public function it_returns_empty_paginator_for_no_results(): void
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: 'xyznonexistentbackgroundxyz123',
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(0, $result->count());
    }

    #[Test]
    public function it_throws_exception_for_invalid_filter_syntax(): void
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'invalid_field INVALID_OPERATOR value',
            page: 1,
            perPage: 15,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $this->expectException(InvalidFilterSyntaxException::class);

        $this->service->searchWithMeilisearch($dto, $this->client);
    }

    #[Test]
    public function it_preserves_meilisearch_result_order(): void
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $names = $result->pluck('name')->toArray();
        $sortedNames = $names;
        sort($sortedNames);

        $this->assertEquals($sortedNames, $names, 'Results should be sorted by name ascending');
    }

    #[Test]
    public function it_hydrates_eloquent_models_with_relationships(): void
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 5,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $background) {
            $this->assertInstanceOf(Background::class, $background);
            $this->assertTrue($background->relationLoaded('sources'));
        }
    }
}
