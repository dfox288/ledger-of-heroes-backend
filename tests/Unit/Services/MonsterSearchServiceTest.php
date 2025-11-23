<?php

namespace Tests\Unit\Services;

use App\DTOs\MonsterSearchDTO;
use App\Services\MonsterSearchService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonsterSearchServiceTest extends TestCase
{
    private MonsterSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MonsterSearchService;
    }

    // ===================================================================
    // Relationship Method Tests
    // ===================================================================

    #[Test]
    public function it_returns_default_relationships()
    {
        $relationships = $this->service->getDefaultRelationships();

        $this->assertIsArray($relationships);
        $this->assertContains('size', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('modifiers.abilityScore', $relationships);
        $this->assertContains('modifiers.skill', $relationships);
        $this->assertContains('modifiers.damageType', $relationships);
        $this->assertContains('conditions', $relationships);
    }

    #[Test]
    public function it_returns_index_relationships()
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertCount(6, $relationships);
        $this->assertEquals([
            'size',
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'conditions',
        ], $relationships);
    }

    #[Test]
    public function it_returns_show_relationships()
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertGreaterThan(6, count($relationships), 'Show relationships should include more than index relationships');

        // Show relationships should include all index relationships plus extras
        $this->assertContains('size', $relationships);
        $this->assertContains('sources.source', $relationships);
        $this->assertContains('traits', $relationships);
        $this->assertContains('actions', $relationships);
        $this->assertContains('legendaryActions', $relationships);
        $this->assertContains('spellcasting', $relationships);
        $this->assertContains('entitySpells', $relationships);
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
        $dto = new MonsterSearchDTO(
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
    public function it_builds_database_query_with_challenge_rating_filter()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['challenge_rating' => '5'],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertStringContainsString('challenge_rating', strtolower($sql));
        $this->assertContains('5', $bindings, 'Challenge rating filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_min_cr_filter()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['min_cr' => 3],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('cast', strtolower($sql));
        $this->assertStringContainsString('>=', $sql);
        $this->assertContains(3, $bindings, 'Min CR filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_max_cr_filter()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['max_cr' => 10],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('cast', strtolower($sql));
        $this->assertStringContainsString('<=', $sql);
        $this->assertContains(10, $bindings, 'Max CR filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_type_filter()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['type' => 'dragon'],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertStringContainsString('type', strtolower($sql));
        $this->assertContains('dragon', $bindings, 'Type filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_size_filter()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['size' => 'L'],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Size filter uses whereHas relationship
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('sizes', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_alignment_filter()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['alignment' => 'lawful'],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('alignment', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
        $this->assertContains('%lawful%', $bindings, 'Alignment filter should be in bindings with wildcards');
    }

    #[Test]
    public function it_builds_database_query_with_spells_filter_and_operator()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [
                'spells' => 'fireball,lightning-bolt',
                'spells_operator' => 'OR',
            ],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Spell filter uses whereHas relationship with entitySpells
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('entity_spells', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_spell_level_filter()
    {
        $dto = new MonsterSearchDTO(
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
        $this->assertStringContainsString('entity_spells', strtolower($sql));
        $this->assertContains(3, $bindings, 'Spell level filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_spellcasting_ability_filter()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: ['spellcasting_ability' => 'INT'],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('monster_spellcasting', strtolower($sql));
        $this->assertContains('%INT%', $bindings, 'Spellcasting ability filter should be in bindings with wildcards');
    }

    #[Test]
    public function it_builds_database_query_with_custom_sorting()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [],
            sortBy: 'challenge_rating',
            sortDirection: 'desc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('challenge_rating', strtolower($sql));
        $this->assertStringContainsString('desc', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_multiple_filters()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [
                'challenge_rating' => '5',
                'type' => 'dragon',
                'alignment' => 'chaotic',
                'min_cr' => 3,
                'max_cr' => 10,
            ],
            sortBy: 'name',
            sortDirection: 'asc',
            meilisearchFilter: null
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Should have WHERE clauses for each filter
        $this->assertGreaterThanOrEqual(1, substr_count(strtolower($sql), 'where'), 'Should have at least one WHERE clause');
        $this->assertStringContainsString('challenge_rating', strtolower($sql));
        $this->assertStringContainsString('type', strtolower($sql));
        $this->assertStringContainsString('alignment', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_search_query()
    {
        $dto = new MonsterSearchDTO(
            searchQuery: 'Dragon',
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
        $this->assertContains('%Dragon%', $bindings, 'Search query should be in bindings with wildcards');
    }

    // ===================================================================
    // Edge Cases & Validation
    // ===================================================================

    #[Test]
    public function it_handles_empty_filters_array_gracefully()
    {
        $dto = new MonsterSearchDTO(
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
        $dto = new MonsterSearchDTO(
            searchQuery: null,
            perPage: 15,
            page: 1,
            filters: [
                'challenge_rating' => null,
                'type' => null,
                'size' => null,
                'alignment' => null,
                'spells' => null,
                'spell_level' => null,
                'spellcasting_ability' => null,
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
