<?php

namespace Tests\Feature\Services;

use App\DTOs\SpellSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Spell;
use App\Services\SpellSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MeiliSearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-search')]
class SpellSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    private SpellSearchService $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SpellSearchService;
        $this->client = app(Client::class);
    }

    #[Test]
    public function it_returns_index_relationships(): void
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('spellSchool', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('effects.damageType', $relationships);
        $this->assertContains('classes', $relationships);
    }

    #[Test]
    public function it_returns_show_relationships(): void
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('spellSchool', $relationships);
        $this->assertContains('tags', $relationships);
        $this->assertContains('savingThrows', $relationships);
        $this->assertContains('dataTables.entries', $relationships);
        $this->assertContains('monsters', $relationships);
        $this->assertContains('items', $relationships);
        $this->assertContains('races', $relationships);
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
        $dto = new SpellSearchDTO(
            searchQuery: 'fireball',
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $builder = $this->service->buildScoutQuery($dto);

        $this->assertInstanceOf(\Laravel\Scout\Builder::class, $builder);
    }

    #[Test]
    public function it_builds_database_query_with_eager_loading(): void
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $builder = $this->service->buildDatabaseQuery($dto);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $builder);

        // Verify eager loading is set up
        $eagerLoads = $builder->getEagerLoads();
        $this->assertArrayHasKey('spellSchool', $eagerLoads);
        $this->assertArrayHasKey('sources.source', $eagerLoads);
    }

    #[Test]
    public function it_applies_sorting_to_database_query(): void
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            sortBy: 'level',
            sortDirection: 'desc',
        );

        $builder = $this->service->buildDatabaseQuery($dto);

        // Execute query and check it works
        $results = $builder->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
    }

    #[Test]
    public function it_searches_with_meilisearch_without_filters(): void
    {
        $dto = new SpellSearchDTO(
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
        $spell = Spell::first();
        $this->assertNotNull($spell, 'Should have spells in database');

        $dto = new SpellSearchDTO(
            searchQuery: $spell->name,
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains($spell->name, $names);
    }

    #[Test]
    public function it_searches_with_meilisearch_with_level_filter(): void
    {
        $dto = new SpellSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'level = 0',
            page: 1,
            perPage: 50,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);

        foreach ($result->items() as $spell) {
            $this->assertEquals(0, $spell->level, 'All spells should be cantrips (level 0)');
        }
    }

    #[Test]
    public function it_searches_with_meilisearch_with_school_filter(): void
    {
        $dto = new SpellSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'school_code = EV',
            page: 1,
            perPage: 50,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);

        foreach ($result->items() as $spell) {
            $this->assertEquals('EV', $spell->spellSchool->code, 'All spells should be Evocation');
        }
    }

    #[Test]
    public function it_handles_pagination_correctly(): void
    {
        $page1Dto = new SpellSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 5,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $page2Dto = new SpellSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 2,
            perPage: 5,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $page1Results = $this->service->searchWithMeilisearch($page1Dto, $this->client);
        $page2Results = $this->service->searchWithMeilisearch($page2Dto, $this->client);

        $this->assertEquals(1, $page1Results->currentPage());
        $this->assertEquals(2, $page2Results->currentPage());
        $this->assertEquals(5, $page1Results->perPage());

        // Ensure different results on different pages
        $page1Ids = $page1Results->pluck('id')->toArray();
        $page2Ids = $page2Results->pluck('id')->toArray();

        if (count($page2Ids) > 0) {
            $this->assertNotEquals($page1Ids, $page2Ids, 'Different pages should have different results');
        }
    }

    #[Test]
    public function it_returns_empty_paginator_for_no_results(): void
    {
        $dto = new SpellSearchDTO(
            searchQuery: 'xyznonexistentspellxyz123',
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
        $dto = new SpellSearchDTO(
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
        $dto = new SpellSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        // Verify results are sorted by name ascending
        $names = $result->pluck('name')->toArray();
        $sortedNames = $names;
        sort($sortedNames);

        $this->assertEquals($sortedNames, $names, 'Results should be sorted by name ascending');
    }

    #[Test]
    public function it_applies_sort_direction(): void
    {
        $ascDto = new SpellSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $descDto = new SpellSearchDTO(
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

        // First item in asc should be last in desc (or vice versa)
        if (count($ascNames) > 1 && count($descNames) > 1) {
            $this->assertNotEquals($ascNames[0], $descNames[0], 'First items should differ between asc and desc');
        }
    }

    #[Test]
    public function it_searches_with_compound_filter(): void
    {
        $dto = new SpellSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'level >= 1 AND level <= 3',
            page: 1,
            perPage: 50,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $spell) {
            $this->assertGreaterThanOrEqual(1, $spell->level);
            $this->assertLessThanOrEqual(3, $spell->level);
        }
    }

    #[Test]
    public function it_hydrates_eloquent_models_with_relationships(): void
    {
        $dto = new SpellSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 5,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $spell) {
            $this->assertInstanceOf(Spell::class, $spell);
            // Verify relationships are loaded (not lazy loaded)
            $this->assertTrue($spell->relationLoaded('spellSchool'));
            $this->assertTrue($spell->relationLoaded('sources'));
        }
    }
}
