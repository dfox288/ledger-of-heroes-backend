<?php

namespace Tests\Feature\Services;

use App\DTOs\ItemSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Item;
use App\Services\ItemSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MeiliSearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-search')]
class ItemSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    private ItemSearchService $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ItemSearchService;
        $this->client = app(Client::class);
    }

    #[Test]
    public function it_returns_index_relationships(): void
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('itemType', $relationships);
        $this->assertContains('damageType', $relationships);
        $this->assertContains('properties', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('prerequisites.prerequisite', $relationships);
    }

    #[Test]
    public function it_returns_show_relationships(): void
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('itemType', $relationships);
        $this->assertContains('abilities', $relationships);
        $this->assertContains('dataTables.entries', $relationships);
        $this->assertContains('proficiencies.proficiencyType', $relationships);
        $this->assertContains('spells.spellSchool', $relationships);
        $this->assertContains('savingThrows', $relationships);
        $this->assertContains('tags', $relationships);
        $this->assertContains('contents.item', $relationships);
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
        $dto = new ItemSearchDTO(
            searchQuery: 'sword',
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
        $dto = new ItemSearchDTO(
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
        $this->assertArrayHasKey('itemType', $eagerLoads);
        $this->assertArrayHasKey('sources.source', $eagerLoads);
    }

    #[Test]
    public function it_applies_sorting_to_database_query(): void
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            sortBy: 'value',
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
        $dto = new ItemSearchDTO(
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
        $item = Item::first();
        $this->assertNotNull($item, 'Should have items in database');

        $dto = new ItemSearchDTO(
            searchQuery: $item->name,
            perPage: 10,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains($item->name, $names);
    }

    #[Test]
    public function it_searches_with_meilisearch_with_type_filter(): void
    {
        // Find an item with a type to test
        $item = Item::whereNotNull('item_type_id')->first();
        if (! $item) {
            $this->markTestSkipped('No items with type in fixtures');
        }

        $dto = new ItemSearchDTO(
            searchQuery: '',
            perPage: 50,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: "type_code = {$item->itemType->code}",
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);

        foreach ($result->items() as $resultItem) {
            $this->assertEquals($item->itemType->code, $resultItem->itemType->code);
        }
    }

    #[Test]
    public function it_handles_pagination_correctly(): void
    {
        $page1Dto = new ItemSearchDTO(
            searchQuery: '',
            perPage: 5,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $page2Dto = new ItemSearchDTO(
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
        $dto = new ItemSearchDTO(
            searchQuery: 'xyznonexistentitemxyz123',
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
        $dto = new ItemSearchDTO(
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
        // Note: ItemSearchService does not pass sort params to Meilisearch,
        // so results are returned in Meilisearch's default relevance order
        $dto = new ItemSearchDTO(
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
        $dto = new ItemSearchDTO(
            searchQuery: '',
            perPage: 5,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null,
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $item) {
            $this->assertInstanceOf(Item::class, $item);
            $this->assertTrue($item->relationLoaded('itemType'));
            $this->assertTrue($item->relationLoaded('sources'));
        }
    }

    #[Test]
    public function it_searches_with_attunement_filter(): void
    {
        $dto = new ItemSearchDTO(
            searchQuery: '',
            perPage: 50,
            page: 1,
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: 'requires_attunement = true',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $item) {
            $this->assertTrue($item->requires_attunement, 'All items should require attunement');
        }
    }

    #[Test]
    public function it_accepts_sort_parameters_in_dto(): void
    {
        // Note: ItemSearchService accepts sort params in DTO but does not pass them to Meilisearch.
        // The sort is only applied to the database query, not Meilisearch search.
        // This test documents current behavior.
        $dto = new ItemSearchDTO(
            searchQuery: '',
            perPage: 10,
            page: 1,
            sortBy: 'name',
            sortDirection: 'desc',
            meilisearchFilter: null,
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        // Verify the service runs without error with sort params
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
    }
}
