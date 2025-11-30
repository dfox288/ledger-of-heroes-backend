<?php

namespace App\Services;

use App\DTOs\FeatSearchDTO;
use App\Exceptions\InvalidFilterSyntaxException;
use App\Models\Feat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

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
        'spells.spell',
        'spells.abilityScore',
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

    /**
     * Search using Meilisearch with custom filter expressions
     */
    public function searchWithMeilisearch(FeatSearchDTO $dto, Client $client): LengthAwarePaginator
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
            $indexName = (new Feat)->searchableAs();
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
        $featIds = collect($resultsArray['hits'])->pluck('id');

        if ($featIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
        }

        $feats = Feat::with(self::INDEX_RELATIONSHIPS)
            ->findMany($featIds);

        // Preserve Meilisearch result order
        $orderedFeats = $featIds->map(function ($id) use ($feats) {
            return $feats->firstWhere('id', $id);
        })->filter();

        return new LengthAwarePaginator(
            $orderedFeats,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page ?? 1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
