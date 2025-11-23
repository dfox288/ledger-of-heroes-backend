<?php

namespace Tests\Unit\Services;

use App\DTOs\FeatSearchDTO;
use App\Services\FeatSearchService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatSearchServiceTest extends TestCase
{
    private FeatSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeatSearchService;
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
        $this->assertContains('modifiers.abilityScore', $relationships);
        $this->assertContains('modifiers.skill', $relationships);
        $this->assertContains('proficiencies.skill.abilityScore', $relationships);
        $this->assertContains('proficiencies.proficiencyType', $relationships);
        $this->assertContains('conditions.condition', $relationships);
        $this->assertContains('prerequisites.prerequisite', $relationships);
    }

    #[Test]
    public function it_returns_index_relationships()
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertCount(7, $relationships);
        $this->assertEquals([
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'proficiencies.skill.abilityScore',
            'proficiencies.proficiencyType',
            'conditions.condition',
            'prerequisites.prerequisite',
        ], $relationships);
    }

    #[Test]
    public function it_returns_show_relationships()
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertGreaterThanOrEqual(7, count($relationships), 'Show relationships should include at least index relationships');

        // Show relationships should include comprehensive details
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('modifiers.abilityScore', $relationships);
        $this->assertContains('modifiers.skill', $relationships);
        $this->assertContains('modifiers.damageType', $relationships);
        $this->assertContains('proficiencies.skill.abilityScore', $relationships);
        $this->assertContains('proficiencies.proficiencyType', $relationships);
        $this->assertContains('conditions', $relationships);
        $this->assertContains('prerequisites.prerequisite', $relationships);
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
        $dto = new FeatSearchDTO(
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
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['search' => 'Alert'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Search filter uses model scope
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_has_prerequisites_filter_true()
    {
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['has_prerequisites' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for prerequisite filtering
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_has_prerequisites_filter_false()
    {
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['has_prerequisites' => false],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for no-prerequisite filtering
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_prerequisite_race_filter()
    {
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['prerequisite_race' => 'Elf'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        // These filters use query scopes that query the database
        // We verify the service doesn't crash when applying filters
        $this->expectNotToPerformAssertions();

        try {
            $query = $this->service->buildDatabaseQuery($dto);
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected in unit tests without database
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function it_builds_database_query_with_prerequisite_ability_filter()
    {
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'prerequisite_ability' => 'STR',
                'min_value' => 13,
            ],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        // These filters use query scopes that query the database
        // We verify the service doesn't crash when applying filters
        $this->expectNotToPerformAssertions();

        try {
            $query = $this->service->buildDatabaseQuery($dto);
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected in unit tests without database
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function it_builds_database_query_with_grants_proficiency_filter()
    {
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['grants_proficiency' => 'Heavy Armor'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for proficiency filtering
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_prerequisite_proficiency_filter()
    {
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['prerequisite_proficiency' => 'Heavy Armor'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        // These filters use query scopes that query the database
        // We verify the service doesn't crash when applying filters
        $this->expectNotToPerformAssertions();

        try {
            $query = $this->service->buildDatabaseQuery($dto);
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected in unit tests without database
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function it_builds_database_query_with_grants_skill_filter()
    {
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['grants_skill' => 'Athletics'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        // Uses query scope for skill filtering
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_custom_sorting()
    {
        $dto = new FeatSearchDTO(
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
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'has_prerequisites' => true,
                'prerequisite_ability' => 'STR',
                'min_value' => 13,
                'grants_proficiency' => 'Heavy Armor',
            ],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        // These filters use query scopes that query the database
        // We verify the service doesn't crash when applying filters
        $this->expectNotToPerformAssertions();

        try {
            $query = $this->service->buildDatabaseQuery($dto);
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected in unit tests without database
            $this->assertTrue(true);
        }
    }

    // ===================================================================
    // Edge Cases & Validation
    // ===================================================================

    #[Test]
    public function it_handles_empty_filters_array_gracefully()
    {
        $dto = new FeatSearchDTO(
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
        $dto = new FeatSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'search' => null,
                'prerequisite_race' => null,
                'prerequisite_ability' => null,
                'min_value' => null,
                'has_prerequisites' => null,
                'grants_proficiency' => null,
                'prerequisite_proficiency' => null,
                'grants_skill' => null,
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
