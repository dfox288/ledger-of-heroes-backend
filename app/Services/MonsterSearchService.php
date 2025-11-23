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
        'conditions',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'size',
        'traits',
        'actions',
        'legendaryActions',
        'entitySpells',
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'conditions',
        'tags',
    ];

    /**
     * Backward compatibility alias
     */
    private const DEFAULT_RELATIONSHIPS = self::INDEX_RELATIONSHIPS;

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

        // Spell filter (Meilisearch-optimized with AND/OR logic)
        // Uses spell_slugs array field for fast filtering
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));
            $operator = $dto->filters['spells_operator'] ?? 'AND';

            if ($operator === 'AND') {
                // Must have ALL spells: spell_slugs = 'fireball' AND spell_slugs = 'lightning-bolt'
                foreach ($spellSlugs as $slug) {
                    $search->where('spell_slugs', $slug);
                }
            } else {
                // Must have AT LEAST ONE spell: spell_slugs IN ['fireball', 'lightning-bolt']
                // Note: Meilisearch uses = for IN operator with arrays
                $search->whereIn('spell_slugs', $spellSlugs);
            }
        }

        return $search;
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

        // Spell filter (AND/OR logic)
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));
            $operator = $dto->filters['spells_operator'] ?? 'AND';

            if ($operator === 'AND') {
                // Must have ALL spells (nested whereHas)
                foreach ($spellSlugs as $slug) {
                    $query->whereHas('entitySpells', function ($q) use ($slug) {
                        $q->where('slug', $slug);
                    });
                }
            } else {
                // Must have AT LEAST ONE spell (single whereHas with whereIn)
                $query->whereHas('entitySpells', function ($q) use ($spellSlugs) {
                    $q->whereIn('slug', $spellSlugs);
                });
            }
        }

        // Spell level filter (monsters that know spells of specific level)
        if (isset($dto->filters['spell_level'])) {
            $query->whereHas('entitySpells', function ($q) use ($dto) {
                $q->where('level', $dto->filters['spell_level']);
            });
        }

        // REMOVED: spellcasting_ability filter - monster_spellcasting table deleted
        // Feature was never implemented (0 rows). Spells are now in entity_spells polymorphic table
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
        $monsterIds = collect($results->getHits())->pluck('id')->all();

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
            $results->getEstimatedTotalHits(),
            $dto->perPage,
            $dto->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
