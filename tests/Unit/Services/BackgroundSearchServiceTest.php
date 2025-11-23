<?php

namespace Tests\Unit\Services;

use App\DTOs\BackgroundSearchDTO;
use App\Services\BackgroundSearchService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundSearchServiceTest extends TestCase
{
    private BackgroundSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackgroundSearchService;
    }

    // ===================================================================
    // Relationship Method Tests
    // ===================================================================

    #[Test]
    public function it_returns_default_relationships()
    {
        $relationships = $this->service->getDefaultRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('sources.source', $relationships);
    }

    #[Test]
    public function it_returns_index_relationships()
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertCount(1, $relationships);
        $this->assertEquals([
            'sources.source',
        ], $relationships);
    }

    #[Test]
    public function it_returns_show_relationships()
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertGreaterThan(1, count($relationships), 'Show relationships should include more than index relationships');

        // Show relationships should include comprehensive details
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('traits.randomTables.entries', $relationships);
        $this->assertContains('proficiencies.skill.abilityScore', $relationships);
        $this->assertContains('proficiencies.proficiencyType', $relationships);
        $this->assertContains('languages.language', $relationships);
        $this->assertContains('equipment', $relationships);
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
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
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
    public function it_builds_database_query_with_search_filter()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['search' => 'Acolyte'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Search filter uses model scope which may add WHERE clauses
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_grants_proficiency_filter()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['grants_proficiency' => 'Insight'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for proficiency filtering
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_grants_skill_filter()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['grants_skill' => 'Perception'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for skill filtering
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_speaks_language_filter()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['speaks_language' => 'Elvish'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for language filtering
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_language_choice_count_filter()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['language_choice_count' => 2],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for language choice count
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_grants_languages_filter()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['grants_languages' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for grants languages
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_custom_sorting()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [],
            sortBy: 'name',
            sortDirection: 'desc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('name', strtolower($sql));
        $this->assertStringContainsString('desc', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_multiple_filters()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'grants_proficiency' => 'Insight',
                'grants_skill' => 'Perception',
                'speaks_language' => 'Common',
            ],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Multiple filters should all be applied via scopes
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    // ===================================================================
    // Edge Cases & Validation
    // ===================================================================

    #[Test]
    public function it_handles_empty_filters_array_gracefully()
    {
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
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
        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'search' => null,
                'grants_proficiency' => null,
                'grants_skill' => null,
                'speaks_language' => null,
                'language_choice_count' => null,
                'grants_languages' => null,
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
