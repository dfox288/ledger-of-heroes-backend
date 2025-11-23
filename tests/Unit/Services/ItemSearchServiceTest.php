<?php

namespace Tests\Unit\Services;

use App\DTOs\ItemSearchDTO;
use App\Services\ItemSearchService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemSearchServiceTest extends TestCase
{
    private ItemSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ItemSearchService;
    }

    // ===================================================================
    // Relationship Method Tests
    // ===================================================================

    #[Test]
    public function it_returns_default_relationships()
    {
        $relationships = $this->service->getDefaultRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('itemType', $relationships);
        $this->assertContains('damageType', $relationships);
        $this->assertContains('properties', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('prerequisites.prerequisite', $relationships);
    }

    #[Test]
    public function it_returns_index_relationships()
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertCount(5, $relationships);
        $this->assertEquals([
            'itemType',
            'damageType',
            'properties',
            'sources.source',
            'prerequisites.prerequisite',
        ], $relationships);
    }

    #[Test]
    public function it_returns_show_relationships()
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertGreaterThan(5, count($relationships), 'Show relationships should include more than index relationships');

        // Show relationships should include all index relationships plus extras
        $this->assertContains('itemType', $relationships);
        $this->assertContains('properties', $relationships);
        $this->assertContains('abilities', $relationships);
        $this->assertContains('randomTables.entries', $relationships);
        $this->assertContains('proficiencies.proficiencyType', $relationships);
        $this->assertContains('modifiers.abilityScore', $relationships);
        $this->assertContains('spells', $relationships);
        $this->assertContains('savingThrows', $relationships);
        $this->assertContains('tags', $relationships);
    }

    #[Test]
    public function default_relationships_equal_index_relationships()
    {
        $this->assertEquals(
            $this->service->getDefaultRelationships(),
            $this->service->getIndexRelationships(),
            'Default relationships should match index relationships for backwards compatibility'
        );
    }

    // ===================================================================
    // buildDatabaseQuery Tests
    // ===================================================================

    #[Test]
    public function it_builds_database_query_with_no_filters()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);

        // Check that default sorting is applied
        $sql = $query->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_rarity_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['rarity' => 'legendary'],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertStringContainsString('rarity', strtolower($sql));
        $this->assertContains('legendary', $bindings, 'Rarity filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_is_magic_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['is_magic' => true],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        $this->assertStringContainsString('is_magic', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_requires_attunement_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['requires_attunement' => false],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        $this->assertStringContainsString('requires_attunement', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_item_type_id_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['item_type_id' => 5],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('item_type_id', strtolower($sql));
        $this->assertContains(5, $bindings, 'Item type ID filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_type_code_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['type' => 'W'],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Type filter uses whereHas relationship
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('item_types', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_has_charges_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['has_charges' => true],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        $this->assertStringContainsString('charges_max', strtolower($sql));
        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_spells_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [
                'spells' => 'fireball,magic-missile',
                'spells_operator' => 'OR',
            ],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Spell filter uses whereHas relationship
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('spells', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_spell_level_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['spell_level' => 3],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('spells', strtolower($sql));
        $this->assertContains(3, $bindings, 'Spell level filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_search_filter()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['search' => 'sword'],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('name', strtolower($sql));
        $this->assertStringContainsString('description', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
        $this->assertContains('%sword%', $bindings, 'Search filter should be in bindings with wildcards');
    }

    #[Test]
    public function it_builds_database_query_with_custom_sorting()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [],
            sortBy: 'rarity',
            sortDirection: 'desc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('rarity', strtolower($sql));
        $this->assertStringContainsString('desc', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_multiple_filters()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [
                'rarity' => 'rare',
                'is_magic' => true,
                'requires_attunement' => true,
                'has_charges' => true,
            ],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Should have WHERE clauses for each filter
        $this->assertGreaterThanOrEqual(1, substr_count(strtolower($sql), 'where'), 'Should have at least one WHERE clause');
        $this->assertStringContainsString('rarity', strtolower($sql));
        $this->assertStringContainsString('is_magic', strtolower($sql));
        $this->assertStringContainsString('requires_attunement', strtolower($sql));
        $this->assertStringContainsString('charges_max', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_search_query()
    {
        $dto = new ItemSearchDTO(
            searchQuery: 'Longsword',
            perPage: 15,
            page: 1,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('name', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
        $this->assertContains('%Longsword%', $bindings, 'Search query should be in bindings with wildcards');
    }

    // ===================================================================
    // Edge Cases & Validation
    // ===================================================================

    #[Test]
    public function it_handles_empty_filters_array_gracefully()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_handles_null_filter_values_gracefully()
    {
        $dto = new ItemSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [
                'rarity' => null,
                'is_magic' => null,
                'requires_attunement' => null,
                'item_type_id' => null,
                'type' => null,
                'spells' => null,
                'spell_level' => null,
                'has_charges' => null,
            ],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Should not crash, should produce valid query
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);

        // No WHERE clauses should be added for null values
        $sql = $query->toSql();
        $whereCount = substr_count(strtolower($sql), 'where');

        // Should have minimal WHERE clauses (possibly none or just defaults)
        $this->assertLessThan(3, $whereCount, 'Null filters should not add WHERE clauses');
    }
}
