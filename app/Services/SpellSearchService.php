<?php

namespace App\Services;

use App\DTOs\SpellSearchDTO;
use App\Models\Spell;
use Illuminate\Database\Eloquent\Builder;

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
            $schoolId = $dto->filters['school'];
            $schoolName = \App\Models\SpellSchool::find($schoolId)?->name;
            if ($schoolName) {
                $search->where('school_name', $schoolName);
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
    }

    private function applySorting(Builder $query, SpellSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
