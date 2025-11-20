<?php

namespace App\Services;

use App\DTOs\BackgroundSearchDTO;
use App\Models\Background;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Service for searching and filtering D&D backgrounds
 *
 * Handles Scout/Meilisearch search with MySQL fallback, applies filters,
 * and returns paginated results. Keeps controllers thin by extracting
 * all query-building logic into this testable service layer.
 */
final class BackgroundSearchService
{
    /**
     * Search backgrounds using Scout or MySQL fallback
     */
    public function search(BackgroundSearchDTO $dto): LengthAwarePaginator
    {
        // If search query provided, use Scout with MySQL fallback
        if ($dto->searchQuery !== null) {
            return $this->performScoutSearch($dto);
        }

        // Otherwise, standard database query
        return $this->buildStandardQuery($dto)->paginate($dto->perPage);
    }

    /**
     * Perform Scout/Meilisearch search with graceful MySQL fallback
     */
    private function performScoutSearch(BackgroundSearchDTO $dto): LengthAwarePaginator
    {
        try {
            $search = Background::search($dto->searchQuery);

            // Apply any additional filters if needed in the future
            // (Scout doesn't support complex filters well, so we keep it simple)

            return $search->paginate($dto->perPage);
        } catch (\Exception $e) {
            // Log the failure and fall back to MySQL
            Log::warning('Meilisearch search failed, falling back to MySQL', [
                'query' => $dto->searchQuery,
                'error' => $e->getMessage(),
            ]);

            return $this->performMysqlSearch($dto);
        }
    }

    /**
     * Perform MySQL FULLTEXT search fallback
     */
    private function performMysqlSearch(BackgroundSearchDTO $dto): LengthAwarePaginator
    {
        $query = Background::with(['sources.source']);

        // MySQL search using LIKE
        if ($dto->searchQuery !== null) {
            $query->where('name', 'LIKE', '%'.$dto->searchQuery.'%');
        }

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query->paginate($dto->perPage);
    }

    /**
     * Build standard database query with filters (no search)
     */
    private function buildStandardQuery(BackgroundSearchDTO $dto): Builder
    {
        $query = Background::with(['sources.source']);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    /**
     * Apply all filters to the query
     */
    private function applyFilters(Builder $query, BackgroundSearchDTO $dto): void
    {
        // Legacy search filter (using model scope)
        if (isset($dto->filters['search']) && $dto->filters['search'] !== null) {
            $query->search($dto->filters['search']);
        }

        // Filter by granted proficiency
        if (isset($dto->filters['grants_proficiency']) && $dto->filters['grants_proficiency'] !== null) {
            $query->grantsProficiency($dto->filters['grants_proficiency']);
        }

        // Filter by granted skill
        if (isset($dto->filters['grants_skill']) && $dto->filters['grants_skill'] !== null) {
            $query->grantsSkill($dto->filters['grants_skill']);
        }

        // Filter by spoken language
        if (isset($dto->filters['speaks_language']) && $dto->filters['speaks_language'] !== null) {
            $query->speaksLanguage($dto->filters['speaks_language']);
        }

        // Filter by language choice count
        if (isset($dto->filters['language_choice_count']) && $dto->filters['language_choice_count'] !== null) {
            $query->languageChoiceCount((int) $dto->filters['language_choice_count']);
        }

        // Filter entities granting any languages
        if (isset($dto->filters['grants_languages']) && $dto->filters['grants_languages']) {
            $query->grantsLanguages();
        }
    }

    /**
     * Apply sorting to the query
     */
    private function applySorting(Builder $query, BackgroundSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
