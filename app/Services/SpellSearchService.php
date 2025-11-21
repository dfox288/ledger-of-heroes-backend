<?php

namespace App\Services;

use App\DTOs\SpellSearchDTO;
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
     * Build Scout search query for full-text search
     */
    public function buildScoutQuery(SpellSearchDTO $dto): \Laravel\Scout\Builder
    {
        $search = Spell::search($dto->searchQuery);

        // Apply Scout-compatible filters
        if (isset($dto->filters['level'])) {
            $search->where('level', $dto->filters['level']);
        }

        if (isset($dto->filters['school'])) {
            $schoolId = $dto->filters['school'];
            $schoolName = \App\Models\SpellSchool::find($schoolId)?->name;
            if ($schoolName) {
                $search->where('school_name', $schoolName);
            }
        }

        if (isset($dto->filters['concentration'])) {
            $search->where('concentration', (bool) $dto->filters['concentration']);
        }

        if (isset($dto->filters['ritual'])) {
            $search->where('ritual', (bool) $dto->filters['ritual']);
        }

        return $search;
    }

    /**
     * Build Eloquent database query with filters
     */
    public function buildDatabaseQuery(SpellSearchDTO $dto): Builder
    {
        $query = Spell::with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    private function applyFilters(Builder $query, SpellSearchDTO $dto): void
    {
        if (isset($dto->filters['search'])) {
            $query->search($dto->filters['search']);
        }

        if (isset($dto->filters['level'])) {
            $query->level($dto->filters['level']);
        }

        if (isset($dto->filters['school'])) {
            $query->school($dto->filters['school']);
        }

        if (isset($dto->filters['concentration'])) {
            $query->concentration($dto->filters['concentration']);
        }

        if (isset($dto->filters['ritual'])) {
            $query->ritual($dto->filters['ritual']);
        }
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
        $results = $client->index('spells')->search($dto->searchQuery ?? '', $searchParams);

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
