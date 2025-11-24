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
        'randomTables.entries',
        'sources.source',
        'proficiencies.proficiencyType',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'prerequisites.prerequisite',
        'spells',
        'savingThrows',
        'tags',
    ];

    /**
     * Backward compatibility alias
     */
    private const DEFAULT_RELATIONSHIPS = self::INDEX_RELATIONSHIPS;

    /**
     * Build Scout search query for full-text search
     */
    public function buildScoutQuery(ItemSearchDTO $dto): \Laravel\Scout\Builder
    {
        $search = Item::search($dto->searchQuery);

        if (isset($dto->filters['item_type_id'])) {
            $search->where('item_type_id', $dto->filters['item_type_id']);
        }

        if (isset($dto->filters['rarity'])) {
            $search->where('rarity', $dto->filters['rarity']);
        }

        if (isset($dto->filters['is_magic'])) {
            $search->where('is_magic', (bool) $dto->filters['is_magic']);
        }

        if (isset($dto->filters['requires_attunement'])) {
            $search->where('requires_attunement', (bool) $dto->filters['requires_attunement']);
        }

        // Spell filter (Meilisearch-optimized with AND/OR logic)
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));
            $operator = $dto->filters['spells_operator'] ?? 'AND';

            if ($operator === 'AND') {
                // Must have ALL spells
                foreach ($spellSlugs as $slug) {
                    $search->where('spell_slugs', $slug);
                }
            } else {
                // Must have AT LEAST ONE spell
                $search->whereIn('spell_slugs', $spellSlugs);
            }
        }

        return $search;
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
        // Name search
        if (isset($dto->searchQuery)) {
            $query->where('name', 'like', '%'.$dto->searchQuery.'%');
        }

        if (isset($dto->filters['search'])) {
            $query->where(function ($q) use ($dto) {
                $q->where('name', 'like', "%{$dto->filters['search']}%")
                    ->orWhere('description', 'like', "%{$dto->filters['search']}%");
            });
        }

        if (isset($dto->filters['item_type_id'])) {
            $query->where('item_type_id', $dto->filters['item_type_id']);
        }

        // Type filter (by item type code)
        if (isset($dto->filters['type'])) {
            $query->whereHas('itemType', function ($q) use ($dto) {
                $q->where('code', $dto->filters['type']);
            });
        }

        if (isset($dto->filters['rarity'])) {
            $query->where('rarity', $dto->filters['rarity']);
        }

        if (isset($dto->filters['is_magic'])) {
            $query->where('is_magic', (bool) $dto->filters['is_magic']);
        }

        if (isset($dto->filters['requires_attunement'])) {
            $query->where('requires_attunement', (bool) $dto->filters['requires_attunement']);
        }

        if (isset($dto->filters['min_strength'])) {
            $query->whereMinStrength((int) $dto->filters['min_strength']);
        }

        if (isset($dto->filters['has_prerequisites']) && (bool) $dto->filters['has_prerequisites']) {
            $query->hasPrerequisites();
        }

        // Has charges filter
        if (isset($dto->filters['has_charges'])) {
            if ((bool) $dto->filters['has_charges']) {
                $query->whereNotNull('charges_max');
            } else {
                $query->whereNull('charges_max');
            }
        }

        // Spell filter (AND/OR logic)
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));
            $operator = $dto->filters['spells_operator'] ?? 'AND';

            if ($operator === 'AND') {
                // Must have ALL spells (nested whereHas)
                foreach ($spellSlugs as $slug) {
                    $query->whereHas('spells', function ($q) use ($slug) {
                        $q->where('slug', $slug);
                    });
                }
            } else {
                // Must have AT LEAST ONE spell (single whereHas with whereIn)
                $query->whereHas('spells', function ($q) use ($spellSlugs) {
                    $q->whereIn('slug', $spellSlugs);
                });
            }
        }

        // Spell level filter (items that grant spells of specific level)
        if (isset($dto->filters['spell_level'])) {
            $query->whereHas('spells', function ($q) use ($dto) {
                $q->where('level', $dto->filters['spell_level']);
            });
        }
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
