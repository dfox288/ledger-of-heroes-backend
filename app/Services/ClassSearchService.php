<?php

namespace App\Services;

use App\DTOs\ClassSearchDTO;
use App\Models\CharacterClass;
use Illuminate\Database\Eloquent\Builder;

final class ClassSearchService
{
    public function buildScoutQuery(string $searchQuery): \Laravel\Scout\Builder
    {
        return CharacterClass::search($searchQuery);
    }

    public function buildDatabaseQuery(ClassSearchDTO $dto): Builder
    {
        $query = CharacterClass::with([
            'spellcastingAbility',
            'proficiencies.proficiencyType',
            'traits',
            'sources.source',
            'features',
            'levelProgression',
            'counters',
            'subclasses.features',
            'subclasses.counters',
        ]);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    private function applyFilters(Builder $query, ClassSearchDTO $dto): void
    {
        if (isset($dto->filters['search'])) {
            $query->where('name', 'LIKE', '%'.$dto->filters['search'].'%');
        }

        if (isset($dto->filters['base_only']) && $dto->filters['base_only']) {
            $query->whereNull('parent_class_id');
        }

        if (isset($dto->filters['grants_proficiency'])) {
            $query->grantsProficiency($dto->filters['grants_proficiency']);
        }

        if (isset($dto->filters['grants_skill'])) {
            $query->grantsSkill($dto->filters['grants_skill']);
        }

        if (isset($dto->filters['grants_saving_throw'])) {
            $abilityName = $dto->filters['grants_saving_throw'];
            $query->whereHas('proficiencies', function ($q) use ($abilityName) {
                $q->where('proficiency_type', 'saving_throw')
                    ->whereHas('abilityScore', function ($abilityQuery) use ($abilityName) {
                        $abilityQuery->where('code', strtoupper($abilityName))
                            ->orWhere('name', 'LIKE', "%{$abilityName}%");
                    });
            });
        }
    }

    private function applySorting(Builder $query, ClassSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
