<?php

namespace Tests\Unit\Services;

use App\DTOs\SpellSearchDTO;
use App\Services\SpellSearchService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellSearchServiceTest extends TestCase
{
    private SpellSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SpellSearchService;
    }

    // ===================================================================
    // Relationship Method Tests
    // ===================================================================

    #[Test]
    public function it_returns_default_relationships()
    {
        $relationships = $this->service->getDefaultRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('spellSchool', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('effects.damageType', $relationships);
        $this->assertContains('classes', $relationships);
    }

    #[Test]
    public function it_returns_index_relationships()
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertCount(4, $relationships);
        $this->assertEquals([
            'spellSchool',
            'sources.source',
            'effects.damageType',
            'classes',
        ], $relationships);
    }

    #[Test]
    public function it_returns_show_relationships()
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertGreaterThan(4, count($relationships), 'Show relationships should include more than index relationships');

        // Show relationships should include all index relationships plus extras
        $this->assertContains('spellSchool', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('tags', $relationships);
        $this->assertContains('savingThrows', $relationships);
        $this->assertContains('randomTables.entries', $relationships);
        $this->assertContains('monsters', $relationships);
        $this->assertContains('items', $relationships);
        $this->assertContains('races', $relationships);
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
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);

        // Check that default sorting is applied
        $sql = $query->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_level_filter()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: ['level' => 3],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Should call Spell::level(3) scope
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertContains(3, $bindings, 'Level filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_concentration_filter()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: ['concentration' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('needs_concentration', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_ritual_filter()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: ['ritual' => false],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('is_ritual', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_verbal_component_filter()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: ['requires_verbal' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('components', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_somatic_component_filter()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: ['requires_somatic' => false],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('components', strtolower($sql));
        $this->assertStringContainsString('not like', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_material_component_filter()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: ['requires_material' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('components', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_custom_sorting()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: [],
            sortBy: 'level',
            sortDirection: 'desc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('desc', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_multiple_filters()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: [
                'level' => 3,
                'concentration' => true,
                'ritual' => false,
                'requires_verbal' => true,
            ],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Should have WHERE clauses for each filter
        $this->assertGreaterThanOrEqual(1, substr_count(strtolower($sql), 'where'), 'Should have at least one WHERE clause');
        $this->assertStringContainsString('needs_concentration', strtolower($sql));
        $this->assertStringContainsString('is_ritual', strtolower($sql));
        $this->assertStringContainsString('components', strtolower($sql));
    }

    // ===================================================================
    // Edge Cases & Validation
    // ===================================================================

    #[Test]
    public function it_handles_empty_filters_array_gracefully()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_handles_null_filter_values_gracefully()
    {
        $dto = new SpellSearchDTO(
            searchQuery: null,
            meilisearchFilter: null,
            page: 1,
            perPage: 15,
            filters: [
                'level' => null,
                'school' => null,
                'concentration' => null,
            ],
            sortBy: 'name',
            sortDirection: 'asc'
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
