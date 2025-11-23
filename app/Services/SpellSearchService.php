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
            $schoolIdentifier = $dto->filters['school'];
            // Resolve school (accepts ID, code like "EV", or name like "evocation")
            $school = is_numeric($schoolIdentifier)
                ? \App\Models\SpellSchool::find($schoolIdentifier)
                : \App\Models\SpellSchool::where('code', strtoupper($schoolIdentifier))
                    ->orWhere('name', 'LIKE', $schoolIdentifier)
                    ->first();

            if ($school) {
                $search->where('school_name', $school->name);
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

        // Damage type filter (via effects relationship)
        if (isset($dto->filters['damage_type'])) {
            $damageTypes = array_map('trim', explode(',', $dto->filters['damage_type']));

            $query->whereHas('effects', function ($q) use ($damageTypes) {
                $q->whereHas('damageType', function ($dq) use ($damageTypes) {
                    // Try code first (exact match), fallback to name (case-insensitive)
                    $dq->where(function ($sq) use ($damageTypes) {
                        foreach ($damageTypes as $type) {
                            $sq->orWhere('code', strtoupper($type))
                                ->orWhereRaw('LOWER(name) = ?', [strtolower($type)]);
                        }
                    });
                });
            });
        }

        // Saving throw filter (via savingThrows relationship)
        if (isset($dto->filters['saving_throw'])) {
            $abilities = array_map('trim', explode(',', $dto->filters['saving_throw']));

            // Get ability score IDs for the requested abilities
            $abilityIds = \App\Models\AbilityScore::where(function ($q) use ($abilities) {
                foreach ($abilities as $ability) {
                    $q->orWhere('ability_scores.code', strtoupper($ability))
                        ->orWhereRaw('LOWER(ability_scores.name) = ?', [strtolower($ability)]);
                }
            })->pluck('id')->toArray();

            if (! empty($abilityIds)) {
                $query->whereHas('savingThrows', function ($q) use ($abilityIds) {
                    $q->whereIn('ability_scores.id', $abilityIds);
                });
            }
        }

        // Component filters (direct column checks using LIKE for string matching)
        if (isset($dto->filters['requires_verbal'])) {
            $value = filter_var($dto->filters['requires_verbal'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === true) {
                $query->where('components', 'LIKE', '%V%');
            } elseif ($value === false) {
                $query->where('components', 'NOT LIKE', '%V%');
            }
        }

        if (isset($dto->filters['requires_somatic'])) {
            $value = filter_var($dto->filters['requires_somatic'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === true) {
                $query->where('components', 'LIKE', '%S%');
            } elseif ($value === false) {
                $query->where('components', 'NOT LIKE', '%S%');
            }
        }

        if (isset($dto->filters['requires_material'])) {
            $value = filter_var($dto->filters['requires_material'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === true) {
                $query->where('components', 'LIKE', '%M%');
            } elseif ($value === false) {
                $query->where('components', 'NOT LIKE', '%M%');
            }
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
        try {
            $results = $client->index('spells')->search($dto->searchQuery ?? '', $searchParams);
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
