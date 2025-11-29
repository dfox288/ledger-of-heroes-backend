<?php

namespace App\Services;

use App\DTOs\MonsterSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Monster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

/**
 * Service for searching and filtering D&D monsters
 */
final class MonsterSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'size',
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'conditions.condition',
        'senses.sense',
        'tags',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'size',
        'traits',
        'actions',
        'legendaryActions',
        'entitySpells.spell',
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'conditions.condition',
        'senses.sense',
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
     * - ?filter=spell_slugs IN [fireball]
     * - ?filter=challenge_rating >= 5
     * - ?filter=type = dragon AND challenge_rating >= 10
     * - ?filter=tag_slugs IN [undead] AND hit_points_average > 100
     */
    public function buildScoutQuery(MonsterSearchDTO $dto): \Laravel\Scout\Builder
    {
        return Monster::search($dto->searchQuery);
    }

    /**
     * Build Eloquent database query with filters
     */
    public function buildDatabaseQuery(MonsterSearchDTO $dto): Builder
    {
        $query = Monster::with(self::INDEX_RELATIONSHIPS);

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

    private function applyFilters(Builder $query, MonsterSearchDTO $dto): void
    {
        // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
        //
        // Examples:
        // - ?filter=spell_slugs IN [fireball]
        // - ?filter=spell_slugs IN [fireball, lightning-bolt]
        // - ?filter=challenge_rating >= 5
        // - ?filter=challenge_rating >= 10 AND challenge_rating <= 20
        // - ?filter=type = dragon
        // - ?filter=type = dragon AND spell_slugs IN [fireball]
        // - ?filter=tag_slugs IN [undead] AND hit_points_average > 100
        // - ?filter=armor_class >= 18 AND hit_points_average >= 100
        // - ?filter=size_code = L AND strength >= 20
        //
        // All filtering should happen via Meilisearch for consistency and performance.
    }

    private function applySorting(Builder $query, MonsterSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }

    /**
     * Search using Meilisearch with custom filter expressions
     */
    public function searchWithMeilisearch(MonsterSearchDTO $dto, Client $client): LengthAwarePaginator
    {
        $searchParams = [
            'limit' => $dto->perPage,
            'offset' => ($dto->page - 1) * $dto->perPage,
        ];

        // Add filter if provided
        if ($dto->meilisearchFilter) {
            // Validate filter syntax
            try {
                $searchParams['filter'] = $dto->meilisearchFilter;
            } catch (\Exception $e) {
                throw new InvalidFilterSyntaxException($dto->meilisearchFilter, $e->getMessage());
            }
        }

        // Execute search
        try {
            // Use model's searchableAs() to respect Scout prefix (test_ for testing, none for production)
            $indexName = (new Monster)->searchableAs();
            $index = $client->index($indexName);
            $results = $index->search($dto->searchQuery ?? '', $searchParams);
        } catch (\MeiliSearch\Exceptions\ApiException $e) {
            throw new InvalidFilterSyntaxException(
                $dto->meilisearchFilter ?? '',
                $e->getMessage()
            );
        }

        // Get monster IDs from results
        $monsterIds = collect($results->getHits())->pluck('id')->all();

        // Fetch full monsters from database in correct order
        $monsters = Monster::with(self::INDEX_RELATIONSHIPS)
            ->whereIn('id', $monsterIds)
            ->get()
            ->sortBy(function ($monster) use ($monsterIds) {
                return array_search($monster->id, $monsterIds);
            })
            ->values();

        // Create paginator
        return new LengthAwarePaginator(
            $monsters,
            $results->getEstimatedTotalHits(),
            $dto->perPage,
            $dto->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
