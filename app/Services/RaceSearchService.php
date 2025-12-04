<?php

namespace App\Services;

use App\DTOs\RaceSearchDTO;
use App\Exceptions\Search\InvalidFilterSyntaxException;
use App\Models\Race;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MeiliSearch\Client;

final class RaceSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     * Uses entitySpellRecords for full EntitySpell pivot records
     */
    private const INDEX_RELATIONSHIPS = [
        'size',
        'sources.source',
        'proficiencies.skill',
        'proficiencies.item',
        'traits.dataTables.entries',
        'modifiers.abilityScore',
        'conditions.condition',
        'entitySpellRecords.spell',
        'entitySpellRecords.abilityScore',
        'entitySpellRecords.school',
        'entitySpellRecords.characterClass',
        'senses.sense',
        'parent',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     * Uses entitySpellRecords for full EntitySpell pivot records
     */
    private const SHOW_RELATIONSHIPS = [
        'size',
        'sources.source',
        'parent.size',
        'parent.sources.source',
        'parent.proficiencies.skill.abilityScore',
        'parent.proficiencies.item',
        'parent.proficiencies.abilityScore',
        'parent.traits.dataTables.entries',
        'parent.modifiers.abilityScore',
        'parent.modifiers.skill',
        'parent.modifiers.damageType',
        'parent.languages.language',
        'parent.conditions.condition',
        'parent.entitySpellRecords.spell',
        'parent.entitySpellRecords.abilityScore',
        'parent.entitySpellRecords.school',
        'parent.entitySpellRecords.characterClass',
        'parent.senses.sense',
        'parent.tags',
        'subraces',
        'proficiencies.skill.abilityScore',
        'proficiencies.item',
        'proficiencies.abilityScore',
        'traits.dataTables.entries',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'languages.language',
        'conditions.condition',
        'entitySpellRecords.spell',
        'entitySpellRecords.abilityScore',
        'entitySpellRecords.school',
        'entitySpellRecords.characterClass',
        'senses.sense',
        'tags',
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
     * - ?filter=size_code = M
     * - ?filter=speed >= 30
     * - ?filter=has_darkvision = true
     * - ?filter=spell_slugs IN [misty-step, faerie-fire]
     * - ?filter=tag_slugs IN [darkvision, fey-ancestry]
     */
    public function buildScoutQuery(RaceSearchDTO $dto): \Laravel\Scout\Builder
    {
        return Race::search($dto->searchQuery);
    }

    public function buildDatabaseQuery(RaceSearchDTO $dto): Builder
    {
        $query = Race::with(self::INDEX_RELATIONSHIPS);

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

    private function applyFilters(Builder $query, RaceSearchDTO $dto): void
    {
        // MySQL filtering has been removed - use Meilisearch ?filter= parameter instead
        //
        // Examples:
        // - ?filter=size_code = M
        // - ?filter=speed >= 30
        // - ?filter=has_darkvision = true
        // - ?filter=spell_slugs IN [misty-step, faerie-fire]
        // - ?filter=tag_slugs IN [darkvision, fey-ancestry]
        // - ?filter=tag_slugs IN [darkvision] AND speed >= 35
        // - ?filter=spell_slugs IN [dancing-lights] AND size_code = M
        //
        // All filtering should happen via Meilisearch for consistency and performance.
    }

    private function applySorting(Builder $query, RaceSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }

    /**
     * Search using Meilisearch with custom filter expressions
     */
    public function searchWithMeilisearch(RaceSearchDTO $dto, Client $client): LengthAwarePaginator
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
            $indexName = (new Race)->searchableAs();
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
        $raceIds = collect($resultsArray['hits'])->pluck('id');

        if ($raceIds->isEmpty()) {
            // Return empty paginator with correct metadata
            return new LengthAwarePaginator(
                collect([]),
                $resultsArray['estimatedTotalHits'] ?? 0,
                $dto->perPage,
                $dto->page,
                ['path' => request()->url()]
            );
        }

        // Fetch races with relationships, preserving Meilisearch order
        $races = Race::with(self::INDEX_RELATIONSHIPS)
            ->whereIn('id', $raceIds)
            ->get()
            ->sortBy(function ($race) use ($raceIds) {
                return $raceIds->search($race->id);
            })
            ->values();

        // Build paginator manually to match Meilisearch results
        return new LengthAwarePaginator(
            $races,
            $resultsArray['estimatedTotalHits'] ?? 0,
            $dto->perPage,
            $dto->page,
            ['path' => request()->url()]
        );
    }
}
