<?php

namespace App\Services;

use App\DTOs\FeatSearchDTO;
use App\Models\Feat;
use Illuminate\Database\Eloquent\Builder;

final class FeatSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'conditions.condition',
        'prerequisites.prerequisite',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'conditions.condition',
        'prerequisites.prerequisite',
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
     * - ?filter=tag_slugs IN [combat]
     * - ?filter=tag_slugs IN [magic, skill-improvement]
     * - ?filter=source_codes IN [PHB, XGE]
     * - ?filter=tag_slugs IN [combat] AND source_codes IN [PHB]
     */
    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return Feat::search($searchQuery);
    }

    public function buildDatabaseQuery(FeatSearchDTO $dto): Builder
    {
        $query = Feat::with(self::INDEX_RELATIONSHIPS);

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

    private function applyFilters(Builder $query, FeatSearchDTO $dto): void
    {
        // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
        //
        // Examples:
        // - ?filter=tag_slugs IN [combat]
        // - ?filter=tag_slugs IN [magic, skill-improvement]
        // - ?filter=source_codes IN [PHB, XGE]
        // - ?filter=tag_slugs IN [combat] AND source_codes IN [PHB]
        //
        // All filtering should happen via Meilisearch for consistency and performance.
        //
        // NOTE: Legacy MySQL filters (prerequisite_race, prerequisite_ability, has_prerequisites,
        // grants_proficiency, prerequisite_proficiency, grants_skill) are deprecated.
        // These complex relational filters should be migrated to Meilisearch by indexing
        // the prerequisite/proficiency data in toSearchableArray() method.
    }

    private function applySorting(Builder $query, FeatSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
