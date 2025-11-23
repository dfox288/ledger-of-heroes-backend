<?php

namespace App\Services;

use App\DTOs\BackgroundSearchDTO;
use App\Models\Background;
use Illuminate\Database\Eloquent\Builder;

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
     * Default relationships to eager-load for both Scout and database queries
     */
    private const DEFAULT_RELATIONSHIPS = [
        'sources.source',
        'modifiers',
        'proficiencies',
        'traits',
        'languages',
        'tags',
    ];

    /**
     * Build Scout search query for full-text search
     */
    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return Background::search($searchQuery);
    }

    /**
     * Build Eloquent database query with filters
     */
    public function buildDatabaseQuery(BackgroundSearchDTO $dto): Builder
    {
        return $this->buildStandardQuery($dto);
    }

    /**
     * Build standard database query with filters (no search)
     */
    private function buildStandardQuery(BackgroundSearchDTO $dto): Builder
    {
        $query = Background::with(self::DEFAULT_RELATIONSHIPS);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    /**
     * Get default relationships for eager loading
     */
    public function getDefaultRelationships(): array
    {
        return self::DEFAULT_RELATIONSHIPS;
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
