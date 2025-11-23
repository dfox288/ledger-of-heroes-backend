<?php

namespace Tests\Unit\Services;

use App\DTOs\RaceSearchDTO;
use App\Services\RaceSearchService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaceSearchServiceTest extends TestCase
{
    private RaceSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RaceSearchService;
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
        $this->assertContains('proficiencies.skill', $relationships);
        $this->assertContains('traits.randomTables.entries', $relationships);
        $this->assertContains('modifiers.abilityScore', $relationships);
        $this->assertContains('conditions.condition', $relationships);
        $this->assertContains('spells.spell', $relationships);
        $this->assertContains('spells.abilityScore', $relationships);
        $this->assertContains('parent', $relationships);
    }

    #[Test]
    public function it_returns_index_relationships()
    {
        $relationships = $this->service->getIndexRelationships();

        $this->assertIsArray($relationships);
        $this->assertCount(9, $relationships);
        $this->assertEquals([
            'size',
            'sources.source',
            'proficiencies.skill',
            'traits.randomTables.entries',
            'modifiers.abilityScore',
            'conditions.condition',
            'spells.spell',
            'spells.abilityScore',
            'parent',
        ], $relationships);
    }

    #[Test]
    public function it_returns_show_relationships()
    {
        $relationships = $this->service->getShowRelationships();

        $this->assertIsArray($relationships);
        $this->assertGreaterThan(9, count($relationships), 'Show relationships should include more than index relationships');

        // Show relationships should include comprehensive parent/subrace nesting
        $this->assertContains('size', $relationships);
        $this->assertContains('parent.size', $relationships);
        $this->assertContains('parent.proficiencies.skill.abilityScore', $relationships);
        $this->assertContains('parent.traits.randomTables.entries', $relationships);
        $this->assertContains('parent.languages.language', $relationships);
        $this->assertContains('parent.spells.spell', $relationships);
        $this->assertContains('subraces', $relationships);
        $this->assertContains('languages.language', $relationships);
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
        $dto = new RaceSearchDTO(
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
    public function it_builds_database_query_with_size_filter()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['size' => 'M'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Size filter uses whereHas relationship
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('sizes', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_min_speed_filter()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['min_speed' => 30],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('speed', strtolower($sql));
        $this->assertStringContainsString('>=', $sql);
        $this->assertContains(30, $bindings, 'Min speed filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_has_darkvision_filter()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['has_darkvision' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Darkvision filter uses whereHas on traits with LIKE clause
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('traits', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
        $this->assertContains('%darkvision%', $bindings, 'Darkvision should be in bindings as LIKE pattern');
    }

    #[Test]
    public function it_builds_database_query_with_ability_bonus_filter()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['ability_bonus' => 'STR'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Ability bonus uses whereHas on modifiers
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('modifiers', strtolower($sql));
        $this->assertStringContainsString('ability_scores', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_has_innate_spells_filter()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['has_innate_spells' => true],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Innate spells uses has() on entitySpells
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('entity_spells', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_spells_filter()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'spells' => 'light,dancing-lights',
                'spells_operator' => 'OR',
            ],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Spell filter uses whereHas on entitySpells
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('entity_spells', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_spell_level_filter()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['spell_level' => 0],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('entity_spells', strtolower($sql));
        $this->assertContains(0, $bindings, 'Spell level filter should be in bindings');
    }

    #[Test]
    public function it_builds_database_query_with_language_choice_count_filter()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['language_choice_count' => 1],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Language choice count uses query scope
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    #[Test]
    public function it_builds_database_query_with_custom_sorting()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [],
            sortBy: 'speed',
            sortDirection: 'desc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('speed', strtolower($sql));
        $this->assertStringContainsString('desc', strtolower($sql));
    }

    #[Test]
    public function it_builds_database_query_with_multiple_filters()
    {
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'size' => 'M',
                'min_speed' => 30,
                'has_darkvision' => true,
                'ability_bonus' => 'DEX',
            ],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);

        $sql = $query->toSql();

        // Should have WHERE/EXISTS clauses for each filter
        $this->assertGreaterThanOrEqual(1, substr_count(strtolower($sql), 'where'), 'Should have at least one WHERE clause');
        $this->assertStringContainsString('speed', strtolower($sql));
    }

    // ===================================================================
    // Edge Cases & Validation
    // ===================================================================

    #[Test]
    public function it_handles_empty_filters_array_gracefully()
    {
        $dto = new RaceSearchDTO(
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
        $dto = new RaceSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'search' => null,
                'size' => null,
                'grants_proficiency' => null,
                'grants_skill' => null,
                'grants_proficiency_type' => null,
                'speaks_language' => null,
                'language_choice_count' => null,
                'grants_languages' => null,
                'spells' => null,
                'spell_level' => null,
                'has_innate_spells' => null,
                'ability_bonus' => null,
                'min_speed' => null,
                'has_darkvision' => null,
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
