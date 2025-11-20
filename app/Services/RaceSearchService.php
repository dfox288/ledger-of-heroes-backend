<?php

namespace App\Services;

use App\DTOs\RaceSearchDTO;
use App\Models\Race;
use Illuminate\Database\Eloquent\Builder;

final class RaceSearchService
{
    public function buildScoutQuery(RaceSearchDTO $dto): \Laravel\Scout\Builder
    {
        $search = Race::search($dto->searchQuery);

        if (isset($dto->filters['size'])) {
            $search->where('size_id', $dto->filters['size']);
        }

        return $search;
    }

    public function buildDatabaseQuery(RaceSearchDTO $dto): Builder
    {
        $query = Race::with([
            'size',
            'sources.source',
            'proficiencies.skill',
            'traits.randomTables.entries',
            'modifiers.abilityScore',
            'conditions.condition',
            'spells.spell',
            'spells.abilityScore',
        ]);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    private function applyFilters(Builder $query, RaceSearchDTO $dto): void
    {
        if (isset($dto->filters['search'])) {
            $query->search($dto->filters['search']);
        }

        if (isset($dto->filters['size'])) {
            $query->size($dto->filters['size']);
        }

        if (isset($dto->filters['grants_proficiency'])) {
            $query->grantsProficiency($dto->filters['grants_proficiency']);
        }

        if (isset($dto->filters['grants_skill'])) {
            $query->grantsSkill($dto->filters['grants_skill']);
        }

        if (isset($dto->filters['grants_proficiency_type'])) {
            $query->grantsProficiencyType($dto->filters['grants_proficiency_type']);
        }

        if (isset($dto->filters['speaks_language'])) {
            $query->speaksLanguage($dto->filters['speaks_language']);
        }

        if (isset($dto->filters['language_choice_count'])) {
            $query->languageChoiceCount((int) $dto->filters['language_choice_count']);
        }

        if (isset($dto->filters['grants_languages']) && $dto->filters['grants_languages']) {
            $query->grantsLanguages();
        }
    }

    private function applySorting(Builder $query, RaceSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
