<?php

namespace Tests\Feature\Services;

use App\DTOs\FeatSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Feat;
use App\Services\FeatSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MeiliSearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-search')]
class FeatSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    private FeatSearchService $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeatSearchService;
        $this->client = app(Client::class);
    }

    #[Test]
    public function it_returns_index_relationships(): void
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('modifiers.abilityScore', $relationships);
        $this->assertContains('proficiencies.skill.abilityScore', $relationships);
        $this->assertContains('conditions.condition', $relationships);
        $this->assertContains('prerequisites.prerequisite', $relationships);
        $this->assertContains('languages.language', $relationships);
    }

    #[Test]
    public function it_returns_show_relationships(): void
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('modifiers.damageType', $relationships);
        $this->assertContains('tags', $relationships);
        $this->assertContains('entitySpellRecords.spell', $relationships);
        $this->assertContains('entitySpellRecords.abilityScore', $relationships);
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
        $builder = $this->service->buildScoutQuery('alert');

        $this->assertInstanceOf(\Laravel\Scout\Builder::class, $builder);
    }

    #[Test]
    public function it_builds_database_query_with_eager_loading(): void
    {
        $dto = new FeatSearchDTO(
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
        $this->assertArrayHasKey('modifiers.abilityScore', $eagerLoads);
    }

    #[Test]
    public function it_applies_sorting_to_database_query(): void
    {
        $dto = new FeatSearchDTO(
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
        $dto = new FeatSearchDTO(
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
        $feat = Feat::first();
        $this->assertNotNull($feat, 'Should have feats in database');

        $dto = new FeatSearchDTO(
            searchQuery: $feat->name,
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains($feat->name, $names);
    }

    #[Test]
    public function it_searches_with_meilisearch_with_source_filter(): void
    {
        $dto = new FeatSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'source_codes IN [PHB]',
            page: 1,
            perPage: 50,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);

        foreach ($result->items() as $feat) {
            $this->assertInstanceOf(Feat::class, $feat);
        }
    }

    #[Test]
    public function it_handles_pagination_correctly(): void
    {
        $page1Dto = new FeatSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 3,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $page2Dto = new FeatSearchDTO(
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
        $dto = new FeatSearchDTO(
            searchQuery: 'xyznonexistentfeatxyz123',
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
        $dto = new FeatSearchDTO(
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
        $dto = new FeatSearchDTO(
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
        $dto = new FeatSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 5,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $feat) {
            $this->assertInstanceOf(Feat::class, $feat);
            $this->assertTrue($feat->relationLoaded('sources'));
            $this->assertTrue($feat->relationLoaded('modifiers'));
        }
    }

    #[Test]
    public function it_applies_sort_direction(): void
    {
        $ascDto = new FeatSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $descDto = new FeatSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'desc',
        );

        $ascResults = $this->service->searchWithMeilisearch($ascDto, $this->client);
        $descResults = $this->service->searchWithMeilisearch($descDto, $this->client);

        $ascNames = $ascResults->pluck('name')->toArray();
        $descNames = $descResults->pluck('name')->toArray();

        if (count($ascNames) > 1 && count($descNames) > 1) {
            $this->assertNotEquals($ascNames[0], $descNames[0], 'First items should differ between asc and desc');
        }
    }
}
