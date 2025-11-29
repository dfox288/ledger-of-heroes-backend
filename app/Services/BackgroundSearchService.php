<?php

namespace App\Services;

use App\DTOs\BackgroundSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Background;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

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
        'traits.dataTables.entries',
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

    /**
     * Search using Meilisearch with custom filter expressions
     */
    public function searchWithMeilisearch(BackgroundSearchDTO $dto, Client $client): LengthAwarePaginator
    {
        $searchParams = [
            'limit' => $dto->perPage,
            'offset' => ($dto->page - 1) * $dto->perPage,
        ];

        // Add filter if provided
        if ($dto->meilisearchFilter) {
            $searchParams['filter'] = $dto->meilisearchFilter;
        }

        // Add sort if needed
        if ($dto->sortBy && $dto->sortDirection) {
            $searchParams['sort'] = ["{$dto->sortBy}:{$dto->sortDirection}"];
        }

        // Execute search
        try {
            // Use model's searchableAs() to respect Scout prefix (test_ for testing, none for production)
            $indexName = (new Background)->searchableAs();
            $results = $client->index($indexName)->search($dto->searchQuery ?? '', $searchParams);
        } catch (\MeiliSearch\Exceptions\ApiException $e) {
            throw new InvalidFilterSyntaxException(
                filter: $dto->meilisearchFilter ?? 'unknown',
                meilisearchMessage: $e->getMessage(),
                previous: $e
            );
        }

        // Convert SearchResult object to array
        $resultsArray = $results->toArray();

        // Hydrate Eloquent models to use with API Resources
        $backgroundIds = collect($resultsArray['hits'])->pluck('id');

        if ($backgroundIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
        }

        $backgrounds = Background::with(self::INDEX_RELATIONSHIPS)
            ->findMany($backgroundIds);

        // Preserve Meilisearch result order
        $orderedBackgrounds = $backgroundIds->map(function ($id) use ($backgrounds) {
            return $backgrounds->firstWhere('id', $id);
        })->filter();

        return new LengthAwarePaginator(
            $orderedBackgrounds,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page ?? 1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
