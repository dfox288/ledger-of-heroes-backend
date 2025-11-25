<?php

namespace App\Services;

use App\DTOs\RaceSearchDTO;
use App\Models\Race;
use Illuminate\Database\Eloquent\Builder;

final class RaceSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'size',
        'sources.source',
        'proficiencies.skill',
        'proficiencies.item',
        'traits.randomTables.entries',
        'modifiers.abilityScore',
        'conditions.condition',
        'spells.spell',
        'spells.abilityScore',
        'parent',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'size',
        'sources.source',
        'parent.size',
        'parent.sources.source',
        'parent.proficiencies.skill.abilityScore',
        'parent.proficiencies.item',
        'parent.proficiencies.abilityScore',
        'parent.traits.randomTables.entries',
        'parent.modifiers.abilityScore',
        'parent.modifiers.skill',
        'parent.modifiers.damageType',
        'parent.languages.language',
        'parent.conditions.condition',
        'parent.spells.spell',
        'parent.spells.abilityScore',
        'parent.tags',
        'subraces',
        'proficiencies.skill.abilityScore',
        'proficiencies.item',
        'proficiencies.abilityScore',
        'traits.randomTables.entries',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'languages.language',
        'conditions.condition',
        'spells.spell',
        'spells.abilityScore',
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
}
