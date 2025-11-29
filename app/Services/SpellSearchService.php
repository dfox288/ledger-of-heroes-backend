<?php

namespace App\Services;

use App\DTOs\SpellSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Spell;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

/**
 * Service for searching and filtering D&D spells
 */
final class SpellSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'spellSchool',
        'sources.source',
        'effects.damageType',
        'classes',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'spellSchool',
        'sources.source',
        'effects.damageType',
        'classes',
        'tags',
        'savingThrows',
        'dataTables.entries',
        'monsters',
        'items',
        'races',
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
     * - ?filter=level = 0
     * - ?filter=school = EV
     * - ?filter=concentration = true
     * - ?filter=level <= 3 AND school = EV
     */
    public function buildScoutQuery(SpellSearchDTO $dto): \Laravel\Scout\Builder
    {
        return Spell::search($dto->searchQuery);
    }

    /**
     * Build Eloquent database query with filters
     */
    public function buildDatabaseQuery(SpellSearchDTO $dto): Builder
    {
        $query = Spell::with(self::INDEX_RELATIONSHIPS);

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

    private function applyFilters(Builder $query, SpellSearchDTO $dto): void
    {
        // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
        //
        // Examples:
        // - ?filter=level = 0
        // - ?filter=school = EV
        // - ?filter=concentration = true AND ritual = false
        // - ?filter=tag_slugs IN [fire, aoe]
        // - ?filter=class_slugs IN [wizard, sorcerer]
        // - ?filter=class_slugs IN [bard] AND level <= 3
        //
        // All filtering should happen via Meilisearch for consistency and performance.
    }

    private function applySorting(Builder $query, SpellSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }

    /**
     * Search using Meilisearch with custom filter expressions
     */
    public function searchWithMeilisearch(SpellSearchDTO $dto, Client $client): LengthAwarePaginator
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
            $indexName = (new Spell)->searchableAs();
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
        $spellIds = collect($resultsArray['hits'])->pluck('id');

        if ($spellIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
        }

        $spells = Spell::with(['spellSchool', 'sources.source', 'effects.damageType', 'classes'])
            ->findMany($spellIds);

        // Preserve Meilisearch result order
        $orderedSpells = $spellIds->map(function ($id) use ($spells) {
            return $spells->firstWhere('id', $id);
        })->filter();

        return new LengthAwarePaginator(
            $orderedSpells,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page ?? 1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
