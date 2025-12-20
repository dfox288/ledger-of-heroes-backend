<?php

namespace App\Services;

use App\DTOs\ClassSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\CharacterClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

final class ClassSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'spellcastingAbility',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'traits',
        'sources.source',
        'features',
        'levelProgression',
        'counters',
        'subclasses.features',
        'subclasses.counters',
        'subclasses.sources.source',
        'tags',
        'parentClass',
        'parentClass.levelProgression', // For subclass spellcasting_type
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'spellcastingAbility',
        'parentClass.spellcastingAbility',
        'parentClass.proficiencies.proficiencyType',
        'parentClass.proficiencies.item',
        'parentClass.proficiencies.skill.abilityScore',
        'parentClass.proficiencies.abilityScore',
        'parentClass.traits.dataTables.entries',
        'parentClass.sources.source',
        'parentClass.features.dataTables.entries',
        'parentClass.features.childFeatures',
        'parentClass.features.spells',
        'parentClass.levelProgression',
        'parentClass.counters',
        'parentClass.equipment.item.contents.item',
        'parentClass.languages.language',
        'parentClass.spells',
        'parentClass.optionalFeatures',
        'parentClass.tags',
        'subclasses',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'proficiencies.skill.abilityScore',
        'proficiencies.abilityScore',
        'traits.dataTables.entries',
        'sources.source',
        'features.dataTables.entries',
        'features.childFeatures',
        'features.spells',
        'levelProgression',
        'counters',
        'equipment.item.contents.item',
        'equipmentChoices',
        'languages.language',
        'spells',
        'optionalFeatures.sources.source',
        'subclasses.features.dataTables.entries',
        'subclasses.features.childFeatures',
        'subclasses.features.spells',
        'subclasses.counters',
        'subclasses.sources.source',
        'tags',
        'multiclassRequirements.abilityScore',
        'parentClass.multiclassRequirements.abilityScore',
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
     * - ?filter=is_subclass = false
     * - ?filter=hit_die = 12
     * - ?filter=spellcasting_ability = INT
     * - ?filter=tag_slugs IN [spellcaster]
     * - ?filter=is_subclass = false AND hit_die >= 10
     */
    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return CharacterClass::search($searchQuery);
    }

    /**
     * Build Eloquent database query for pagination (no filters - use Meilisearch for filtering)
     */
    public function buildDatabaseQuery(ClassSearchDTO $dto): Builder
    {
        $query = CharacterClass::with(self::INDEX_RELATIONSHIPS)
            ->withCount('spells');

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

    private function applySorting(Builder $query, ClassSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }

    /**
     * Search using Meilisearch with optional filter and sort.
     *
     * Supports filter-only queries (no search term required).
     */
    public function searchWithMeilisearch(ClassSearchDTO $dto, Client $client): LengthAwarePaginator
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
            // Use model's searchableAs() to respect Scout prefix (test_ for testing, none for production)
            $indexName = (new CharacterClass)->searchableAs();
            $results = $client->index($indexName)->search($dto->searchQuery ?? '', $searchParams);
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
        $classIds = collect($resultsArray['hits'])->pluck('id');

        if ($classIds->isEmpty()) {
            return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
        }

        $classes = CharacterClass::with(self::INDEX_RELATIONSHIPS)
            ->withCount('spells')
            ->findMany($classIds);

        // Preserve Meilisearch result order
        $orderedClasses = $classIds->map(function ($id) use ($classes) {
            return $classes->firstWhere('id', $id);
        })->filter();

        return new LengthAwarePaginator(
            $orderedClasses,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page ?? 1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Get spells for a class with optional filtering.
     *
     * @param  array{search?: string, level?: int, school?: int, concentration?: bool, ritual?: bool, sort_by?: string, sort_direction?: string, per_page?: int}  $filters
     */
    public function getClassSpells(CharacterClass $class, array $filters = []): PaginatorContract
    {
        $query = $class->spells()
            ->with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

        // Text search on name and description
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('spells.name', 'LIKE', "%{$search}%")
                    ->orWhere('spells.description', 'LIKE', "%{$search}%");
            });
        }

        // Filter by spell level
        if (isset($filters['level'])) {
            $query->where('spells.level', $filters['level']);
        }

        // Filter by school
        if (isset($filters['school'])) {
            $query->where('spells.spell_school_id', $filters['school']);
        }

        // Filter by concentration
        if (isset($filters['concentration'])) {
            $query->where('spells.needs_concentration', $filters['concentration']);
        }

        // Filter by ritual
        if (isset($filters['ritual'])) {
            $query->where('spells.is_ritual', $filters['ritual']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';

        // Prefix with table name for pivot queries
        if (! str_contains($sortBy, '.')) {
            $sortBy = 'spells.'.$sortBy;
        }

        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }
}
