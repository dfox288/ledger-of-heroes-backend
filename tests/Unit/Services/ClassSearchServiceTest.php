<?php

namespace Tests\Unit\Services;

use App\DTOs\ClassSearchDTO;
use App\Services\ClassSearchService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassSearchServiceTest extends TestCase
{
    private ClassSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClassSearchService;
    }

    // ===================================================================
    // Relationship Method Tests
    // ===================================================================

    #[Test]
    public function it_returns_default_relationships()
    {
        $relationships = $this->service->getDefaultRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('spellcastingAbility', $relationships);
        $this->assertContains('proficiencies.proficiencyType', $relationships);
        $this->assertContains('traits', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('features', $relationships);
        $this->assertContains('levelProgression', $relationships);
        $this->assertContains('counters', $relationships);
        $this->assertContains('subclasses.features', $relationships);
        $this->assertContains('tags', $relationships);
        $this->assertContains('parentClass', $relationships);
    }

    #[Test]
    public function it_returns_index_relationships()
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertCount(12, $relationships);
        $this->assertEquals([
            'spellcastingAbility',
            'proficiencies.proficiencyType',
            'proficiencies.item',
            'traits',
            'sources.source',
            'features',
            'levelProgression',
            'counters',
            'subclasses.features',
            'subclasses.counters',
            'tags',
            'parentClass',
        ], $relationships);
    }

    #[Test]
    public function it_returns_show_relationships()
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertGreaterThan(11, count($relationships), 'Show relationships should include more than index relationships');

        // Show relationships should include comprehensive parent/subclass nesting
        $this->assertContains('spellcastingAbility', $relationships);
        $this->assertContains('parentClass.spellcastingAbility', $relationships);
        $this->assertContains('parentClass.proficiencies.proficiencyType', $relationships);
        $this->assertContains('parentClass.traits.randomTables.entries', $relationships);
        $this->assertContains('features.randomTables.entries', $relationships);
        $this->assertContains('subclasses', $relationships);
        $this->assertContains('equipment.item', $relationships);
        $this->assertContains('spells', $relationships);
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
        $dto = new ClassSearchDTO(
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
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['search' => 'Wizard'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertStringContainsString('name', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
        $this->assertContains('%Wizard%', $bindings, 'Search filter should be in bindings with wildcards');
    }

    #[Test]
    public function it_builds_database_query_with_base_only_filter()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['base_only' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        $this->assertStringContainsString('parent_class_id', strtolower($sql));
        $this->assertStringContainsString('is null', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_is_spellcaster_filter_true()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['is_spellcaster' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        $this->assertStringContainsString('spellcasting_ability_id', strtolower($sql));
        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_is_spellcaster_filter_false()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['is_spellcaster' => false],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        $this->assertStringContainsString('spellcasting_ability_id', strtolower($sql));
        $this->assertStringContainsString('is null', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_hit_die_filter()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['hit_die' => 10],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('hit_die', strtolower($sql));
        $this->assertContains(10, $bindings, 'Hit die filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_grants_saving_throw_filter()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['grants_saving_throw' => 'DEX'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Uses whereHas with proficiencies and abilityScore relationships
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('proficiencies', strtolower($sql));
        $this->assertStringContainsString('ability_scores', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_spells_filter()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'spells' => 'fireball,magic-missile',
                'spells_operator' => 'OR',
            ],
            sortBy: 'name',
            sortDirection: 'asc'
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
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['spell_level' => 5],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('spells', strtolower($sql));
        $this->assertContains(5, $bindings, 'Spell level filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_max_spell_level_filter()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['max_spell_level' => 9],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('spells', strtolower($sql));
        $this->assertContains(9, $bindings, 'Max spell level filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_custom_sorting()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [],
            sortBy: 'hit_die',
            sortDirection: 'desc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('hit_die', strtolower($sql));
        $this->assertStringContainsString('desc', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_multiple_filters()
    {
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'base_only' => true,
                'is_spellcaster' => true,
                'hit_die' => 8,
                'spell_level' => 3,
            ],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Should have WHERE clauses for each filter
        $this->assertGreaterThanOrEqual(1, substr_count(strtolower($sql), 'where'), 'Should have at least one WHERE clause');
        $this->assertStringContainsString('parent_class_id', strtolower($sql));
        $this->assertStringContainsString('spellcasting_ability_id', strtolower($sql));
        $this->assertStringContainsString('hit_die', strtolower($sql));
    }

    // ===================================================================
    // Edge Cases & Validation
    // ===================================================================

    #[Test]
    public function it_handles_empty_filters_array_gracefully()
    {
        $dto = new ClassSearchDTO(
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
        $dto = new ClassSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'search' => null,
                'base_only' => null,
                'grants_proficiency' => null,
                'grants_skill' => null,
                'grants_saving_throw' => null,
                'spells' => null,
                'spell_level' => null,
                'is_spellcaster' => null,
                'hit_die' => null,
                'max_spell_level' => null,
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
