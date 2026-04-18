<?php

namespace App\Services;

use App\DTOs\MonsterSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Monster;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

/**
 * Service for searching and filtering D&D monsters
 */
final class MonsterSearchService extends AbstractSearchService
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
        'entityTraits',
        'actions',
        'legendaryActions',
        'spells',
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'conditions.condition',
        'senses.sense',
        'tags',
    ];

    /**
     * Get the fully qualified model class name
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(): string
    {
        return Monster::class;
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
     * Search using Meilisearch with custom filter expressions.
     *
     * NOTE: MonsterSearchService intentionally does NOT pass sort parameters
     * to Meilisearch — results are returned in Meilisearch's default relevance
     * order. Sorting via the DTO is applied at the Eloquent query level only
     * (see buildDatabaseQuery). This preserves existing behavior documented
     * in MonsterSearchServiceTest::it_returns_results_in_meilisearch_relevance_order.
     *
     * Examples:
     * - ?filter=spell_slugs IN [fireball]
     * - ?filter=challenge_rating >= 5
     * - ?filter=type = dragon AND challenge_rating >= 10
     * - ?filter=tag_slugs IN [undead] AND hit_points_average > 100
     */
    public function searchWithMeilisearch(object $dto, Client $client): LengthAwarePaginator
    {
        $searchParams = [
            'limit' => $dto->perPage,
            'offset' => ($dto->page - 1) * $dto->perPage,
        ];

        if ($dto->meilisearchFilter) {
            $searchParams['filter'] = $dto->meilisearchFilter;
        }

        try {
            $indexName = (new Monster)->searchableAs();
            $results = $client->index($indexName)->search($dto->searchQuery ?? '', $searchParams);
        } catch (\MeiliSearch\Exceptions\ApiException $e) {
            throw new InvalidFilterSyntaxException(
                filter: $dto->meilisearchFilter ?? 'unknown',
                meilisearchMessage: $e->getMessage(),
                previous: $e
            );
        }

        $resultsArray = $results->toArray();
        $monsterIds = collect($resultsArray['hits'])->pluck('id');

        if ($monsterIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
        }

        $monsters = Monster::with(self::INDEX_RELATIONSHIPS)
            ->findMany($monsterIds);

        // Preserve Meilisearch result order
        $orderedMonsters = $monsterIds->map(function ($id) use ($monsters) {
            return $monsters->firstWhere('id', $id);
        })->filter();

        return new LengthAwarePaginator(
            $orderedMonsters,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page ?? 1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Build Scout search query for full-text search.
     *
     * @param  MonsterSearchDTO|object  $dto
     */
    public function buildScoutQuery(object $dto): \Laravel\Scout\Builder
    {
        return Monster::search($dto->searchQuery ?? '');
    }
}
