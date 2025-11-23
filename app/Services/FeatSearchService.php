<?php

namespace App\Services;

use App\DTOs\FeatSearchDTO;
use App\Models\Feat;
use Illuminate\Database\Eloquent\Builder;

final class FeatSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'conditions.condition',
        'prerequisites.prerequisite',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'conditions',
        'prerequisites.prerequisite',
        'tags',
    ];

    /**
     * Backward compatibility alias
     */
    private const DEFAULT_RELATIONSHIPS = self::INDEX_RELATIONSHIPS;

    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return Feat::search($searchQuery);
    }

    public function buildDatabaseQuery(FeatSearchDTO $dto): Builder
    {
        $query = Feat::with(self::INDEX_RELATIONSHIPS);

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

    private function applyFilters(Builder $query, FeatSearchDTO $dto): void
    {
        if (isset($dto->filters['search'])) {
            $query->search($dto->filters['search']);
        }

        if (isset($dto->filters['prerequisite_race'])) {
            $query->wherePrerequisiteRace($dto->filters['prerequisite_race']);
        }

        if (isset($dto->filters['prerequisite_ability'])) {
            $minValue = $dto->filters['min_value'] ?? null;
            $query->wherePrerequisiteAbility($dto->filters['prerequisite_ability'], $minValue);
        }

        if (isset($dto->filters['has_prerequisites'])) {
            $hasPrerequisites = filter_var($dto->filters['has_prerequisites'], FILTER_VALIDATE_BOOLEAN);
            $query->withOrWithoutPrerequisites($hasPrerequisites);
        }

        if (isset($dto->filters['grants_proficiency'])) {
            $query->grantsProficiency($dto->filters['grants_proficiency']);
        }

        if (isset($dto->filters['prerequisite_proficiency'])) {
            $query->wherePrerequisiteProficiency($dto->filters['prerequisite_proficiency']);
        }

        if (isset($dto->filters['grants_skill'])) {
            $query->grantsSkill($dto->filters['grants_skill']);
        }
    }

    private function applySorting(Builder $query, FeatSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
