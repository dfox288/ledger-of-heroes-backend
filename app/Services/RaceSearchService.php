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

        // Spell filter (AND/OR logic) - Uses entity_spells polymorphic table
        if (isset($dto->filters['spells'])) {
            $spellSlugs = array_map('trim', explode(',', $dto->filters['spells']));
            $spellSlugs = array_map('strtolower', $spellSlugs); // Case-insensitive
            $operator = $dto->filters['spells_operator'] ?? 'AND';

            if ($operator === 'AND') {
                // Must have ALL spells (nested whereHas)
                foreach ($spellSlugs as $slug) {
                    $query->whereHas('entitySpells', function ($q) use ($slug) {
                        $q->whereRaw('LOWER(slug) = ?', [$slug]);
                    });
                }
            } else {
                // Must have AT LEAST ONE spell (single whereHas with whereIn)
                $query->whereHas('entitySpells', function ($q) use ($spellSlugs) {
                    $q->whereIn(\DB::raw('LOWER(slug)'), $spellSlugs);
                });
            }
        }

        // Spell level filter (races that know spells of specific level)
        if (isset($dto->filters['spell_level'])) {
            $query->whereHas('entitySpells', function ($q) use ($dto) {
                $q->where('level', $dto->filters['spell_level']);
            });
        }

        // Has innate spells filter
        if (isset($dto->filters['has_innate_spells']) && filter_var($dto->filters['has_innate_spells'], FILTER_VALIDATE_BOOLEAN)) {
            $query->has('entitySpells');
        }
    }

    private function applySorting(Builder $query, RaceSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}
