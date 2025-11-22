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
     * Build Scout search query for full-text search
     */
    public function buildScoutQuery(MonsterSearchDTO $dto): \Laravel\Scout\Builder
    {
        $search = Monster::search($dto->searchQuery);

        // Apply Scout-compatible filters
        if (isset($dto->filters['challenge_rating'])) {
            $search->where('challenge_rating', $dto->filters['challenge_rating']);
        }

        if (isset($dto->filters['type'])) {
            $search->where('type', $dto->filters['type']);
        }

        if (isset($dto->filters['size'])) {
            $search->where('size_code', $dto->filters['size']);
        }

        if (isset($dto->filters['alignment'])) {
            $search->where('alignment', $dto->filters['alignment']);
        }

        // Spell filter (Meilisearch-optimized with AND logic)
        // Uses spell_slugs array field for fast filtering
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));

            // Build Meilisearch filter: spell_slugs = 'fireball' AND spell_slugs = 'lightning-bolt'
            foreach ($spellSlugs as $slug) {
                $search->where('spell_slugs', $slug);
            }
        }

        return $search;
    }

    /**
     * Build Eloquent database query with filters
     */
    public function buildDatabaseQuery(MonsterSearchDTO $dto): Builder
    {
        $query = Monster::with([
            'size',
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'conditions',
        ]);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    private function applyFilters(Builder $query, MonsterSearchDTO $dto): void
    {
        // Name search
        if (isset($dto->searchQuery)) {
            $query->where('name', 'like', '%'.$dto->searchQuery.'%');
        }

        // Exact challenge rating
        if (isset($dto->filters['challenge_rating'])) {
            $query->where('challenge_rating', $dto->filters['challenge_rating']);
        }

        // CR range filters
        if (isset($dto->filters['min_cr'])) {
            $query->whereRaw('CAST(challenge_rating AS DECIMAL(5,2)) >= ?', [$dto->filters['min_cr']]);
        }

        if (isset($dto->filters['max_cr'])) {
            $query->whereRaw('CAST(challenge_rating AS DECIMAL(5,2)) <= ?', [$dto->filters['max_cr']]);
        }

        // Type filter
        if (isset($dto->filters['type'])) {
            $query->where('type', $dto->filters['type']);
        }

        // Size filter
        if (isset($dto->filters['size'])) {
            $query->whereHas('size', function ($q) use ($dto) {
                $q->where('code', $dto->filters['size']);
            });
        }

        // Alignment filter
        if (isset($dto->filters['alignment'])) {
            $query->where('alignment', 'like', '%'.$dto->filters['alignment'].'%');
        }

        // Spell filter (AND logic: monster must have ALL specified spells)
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));

            foreach ($spellSlugs as $slug) {
                $query->whereHas('entitySpells', function ($q) use ($slug) {
                    $q->where('slug', $slug);
                });
            }
        }
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
            $index = $client->index('monsters_index');
            $results = $index->search($dto->searchQuery ?? '', $searchParams);
        } catch (\MeiliSearch\Exceptions\ApiException $e) {
            throw new InvalidFilterSyntaxException(
                $dto->meilisearchFilter ?? '',
                $e->getMessage()
            );
        }

        // Get monster IDs from results
        $monsterIds = collect($results['hits'])->pluck('id')->all();

        // Fetch full monsters from database in correct order
        $monsters = Monster::with([
            'size',
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'conditions',
        ])
            ->whereIn('id', $monsterIds)
            ->get()
            ->sortBy(function ($monster) use ($monsterIds) {
                return array_search($monster->id, $monsterIds);
            })
            ->values();

        // Create paginator
        return new LengthAwarePaginator(
            $monsters,
            $results['estimatedTotalHits'],
            $dto->perPage,
            $dto->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
