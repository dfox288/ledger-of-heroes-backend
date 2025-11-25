<?php

namespace App\Services;

use App\DTOs\ClassSearchDTO;
use App\Models\CharacterClass;
use Illuminate\Database\Eloquent\Builder;

final class ClassSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
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
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'spellcastingAbility',
        'parentClass.spellcastingAbility',
        'parentClass.proficiencies.proficiencyType',
        'parentClass.proficiencies.item',
        'parentClass.proficiencies.skill.abilityScore',
        'parentClass.proficiencies.abilityScore',
        'parentClass.traits.randomTables.entries',
        'parentClass.sources.source',
        'parentClass.features.randomTables.entries',
        'parentClass.levelProgression',
        'parentClass.counters',
        'parentClass.equipment.item',
        'parentClass.spells',
        'parentClass.tags',
        'subclasses',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'proficiencies.skill.abilityScore',
        'proficiencies.abilityScore',
        'traits.randomTables.entries',
        'sources.source',
        'features.randomTables.entries',
        'levelProgression',
        'counters',
        'equipment.item',
        'spells',
        'subclasses.features.randomTables.entries',
        'subclasses.counters',
        'tags',
    ];

    /**
     * Backward compatibility alias
     */
    private const DEFAULT_RELATIONSHIPS = self::INDEX_RELATIONSHIPS;

    /**
     * Build Scout search query for full-text search
     *
     * NOTE: MySQL filtering has been removed. Use Meilisearch ?filter= parameter instead.
     *
     * Examples:
     * - ?filter=is_subclass = false
     * - ?filter=hit_die = 12
     * - ?filter=spellcasting_ability = INT
     * - ?filter=tag_slugs IN [spellcaster]
     * - ?filter=is_subclass = false AND hit_die >= 10
     */
    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return CharacterClass::search($searchQuery);
    }

    public function buildDatabaseQuery(ClassSearchDTO $dto): Builder
    {
        $query = CharacterClass::with(self::INDEX_RELATIONSHIPS);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    /**
     * Get default relationships for eager loading (index endpoints)
     */
    public function getDefaultRelationships(): array
    {
        return self::INDEX_RELATIONSHIPS;
    }

    /**
     * Get relationships for index/list endpoints
     */
    public function getIndexRelationships(): array
    {
        return self::INDEX_RELATIONSHIPS;
    }

    /**
     * Get relationships for show/detail endpoints
     */
    public function getShowRelationships(): array
    {
        return self::SHOW_RELATIONSHIPS;
    }

    private function applyFilters(Builder $query, ClassSearchDTO $dto): void
    {
        // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
        //
        // Examples:
        // - Base classes only: ?filter=is_subclass = false
        // - Subclasses only: ?filter=is_subclass = true
        // - High HP classes: ?filter=hit_die >= 10
        // - Spellcasters: ?filter=spellcasting_ability != null
        // - INT casters: ?filter=spellcasting_ability = INT
        // - Tag-based: ?filter=tag_slugs IN [spellcaster, martial]
        // - Combined: ?filter=is_subclass = false AND tag_slugs IN [full-caster]
        //
        // All filtering should happen via Meilisearch for consistency and performance.
    }

    private function applySorting(Builder $query, ClassSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
