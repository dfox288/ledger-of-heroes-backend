<?php

namespace App\Services;

use App\DTOs\ItemSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

final class ItemSearchService
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
     * Backward compatibility alias
     */
    private const DEFAULT_RELATIONSHIPS = self::INDEX_RELATIONSHIPS;

    /**
     * Build Scout search query for full-text search
     *
     * NOTE: MySQL filtering has been removed. Use Meilisearch ?filter= parameter instead.
     *
     * Examples:
     * - ?filter=rarity IN [rare, legendary]
     * - ?filter=type_code = WD
     * - ?filter=requires_attunement = true
     * - ?filter=spell_slugs IN [fireball]
     * - ?filter=spell_slugs IN [fireball] AND rarity = rare
     * - ?filter=has_charges = true AND type_code IN [WD, ST]
     */
    public function buildScoutQuery(ItemSearchDTO $dto): \Laravel\Scout\Builder
    {
        return Item::search($dto->searchQuery);
    }

    /**
     * Build Eloquent database query with filters
     */
    public function buildDatabaseQuery(ItemSearchDTO $dto): Builder
    {
        $query = Item::with(self::INDEX_RELATIONSHIPS);

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

    private function applyFilters(Builder $query, ItemSearchDTO $dto): void
    {
        // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
        //
        // Examples:
        // - ?filter=rarity IN [rare, very_rare, legendary]
        // - ?filter=type_code = WD (wands)
        // - ?filter=type_code = SCR (scrolls)
        // - ?filter=requires_attunement = true
        // - ?filter=is_magic = true
        // - ?filter=has_charges = true
        // - ?filter=spell_slugs IN [fireball] (items containing Fireball)
        // - ?filter=spell_slugs IN [fireball, lightning-bolt] (items with either spell)
        // - ?filter=tag_slugs IN [fire, damage]
        // - ?filter=cost_cp >= 5000 (items worth 50+ gold)
        // - ?filter=weight <= 1.0 (lightweight items)
        //
        // Combined filters:
        // - ?filter=spell_slugs IN [fireball] AND type_code = WD AND rarity = rare
        // - ?filter=has_charges = true AND spell_slugs IN [teleport]
        // - ?filter=is_magic = true AND rarity IN [legendary, artifact]
        //
        // All filtering should happen via Meilisearch for consistency and performance.
    }

    private function applySorting(Builder $query, ItemSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }

    /**
     * Search using Meilisearch with custom filter expressions
     */
    public function searchWithMeilisearch(ItemSearchDTO $dto, Client $client): LengthAwarePaginator
    {
        $searchParams = [
            'limit' => $dto->perPage,
            'offset' => ($dto->page - 1) * $dto->perPage,
        ];

        // Add filter if provided
        if ($dto->meilisearchFilter) {
            try {
                $searchParams['filter'] = $dto->meilisearchFilter;
            } catch (\Exception $e) {
                throw new InvalidFilterSyntaxException($dto->meilisearchFilter, $e->getMessage());
            }
        }

        // Execute search
        try {
            // Use model's searchableAs() to respect Scout prefix (test_ for testing, none for production)
            $indexName = (new Item)->searchableAs();
            $index = $client->index($indexName);
            $results = $index->search($dto->searchQuery ?? '', $searchParams);
        } catch (\MeiliSearch\Exceptions\ApiException $e) {
            throw new InvalidFilterSyntaxException(
                $dto->meilisearchFilter ?? '',
                $e->getMessage()
            );
        }

        // Get item IDs from results
        $itemIds = collect($results->getHits())->pluck('id')->all();

        // Fetch full items from database in correct order
        $items = Item::with([
            'itemType',
            'damageType',
            'properties',
            'sources.source',
            'prerequisites.prerequisite',
        ])
            ->whereIn('id', $itemIds)
            ->get()
            ->sortBy(function ($item) use ($itemIds) {
                return array_search($item->id, $itemIds);
            })
            ->values();

        // Create paginator
        return new LengthAwarePaginator(
            $items,
            $results->getEstimatedTotalHits(),
            $dto->perPage,
            $dto->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
