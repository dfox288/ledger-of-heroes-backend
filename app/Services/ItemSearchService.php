<?php

namespace App\Services;

use App\DTOs\ItemSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Item;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

/**
 * Service for searching and filtering D&D items
 */
final class ItemSearchService extends AbstractSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'itemType',
        'damageType',
        'properties',
        'sources.source',
        'prerequisites.prerequisite',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'itemType',
        'damageType',
        'properties',
        'abilities',
        'dataTables.entries',
        'sources.source',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'prerequisites.prerequisite',
        'spells.spellSchool',
        'savingThrows',
        'tags',
        'contents.item',
    ];

    /**
     * Get the fully qualified model class name
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(): string
    {
        return Item::class;
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
     * NOTE: ItemSearchService intentionally does NOT pass sort parameters
     * to Meilisearch — results are returned in Meilisearch's default relevance
     * order. Sorting via the DTO is applied at the Eloquent query level only
     * (see buildDatabaseQuery). This preserves existing behavior documented
     * in ItemSearchServiceTest::it_returns_results_in_meilisearch_relevance_order.
     *
     * Examples:
     * - ?filter=rarity IN [rare, legendary]
     * - ?filter=type_code = WD
     * - ?filter=requires_attunement = true
     * - ?filter=spell_slugs IN [fireball]
     * - ?filter=spell_slugs IN [fireball] AND rarity = rare
     * - ?filter=has_charges = true AND type_code IN [WD, ST]
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
            $indexName = (new Item)->searchableAs();
            $results = $client->index($indexName)->search($dto->searchQuery ?? '', $searchParams);
        } catch (\MeiliSearch\Exceptions\ApiException $e) {
            throw new InvalidFilterSyntaxException(
                filter: $dto->meilisearchFilter ?? 'unknown',
                meilisearchMessage: $e->getMessage(),
                previous: $e
            );
        }

        $resultsArray = $results->toArray();
        $itemIds = collect($resultsArray['hits'])->pluck('id');

        if ($itemIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
        }

        $items = Item::with(self::INDEX_RELATIONSHIPS)
            ->findMany($itemIds);

        // Preserve Meilisearch result order
        $orderedItems = $itemIds->map(function ($id) use ($items) {
            return $items->firstWhere('id', $id);
        })->filter();

        return new LengthAwarePaginator(
            $orderedItems,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page ?? 1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Build Scout search query for full-text search.
     *
     * @param  ItemSearchDTO|object  $dto
     */
    public function buildScoutQuery(object $dto): \Laravel\Scout\Builder
    {
        return Item::search($dto->searchQuery ?? '');
    }
}
