<?php

namespace Tests\Feature\Services;

use App\DTOs\ClassSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\CharacterClass;
use App\Services\ClassSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MeiliSearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-search')]
class ClassSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    private ClassSearchService $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClassSearchService;
        $this->client = app(Client::class);
    }

    #[Test]
    public function it_returns_index_relationships(): void
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('spellcastingAbility', $relationships);
        $this->assertContains('proficiencies.proficiencyType', $relationships);
        $this->assertContains('traits', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('features', $relationships);
        $this->assertContains('subclasses.features', $relationships);
        $this->assertContains('tags', $relationships);
    }

    #[Test]
    public function it_returns_show_relationships(): void
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('spellcastingAbility', $relationships);
        $this->assertContains('subclasses', $relationships);
        $this->assertContains('parentClass.spellcastingAbility', $relationships);
        $this->assertContains('equipment.item.contents.item', $relationships);
        $this->assertContains('optionalFeatures.sources.source', $relationships);
        $this->assertContains('multiclassRequirements.abilityScore', $relationships);
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
        $builder = $this->service->buildScoutQuery('wizard');

        $this->assertInstanceOf(\Laravel\Scout\Builder::class, $builder);
    }

    #[Test]
    public function it_builds_database_query_with_eager_loading(): void
    {
        $dto = new ClassSearchDTO(
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
        $this->assertArrayHasKey('spellcastingAbility', $eagerLoads);
        $this->assertArrayHasKey('sources.source', $eagerLoads);
    }

    #[Test]
    public function it_applies_sorting_to_database_query(): void
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            sortBy: 'hit_die',
            sortDirection: 'desc',
        );

        $builder = $this->service->buildDatabaseQuery($dto);

        $results = $builder->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
    }

    #[Test]
    public function it_searches_with_meilisearch_without_filters(): void
    {
        $dto = new ClassSearchDTO(
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
        $class = CharacterClass::first();
        $this->assertNotNull($class, 'Should have classes in database');

        $dto = new ClassSearchDTO(
            searchQuery: $class->name,
            meilisearchFilter: null,
            page: 1,
            perPage: 10,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains($class->name, $names);
    }

    #[Test]
    public function it_searches_with_meilisearch_with_subclass_filter(): void
    {
        $dto = new ClassSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'is_subclass = false',
            page: 1,
            perPage: 50,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);

        foreach ($result->items() as $class) {
            $this->assertNull($class->parent_class_id, 'All classes should be base classes, not subclasses');
        }
    }

    #[Test]
    public function it_searches_with_meilisearch_with_hit_die_filter(): void
    {
        // Filter for base classes with hit_die >= 8 (excludes subclasses which have hit_die = 0)
        $dto = new ClassSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'hit_die >= 8 AND is_subclass = false',
            page: 1,
            perPage: 50,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);

        foreach ($result->items() as $class) {
            $this->assertGreaterThanOrEqual(8, $class->hit_die, 'All base classes should have hit die >= 8');
            $this->assertNull($class->parent_class_id, 'Should only return base classes, not subclasses');
        }
    }

    #[Test]
    public function it_handles_pagination_correctly(): void
    {
        $page1Dto = new ClassSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 3,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $page2Dto = new ClassSearchDTO(
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
        $dto = new ClassSearchDTO(
            searchQuery: 'xyznonexistentclassxyz123',
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
        $dto = new ClassSearchDTO(
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
        $dto = new ClassSearchDTO(
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
        $dto = new ClassSearchDTO(
            searchQuery: '',
            meilisearchFilter: null,
            page: 1,
            perPage: 5,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $class) {
            $this->assertInstanceOf(CharacterClass::class, $class);
            $this->assertTrue($class->relationLoaded('sources'));
            $this->assertTrue($class->relationLoaded('proficiencies'));
        }
    }

    #[Test]
    public function it_searches_with_compound_filter(): void
    {
        $dto = new ClassSearchDTO(
            searchQuery: '',
            meilisearchFilter: 'is_subclass = false AND hit_die >= 8',
            page: 1,
            perPage: 50,
            sortBy: 'name',
            sortDirection: 'asc',
        );

        $result = $this->service->searchWithMeilisearch($dto, $this->client);

        foreach ($result->items() as $class) {
            $this->assertNull($class->parent_class_id);
            $this->assertGreaterThanOrEqual(8, $class->hit_die);
        }
    }
}
