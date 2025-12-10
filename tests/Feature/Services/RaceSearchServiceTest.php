<?php

namespace Tests\Feature\Services;

use App\DTOs\RaceSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Race;
use App\Services\RaceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MeiliSearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-search')]
class RaceSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    private RaceSearchService $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RaceSearchService;
        $this->client = app(Client::class);
    }

    #[Test]
    public function it_returns_index_relationships(): void
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('size', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('proficiencies.skill', $relationships);
        $this->assertContains('traits.dataTables.entries', $relationships);
        $this->assertContains('modifiers.abilityScore', $relationships);
        $this->assertContains('parent', $relationships);
    }

    #[Test]
    public function it_returns_show_relationships(): void
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('size', $relationships);
        $this->assertContains('subraces.sources.source', $relationships);
        $this->assertContains('parent.size', $relationships);
        $this->assertContains('parent.languages.language', $relationships);
        $this->assertContains('parent.entitySpellRecords.spell', $relationships);
        $this->assertContains('parent.tags', $relationships);
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
        $dto = new RaceSearchDTO(
            searchQuery: 'elf',
            page: 1,
            perPage: 15,
            meilisearchFilter: null,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $builder = $this->service->buildScoutQuery($dto);

        $this->assertInstanceOf(\Laravel\Scout\Builder::class, $builder);
    }

    #[Test]
    public function it_builds_database_query_with_eager_loading(): void
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            page: 1,
            perPage: 15,
            meilisearchFilter: null,
            sortBy: 'name',
            sortDirection: 'asc',
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
        $dto = new RaceSearchDTO(
            searchQuery: null,
            page: 1,
            perPage: 15,
            meilisearchFilter: null,
            sortBy: 'speed',
            sortDirection: 'desc',
        );

        $builder = $this->service->buildDatabaseQuery($dto);

        $results = $builder->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
    }

    #[Test]
    public function it_searches_with_meilisearch_without_filters(): void
    {
        $dto = new RaceSearchDTO(
            searchQuery: '',
            page: 1,
            perPage: 10,
            meilisearchFilter: null,
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
        $race = Race::first();
        $this->assertNotNull($race, 'Should have races in database');

        $dto = new RaceSearchDTO(
            searchQuery: $race->name,
            page: 1,
            perPage: 10,
            meilisearchFilter: null,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains($race->name, $names);
    }

    #[Test]
    public function it_searches_with_meilisearch_with_size_filter(): void
    {
        $dto = new RaceSearchDTO(
            searchQuery: '',
            page: 1,
            perPage: 50,
            meilisearchFilter: 'size_code = M',
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);

        foreach ($result->items() as $race) {
            $this->assertEquals('M', $race->size->code, 'All races should be Medium size');
        }
    }

    #[Test]
    public function it_handles_pagination_correctly(): void
    {
        $page1Dto = new RaceSearchDTO(
            searchQuery: '',
            page: 1,
            perPage: 3,
            meilisearchFilter: null,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $page2Dto = new RaceSearchDTO(
            searchQuery: '',
            page: 2,
            perPage: 3,
            meilisearchFilter: null,
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
        $dto = new RaceSearchDTO(
            searchQuery: 'xyznonexistentracexyz123',
            page: 1,
            perPage: 15,
            meilisearchFilter: null,
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
        $dto = new RaceSearchDTO(
            searchQuery: '',
            page: 1,
            perPage: 15,
            meilisearchFilter: 'invalid_field INVALID_OPERATOR value',
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $this->expectException(InvalidFilterSyntaxException::class);

        $this->service->searchWithMeilisearch($dto, $this->client);
    }

    #[Test]
    public function it_preserves_meilisearch_result_order(): void
    {
        $dto = new RaceSearchDTO(
            searchQuery: '',
            page: 1,
            perPage: 10,
            meilisearchFilter: null,
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
        $dto = new RaceSearchDTO(
            searchQuery: '',
            page: 1,
            perPage: 5,
            meilisearchFilter: null,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $race) {
            $this->assertInstanceOf(Race::class, $race);
            $this->assertTrue($race->relationLoaded('size'));
            $this->assertTrue($race->relationLoaded('sources'));
        }
    }

    #[Test]
    public function it_searches_with_speed_filter(): void
    {
        $dto = new RaceSearchDTO(
            searchQuery: '',
            page: 1,
            perPage: 50,
            meilisearchFilter: 'speed >= 30',
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $race) {
            $this->assertGreaterThanOrEqual(30, $race->speed);
        }
    }
}
