<?php

namespace App\Services;

use App\DTOs\OptionalFeatureSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\OptionalFeature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

/**
 * Service for searching and filtering D&D optional features
 */
final class OptionalFeatureSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'classes',
        'sources.source',
        'spellSchool',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'classes',
        'sources.source',
        'spellSchool',
        'tags',
        'prerequisites',
        'rolls',
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
     * - ?filter=feature_type = eldritch_invocation
     * - ?filter=level_requirement <= 5
     * - ?filter=class_slugs IN [warlock]
     * - ?filter=has_spell_mechanics = true
     */
    public function buildScoutQuery(OptionalFeatureSearchDTO $dto): \Laravel\Scout\Builder
    {
        return OptionalFeature::search($dto->searchQuery);
    }

    /**
     * Build Eloquent database query with filters
     */
    public function buildDatabaseQuery(OptionalFeatureSearchDTO $dto): Builder
    {
        $query = OptionalFeature::with(self::INDEX_RELATIONSHIPS);

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

    private function applyFilters(Builder $query, OptionalFeatureSearchDTO $dto): void
    {
        // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
        //
        // Examples:
        // - ?filter=feature_type = eldritch_invocation
        // - ?filter=level_requirement <= 5
        // - ?filter=class_slugs IN [warlock] AND level_requirement <= 5
        // - ?filter=has_spell_mechanics = true
        // - ?filter=resource_type = ki_points
        //
        // All filtering should happen via Meilisearch for consistency and performance.
    }

    private function applySorting(Builder $query, OptionalFeatureSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }

    /**
     * Search using Meilisearch with custom filter expressions
     */
    public function searchWithMeilisearch(OptionalFeatureSearchDTO $dto, Client $client): LengthAwarePaginator
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
            $indexName = (new OptionalFeature)->searchableAs();
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
        $featureIds = collect($resultsArray['hits'])->pluck('id');

        if ($featureIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
        }

        $features = OptionalFeature::with(self::INDEX_RELATIONSHIPS)
            ->findMany($featureIds);

        // Preserve Meilisearch result order
        $orderedFeatures = $featureIds->map(function ($id) use ($features) {
            return $features->firstWhere('id', $id);
        })->filter();

        return new LengthAwarePaginator(
            $orderedFeatures,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page ?? 1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
