<?php

namespace App\Services;

use App\DTOs\BackgroundSearchDTO;
use App\Models\Background;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service for searching and filtering D&D backgrounds
 *
 * Handles Scout/Meilisearch search with MySQL fallback, applies filters,
 * and returns paginated results. Keeps controllers thin by extracting
 * all query-building logic into this testable service layer.
 */
final class BackgroundSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'sources.source',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'sources.source',
        'traits.randomTables.entries',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'languages.language',
        'equipment.item',
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
     * - ?filter=tag_slugs IN [criminal]
     * - ?filter=tag_slugs IN [noble, outlander]
     * - ?filter=source_codes IN [PHB]
     */
    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return Background::search($searchQuery);
    }

    /**
     * Build Eloquent database query with filters
     */
    public function buildDatabaseQuery(BackgroundSearchDTO $dto): Builder
    {
        return $this->buildStandardQuery($dto);
    }

    /**
     * Build standard database query with filters (no search)
     */
    private function buildStandardQuery(BackgroundSearchDTO $dto): Builder
    {
        $query = Background::with(self::INDEX_RELATIONSHIPS);

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

    /**
     * Apply all filters to the query
     */
    private function applyFilters(Builder $query, BackgroundSearchDTO $dto): void
    {
        // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
        //
        // Examples:
        // - ?filter=tag_slugs IN [criminal]
        // - ?filter=tag_slugs IN [noble, outlander]
        // - ?filter=source_codes IN [PHB]
        // - ?filter=slug = acolyte
        //
        // All filtering should happen via Meilisearch for consistency and performance.
    }

    /**
     * Apply sorting to the query
     */
    private function applySorting(Builder $query, BackgroundSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
