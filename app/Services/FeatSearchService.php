<?php

namespace App\Services;

use App\DTOs\FeatSearchDTO;
use App\Models\Feat;
use Illuminate\Database\Eloquent\Builder;

final class FeatSearchService
{
    /**
     * Default relationships to eager-load for both Scout and database queries
     */
    private const DEFAULT_RELATIONSHIPS = [
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'conditions.condition',
        'prerequisites.prerequisite',
        'tags',
    ];

    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return Feat::search($searchQuery);
    }

    public function buildDatabaseQuery(FeatSearchDTO $dto): Builder
    {
        $query = Feat::with(self::DEFAULT_RELATIONSHIPS);

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
