<?php

namespace App\Services;

use App\Exceptions\Search\InvalidFilterSyntaxException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

/**
 * Base class for search services using Meilisearch
 *
 * Provides common searchWithMeilisearch() implementation and relationship management.
 * Subclasses must define their model class and relationships.
 */
abstract class AbstractSearchService
{
    /**
     * Get the fully qualified model class name
     *
     * @return class-string<Model>
     */
    abstract protected function getModelClass(): string;

    /**
     * Get relationships for index/list endpoints (lightweight)
     */
    abstract public function getIndexRelationships(): array;

    /**
     * Get relationships for show/detail endpoints (comprehensive)
     */
    abstract public function getShowRelationships(): array;

    /**
     * Get default relationships for eager loading (index endpoints)
     * Backward compatibility alias for getIndexRelationships()
     */
    public function getDefaultRelationships(): array
    {
        return $this->getIndexRelationships();
    }

    /**
     * Search using Meilisearch with custom filter expressions
     *
     * Common implementation extracted from all search services.
     * Handles:
     * - Pagination (limit/offset)
     * - Filtering (Meilisearch filter syntax)
     * - Sorting (single field sort)
     * - Result hydration (Eloquent models with relationships)
     * - Order preservation (maintains Meilisearch ranking)
     *
     * @param  object  $dto  Search DTO with searchQuery, meilisearchFilter, page, perPage, sortBy, sortDirection
     * @param  Client  $client  Meilisearch client instance
     *
     * @throws InvalidFilterSyntaxException
     */
    public function searchWithMeilisearch(object $dto, Client $client): LengthAwarePaginator
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
            $modelClass = $this->getModelClass();
            $indexName = (new $modelClass)->searchableAs();
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
        $entityIds = collect($resultsArray['hits'])->pluck('id');

        if ($entityIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
        }

        // Fetch entities with relationships
        $modelClass = $this->getModelClass();
        $entities = $modelClass::with($this->getIndexRelationships())
            ->findMany($entityIds);

        // Preserve Meilisearch result order
        $orderedEntities = $entityIds->map(function ($id) use ($entities) {
            return $entities->firstWhere('id', $id);
        })->filter();

        return new LengthAwarePaginator(
            $orderedEntities,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page ?? 1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Apply sorting to Eloquent query
     *
     * @param  Builder  $query  Eloquent query builder
     * @param  object  $dto  Search DTO with sortBy and sortDirection
     */
    protected function applySorting(Builder $query, object $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }

    /**
     * Build Eloquent database query for pagination (no filters - use Meilisearch for filtering)
     *
     * Common implementation for most search services.
     * Subclasses can override for custom behavior.
     */
    public function buildDatabaseQuery(object $dto): Builder
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::with($this->getIndexRelationships());

        $this->applySorting($query, $dto);

        return $query;
    }

    /**
     * Build Scout search query for full-text search
     *
     * Common implementation for most search services.
     * Subclasses can override for custom behavior.
     */
    public function buildScoutQuery(object $dto): \Laravel\Scout\Builder
    {
        $modelClass = $this->getModelClass();
        $searchQuery = $dto->searchQuery ?? '';

        return $modelClass::search($searchQuery);
    }
}
